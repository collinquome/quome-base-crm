# Union Bay CRM — MCP server

Lets Claude Desktop, Claude.ai, or Claude Code manage your CRM on your
behalf — list/create/update leads, manage next-actions, leave comments,
browse contacts, view the dashboard.

It's a thin Python wrapper over the existing `/api/v1/*` REST API
(Sanctum bearer tokens), so it inherits the CRM's permissions exactly
— Claude only sees what your user account sees.

Two deployment modes:

- **Hosted (Custom Connector)** — runs on Railway, uses OAuth so users
  sign in with their CRM email + password from inside Claude. **This is
  what you want for day-to-day use.**
- **Local stdio** — for hacking on the server itself, or as a fallback
  if the hosted version is unavailable.

---

## Tools exposed

| Tool | What it does |
|---|---|
| `whoami` | Confirm the token works and show the authenticated user |
| `list_pipelines` | Discover sales pipelines and their stage IDs |
| `list_leads` | Browse leads (filter by search / pipeline / stage) |
| `get_lead` | Full detail for one lead |
| `create_lead` | Create a new lead |
| `update_lead` | Change title / value / stage |
| `delete_lead` | Soft-delete (recoverable from trash for 30 days) |
| `list_contacts` | Browse persons (filter by search) |
| `get_contact` | Full detail for one contact |
| `create_contact` | Quick-add a contact (name, email, phone) |
| `list_action_stream` | Today's prioritized to-do list |
| `add_next_action` | Schedule a follow-up on a lead/person |
| `complete_action` | Mark a next-action done |
| `snooze_action` | Push a next-action out to a future date |
| `dashboard_summary` | Pipeline + activity rollup |
| `overdue_count` | How many actions are past due right now |
| `add_comment` | Leave a note (use `@username` to mention) |

---

## Adding the dev server as a Custom Connector

The dev MCP server is deployed at:

> **`https://mcp-server-dev-0f6d.up.railway.app/api/v1/mcp`**

It talks to the dev CRM at `https://crm-app-dev-1b90.up.railway.app`.

### In Claude Desktop

1. Open **Settings → Connectors** (or the 🔌 icon in the chat input).
2. Click **Add custom connector**.
3. Paste the URL above. Claude will discover the OAuth endpoints
   automatically.
4. You'll be redirected to a sign-in page hosted by the MCP server.
   Use your **CRM email + password** (the same ones you use at
   `https://crm-app-dev-1b90.up.railway.app/admin/login`).
5. After sign-in, the connector shows up green and the 17 tools above
   are available in chat.

### In Claude.ai (web)

Same flow — **Settings → Connectors → Add custom connector**, paste
the URL.

### Auth model

When you sign in via the connector, the MCP server calls the CRM's
`/api/v1/auth/login` and uses the resulting Sanctum token *as* your
OAuth access token. Implications:

- The token is bound to your CRM user — Claude sees exactly what you'd
  see in the admin UI.
- Revoke access anytime from the CRM admin's Personal Access Tokens
  panel; the connector dies immediately.
- No separate token store, no JWT signing keys, no Auth0 dependency.

This is good enough for dev. For prod we'll likely want Microsoft SSO
(swap one function in `oauth_proxy.py`).

---

## Local stdio mode (alternative)

For hacking on the server, or if you don't want to use the hosted
deployment:

1. **Make sure the CRM is running locally:**
   ```sh
   cd ..
   docker compose up -d
   curl -s -o /dev/null -w "%{http_code}\n" http://localhost:8190/admin/login   # → 200
   ```

2. **Install deps:**
   ```sh
   cd mcp-server
   uv sync
   ```

3. **Mint a token:**
   ```sh
   uv run login.py
   # Email: admin@example.com
   # Password: ********
   ```

4. **Wire into Claude Desktop** by editing
   `~/Library/Application Support/Claude/claude_desktop_config.json`:
   ```json
   {
     "mcpServers": {
       "union-bay-crm-local": {
         "command": "uv",
         "args": [
           "--directory",
           "/Users/collinquome/code/quome/customers/union-bay-risk/cust-union-bay-risk-crm/mcp-server",
           "run", "server.py"
         ],
         "env": {
           "UNION_BAY_CRM_URL": "http://localhost:8190",
           "UNION_BAY_CRM_TOKEN": "PASTE_TOKEN_HERE"
         }
       }
     }
   }
   ```
   Restart Claude Desktop.

---

## Running the HTTP server locally

```sh
UNION_BAY_CRM_URL=http://localhost:8190 \
  uv run uvicorn main:app --host 127.0.0.1 --port 8765 --reload
```

Then probe:
```sh
curl -s http://127.0.0.1:8765/healthz
curl -s http://127.0.0.1:8765/.well-known/oauth-authorization-server | jq .
```

---

## Architecture

```
Claude Desktop / claude.ai
        │
        │  HTTPS
        ▼
┌─────────────────────────────────────┐
│  mcp-server (Railway)               │
│                                     │
│  /.well-known/*    ─┐               │
│  /oauth/register    │  OAuth 2.1    │
│  /oauth/authorize   │  proxy        │
│  /oauth/login       │               │
│  /oauth/token      ─┘               │
│                                     │
│  /api/v1/mcp        FastMCP         │
│      └─ 17 tools                    │
│         (calls CRM PublicApi        │
│          with the user's            │
│          Sanctum bearer)            │
└──────────────┬──────────────────────┘
               │  HTTPS + Bearer
               ▼
┌─────────────────────────────────────┐
│  CRM (Laravel — crm-app-dev)        │
│  /api/v1/auth/login                 │
│  /api/v1/leads, /contacts, etc.     │
└─────────────────────────────────────┘
```

## What's not in here yet

- No write access to bulk email send (intentional — easy to misuse).
- No reassignment (`leads.user_id` change) — manager action, admin-UI only.
- No GDPR erase (intentional — destructive).
- No integration toggles (QuickBooks/Xero/Mailchimp config).

If you want any of those exposed, add them to `server.py` — the pattern
is one `@mcp.tool()` decorated function per endpoint.

## Debugging the hosted deploy

```sh
railway logs --service mcp-server          # runtime logs
railway logs --service mcp-server --build  # build logs
railway variables --service mcp-server     # env
```

Set `UNION_BAY_CRM_DEBUG=1` to log every outbound CRM request to stderr.
