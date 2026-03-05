# Architecture Planning: White-Label CRM based on Quome Laravel CRM

## Overview

This project forks Quome Laravel CRM (v2.1) and extends it to achieve feature parity with OnePageCRM while adding white-label capabilities. The result is a self-hosted, brandable CRM with action-first workflows, mobile apps, email sophistication, and a rich integration ecosystem.

## Tech Stack

### Backend (Existing - Quome)
- **Framework:** Laravel (PHP 8.1+)
- **Database:** MySQL 8.0+
- **Queue:** Laravel Queue (Redis recommended for production)
- **Cache:** Redis
- **Search:** Laravel Scout (optional, for full-text search)

### Frontend (Existing - Quome)
- **SPA Framework:** Vue.js 3
- **Build Tool:** Vite
- **UI Components:** Custom Vue components

### New Additions
- **Mobile App:** React Native (Expo) — cross-platform iOS + Android
- **Browser Extension:** Chrome Extension (Manifest V3) — Lead Clipper
- **Real-Time:** Laravel WebSockets (Pusher protocol) or Soketi
- **Email Sync:** php-imap + SMTP for two-way sync
- **Email Tracking:** Tracking pixel + link wrapping service
- **API:** RESTful public API (Laravel Sanctum for auth)
- **E2E Testing:** Playwright (web) + Detox (mobile)
- **Integration Platform:** Zapier CLI for Zapier app, Make.com HTTP module

## Modular Architecture

Quome uses a package-based architecture under `packages/Webkul/`. New features follow the same pattern:

```
packages/Webkul/
├── ActionStream/          # NEW — Action Stream & Next Actions
├── Notification/          # NEW — In-app notifications & @mentions
├── EmailSync/             # NEW — Two-way IMAP/SMTP email sync
├── EmailTracking/         # NEW — Email open/click tracking
├── BulkEmail/             # NEW — Bulk email sending
├── ReportBuilder/         # NEW — Custom report builder
├── Forecast/              # NEW — Revenue forecasting
├── WhiteLabel/            # NEW — Branding & white-label config
├── PublicApi/             # NEW — Documented public REST API
├── CalendarSync/          # NEW — Google/Outlook calendar sync
├── ZapierConnector/       # NEW — Zapier integration
├── Activity/              # EXISTING — extend with Action Stream
├── Admin/                 # EXISTING — extend with notifications UI
├── Automation/            # EXISTING — extend with sequences
├── Contact/               # EXISTING — extend with enrichment
├── Email/                 # EXISTING — extend with IMAP sync
├── Lead/                  # EXISTING — extend with delivery pipeline
├── ... (other existing packages)
```

## White-Label System

The white-label system stores branding configuration in the database and filesystem:

- **Config table:** `white_label_settings` — app name, logo URLs, favicon, primary/secondary colors, email sender name, support URL
- **Asset override:** `/storage/branding/` — logo files, favicon, custom CSS
- **Theme system:** CSS custom properties driven by database values
- **Email templates:** Brandable email headers/footers
- **Login page:** Customizable login screen with branding
- **Admin setting page:** UI for uploading logos and setting colors

## Database Additions

### New Tables
- `next_actions` — links contacts/leads to their next scheduled action
- `notifications` — in-app notifications with read/unread state
- `comments` — polymorphic comments on any entity
- `email_accounts` — IMAP/SMTP credentials for two-way sync
- `email_tracking_events` — open/click events for tracked emails
- `scheduled_emails` — emails queued for future delivery
- `report_definitions` — saved custom report configurations
- `report_schedules` — scheduled report delivery settings
- `calendar_connections` — OAuth tokens for Google/Outlook calendars
- `white_label_settings` — branding configuration
- `api_tokens` — public API authentication tokens
- `delivery_pipeline_stages` — stages for post-sale pipelines

### Modified Tables
- `leads` — add `delivery_pipeline_id`, `deal_velocity_data` columns
- `activities` — add `is_next_action` flag, `priority` column
- `persons` — add `soft_deletes`, `enrichment_data` columns
- `pipeline_stages` — add `expected_days` for velocity tracking

## API Design

The public REST API follows JSON:API conventions:

```
/api/v1/contacts
/api/v1/contacts/{id}
/api/v1/leads
/api/v1/leads/{id}
/api/v1/deals
/api/v1/pipelines
/api/v1/activities
/api/v1/emails
/api/v1/tags
/api/v1/users
/api/v1/webhooks
/api/v1/action-stream
```

Authentication: Bearer token (Laravel Sanctum)

## Mobile App Architecture

React Native (Expo) app with:
- **State management:** Zustand or Redux Toolkit
- **API client:** Axios with interceptors for auth
- **Offline support:** AsyncStorage + sync queue
- **Push notifications:** Expo Notifications (FCM/APNs)
- **Navigation:** React Navigation v6
- **Maps:** react-native-maps (for Route Planner)
- **Camera:** expo-camera (for Business Card Scanner)

## Testing Strategy

- **Unit tests:** PHPUnit (backend), Jest (frontend, mobile)
- **Integration tests:** PHPUnit feature tests for API endpoints
- **E2E tests:** Playwright for web app, Detox for mobile app
- **Test coverage target:** 80%+ for new code
- **CI/CD:** GitHub Actions pipeline
