# OJS MailGuard — Administration and Recipient UX

**State:** PLAN

---

## 1. UX goal

MailGuard should make outbound email operations understandable to a journal manager without requiring SMTP expertise, while still exposing enough detail for a site administrator to diagnose delivery problems safely.

The interface must distinguish four concepts clearly:

1. **OJS intended recipients** — who OJS/business rules selected;
2. **MailGuard eligible deliveries** — who remains after preference/suppression/dedup policy;
3. **queued/released mail** — what MailGuard is pacing;
4. **provider feedback** — what happened after handoff.

Never label provider `accepted` as guaranteed human `delivered`.

---

## 2. Navigation

Proposed Site Administration navigation:

```text
MailGuard
  Overview
  Queue
  Campaigns
  Suppressions
  Providers
  Policies
  Mail Types
  Audit
  Diagnostics
```

Journal-level navigation, where permissions allow:

```text
MailGuard
  Overview
  Campaigns
  Preferences & Notices
  Suppressions (journal scope)
```

Do not expose site/provider secrets in journal screens.

---

## 3. Site overview

Top status strip:

```text
Mode: ENFORCE BULK
Provider: Brevo / Healthy
Queue: 931
Oldest queued: 5h 22m
Bulk: Running
Critical reserve: Protected
Webhook: Healthy
```

Summary cards:

- Sent/accepted today
- Queued
- Held by quota
- Deferred/retrying
- Suppressed
- Failed/uncertain

Capacity section:

```text
Provider daily capacity
187 / 300 consumed

Protected capacity
Critical reserve     20
Workflow reserve     50

Discretionary bulk available now
43
```

If a provider limit is configured manually rather than discovered, say so.

Health warnings appear above metrics when action is required:

- scheduler not running;
- webhook stale;
- provider authentication failing;
- queue age above policy;
- unknown OJS version/adapter mismatch;
- uncertain sends pending reconciliation;
- suppression spike;
- campaign recipient explosion.

---

## 4. Queue screen

Columns:

- status
- priority/class
- recipient masked email
- mail type
- journal
- campaign/source
- next attempt / held until
- attempt count
- reason
- age

Filters:

- journal/context
- class/priority
- state
- reason code
- provider
- campaign
- age
- date range

Bulk actions are conservative:

- pause selected campaign;
- cancel unsent campaign deliveries;
- retry specifically eligible failed/deferred messages;
- do **not** provide a casual “send all now ignoring limits” button.

Site administrator overrides require confirmation and audit reason.

---

## 5. Campaigns screen

Each campaign displays a funnel:

```text
Intended accounts             1,204
Unique normalized addresses   1,173
Opted out                        61
Suppressed                         8
Deduplicated account aliases      31
Eligible                        1,104
Accepted                          225
Queued                            879
Failed                              0
```

Campaign detail includes:

- source event/object;
- journal;
- initiated by;
- sender;
- mail type;
- generation;
- created/started/completed timestamps;
- frozen policy version;
- provider route;
- queue expiry;
- state timeline;
- recipient/reason breakdown;
- deliberate resend history.

### Resend UX

Button wording:

**Create resend campaign**

Not:

**Retry all**

Confirmation explains:

- this creates a new generation;
- previously accepted recipients may receive the message again;
- current preference/suppression rules will be evaluated according to selected resend policy;
- the action is audited.

Admin chooses one of clearly defined scopes, e.g.:

- failed/expired recipients only;
- never-accepted recipients only;
- all currently eligible recipients.

“Everyone again” must not be the accidental default.

---

## 6. New-issue publication UX

MailGuard integrates into the existing publication workflow without making publication depend on immediate email completion.

When the editor selects the native/new issue notification option, MailGuard should present a preview or confirmation panel when supported:

```text
New Issue Notification

Subscribed OJS accounts          1,204
Unique email addresses           1,173
Unsubscribed / opted out             61
Delivery suppressed                  8
Eligible deliveries              1,104

Provider: Brevo
Bulk capacity available now        225
Remainder will remain safely queued.
```

Primary action:

**Publish & Queue Notification**

If recipient count is still resolving asynchronously, show honest state rather than fake precision:

```text
Publication can proceed now. MailGuard will finalize recipient eligibility before releasing email.
```

### Recipient explosion warning

Compare current campaign size against configurable historical/baseline signals.

Example:

```text
Warning: this notification targets 18,442 eligible addresses.
The previous three issue campaigns ranged from 590–710.

Review recipients before queueing.
```

This warning never auto-cancels a legitimate large journal by itself unless site policy explicitly sets a hard maximum.

---

## 7. Suppressions UX

Columns:

- masked email
- reason
- scope
- source/provider
- first seen
- last seen
- status
- restoration eligibility

Detail view explains plain-language consequence:

```text
Hard bounce
This address has been reported as permanently undeliverable.
MailGuard will not attempt further messages to this address until the suppression is deliberately restored or the user's address changes.
```

### Restore action

Requires:

- permission;
- explicit reason;
- warning appropriate to reason;
- audit record.

Spam complaints and hard bounces receive stronger warnings than temporary soft-bounce holds.

---

## 8. Provider UX

Provider card:

```text
Brevo
Status: Healthy
Mode: SMTP + feedback webhook
Configured daily limit: 300
Consumed today: 187
Webhook: Healthy
Last event: 2 minutes ago
Last send: 41 seconds ago
```

Actions:

- configure/test connection;
- verify webhook setup;
- send administrator test message;
- view normalized event counts;
- disable provider route safely.

Never display full API keys after save.

Provider test must not mutate recipient suppression state from a fabricated bounce unless explicitly run in a test sandbox.

---

## 9. Policies UX

Separate **site safety ceilings** from **journal preferences**.

Site policy sections:

- operational mode;
- provider capacity;
- class priority;
- protected reserve;
- retry/backoff;
- max queue age defaults;
- optional recipient frequency caps;
- domain throttles;
- unknown mailable behavior;
- retention;
- journal-manager permissions.

Configuration should include explanations such as:

> Bulk capacity is the portion that publication and announcement campaigns may use. Reserving capacity protects password and editorial workflow messages from being delayed by a large campaign.

Avoid terms like token bucket in the main UI even if implemented internally.

---

## 10. Mail types UX

Discovery table:

- MailGuard key
- OJS/PKP class
- discovered source
- class
- controlled/observed/bypassed
- unsubscribe mode
- last seen
- compatibility adapter

Unknown item warning:

```text
Unclassified mailable discovered
Class: Vendor\Plugin\Mail\SomethingHappened
First seen: ...
Current behavior: observed; native delivery allowed
```

Admin can inspect but built-in safety-critical classifications should not be casually editable without an advanced override path.

---

## 11. Audit UX

Audit entries are action-oriented, not raw logs.

Examples:

```text
Site Admin changed bulk daily limit: 225 -> 180
Journal Manager created resend generation #2 for Issue 42
System activated hard-bounce suppression from Brevo event
User unsubscribed from New Issues for Journal A via one-click link
Site Admin restored suppression with reason: address corrected
```

Filters:

- actor
- journal
- event type
- subject
- date range

---

## 12. Diagnostics UX

Diagnostics page must answer installation/upgrade questions.

Checks:

- OJS version detected;
- MailGuard compatibility adapter;
- required hooks registered;
- queue/scheduler heartbeat;
- migrations current;
- encryption capability;
- provider route valid;
- webhook route reachable/configured;
- cron/task runner configuration warning;
- unknown mailables discovered;
- stuck queue leases;
- oldest unprocessed provider event;
- PHP/database requirements.

Each result:

```text
PASS / WARN / FAIL
What was checked
Why it matters
Recommended action
```

No opaque green/red light with no explanation.

---

## 13. Recipient preference centre

Public signed URL requires no OJS login for the scoped recipient operation.

Page structure:

```text
Email preferences — Journal Name

Publication updates
[x] New issues
[x] New articles
[ ] Announcements
[x] Calls for papers

Essential account and editorial messages
These messages support your account, submissions, reviews, or editorial workflow and are not controlled by these optional email preferences.

[Save preferences]

[Unsubscribe from all optional emails for this journal]
```

If the user has accounts across multiple journals, the signed link should remain scoped to the originating journal by default. An authenticated broader preference page may later aggregate contexts safely.

### Success states

One-click POST response should be simple:

```text
You have been unsubscribed from New Issue emails for Journal Name.

Manage other email preferences
```

Do not require login after the standards-compliant one-click action.

---

## 14. Template merge variables

Proposed variables for eligible MailGuard mailables:

```text
{$mailGuardUnsubscribeUrl}
{$mailGuardPreferencesUrl}
{$mailGuardUnsubscribeJournalUrl}
{$mailGuardNotificationCategory}
```

Names are proposals until collision/reserved-variable review is complete.

Rules:

- variables appear in email-template documentation only when available to that mailable;
- editor may place them explicitly;
- if required subscription unsubscribe behavior is absent from the template, MailGuard adds a standard footer at render time;
- the template itself is not rewritten in the database.

---

## 15. Footer UX

Default subscription footer should be short and journal-specific.

Concept:

```text
You received this because you are subscribed to publication updates from Journal Name.
Unsubscribe from this type of email | Manage email preferences
```

Localized through normal OJS locale mechanisms.

Do not put a global “unsubscribe from everything” link as the primary action on a new-issue message.

---

## 16. Accessibility/localization

- all MailGuard interface strings localizable;
- no status communicated only by color;
- keyboard-accessible controls;
- tables include accessible labels and sensible responsive behavior;
- confirmation dialogs state the consequence in text;
- date/time displayed in configured/local context with timezone where operationally relevant;
- masked addresses remain distinguishable enough for administrators without excessive PII exposure.

---

## 17. UX acceptance gate

Before implementation freeze, wireframes or component-level specs must cover:

- Site overview
- Queue
- Campaign detail
- New-issue preview
- Suppression detail/restore
- Provider setup/health
- Policies
- Mail types/discovery
- Diagnostics
- Recipient preference centre
- One-click unsubscribe success/error/expired-token states
- pause/resume confirmations
- resend generation confirmation
