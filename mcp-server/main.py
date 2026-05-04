"""
ASGI entrypoint that mounts:

  - OAuth 2.1 proxy routes (`/.well-known/*`, `/oauth/*`)
  - MCP Streamable HTTP transport at `/api/v1/mcp` (FastMCP)
  - Bearer-extraction middleware that puts the token into the
    `_request_token` contextvar so tool handlers can read it.

Run locally:
    uv run uvicorn main:app --host 0.0.0.0 --port 8000 --reload

The Dockerfile uses uvicorn with $PORT for Railway.
"""

from __future__ import annotations

import logging

from starlette.applications import Starlette
from starlette.middleware import Middleware
from starlette.middleware.base import BaseHTTPMiddleware
from starlette.requests import Request
from starlette.responses import JSONResponse
from starlette.routing import Mount, Route

import oauth_proxy
from server import _request_token, mcp

logger = logging.getLogger(__name__)


class BearerTokenMiddleware(BaseHTTPMiddleware):
    """Extracts `Authorization: Bearer <token>` and stashes it in a contextvar.

    Only applies to `/api/v1/mcp` paths — the OAuth endpoints don't need it.
    """

    async def dispatch(self, request: Request, call_next):
        path = request.url.path
        token_set = False
        token_reset = None
        if path.startswith("/api/v1/mcp"):
            auth = request.headers.get("authorization", "")
            if auth.lower().startswith("bearer "):
                token = auth[7:].strip()
                token_reset = _request_token.set(token)
                token_set = True
            else:
                # Unauthenticated MCP call → 401 with WWW-Authenticate so the
                # client knows where to start the OAuth flow.
                base = request.headers.get("x-forwarded-proto", request.url.scheme) + "://" + (
                    request.headers.get("x-forwarded-host") or request.headers.get("host") or request.url.netloc
                )
                return JSONResponse(
                    {"error": "unauthorized", "error_description": "Bearer token required"},
                    status_code=401,
                    headers={
                        "WWW-Authenticate": (
                            f'Bearer realm="union-bay-crm-mcp", '
                            f'resource_metadata="{base}/.well-known/oauth-protected-resource"'
                        )
                    },
                )
        try:
            return await call_next(request)
        finally:
            if token_set and token_reset is not None:
                _request_token.reset(token_reset)


async def healthcheck(_request: Request) -> JSONResponse:
    return JSONResponse({"ok": True, "service": "union-bay-crm-mcp"})


# FastMCP gives us a Starlette app that handles `/mcp` (Streamable HTTP).
mcp_app = mcp.streamable_http_app()

routes = [
    Route("/", healthcheck),
    Route("/healthz", healthcheck),
    *oauth_proxy.routes,
    Mount("/api/v1", app=mcp_app),
]

app = Starlette(
    routes=routes,
    middleware=[Middleware(BearerTokenMiddleware)],
    lifespan=mcp_app.router.lifespan_context,
)
