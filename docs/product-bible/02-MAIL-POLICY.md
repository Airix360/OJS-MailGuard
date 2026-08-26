# OJS MailGuard — Mail Policy and State Machines

**State:** PLAN  
**Depends on:** `00-PRODUCT-BIBLE.md`, `01-ARCHITECTURE.md`

---

## 1. Purpose

This document defines how MailGuard decides whether a message may be delivered, when it may be delivered, and how its state changes over time.

Policy decisions must be deterministic from persisted inputs wherever possible and must produce an explicit reason code.

---

## 2. Policy evaluation order

For a controlled delivery, evaluate in this order:

1. MailGuard operational mode
2. mail type registration/classification
3. source/campaign validity
4. recipient identity validity
5. normalized address validity
6. hard/global physical suppression
7. message necessity/class rules
8. context/category preference
9. campaign dedup/idempotency
10. recipient frequency/cooldown rule if enabled
11. provider route availability
12. provider/site/context capacity
13. provider/domain backoff
14. earliest-send/scheduling window
15. release lock
16. transport attempt

A failure at an earlier gate should not be hidden by a later gate. Example: a hard-bounced address should report `suppressed_hard_bounce`, not merely `quota_exhausted`.

---

## 3. Preference resolution

### 3.1 Essential/workflow mail

Preference centre opt-outs do not disable essential mail. Physical delivery suppressions still apply.

### 3.2 Optional/subscription mail

Resolution order, most specific first:

1. explicit message-type preference for the journal/context;
2. category preference for the journal/context;
3. explicit site-wide category preference if the site enables site-wide controls;
4. imported/native OJS preference where MailGuard has not yet established its own value;
5. journal default;
6. site default.

Once MailGuard has an explicit context/category preference, native cross-context blocking must not override it accidentally.

### 3.3 Unsubscribe operations

One-click unsubscribe should target the narrowest useful scope by default:

- new issue email -> `context + new_issues`;
- announcement -> `context + announcements`.

The preference centre may separately expose:

- all optional mail for this journal;
- all optional mail site-wide, only when the site administrator enables that concept.

The one-click endpoint must not silently unsubscribe the user from every journal.

---

## 4. Deduplication policy

### 4.1 Subscription campaigns

Default dedup key:

```text
campaign_id + normalized_email_hash
```

One address receives one logical campaign delivery regardless of how many OJS accounts resolve to it.

MailGuard records all contributing OJS user IDs for audit/traceability but selects one canonical recipient profile for personalization according to deterministic rules.

Canonical-selection rule direction:

1. account explicitly associated with the journal and active;
2. account with most recent relevant activity;
3. lowest stable user ID as final deterministic tie-breaker.

The exact selection algorithm must be tested and documented before implementation freeze.

### 4.2 Transactional/workflow

Do not use address-only campaign dedup.

Default logical key includes the business object/event, for example:

```text
mail_type + submission_id + event_id + recipient_user_id
```

Two submission decisions sent to the same address are distinct messages.

### 4.3 Normalization

Baseline normalization:

- trim surrounding whitespace;
- case-fold domain;
- use conservative local-part handling;
- do not apply provider-specific transformations such as stripping Gmail dots or plus aliases by default.

MailGuard must not assume two syntactically different addresses are the same mailbox unless a deployment explicitly opts into a provider-specific normalization rule.

---

## 5. Campaign state machine

States:

```text
planned
previewed
queued
running
paused
completed
completed_with_errors
cancelled
```

### Transitions

```text
planned -> previewed -> queued -> running -> completed
                          |         |          |
                          |         |          +-> completed_with_errors
                          |         +-> paused -> running
                          |         +-> cancelled
                          +-> cancelled
```

Rules:

- `planned` has source metadata but may not yet have frozen recipients.
- `previewed` is advisory unless frozen immediately into `queued`; counts must be recalculated if source data changes.
- `queued` means durable delivery intents exist.
- `running` means at least one delivery has been released/attempted.
- `paused` stops future release; in-flight attempts may still finish.
- `cancelled` prevents unsent deliveries from release but does not erase historical attempts.
- `completed` requires all logical deliveries to be terminal without unhandled failures.
- `completed_with_errors` means terminal but one or more deliveries ended failed/uncertain.

Explicit resend creates a **new generation/campaign**, never reopens a completed campaign into a duplicate-prone state.

---

## 6. Delivery state machine

States:

```text
pending
queued
held
claimed
sending
accepted
deferred
suppressed
deduplicated
cancelled
failed
uncertain
expired
```

### Meaning

- `pending`: created but eligibility/persistence transaction not finalized.
- `queued`: eligible and waiting for policy release.
- `held`: eligible in principle but blocked by pause/quota/backoff/schedule.
- `claimed`: worker lease acquired.
- `sending`: transport call in progress.
- `accepted`: transport/provider accepted the message for delivery.
- `deferred`: temporary failure; retry scheduled.
- `suppressed`: permanently or policy-scope blocked before send.
- `deduplicated`: represented by another canonical delivery.
- `cancelled`: administrator/campaign cancellation before send.
- `failed`: terminal known failure.
- `uncertain`: provider may have accepted but local confirmation is not trustworthy.
- `expired`: policy decided stale mail should no longer be sent.

`delivered` may be a provider-feedback event/status layered on top of `accepted`, not a guarantee available from generic SMTP.

---

## 7. Attempt policy

Every transport attempt has its own immutable record.

Attempt attributes include:

- attempt number;
- start/end timestamps;
- route/provider;
- immediate result category;
- provider message ID if available;
- SMTP/provider response code where safe;
- retry-after value if provided;
- correlation ID;
- worker identity/lease metadata;
- normalized failure reason.

A retry increments attempt number but retains the same logical delivery ID.

---

## 8. Retry policy

Default retry classes:

### Temporary transport/provider failure

Examples: timeout, `421`, `429`, transient `4xx`, service unavailable.

Default direction:

```text
attempt 1 -> +5 minutes
attempt 2 -> +15 minutes
attempt 3 -> +1 hour
attempt 4 -> +4 hours
attempt 5 -> +12 hours
attempt 6 -> terminal/extended retry policy
```

Exact defaults are configurable and frozen during implementation planning.

Honor provider `Retry-After` where trustworthy and longer than the local minimum.

### Permanent recipient failure

Examples: explicit mailbox-not-found/hard-bounce response.

- terminal failure;
- create/update suppression according to provider evidence;
- no automatic retry.

### Content/policy failure

Examples: malformed recipient, missing required context, invalid sender configuration.

- terminal or administrative hold;
- do not retry in a tight loop.

### Uncertain send

If process failure occurs after the provider may have accepted the message:

- set `uncertain` unless provider idempotency/reconciliation proves outcome;
- do not automatically send again merely because the worker restarted;
- surface for reconciliation or safe provider lookup.

---

## 9. Suppression precedence

Strongest to weakest:

1. legal/policy prohibition
2. address-invalid / hard-bounce physical suppression
3. provider block that makes delivery impossible on selected route
4. spam complaint suppression
5. manual administrative suppression
6. repeated soft-bounce temporary suppression
7. optional preference opt-out
8. recipient/category cooldown

A stronger suppression cannot be bypassed by a weaker preference.

### Restoration

- hard-bounce restoration requires deliberate admin action or verified address change;
- spam-complaint restoration must not be automatic;
- repeated-soft-bounce suppression may expire after configured cooling period;
- manual suppression restoration requires permission and audit reason;
- preference opt-out is restored by recipient/admin preference change according to permissions.

---

## 10. Quota model

Quota dimensions may include:

- provider/day;
- provider/hour;
- provider/minute;
- site/day;
- context/day;
- class/day;
- domain/minute;
- concurrent sends.

Each quota has:

- window definition;
- timezone/source;
- hard or soft enforcement;
- reserve rules;
- current consumption;
- scheduled reset;
- administrative override policy.

### Capacity reservation

Example:

```text
daily provider limit 300
P0 reserve            20
P1 reserve            50
bulk discretionary   230
```

Bulk may not consume the protected 70.

Critical/workflow may consume discretionary capacity when needed.

Whether unused reserve is released late in the window is an optional advanced policy and disabled by default in v1.

---

## 11. Cooldown and frequency policy

Types:

### Event cooldown

Same logical event/recipient cannot generate a second delivery within the same idempotency identity.

### Campaign cooldown

Optional category may define minimum spacing between separate campaigns.

### Recipient optional-mail cap

Example configurable policy:

```text
maximum 3 optional emails / recipient / 24h
```

This does not apply to essential mail.

### Provider/domain cooldown

Temporary provider feedback can impose route- or domain-specific backoff.

---

## 12. Expiration policy

Some mail should become stale rather than arriving days late.

Examples:

- announcement/publication mail may have configurable expiry after several days;
- time-sensitive reviewer reminders may have event-specific expiry;
- password resets are usually generated with their own token lifetime and should not be held indefinitely.

Each mail type can define `max_queue_age`.

When exceeded:

- delivery becomes `expired`;
- reason is recorded;
- no silent send later.

---

## 13. Pause semantics

### Pause bulk

- blocks P3/subscription release;
- already accepted provider messages cannot be recalled;
- P0-P2 continue;
- queue remains intact.

### Pause all

- blocks all new MailGuard transport release;
- in-flight calls may finish;
- queue remains intact;
- webhooks continue to be processed if safe so state does not go stale.

### Campaign pause

- only one campaign stops releasing;
- does not alter preferences/suppressions.

All pause/resume actions are audited.

---

## 14. Reason-code catalogue

Initial codes:

```text
eligible
queued_waiting_capacity
queued_waiting_schedule
held_bulk_paused
held_all_paused
held_provider_backoff
held_domain_backoff
held_recipient_cooldown
suppressed_hard_bounce
suppressed_invalid_address
suppressed_spam_complaint
suppressed_provider_block
suppressed_manual
suppressed_soft_bounce_threshold
opted_out_context_category
opted_out_site_category
deduplicated_same_address
cancelled_campaign
cancelled_delivery
expired_queue_age
failed_transport_permanent
failed_render
failed_configuration
failed_provider_auth
uncertain_send_result
unknown_mailable_bypass
unknown_mailable_held
```

Codes are stable API/audit identifiers; human-facing copy is localized separately.

---

## 15. New-issue policy profile

Initial proposed profile:

```text
key: ojs.issue_published
class: subscription
priority: P3
campaignable: yes
context_required: yes
unsubscribe: context + new_issues
email_dedup: normalized address
retry: standard bulk
max_queue_age: configurable, default proposal 7 days
native_in_app_notification: preserve
quota_bucket: bulk
```

The 7-day default is a proposal, not frozen until implementation review.

---

## 16. Policy acceptance gate

Before implementation, tests must be specified for every state transition and every precedence conflict, especially:

- hard bounce vs explicit subscription preference ON;
- context opt-out vs site default ON;
- same email across two accounts in one campaign;
- same email across two distinct workflow events;
- quota exhaustion + critical reserve;
- pause/resume with workers active;
- temporary failure + retry + idempotency;
- uncertain send after worker crash;
- explicit resend generation;
- stale/expired queued mail.
