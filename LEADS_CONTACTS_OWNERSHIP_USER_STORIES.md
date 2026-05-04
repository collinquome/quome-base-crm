# Leads & Contacts — Ownership User Stories & Test Plan

**Status:** draft for review (Mike Tierney + Collin)
**Created:** 2026-05-04
**Owner:** Collin
**Related ask:** `STILL_NEED_TODO.csv` #11 — verify producers can see assigned contact details after ACL tightening in commit `6b221b7`.

This document exists because the lead-write ACL tightening exposed a deeper question we never wrote down: **what does "ownership" actually mean across producers, managers, persons, leads, and shared/cross-sell scenarios?** Until we answer that, we can't write tests that we trust.

The proposals below are starting points, not decisions. Anything tagged **🟡 OPEN** needs Mike's call before we ship.

---

## 1. Actors

| Actor | Role label in CRM | What they do |
|---|---|---|
| **Producer** | role with per-user-scope view perms | Sales rep. Owns a book of leads/contacts. Should not see other producers' books. |
| **Manager** | `Administrator` (or future `Manager` role) | Sees everything. Reassigns leads. Reviews team performance. |
| **Owner** | `Administrator` | Per Mike: same view as Manager. Treat as Manager. |
| **Player-coach** | `Administrator` who also runs their own pipeline | Mike. Has a personal book *and* full visibility. Needs a "just mine" filter, not separate accounts. |

> Source: Mar 25 transcript, ~00:27:00 — *"There's only one role. Sales producer salesperson"*; ~00:27:30 — *"That would be me [as manager]… I'm both"*; ~00:28:20 — *"I can't just be view only. I have to have like an active pipeline as well."*

---

## 2. Core entities & the ownership question

The CRM has three relevant entities and they don't share a single owner field:

- **Person** (`persons` table) — a human. May appear in many leads over years.
- **Lead** (`leads` table) — a single sales opportunity. Has `user_id` (assigned producer) + `person_id`.
- **Activity / Next Action** — attached to a lead and inherits its scope.

**Today** (post `6b221b7`): lead **write** endpoints check ownership; lead **read** endpoints scope by `user_id`. Person read scope is what we need to verify — it's the actual subject of #11.

**Proposal:** treat ownership at two levels:

1. **Lead ownership** = the assigned producer (`leads.user_id`). Authoritative for that opportunity.
2. **Person stewardship** = the producer who created the person record OR has an active lead on them. A person can have one steward at a time; stewardship transfers when all their leads transfer.

This gives us the clean answer to "if Producer A and Producer B both have a lead on John Smith, who can edit John's phone number?" — whoever holds stewardship.

---

## 3. Happy-path user stories

### US-1 — Producer creates a lead
> As a producer, when I create a new lead, I become its owner and the contact (Person) is stewarded by me unless they already exist.

### US-2 — Producer views their own book
> As a producer, the leads page, Kanban, mega-search, and dashboard show only leads where I am `user_id`.

### US-3 — Producer views their own contacts
> As a producer, I can open the detail page for any Person on a lead I own, and edit Person fields if I'm the steward.

### US-4 — Manager sees everyone
> As a manager (Administrator), every list view defaults to all-producer scope. Per-producer dropdowns (Action Stream, Calendar, Dashboard) let me drill into one rep.

### US-5 — Manager reassigns
> As a manager, I can change `leads.user_id` from Producer A to Producer B. The lead disappears from A's view, appears in B's, full activity history is preserved, and a system-generated activity logs the reassignment.

### US-6 — Player-coach has both
> As Mike, my default is the manager-wide view. A "Just mine" filter chip on the leads page restricts to leads where I am `user_id`.

### US-7 — Cross-sell on existing person
> As Producer A, when I open a lead for a Person who already has another lead (mine or anyone's), the lead detail surfaces "this person has N other leads" with links — gated by my visibility.

---

## 4. Edge cases & open questions

### E-1 — Two producers add the same lead at the same time
**Scenario:** Producer A creates a lead for "John Smith — john@x.com." Five minutes later Producer B (no visibility into A's book) tries to create a lead for the same email.

**Options:**
| | Behavior | Tradeoff |
|---|---|---|
| (a) Hard block | "This person already exists. Talk to your manager." | Safest. Friction. Producer B can't even leave a note. |
| (b) Soft warn | Surface the match, offer "create a new lead on the existing person (you own the lead, A keeps the person)" or "create new person record anyway" | Best UX. Requires good match UI. |
| (c) Silent dedupe | Create the lead, attach to existing person, B owns the new lead | Risk of unintended merges. |
| (d) First-touch wins | Block silently | Worst — looks like a bug. |

**Proposal: (b) soft warn.** Match by email > phone > (first+last+org). On match, show:
- "John Smith already exists, last contacted by Alice on 4/29"
- Buttons: **[Add my lead to John Smith]** | **[Create separate person]** | **[Cancel]**

If B chooses "Add my lead": new lead created, `user_id = B`, `person_id = existing`, person stewardship stays with A.

🟡 **OPEN for Mike:** is the match key strict enough? Insurance often has multiple John Smiths in a county.

### E-2 — Bulk import collides with existing leads
**Scenario:** Producer A imports a 100-row CSV (Datalot lead drop). 5 emails already exist as leads owned by Producer B.

**Proposal:** import skips the 5 colliding rows and writes them to the import error report ("skipped: row 23, email matches existing lead owned by Bob Kerr"). A manager-only "force claim" checkbox on the import form can override.

🟡 **OPEN:** does Datalot's ingestion deserve a special path? The CSV importer is already shipped (commit `16023f2`); collision handling is a logical next iteration.

### E-3 — Person has multiple leads owned by different producers
**Scenario:** Alice owns the lead for John's commercial auto. Bob is recruiting John as an agent and creates a Recruits-stage lead. Both leads are valid and shouldn't block each other.

**Proposal:** allow. Each lead has its own owner. The Person remains stewarded by Alice (first to create). When Alice's lead closes (won/lost) and Bob's becomes the only active lead, stewardship can transfer to Bob via a manager action — or automatically after 30 days inactive (🟡 confirm with Mike).

### E-4 — Who can edit Person fields when there are multiple lead owners?
**Proposal:** only the steward. Other lead owners can edit their own lead but get a read-only Person panel with a "Request edit access" link to the manager.

🟡 **OPEN:** is this overhead worth it? Alternative: anyone with a lead on the person can edit. Pick simplicity if Mike's team is small enough that conflicts are rare.

### E-5 — Producer reassigned mid-quarter — what about historical reports?
**Scenario:** Manager moves a lead from Alice to Bob on Apr 10. End-of-Q2 report runs.

**Proposal (per Mar 25 transcript ~00:43:00, "make it more simple"):** reports use **current** ownership only. We do not reconstruct historical attribution. A reassignment activity is logged on the lead so the audit trail exists, but quarterly leaderboards reflect the current owner.

Mike explicitly said simpler is fine. Documenting it so we don't accidentally over-engineer later.

### E-6 — Producer leaves the company
**Proposal:** on user deactivation, prompt the manager to bulk-reassign their open pipeline. Closed leads stay attributed to the deactivated user (for the audit log) but do not appear on their (now-disabled) account.

🟡 **OPEN:** out of scope for first pass — document only.

### E-7 — Sharing a lead read-only with another producer
**Scenario:** Alice wants Bob to weigh in on a deal but doesn't want to hand it off.

**Proposal:** out of scope for v1. Mention the lead in a comment with `@bob` (commenting/@mentions exist per T035). Notification gives Bob a one-time link. If Bob needs persistent access, manager reassigns or adds him as a co-owner — whichever simpler model wins.

🟡 **OPEN:** are notifications enough, or do we need real shared access?

### E-8 — Recruits column ownership
**Current:** Recruits is a Kanban stage; column hidden for non-Administrators (commit `295466d`). Recruits live in the leads table.

**Proposal:** recruits inherit the same ownership model as leads. The recruiter is the lead owner. Manager sees all. **No change needed** — current behavior matches the model.

### E-9 — Dashboard / Action Stream / Calendar — who-sees-whose
The user-picker dropdowns we added (`29e76be` Action Stream, `ca24bd3` Calendar) already expose other producers' data **to administrators only**. Need to verify the picker is hidden / read-only / fails-closed for producers.

🟡 **OPEN — to verify in test TC-11.**

### E-10 — API parity
Every rule above must hold through `/api/v1/*` (the Public REST API), not just the admin UI. The MCP server we're about to build hits these endpoints, so a leak there means a leak through Claude.

---

## 5. Decisions we're proposing (recap)

| ID | Decision | Status |
|---|---|---|
| D-1 | Lead has one owner (`user_id`); Person has one steward | proposed |
| D-2 | Duplicate detection on email > phone > (first+last+org), soft-warn UX | proposed |
| D-3 | Bulk import skips owned collisions, lists in error report | proposed |
| D-4 | Reports use current ownership only (no historical attribution) | proposed, supported by Mar 25 transcript |
| D-5 | Player-coach = Administrator + "Just mine" filter (no separate role) | proposed |
| D-6 | Cross-sell visibility surfaces "N other leads exist" subject to scope | proposed |
| D-7 | Sharing = comments + @mentions; otherwise reassign | proposed |

---

## 6. Test plan

All tests are Playwright E2E in `tests/e2e/`, run against the Docker Compose stack on `http://localhost:8190`. Test fixtures should seed two producers (Alice, Bob) and one administrator (Mike).

**Naming convention:** `ownership-{nn}-{slug}.spec.ts`.

### Phase 1 — Tests that directly close ask #11

| # | Test | Setup | Assertion |
|---|---|---|---|
| TC-1 | Producer can view their own contact detail | Login Alice; person owned by Alice | GET `/admin/contacts/persons/{id}` → 200, person fields render |
| TC-2 | Producer cannot view another producer's contact detail | Login Alice; person owned by Bob | GET → 403 or redirect, no leak |
| TC-3 | Producer can edit their own contact | Login Alice | PUT update of owned person → 200, change persists |
| TC-4 | Producer cannot edit another producer's contact | Login Alice | PUT update of Bob's person → 403, no change |
| TC-5 | Manager can view any contact | Login Mike | GET both Alice's and Bob's persons → 200 |
| TC-6 | Producer's leads list excludes others | Login Alice | `/admin/leads` HTML contains only Alice's leads; mega-search "Persons" tab same |
| TC-7 | Public API parity | Same as TC-1..6 but through `/api/v1/contacts` and `/api/v1/leads` with sanctum tokens | Same outcomes |

### Phase 2 — Ownership semantics

| # | Test | Assertion |
|---|---|---|
| TC-8 | Manager reassigns a lead | After PUT lead.user_id, Alice no longer sees it; Bob does; reassignment activity logged on the lead |
| TC-9 | Producer cannot self-assign or reassign | PUT to leads.user_id by non-owner producer → 403 |
| TC-10 | Player-coach default view shows all | Mike → `/admin/leads` returns full list across producers |
| TC-11 | Player-coach "Just mine" filter | Mike applies filter → only Mike's `user_id` leads returned; verify Action Stream + Calendar pickers unavailable to non-admins |
| TC-12 | Recruits column hidden for producers | Already covered by `recruits-column-admin-only.spec.ts` — verify still passing under this model |

### Phase 3 — Duplicate detection (depends on D-2 ship)

| # | Test | Assertion |
|---|---|---|
| TC-13 | Duplicate person — same email | Alice creates John (email j@x.com); Bob attempts same → soft-warn dialog with options |
| TC-14 | "Add my lead to existing person" path | Bob picks "add my lead" → new lead created, `user_id = Bob`, `person_id` matches Alice's John, John's stewardship unchanged |
| TC-15 | "Create separate person" override | Bob picks override → new person record created (rare, manager-flag-only later) |
| TC-16 | Bulk import skips owned collisions | CSV with 10 rows, 3 colliding emails, import as Alice → 7 created, error report lists 3 with reason |

### Phase 4 — Multi-owner person scenarios

| # | Test | Assertion |
|---|---|---|
| TC-17 | Person fields read-only for non-steward lead owner | Alice = steward; Bob has lead on same person → Bob's lead view shows person panel as read-only |
| TC-18 | Stewardship transfer when all owner-A leads close | Alice's lead closes (won or lost), Bob has open lead → manager action transfers stewardship to Bob (or 🟡 verify auto rule) |

### Phase 5 — Cross-sell visibility (depends on D-6)

| # | Test | Assertion |
|---|---|---|
| TC-19 | Cross-sell hint visible within scope | Alice opens her lead; person has 2 other leads (1 hers, 1 Mike's) → both surface for Alice (she owns one and Mike's is global) |
| TC-20 | Cross-sell hint excludes inaccessible leads | Alice opens her lead; person also has a lead owned by Bob → Alice does NOT see the Bob lead in the hint |

### Phase 6 — Audit & reporting

| # | Test | Assertion |
|---|---|---|
| TC-21 | Reassignment audit log | After TC-8 reassignment, the lead's activity timeline contains "Reassigned from Alice to Bob by Mike on 2026-05-04" |
| TC-22 | Quarterly report uses current ownership (D-4) | Lead reassigned mid-period → end-of-period leaderboard credits Bob, not Alice |

---

## 7. Implementation order (suggested)

1. **Phase 1 only.** Settle whether contact-view ACL leaked (closes #11). 1 test file, 7 cases.
2. Add **TC-8 / TC-9 / TC-21** — they exercise the existing reassignment flow and surface bugs without new product surface.
3. Stop. Go to Mike with this doc + Phase 1 results. Get rulings on 🟡 items.
4. Ship D-2 / D-3 (duplicate detection + bulk import collision) → Phase 3 tests.
5. Defer Phase 4–5 until cross-sell becomes a real-world pain point (Mike will tell us).

---

## 8. Open questions for Mike

1. **D-2 match key** — email + phone + (first+last+org) — is that strict enough for insurance, where you have many "John Smith"s? Should we add ZIP or DOB?
2. **D-3 force-claim** — should bulk import ever override existing ownership, or always skip?
3. **D-4 historical reports** — confirm: leaderboards always reflect current ownership, never reconstruct?
4. **D-5 just-mine filter** — should Mike's *default* be all-producer or just-mine when he logs in? Sticky preference?
5. **E-3 stewardship transfer** — auto after 30 days inactive, or manager-only?
6. **E-4 person edit access** — strict (steward only) or loose (any lead owner)?
7. **E-7 sharing** — is "@mention in a comment" enough, or do you want explicit lead sharing?
