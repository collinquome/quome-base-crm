# Deployment Guide

## Prerequisites

- Docker & Docker Compose
- Node.js 18+ (for E2E tests and Zapier CLI)
- Git

## Quick Start (Docker)

```bash
git clone <repo-url>
cd base_crm
docker compose up -d
```

This starts:
- **app** — Laravel PHP 8.2 application (Quome CRM)
- **nginx** — Reverse proxy on port 8190
- **db** — MySQL 8.0
- **redis** — Cache and queue backend
- **mailpit** — Local email testing (port 8025)
- **queue** — Laravel queue worker
- **scheduler** — Laravel task scheduler
- **soketi** — WebSocket server for real-time events

Access the CRM at: `http://localhost:8190/admin/login`

Default credentials:
- Email: `admin@example.com`
- Password: `admin123`

## Environment Configuration

Copy and customize the environment file:

```bash
cp crm/.env.example crm/.env
```

Key variables:

| Variable | Description | Default |
|----------|-------------|---------|
| `APP_URL` | Public URL of the CRM | `http://localhost` |
| `DB_HOST` | MySQL host | `db` (Docker service) |
| `DB_DATABASE` | Database name | `laravel-crm` |
| `DB_PASSWORD` | Database password | (set in docker-compose) |
| `REDIS_HOST` | Redis host | `redis` |
| `MAIL_HOST` | SMTP host | `mailpit` |
| `PUSHER_HOST` | WebSocket host | `soketi` |

## Database Migrations

Migrations run automatically on first start. To run manually:

```bash
docker compose exec app php artisan migrate
```

## White-Label Branding

Place brand assets in `crm/public/demo-brand/`:

- `logo.png` — Main logo (transparent PNG recommended)
- `logo-dark.png` — Logo for dark backgrounds
- `favicon.png` — Favicon (128x128)

Configure via API:
```bash
curl -X PUT http://localhost:8190/api/v1/white-label/config \
  -H "Authorization: Bearer <token>" \
  -H "Content-Type: application/json" \
  -d '{
    "company_name": "Your Company",
    "primary_color": "#2563eb",
    "accent_color": "#7c3aed"
  }'
```

## Email Account Setup

1. Navigate to **Email > Email Accounts** in the sidebar
2. Click **Add Email Account**
3. Enter IMAP/SMTP credentials
4. Enable email sync

API method:
```bash
curl -X POST http://localhost:8190/api/v1/email-accounts \
  -H "Authorization: Bearer <token>" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Work Email",
    "email": "user@company.com",
    "imap_host": "imap.gmail.com",
    "imap_port": 993,
    "imap_encryption": "ssl",
    "smtp_host": "smtp.gmail.com",
    "smtp_port": 587,
    "smtp_encryption": "tls",
    "password": "app-specific-password"
  }'
```

## Zapier Integration

The CRM includes a Zapier integration in the `zapier/` directory.

### Setup

```bash
cd zapier
npm install
zapier register  # First time only
zapier push
```

### Available Triggers
- New Contact Created
- New Lead Created
- Lead Stage Changed
- Deal Won / Deal Lost
- New Activity Created
- Email Received

### Available Actions
- Create Contact
- Create Lead
- Update Lead Stage
- Create Activity
- Send Email

## Make.com Integration

Configuration files are in `make-com/`. Import `app.json` into Make.com's custom app builder. Uses the same webhook subscription system as Zapier.

## API Authentication

```bash
# Get a bearer token
curl -X POST http://localhost:8190/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email": "admin@example.com", "password": "admin123"}'
```

Use the returned token in subsequent requests:
```
Authorization: Bearer <token>
```

## Running E2E Tests

```bash
npm install
npx playwright install
npx playwright test
```

Set `BASE_URL` to target a different environment:
```bash
BASE_URL=http://staging.example.com npx playwright test
```

## Production Considerations

1. **Change default credentials** — Update admin password immediately
2. **Set `APP_ENV=production`** and `APP_DEBUG=false`
3. **Configure real SMTP** — Replace mailpit with your SMTP provider
4. **Enable HTTPS** — Update nginx config with SSL certificates
5. **Set queue driver** — Use Redis for production: `QUEUE_CONNECTION=redis`
6. **Configure backups** — Use the built-in backup system (Settings > Automation > Data Transfer)
7. **Set up cron** — The scheduler container handles this in Docker; for bare-metal, add Laravel's scheduler to crontab
