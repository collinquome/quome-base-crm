"""
Union Bay CRM — MCP server.

Wraps the CRM's PublicApi (`/api/v1/*`) as a curated set of MCP tools for
Claude Desktop / Claude Code to use on the user's behalf.

Auth: Sanctum bearer token. Mint one with `uv run login.py`, then export it
as UNION_BAY_CRM_TOKEN before starting Claude Desktop.

Env:
  UNION_BAY_CRM_URL    Base URL, default http://localhost:8190
  UNION_BAY_CRM_TOKEN  Sanctum bearer token (required for any non-whoami tool)
  UNION_BAY_CRM_DEBUG  "1" to log requests to stderr
"""

from __future__ import annotations

import os
import sys
from typing import Any

import httpx
from mcp.server.fastmcp import FastMCP

BASE_URL = os.environ.get("UNION_BAY_CRM_URL", "http://localhost:8190").rstrip("/")
TOKEN = os.environ.get("UNION_BAY_CRM_TOKEN", "").strip()
DEBUG = os.environ.get("UNION_BAY_CRM_DEBUG", "0") == "1"

mcp = FastMCP("union-bay-crm")


def _log(msg: str) -> None:
    if DEBUG:
        sys.stderr.write(f"[ub-crm-mcp] {msg}\n")
        sys.stderr.flush()


def _client() -> httpx.Client:
    headers = {"Accept": "application/json"}
    if TOKEN:
        headers["Authorization"] = f"Bearer {TOKEN}"
    return httpx.Client(base_url=f"{BASE_URL}/api/v1", headers=headers, timeout=30.0)


def _call(method: str, path: str, **kwargs) -> dict[str, Any]:
    _log(f"{method} {path} {kwargs.get('params') or kwargs.get('json') or ''}")
    with _client() as c:
        r = c.request(method, path, **kwargs)
    if r.status_code == 401:
        return {
            "error": "unauthorized",
            "hint": "Run `uv run login.py` to mint a fresh token, then re-export UNION_BAY_CRM_TOKEN and restart Claude Desktop.",
        }
    if r.status_code >= 400:
        try:
            body = r.json()
        except Exception:
            body = {"raw": r.text[:400]}
        return {"error": f"HTTP {r.status_code}", "body": body}
    if r.status_code == 204 or not r.content:
        return {"ok": True}
    return r.json()


# ---------------------------------------------------------------------------
# Sanity / discovery
# ---------------------------------------------------------------------------

@mcp.tool()
def whoami() -> dict[str, Any]:
    """Return the currently authenticated user. Use this first to confirm the token works."""
    return _call("GET", "/auth/me")


@mcp.tool()
def list_pipelines() -> dict[str, Any]:
    """List sales pipelines and their stages. Use this to discover stage IDs for create_lead / move_lead_stage."""
    return _call("GET", "/pipelines")


# ---------------------------------------------------------------------------
# Leads
# ---------------------------------------------------------------------------

@mcp.tool()
def list_leads(
    search: str | None = None,
    pipeline_id: int | None = None,
    stage_id: int | None = None,
    per_page: int = 25,
) -> dict[str, Any]:
    """List leads visible to the current user, optionally filtered by title search, pipeline, or stage.

    Returns a paginated response with `data` (leads) and `meta` (pagination info).
    """
    params: dict[str, Any] = {"per_page": min(max(per_page, 1), 100)}
    if search:
        params["search"] = search
    if pipeline_id:
        params["pipeline_id"] = pipeline_id
    if stage_id:
        params["stage_id"] = stage_id
    return _call("GET", "/leads", params=params)


@mcp.tool()
def get_lead(lead_id: int) -> dict[str, Any]:
    """Get full detail for a single lead by ID, including person, organization, products, activities."""
    return _call("GET", f"/leads/{lead_id}")


@mcp.tool()
def create_lead(
    title: str,
    pipeline_id: int,
    stage_id: int,
    person_id: int | None = None,
    lead_value: float | None = None,
) -> dict[str, Any]:
    """Create a new lead.

    Args:
        title: short label for the opportunity (e.g., "John Smith - commercial auto").
        pipeline_id: from list_pipelines().
        stage_id: a stage that belongs to that pipeline (also from list_pipelines()).
        person_id: existing contact to attach. Use list_contacts() to find one, or
            create_contact() first if they don't exist.
        lead_value: estimated premium / deal value (optional).
    """
    payload: dict[str, Any] = {
        "title": title,
        "lead_pipeline_id": pipeline_id,
        "lead_pipeline_stage_id": stage_id,
    }
    if person_id is not None:
        payload["person_id"] = person_id
    if lead_value is not None:
        payload["lead_value"] = lead_value
    return _call("POST", "/leads", json=payload)


@mcp.tool()
def update_lead(
    lead_id: int,
    title: str | None = None,
    lead_value: float | None = None,
    stage_id: int | None = None,
) -> dict[str, Any]:
    """Update fields on a lead. Only the fields you pass are changed.

    To move a lead between Kanban stages, pass `stage_id`.
    """
    payload: dict[str, Any] = {}
    if title is not None:
        payload["title"] = title
    if lead_value is not None:
        payload["lead_value"] = lead_value
    if stage_id is not None:
        payload["lead_pipeline_stage_id"] = stage_id
    if not payload:
        return {"error": "nothing to update — pass at least one of title, lead_value, stage_id"}
    return _call("PUT", f"/leads/{lead_id}", json=payload)


@mcp.tool()
def delete_lead(lead_id: int) -> dict[str, Any]:
    """Soft-delete a lead. Recoverable from the trash for 30 days."""
    return _call("DELETE", f"/leads/{lead_id}")


# ---------------------------------------------------------------------------
# Contacts (Persons)
# ---------------------------------------------------------------------------

@mcp.tool()
def list_contacts(search: str | None = None, per_page: int = 25) -> dict[str, Any]:
    """List contacts (Persons), optionally filtered by name/email substring."""
    params: dict[str, Any] = {"per_page": min(max(per_page, 1), 100)}
    if search:
        params["search"] = search
    return _call("GET", "/contacts", params=params)


@mcp.tool()
def get_contact(person_id: int) -> dict[str, Any]:
    """Get full detail for a contact by ID."""
    return _call("GET", f"/contacts/{person_id}")


@mcp.tool()
def create_contact(
    name: str,
    email: str | None = None,
    phone: str | None = None,
) -> dict[str, Any]:
    """Create a new contact (Person).

    `email` and `phone` are optional. The CRM stores them as labelled arrays
    (label="work" by default).
    """
    payload: dict[str, Any] = {"name": name}
    if email:
        payload["emails"] = [{"value": email, "label": "work"}]
    if phone:
        payload["contact_numbers"] = [{"value": phone, "label": "work"}]
    return _call("POST", "/contacts", json=payload)


# ---------------------------------------------------------------------------
# Action Stream (next actions / today's to-do list)
# ---------------------------------------------------------------------------

@mcp.tool()
def list_action_stream(
    action_type: str | None = None,
    priority: str | None = None,
    per_page: int = 25,
) -> dict[str, Any]:
    """List my prioritized next-actions (overdue first, then by due date).

    Filters:
        action_type: call | email | meeting | task | custom
        priority: urgent | high | normal | low
    """
    params: dict[str, Any] = {"per_page": min(max(per_page, 1), 100)}
    if action_type:
        params["action_type"] = action_type
    if priority:
        params["priority"] = priority
    return _call("GET", "/action-stream", params=params)


@mcp.tool()
def add_next_action(
    target_type: str,
    target_id: int,
    description: str,
    due_date: str,
    action_type: str = "call",
    priority: str = "normal",
) -> dict[str, Any]:
    """Add a next-action attached to a lead or person.

    Args:
        target_type: "leads" or "persons".
        target_id: lead or person ID.
        description: what to do (e.g., "follow up on quote").
        due_date: YYYY-MM-DD.
        action_type: call | email | meeting | task | custom (default: call).
        priority: urgent | high | normal | low (default: normal).
    """
    if target_type not in ("leads", "persons"):
        return {"error": "target_type must be 'leads' or 'persons'"}
    payload = {
        "actionable_type": target_type,
        "actionable_id": target_id,
        "description": description,
        "due_date": due_date,
        "action_type": action_type,
        "priority": priority,
    }
    return _call("POST", "/action-stream", json=payload)


@mcp.tool()
def complete_action(action_id: int) -> dict[str, Any]:
    """Mark a next-action as completed."""
    return _call("POST", f"/action-stream/{action_id}/complete")


@mcp.tool()
def snooze_action(action_id: int, snoozed_until: str) -> dict[str, Any]:
    """Snooze a next-action until a future date.

    Args:
        action_id: the action to snooze.
        snoozed_until: ISO datetime in the future, e.g. "2026-05-08T09:00:00".
    """
    return _call("POST", f"/action-stream/{action_id}/snooze", json={"snoozed_until": snoozed_until})


# ---------------------------------------------------------------------------
# Dashboard
# ---------------------------------------------------------------------------

@mcp.tool()
def dashboard_summary() -> dict[str, Any]:
    """Get the dashboard rollup: leads, activities, pipeline value, win/loss, etc."""
    return _call("GET", "/dashboard")


@mcp.tool()
def overdue_count() -> dict[str, Any]:
    """How many of my next-actions are overdue right now?"""
    return _call("GET", "/action-stream/overdue-count")


# ---------------------------------------------------------------------------
# Comments (notes / @mentions)
# ---------------------------------------------------------------------------

@mcp.tool()
def add_comment(target_type: str, target_id: int, body: str) -> dict[str, Any]:
    """Leave a comment on a lead or person. Use @username to mention a teammate.

    Args:
        target_type: "leads" or "persons".
        target_id: the entity ID.
        body: comment text. Markdown allowed.
    """
    if target_type not in ("leads", "persons"):
        return {"error": "target_type must be 'leads' or 'persons'"}
    return _call(
        "POST",
        "/comments",
        json={"commentable_type": target_type, "commentable_id": target_id, "body": body},
    )


if __name__ == "__main__":
    mcp.run()
