# Union Bay CRM - Project Instructions

## Customer
Union Bay Risk - paying customer deployed on Railway.

## Repository
- **Remote:** git@github.com:quome-cloud/unionbay-crm.git
- **Branch:** `main` (autodeploy to Railway)

## Trello Board (Project Management)
- **Board:** https://trello.com/b/IWLr4Pub/quome-union-bay-risk-todo
- **Board ID:** `69c4c35a9048e3ade832e623`
- **MCP Server:** Configured in `.mcp.json` (gitignored) using `@delorenj/mcp-server-trello`

### Columns
| Column | Purpose |
|--------|---------|
| **Backlog** | All upcoming work, ordered by priority from top to bottom |
| **In Progress** | Currently being worked on (one ticket at a time) |
| **Blocked** | Work that cannot proceed — must have a comment explaining why |
| **Review** | Complete and committed, awaiting review or verification |
| **Done** | Committed, tested, pushed, and deployed |

### Labels
| Label | Color | Use for |
|-------|-------|---------|
| Bug Fix | Red | Defects and broken functionality |
| Feature | Green | New functionality |
| Infrastructure | Blue | Deployment, CI/CD, tooling |
| Urgent | Orange | High-priority, needs immediate attention |

### Trello Workflow (MUST follow for every ticket)
1. **Pick a ticket** from the top of the Backlog
2. **Move the card to In Progress** before starting any work
3. **Work the ticket** — write code, write Playwright tests, verify locally
4. **Commit and push** at least once per ticket (each ticket = separate commit + push to `main`)
5. **If blocked:** move to **Blocked**, add a comment explaining the blocker
6. **If needs review:** move to **Review**, add a comment with what to check
7. **When done:** move to **Done** and add a comment with:
   - Commit hash
   - Summary of changes
   - Playwright test results (pass/fail counts)
   - Screenshot if applicable (use `npx playwright screenshot` or `page.screenshot()`)
8. **Use labels** to categorize cards (Bug Fix, Feature, Infrastructure, Urgent)

### Keeping the Backlog Up to Date
- New work items go into **Backlog**, ordered by priority (most important at top)
- When requirements change or new asks come in, add cards to Backlog
- If a ticket turns out to be unnecessary or duplicate, archive it
- If a ticket is too large, break it into smaller cards

## Railway Deployment & Production
- **Project ID:** `d65119ac-4b43-483b-b54a-d911d465e464`
- **Environment ID:** `07ef2598-e1e7-4486-8dc9-281112b70877`
- **Service:** `crm-app`
- **Railway Host:** `crm-app-production-23d7.up.railway.app`
- **Dashboard:** https://railway.com/project/d65119ac-4b43-483b-b54a-d911d465e464
- **Prod URL:** https://cornerstone-crm.quome.dev/admin/login
- Pushes to `main` trigger autodeploy automatically

### Production Deployment Rules
- **Minimize breaking production** — test locally with Docker Compose before pushing
- Pushes don't need to happen after every single ticket, but **periodically push batches** to keep prod up to date
- After pushing, **verify prod is healthy**: `curl -s -o /dev/null -w "%{http_code}" https://cornerstone-crm.quome.dev/admin/login` should return 200
- If prod breaks after a push, investigate immediately — check Railway logs via dashboard or `railway logs`
- Migrations run automatically on deploy via the prod entrypoint
- **Never push code that fails local Playwright tests**

## Testing Requirements
- **Every ticket MUST include Playwright tests** — no exceptions
- Tests should validate the primary user flows affected by the change
- Run `npx playwright test` locally to verify before committing
- Include test pass/fail counts in the Done comment on Trello
- Tests live in `tests/e2e/` and run against the Docker Compose local environment

### Taking Screenshots for Verification
```bash
# During a Playwright test
await page.screenshot({ path: 'test-results/ticket-name.png', fullPage: true });

# Quick screenshot via CLI
npx playwright screenshot http://localhost:8190/admin/relevant-page test-results/ticket-name.png
```

## Workflow Summary
1. Pick ticket from Backlog → move to In Progress
2. Write code + Playwright tests
3. Run tests locally (`npx playwright test`)
4. Commit with descriptive message
5. Push to `main` (triggers Railway deploy)
6. Verify prod is healthy
7. Move card to Done with verification comment

## Stack
- Laravel PHP 8.2 (Krayin CRM base)
- MySQL 8.0
- Redis (cache/queue)
- Docker Compose for local dev (`docker compose up -d`)
- Playwright for E2E tests
- Trello MCP for project management
