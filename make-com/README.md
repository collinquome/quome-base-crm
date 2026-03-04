# Quome CRM - Make.com Integration

This directory contains the Make.com (formerly Integromat) module definitions for Quome CRM.

## Setup

1. In Make.com, create a new custom app
2. Import the module definitions from this directory
3. Configure authentication with your CRM URL and API credentials

## Authentication

Uses API token authentication. The module will call `/api/v1/auth/login` to obtain a Bearer token.

## Available Triggers (Webhook-based)

All triggers use the CRM's webhook subscription system at `/api/v1/webhooks/subscribe`.

- **New Contact** - Fires when a contact is created
- **New Lead** - Fires when a lead is created
- **Lead Stage Changed** - Fires when a lead moves pipeline stages
- **Deal Won** - Fires when a deal is marked as won
- **Deal Lost** - Fires when a deal is marked as lost
- **New Activity** - Fires when an activity is logged
- **Email Received** - Fires when an email is synced

## Available Actions

- **Create Contact** - POST `/api/v1/contacts`
- **Create Lead** - POST `/api/v1/leads`
- **Update Lead Stage** - PUT `/api/v1/leads/{id}`
- **Create Activity** - POST `/api/v1/activities`
- **Send Email** - POST `/api/v1/emails/bulk`

## Webhook Events

The CRM sends POST requests to registered webhook URLs with this payload format:

```json
{
  "event": "new_contact",
  "data": { ... },
  "triggered_at": "2026-03-04T10:00:00Z"
}
```
