# Union Bay Risk CRM - Kickoff Meeting

**Date:** April 3, 2026
**Attendees:** Mike Tierney (Union Bay Risk), Collin Overbay (Quome)

---

## Overview

We've built a working CRM system on top of a proven open-source base (Krayin CRM) and customized it specifically for Union Bay Risk's insurance sales workflow. The system is deployed and accessible at [cornerstone-crm.quome.dev](https://cornerstone-crm.quome.dev/admin/login).

---

## What We've Accomplished

### Infrastructure & Setup
- Deployed the CRM to Railway with automated deployments on every push
- Set up project management via Trello: [Project Board](https://trello.com/b/IWLr4Pub/quome-union-bay-risk-todo)
- Configured automated E2E testing with Playwright
- Added Railway deployment documentation

### Branding & Customization
- Updated branding throughout the app with Union Bay Risk logo
- Renamed financial fields globally from "Lead Value/Revenue" to **Premium** to match insurance terminology
- Custom product model updates for insurance lines of business
- Added custom source types and data fields relevant to insurance sales

### Pipeline & Lead Management
- Implemented insurance-specific pipeline stages
- Added **Lead Type toggle** (Lead, Prospect, Client, Inactive) for lifecycle tracking
- Custom source types and data fields for insurance workflows

### Roles & Permissions
- Defined **Producer** and **Manager** roles for the sales team
- Manager visibility into employee dashboards so managers can monitor team performance

### Action System (Next Actions / Follow-Ups)
- Built a full **Next Action** system for scheduling and tracking follow-ups on leads
- Full editing capabilities for actions (create, edit, complete, snooze)
- Calendar/date picker UI for scheduling
- Fixed multiple bugs around save button functionality and UI contrast

### Dashboard & Reporting
- Built **dashboard analytics** with timeframe filtering
- **Timeline view** on leads/tickets for activity history
- **Historical performance reporting** by closed date
- Automated **reminders and follow-up notifications** for upcoming/overdue actions

### Bug Fixes (Resolved)
- Fixed action stream endpoint routing
- Fixed action stream logic issues
- Fixed Next Action calendar/date picker UI
- Fixed save button intermittent functionality
- Fixed font color contrast on action buttons

---

## Next Steps & Discussion Topics

### 1. HawkSoft Integration
- Exploring integration with **HawkSoft** (agency management system)
- Goal: sync client/policy data between HawkSoft and the CRM to reduce double-entry

### 2. Email / SMTP Setup
- Need **SMTP sending keys** from Mike to enable automated email sends from the CRM
- Figure out which email address(es) should be the sender
- This unlocks automated follow-up emails, notifications, and outreach

### 3. Custom Domain
- Currently hosted at `cornerstone-crm.quome.dev`
- Option to move to a custom domain (e.g., `crm.unionbayrisk.com`)
- **Decision needed:** Does Mike have a preferred domain?

### 4. Production Transition
- The system is ready to use as a **production system**
- **Key question for Mike:** When we make the switch to production use, do you want to:
  - **Migrate** the leads currently in the database over to the production system?
  - **Start fresh** and keep existing leads where they are now?

### 5. Set Up Shared Communication Channel
- Set up a shared Slack, Teams, or other channel for ongoing communication between Quome and Union Bay Risk
- Faster feedback loop for questions, bugs, and feature requests

### 6. Open Discussion
- What are the other asks? What features or improvements are most important?
- What do we want to accomplish in the **next week**?
- Going forward, we'll have a **weekly cadence** for check-ins and progress updates

---

## Resources

| Resource | Link |
|----------|------|
| CRM Login | [cornerstone-crm.quome.dev](https://cornerstone-crm.quome.dev/admin/login) |
| Trello Board | [Project Board](https://trello.com/b/IWLr4Pub/quome-union-bay-risk-todo) |
| GitHub Repo | [unionbay-crm](https://github.com/quome-cloud/unionbay-crm) (private) |
| Railway Dashboard | [Railway Project](https://railway.com/project/d65119ac-4b43-483b-b54a-d911d465e464) |
