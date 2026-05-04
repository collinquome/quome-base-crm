"""
OAuth 2.1 proxy that fronts the Union Bay CRM Sanctum login.

Implements the endpoints Claude's Custom Connector expects:

  GET  /.well-known/oauth-authorization-server  → AS metadata (RFC 8414)
  GET  /.well-known/oauth-protected-resource     → resource metadata
  POST /oauth/register                           → dynamic client registration (RFC 7591)
  GET  /oauth/authorize                          → render CRM login form
  POST /oauth/login                              → submit login → mint code
  POST /oauth/token                              → exchange code → access token

The "access token" we issue IS the CRM's Sanctum token. We don't mint our
own JWT — the CRM is already the source of truth for whether the token is
valid (it's checked on every PublicApi call). When the user removes their
Sanctum token in the CRM admin, the connector immediately stops working.

Storage: in-memory dicts. Fine for dev (single-process uvicorn). Swap to
Redis when we have multiple workers.
"""

from __future__ import annotations

import base64
import hashlib
import logging
import os
import secrets
import time
from typing import Any
from urllib.parse import urlencode

import httpx
from starlette.requests import Request
from starlette.responses import HTMLResponse, JSONResponse, RedirectResponse, Response
from starlette.routing import Route

logger = logging.getLogger(__name__)

CRM_BASE_URL = os.environ.get("UNION_BAY_CRM_URL", "http://localhost:8190").rstrip("/")
PUBLIC_BASE_URL = os.environ.get("PUBLIC_BASE_URL", "").rstrip("/")
AUTH_CODE_TTL_SEC = 5 * 60
TOKEN_TTL_SEC = 60 * 60 * 24 * 30  # 30 days; CRM Sanctum is the real authority

# In-memory stores (dev-grade)
_clients: dict[str, dict[str, Any]] = {}      # client_id → registration
_codes: dict[str, dict[str, Any]] = {}        # auth_code → {token, code_challenge, ...}


# ---------------------------------------------------------------------------
# Helpers
# ---------------------------------------------------------------------------

def _base_url(request: Request) -> str:
    """Public base URL of this MCP server. Honours Railway proxy headers."""
    if PUBLIC_BASE_URL:
        return PUBLIC_BASE_URL
    proto = request.headers.get("x-forwarded-proto") or request.url.scheme
    host = request.headers.get("x-forwarded-host") or request.headers.get("host") or request.url.netloc
    return f"{proto}://{host}"


def _gc_codes() -> None:
    now = time.time()
    expired = [c for c, v in _codes.items() if v["expires_at"] < now]
    for c in expired:
        _codes.pop(c, None)


def _verify_pkce(code_verifier: str, code_challenge: str, method: str) -> bool:
    if method == "plain":
        return secrets.compare_digest(code_verifier, code_challenge)
    if method == "S256":
        digest = hashlib.sha256(code_verifier.encode()).digest()
        derived = base64.urlsafe_b64encode(digest).rstrip(b"=").decode()
        return secrets.compare_digest(derived, code_challenge)
    return False


# ---------------------------------------------------------------------------
# Discovery
# ---------------------------------------------------------------------------

async def oauth_metadata(request: Request) -> JSONResponse:
    base = _base_url(request)
    return JSONResponse({
        "issuer": base,
        "authorization_endpoint": f"{base}/oauth/authorize",
        "token_endpoint": f"{base}/oauth/token",
        "registration_endpoint": f"{base}/oauth/register",
        "response_types_supported": ["code"],
        "grant_types_supported": ["authorization_code"],
        "code_challenge_methods_supported": ["S256", "plain"],
        "token_endpoint_auth_methods_supported": ["none"],  # public clients
        "scopes_supported": ["mcp"],
    })


async def protected_resource(request: Request) -> JSONResponse:
    base = _base_url(request)
    return JSONResponse({
        "resource": f"{base}/api/v1/mcp",
        "authorization_servers": [base],
        "bearer_methods_supported": ["header"],
        "scopes_supported": ["mcp"],
    })


# ---------------------------------------------------------------------------
# Dynamic client registration (RFC 7591)
# ---------------------------------------------------------------------------

async def register(request: Request) -> JSONResponse:
    try:
        body = await request.json()
    except Exception:
        return JSONResponse({"error": "invalid_request", "error_description": "body must be JSON"}, status_code=400)

    redirect_uris = body.get("redirect_uris") or []
    if not isinstance(redirect_uris, list) or not redirect_uris:
        return JSONResponse(
            {"error": "invalid_redirect_uri", "error_description": "redirect_uris is required and must be non-empty"},
            status_code=400,
        )

    client_id = secrets.token_urlsafe(24)
    _clients[client_id] = {
        "client_id": client_id,
        "redirect_uris": redirect_uris,
        "token_endpoint_auth_method": "none",
        "grant_types": ["authorization_code"],
        "response_types": ["code"],
        "client_name": body.get("client_name") or "Claude",
        "registered_at": time.time(),
    }
    return JSONResponse(_clients[client_id], status_code=201)


# ---------------------------------------------------------------------------
# Authorize — show login form
# ---------------------------------------------------------------------------

_LOGIN_HTML = """<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Sign in — Union Bay CRM</title>
<style>
  * {{ box-sizing: border-box; }}
  body {{ font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", system-ui, sans-serif;
         background: #0f172a; color: #e2e8f0; margin: 0;
         min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 2rem; }}
  .card {{ background: #1e293b; border: 1px solid #334155; border-radius: 12px;
           padding: 2.5rem; width: 100%; max-width: 380px;
           box-shadow: 0 20px 25px -5px rgba(0,0,0,.3); }}
  h1 {{ font-size: 1.4rem; margin: 0 0 .25rem; font-weight: 600; }}
  .sub {{ color: #94a3b8; font-size: .9rem; margin: 0 0 1.5rem; }}
  label {{ display: block; font-size: .85rem; color: #cbd5e1; margin: .9rem 0 .35rem; }}
  input[type=email], input[type=password] {{
    width: 100%; padding: .65rem .8rem; border-radius: 6px;
    background: #0f172a; color: #e2e8f0; border: 1px solid #334155;
    font-size: .95rem;
  }}
  input:focus {{ outline: none; border-color: #6366f1; }}
  button {{ width: 100%; margin-top: 1.5rem; padding: .7rem;
           background: #6366f1; color: white; font-weight: 600;
           border: 0; border-radius: 6px; cursor: pointer; font-size: .95rem; }}
  button:hover {{ background: #4f46e5; }}
  .err {{ background: #7f1d1d; color: #fecaca; padding: .6rem .8rem;
          border-radius: 6px; font-size: .85rem; margin-bottom: 1rem; }}
  .footer {{ font-size: .75rem; color: #64748b; margin-top: 1.5rem; text-align: center; }}
</style>
</head>
<body>
<form class="card" method="POST" action="/oauth/login">
  <h1>Sign in to Union Bay CRM</h1>
  <p class="sub">Authorize <strong>{client_name}</strong> to act on your behalf.</p>
  {error_html}
  <input type="hidden" name="client_id" value="{client_id}">
  <input type="hidden" name="redirect_uri" value="{redirect_uri}">
  <input type="hidden" name="state" value="{state}">
  <input type="hidden" name="code_challenge" value="{code_challenge}">
  <input type="hidden" name="code_challenge_method" value="{code_challenge_method}">
  <label for="email">Email</label>
  <input type="email" name="email" id="email" required autocomplete="username" autofocus value="{prefill_email}">
  <label for="password">Password</label>
  <input type="password" name="password" id="password" required autocomplete="current-password">
  <button type="submit">Sign in &amp; authorize</button>
  <div class="footer">CRM: {crm_host}</div>
</form>
</body>
</html>"""


def _render_login(
    *,
    client_id: str,
    redirect_uri: str,
    state: str,
    code_challenge: str,
    code_challenge_method: str,
    error: str = "",
    prefill_email: str = "",
) -> HTMLResponse:
    client = _clients.get(client_id, {})
    error_html = f'<div class="err">{error}</div>' if error else ""
    html = _LOGIN_HTML.format(
        client_id=client_id,
        redirect_uri=redirect_uri,
        state=state,
        code_challenge=code_challenge,
        code_challenge_method=code_challenge_method or "S256",
        client_name=client.get("client_name") or "an app",
        crm_host=CRM_BASE_URL.replace("https://", "").replace("http://", ""),
        error_html=error_html,
        prefill_email=prefill_email,
    )
    return HTMLResponse(html)


async def authorize(request: Request) -> Response:
    qs = request.query_params
    client_id = qs.get("client_id", "")
    redirect_uri = qs.get("redirect_uri", "")
    state = qs.get("state", "")
    code_challenge = qs.get("code_challenge", "")
    code_challenge_method = qs.get("code_challenge_method", "S256")
    response_type = qs.get("response_type", "code")

    if response_type != "code":
        return JSONResponse({"error": "unsupported_response_type"}, status_code=400)

    client = _clients.get(client_id)
    if not client:
        return JSONResponse({"error": "invalid_client", "error_description": "unknown client_id"}, status_code=400)
    if redirect_uri not in client["redirect_uris"]:
        return JSONResponse(
            {"error": "invalid_redirect_uri", "error_description": "redirect_uri not registered"},
            status_code=400,
        )

    return _render_login(
        client_id=client_id,
        redirect_uri=redirect_uri,
        state=state,
        code_challenge=code_challenge,
        code_challenge_method=code_challenge_method,
    )


# ---------------------------------------------------------------------------
# Login form submit → mint auth code
# ---------------------------------------------------------------------------

async def login(request: Request) -> Response:
    form = await request.form()
    client_id = form.get("client_id", "")
    redirect_uri = form.get("redirect_uri", "")
    state = form.get("state", "")
    code_challenge = form.get("code_challenge", "")
    code_challenge_method = form.get("code_challenge_method", "S256")
    email = (form.get("email") or "").strip()
    password = form.get("password") or ""

    client = _clients.get(client_id)
    if not client or redirect_uri not in client["redirect_uris"]:
        return JSONResponse({"error": "invalid_client"}, status_code=400)

    # Authenticate against the CRM's Sanctum login
    try:
        async with httpx.AsyncClient(timeout=15.0) as ac:
            r = await ac.post(
                f"{CRM_BASE_URL}/api/v1/auth/login",
                json={"email": email, "password": password},
                headers={"Accept": "application/json"},
            )
    except httpx.HTTPError as e:
        logger.exception("CRM login request failed")
        return _render_login(
            client_id=client_id, redirect_uri=redirect_uri, state=state,
            code_challenge=code_challenge, code_challenge_method=code_challenge_method,
            error=f"Couldn't reach CRM: {e}", prefill_email=email,
        )

    if r.status_code != 200:
        return _render_login(
            client_id=client_id, redirect_uri=redirect_uri, state=state,
            code_challenge=code_challenge, code_challenge_method=code_challenge_method,
            error="Invalid email or password.", prefill_email=email,
        )

    data = r.json()
    sanctum_token = data.get("token")
    if not sanctum_token:
        return _render_login(
            client_id=client_id, redirect_uri=redirect_uri, state=state,
            code_challenge=code_challenge, code_challenge_method=code_challenge_method,
            error="CRM did not return a token.", prefill_email=email,
        )

    # Mint a one-time auth code
    _gc_codes()
    code = secrets.token_urlsafe(32)
    _codes[code] = {
        "token": sanctum_token,
        "user": data.get("user"),
        "client_id": client_id,
        "redirect_uri": redirect_uri,
        "code_challenge": code_challenge,
        "code_challenge_method": code_challenge_method,
        "expires_at": time.time() + AUTH_CODE_TTL_SEC,
    }

    qs = urlencode({"code": code, "state": state} if state else {"code": code})
    sep = "&" if "?" in redirect_uri else "?"
    return RedirectResponse(f"{redirect_uri}{sep}{qs}", status_code=302)


# ---------------------------------------------------------------------------
# Token exchange
# ---------------------------------------------------------------------------

async def token(request: Request) -> JSONResponse:
    form = await request.form()
    grant_type = form.get("grant_type", "")
    code = form.get("code", "")
    redirect_uri = form.get("redirect_uri", "")
    client_id = form.get("client_id", "")
    code_verifier = form.get("code_verifier", "")

    if grant_type != "authorization_code":
        return JSONResponse(
            {"error": "unsupported_grant_type"}, status_code=400,
        )

    record = _codes.pop(code, None)  # one-time use
    if not record:
        return JSONResponse({"error": "invalid_grant", "error_description": "unknown or expired code"}, status_code=400)
    if record["expires_at"] < time.time():
        return JSONResponse({"error": "invalid_grant", "error_description": "code expired"}, status_code=400)
    if record["client_id"] != client_id:
        return JSONResponse({"error": "invalid_grant", "error_description": "client_id mismatch"}, status_code=400)
    if record["redirect_uri"] != redirect_uri:
        return JSONResponse({"error": "invalid_grant", "error_description": "redirect_uri mismatch"}, status_code=400)

    if record["code_challenge"]:
        if not code_verifier:
            return JSONResponse({"error": "invalid_grant", "error_description": "code_verifier required"}, status_code=400)
        if not _verify_pkce(code_verifier, record["code_challenge"], record["code_challenge_method"]):
            return JSONResponse({"error": "invalid_grant", "error_description": "PKCE verification failed"}, status_code=400)

    return JSONResponse({
        "access_token": record["token"],
        "token_type": "Bearer",
        "expires_in": TOKEN_TTL_SEC,
        "scope": "mcp",
    })


# ---------------------------------------------------------------------------
# Routes
# ---------------------------------------------------------------------------

routes = [
    Route("/.well-known/oauth-authorization-server", oauth_metadata),
    Route("/.well-known/oauth-protected-resource", protected_resource),
    Route("/.well-known/oauth-protected-resource/api/v1/mcp", protected_resource),
    Route("/oauth/register", register, methods=["POST"]),
    Route("/oauth/authorize", authorize, methods=["GET"]),
    Route("/oauth/login", login, methods=["POST"]),
    Route("/oauth/token", token, methods=["POST"]),
]
