# Union Bay CRM — MCP server

Lets Claude Desktop (or Claude Code, or any MCP client) manage your CRM
on your behalf — list/create/update leads, manage next-actions, leave
comments, browse contacts, view the dashboard.

It's a thin Python wrapper over the existing `/api/v1/*` REST API
(Sanctum bearer tokens), so it inherits the CRM's permissions exactly
— Claude only sees what your user account sees.

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

## Setup (≈ 2 minutes)

1. **Make sure the CRM is running locally.**
   ```sh
   cd ..
   docker compose up -d
   curl -s -o /dev/null -w "%{http_code}\n" http://localhost:8190/admin/login   # → 200
   ```

2. **Install the MCP server's deps.**
   ```sh
   cd mcp-server
   uv sync
   ```

3. **Mint a Sanctum token.**
   ```sh
   uv run login.py
   # Email: admin@example.com    (or your actual login)
   # Password: ********
   #
   # → prints two `export …` lines
   ```

4. **Wire it into Claude Desktop.** Open
   `~/Library/Application Support/Claude/claude_desktop_config.json`
   and add (replace `TOKEN_HERE` with the token from step 3):

   ```json
   {
     "mcpServers": {
       "union-bay-crm": {
         "command": "uv",
         "args": [
           "--directory",
           "/Users/collinquome/code/quome/customers/union-bay-risk/cust-union-bay-risk-crm/mcp-server",
           "run",
           "server.py"
         ],
         "env": {
           "UNION_BAY_CRM_URL": "http://localhost:8190",
           "UNION_BAY_CRM_TOKEN": "TOKEN_HERE"
         }
       }
     }
   }
   ```

5. **Restart Claude Desktop.** You should see a 🔌 with `union-bay-crm`
   in the bottom-right of the input box. Try:
   - *"What's on my action stream today?"*
   - *"Show me leads in the Quoting stage."*
   - *"Create a lead for John Smith on commercial auto in the default pipeline, prospect stage."*
   - *"Snooze action #42 until tomorrow morning."*

## Using it from Claude Code instead

The same env vars work. Add to `.mcp.json` at the project root:

```json
{
  "mcpServers": {
    "union-bay-crm": {
      "command": "uv",
      "args": ["--directory", "./mcp-server", "run", "server.py"],
      "env": { "UNION_BAY_CRM_URL": "http://localhost:8190" }
    }
  }
}
```

Then export `UNION_BAY_CRM_TOKEN` in your shell before launching `claude`.

## Pointing at production

```sh
uv run login.py --url https://cornerstone-crm.quome.dev
```

Then change `UNION_BAY_CRM_URL` in your Claude config to
`https://cornerstone-crm.quome.dev` and restart.

## Debugging

Set `UNION_BAY_CRM_DEBUG=1` to log every request to stderr.
Claude Desktop captures stderr from MCP servers in its log file:

```sh
tail -f ~/Library/Logs/Claude/mcp*.log
```

If a tool returns `{"error": "unauthorized"}`, the token has been
revoked — re-run `uv run login.py`.

## What's not in here yet

- No write access to email send / bulk email (intentional — easy to misuse).
- No reassignment (`leads.user_id` change) — that's a manager action and
  belongs in the admin UI for now.
- No GDPR erase (intentional — destructive).
- No integration toggles (QuickBooks/Xero/Mailchimp config).

If you want any of those exposed, add them to `server.py` — the pattern
is one `@mcp.tool()` decorated function per endpoint.
