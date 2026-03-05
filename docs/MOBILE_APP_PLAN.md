# Mobile App Planning Document

## Overview

Cross-platform mobile CRM app built with React Native (Expo) targeting iOS and Android. Connects to the Quome CRM backend via the Public REST API.

## Core Screens

1. **Login / Auth** — Email + password, biometric unlock, session management
2. **Action Stream** — Prioritized list of next actions, swipe to complete/snooze
3. **Contacts** — Searchable contact list with quick-add, detail view
4. **Deals / Pipeline** — Kanban board (horizontal scroll), deal detail
5. **Activities** — Activity feed, create call/meeting/note
6. **Email** — Inbox, compose, reply (connected to synced email)
7. **Route Planner** — Map view of contacts, optimized route
8. **Business Card Scanner** — Camera capture, OCR, auto-create contact
9. **Notifications** — Push + in-app notification center
10. **Settings** — Account, sync preferences, branding display

## Technical Requirements

- **Minimum iOS:** 15.0
- **Minimum Android:** API 26 (Android 8.0)
- **Offline mode:** Cache contacts, deals, action stream for offline viewing; queue actions for sync
- **Push notifications:** Via FCM (Android) and APNs (iOS) through Expo Push
- **Deep linking:** Open specific contact/deal from push notification or URL
- **Biometric auth:** Face ID / Touch ID / Android biometrics
- **File handling:** Upload attachments, download documents

## API Dependencies

The mobile app requires the Public REST API (Phase 4 task) to be complete before development begins. Key endpoints needed:

- Authentication (login, token refresh, logout)
- Contacts CRUD
- Leads/Deals CRUD
- Activities CRUD
- Action Stream (list, complete, snooze, create)
- Pipeline stages
- Email (inbox, send, reply)
- Notifications (list, mark read)
- User profile

## Testing

- **Unit tests:** Jest for business logic and utilities
- **Component tests:** React Native Testing Library
- **E2E tests:** Detox for critical user flows
- **Manual testing:** TestFlight (iOS), Firebase App Distribution (Android)
