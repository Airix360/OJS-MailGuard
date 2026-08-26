# OJS MailGuard — Product Bible

**Document state:** PLAN / pre-implementation  
**Repository:** `Airix360/OJS-MailGuard`  
**Planning branch:** `planning/product-bible`  
**Product codename:** MailGuard  
**Primary platform:** Open Journal Systems (OJS)  
**Initial compatibility direction:** OJS 3.5.x first-class; OJS 3.6.x adapter/harness tracked against upstream; OJS 3.4.x compatibility evaluated as a legacy-support decision, not assumed.

---

## 1. Product definition

OJS MailGuard is an outbound email control plane for Open Journal Systems.

OJS remains responsible for deciding that a business event occurred, constructing its native mailable, resolving its native template, and expressing the intended recipients. MailGuard governs the delivery lifecycle around that message: classification, eligibility, preference enforcement, deduplication, idempotency, queueing, priority, throttling, provider quota, retries, bounce/complaint suppression, unsubscribe behavior, auditability, and operational controls.

The product is **not** a replacement newsletter platform, not a replacement for OJS email templates, and not an SMTP server. It is the policy and delivery-governance layer between OJS mail intent and the configured downstream transport/provider.

### One-sentence promise

> OJS decides what mail should exist; MailGuard decides whether, when, how, and to whom it may safely be delivered.

---

## 2. Why this product exists

The first concrete use case is the OJS new-issue publication notification. Current OJS builds subscribed user IDs, chunks them into jobs, and sends one mailable per recipient. This avoids role-assignment row fan-out, but it does not create a full delivery-control system.

MailGuard is intended to close the structural gaps that remain:

1. **Identity gap:** native recipient uniqueness is primarily user-oriented; two accounts can still map to the same delivery address.
2. **Idempotency gap:** an administrative replay or future integration error must not accidentally produce a second bulk campaign.
3. **Quota gap:** OJS job chunking is not the same as provider-aware daily/hourly/minute capacity management.
4. **Priority gap:** bulk publication mail must never consume capacity required by password, account, reviewer, editorial, or other critical workflow mail.
5. **Suppression gap:** failed addresses, hard bounces, spam complaints, and provider feedback need durable policy state.
6. **Preference gap:** optional mail needs clear, context-specific subscriptions with trustworthy journal boundaries.
7. **Deliverability gap:** one-click unsubscribe, responsible pacing, retry/backoff, domain-aware throttling, and sender-health controls should be systemic rather than template-by-template habits.
8. **Visibility gap:** administrators need to know what is queued, sent, deferred, suppressed, retried, failed, and why.
9. **Multi-journal gap:** a site-wide mail system must enforce site ceilings while preserving journal-specific policy and preferences.
10. **Extensibility gap:** future OJS plugins should be able to register mail classes and inherit the same safety controls.

---

## 3. Product principles

These are invariants, not suggestions.

### P-001 — Preserve OJS semantics
MailGuard must integrate with OJS mailables, templates, users, journals/contexts, roles, notifications, and jobs. It must not fork core OJS behavior unless no supported extension point can provide the required guarantee.

### P-002 — No core patches as the default architecture
Core modifications are a last-resort compatibility adapter, never the primary installation model. Upgrades must not require reapplying manual OJS patches.

### P-003 — Durable before deliver
When MailGuard controls a send, the delivery intent must be persisted before downstream delivery is attempted. A process crash must not silently lose mail.

### P-004 — Bulk mail can wait; critical mail cannot be starved
Publication and announcement campaigns may span hours or days when provider capacity is constrained. Critical/security/workflow mail must retain reserved capacity and higher priority.

### P-005 — Idempotency is explicit
Every MailGuard-controlled delivery must have a deterministic or recorded idempotency identity. Re-running a worker may retry a delivery; it must not create a logically new delivery.

### P-006 — Deduplication is policy-aware
Optional/bulk campaigns deduplicate on normalized delivery address by default. Transactional/workflow mail must not be blindly collapsed when account- or submission-specific semantics differ.

### P-007 — Suppression is authoritative
Known-undeliverable recipients must not be hammered repeatedly. Suppression decisions are typed, scoped, explainable, timestamped, and reversible only where policy permits.

### P-008 — Unsubscribe is not universal opt-out
A reader can unsubscribe from optional publication/announcement categories without disabling password resets, security, account, submission, review, or editorial messages that are necessary to operate the journal workflow.

### P-009 — Journal boundaries are real boundaries
Preference and campaign state must include explicit OJS context/journal identity. An opt-out in Journal A must not silently become an opt-out in Journal B unless the user explicitly chooses a site-wide preference.

### P-010 — Site policy is the ceiling
Journal Managers may tune permitted settings, but cannot exceed provider, site, security, or deliverability limits established by the Site Administrator.

### P-011 — Explain every non-send
Every deferred, skipped, deduplicated, suppressed, expired, cancelled, or failed message must have a machine-readable reason and human-readable explanation.

### P-012 — Safe degradation
MailGuard must define behavior for plugin disablement, queue outage, database failure, provider outage, malformed webhook events, unknown mailables, and OJS upgrades. Silent mail loss is unacceptable.

### P-013 — Minimize recipient data
Store only what is necessary for delivery control, auditing, and safety. Signed tokens must not expose raw user IDs or email addresses.

### P-014 — Provider independence
The policy engine cannot be coupled to Brevo. Brevo is an important first provider adapter, while generic SMTP remains a baseline transport mode and additional feedback-capable providers can be added later.

### P-015 — Observable operations
Administrators must be able to answer: what happened, who was targeted, what was actually eligible, what remains queued, what consumed quota, what failed, and why.

---

## 4. Mail taxonomy

MailGuard classifies outbound mail before applying policy.

| Class | Typical examples | Default priority | User unsubscribe | Bulk quota eligible |
|---|---|---:|---|---|
| `critical` | password reset, account/security verification | P0 | No | No |
| `workflow` | review invite, decision, revision request, submission acknowledgement | P1 | Normally no | No |
| `operational` | reviewer/editor reminders, scheduled system notices | P2 | Policy-dependent | Usually no |
| `subscription` | new issue, new article, announcements, calls for papers | P3 | Yes | Yes |
| `manual` | deliberate person-to-person OJS email | P1/P2 | Normally no | No |
| `system` | admin diagnostics, scheduled task/provider alerts | P1/P2 | Admin-configurable | No |
| `unknown` | unregistered future mailable/plugin email | Configurable safe default | No automatic assumption | No until classified |

The taxonomy is extensible. Third-party plugins may register additional message types, but each type must map to a MailGuard class and policy profile.

---

## 5. Initial product scope

### v1 MUST

- OJS plugin installation with no core patch requirement in the primary supported path.
- Site-level MailGuard dashboard and controls.
- Journal/context-aware controls and permissions.
- Mailable discovery/classification registry.
- First-class integration for new-issue publication notifications.
- Durable MailGuard queue/spool for controlled mail.
- Recipient normalization and policy-aware email-level deduplication.
- Campaign and delivery idempotency.
- Priority queue with protected capacity for critical/workflow mail.
- Configurable daily/hourly/minute provider limits.
- Configurable bulk cooldown/rate policies.
- Retry policy with exponential/backoff scheduling and terminal states.
- Suppression registry.
- Manual suppression and restoration where allowed.
- Hard-bounce and complaint ingestion architecture.
- Brevo feedback/webhook adapter.
- Generic SMTP transport support.
- Signed unsubscribe links.
- RFC-compatible one-click unsubscribe headers for eligible subscription mail.
- Context-specific preference centre.
- MailGuard merge variables for unsubscribe/preferences.
- Automatic safe footer when an eligible subscription template omits an unsubscribe link.
- Queue, campaign, delivery, suppression, and provider audit views.
- Emergency controls: pause optional/bulk; pause all controlled delivery; resume safely.
- Structured logs and reason codes.
- Database migrations/uninstall policy.
- Automated unit/integration tests for policy, idempotency, preference boundaries, and queue transitions.
- Compatibility harness for supported OJS versions.

### v1 SHOULD

- Domain-aware throttling profiles.
- Quota reservation by class.
- Recipient/campaign preview before publication send.
- Provider health status.
- Webhook replay protection.
- Delivery-event retention controls.
- Exportable operational audit data.
- Dry-run / observe-only mode.
- Unknown-mailable discovery report.
- Plugin API for registering message types and policy metadata.

### v1 COULD

- Daily/weekly digests.
- Per-recipient frequency caps for optional mail.
- Additional provider adapters (SES, Postmark, Mailgun, SendGrid).
- Provider API sending instead of SMTP where justified.
- Adaptive domain throttling based on observed temporary failures.
- Mail preview/test-send tooling.
- Campaign scheduling windows.

### Explicitly NOT v1

- Full marketing automation.
- Visual newsletter builder.
- Contact-list CRM.
- Open/click tracking pixels as a core requirement.
- Replacing OJS's complete email-template administration UI.
- Owning DNS/SPF/DKIM/DMARC configuration.
- Promising provider delivery after acceptance.
- Modifying OJS core files as an installation step.

---

## 6. New-issue publication reference flow

The first end-to-end reference integration is new-issue publication.

```text
Editor publishes issue
        |
        v
OJS resolves native subscribed user IDs
        |
        v
MailGuard captures/receives intended publication campaign
        |
        +--> campaign identity: context + issue + event + generation
        |
        v
Resolve recipient accounts
        |
        v
Normalize delivery addresses
        |
        +--> remove invalid/non-deliverable identities
        +--> apply context preference policy
        +--> apply global/provider suppression
        +--> deduplicate eligible subscription recipients by normalized email
        |
        v
Persist campaign + recipient/delivery intents
        |
        v
Queue according to P3 bulk policy
        |
        +--> site/provider capacity
        +--> protected critical/workflow reserve
        +--> per-minute/hour/day limits
        +--> optional domain limits
        |
        v
Render native OJS mailable/template with MailGuard variables/footer/headers
        |
        v
Send through configured transport/provider
        |
        +--> accepted -> record accepted
        +--> temporary failure -> deferred/retry
        +--> permanent failure -> failed/suppress as policy requires
        |
        v
Provider webhook events update delivery/suppression state
```

### Required new-issue invariants

- Publishing an issue twice must not silently produce duplicate deliveries for the same logical publication event.
- An explicit administrator `Resend` must create a new campaign generation and be clearly labelled as deliberate.
- Multiple OJS accounts sharing one normalized email receive one subscription delivery for that campaign unless a future policy explicitly overrides this.
- In-app OJS notifications and outbound email remain conceptually separable; MailGuard must not destroy native in-app notification semantics merely because an email is suppressed.
- Bulk queue pressure must not consume protected critical/workflow capacity.

---

## 7. Preference model

MailGuard will distinguish **message necessity** from **delivery preference**.

### Optional categories

Initial categories:

- New issues
- New articles / continuous publication updates (future-enabled)
- Announcements
- Calls for papers / journal notices
- Future registered optional categories

### Essential categories

Examples:

- Password/account/security
- Submission acknowledgement and required workflow correspondence
- Review invitations and review workflow correspondence
- Editorial decisions and revision requests
- Other mail where disabling delivery would break the expected OJS workflow

Essential does not mean “ignore a hard bounce.” A physically invalid address remains undeliverable. It means the preference centre must not falsely offer an unsubscribe switch for mail required to operate the workflow.

### Scope hierarchy

Preference resolution must distinguish:

1. global/site preference where explicitly supported,
2. journal/context preference,
3. category preference,
4. message-specific/notification-specific native OJS state where applicable.

The exact precedence is defined in `03-MAIL-POLICY.md`.

---

## 8. Suppression model

Suppression is not one boolean.

Initial suppression reasons:

- `hard_bounce`
- `invalid_address`
- `spam_complaint`
- `provider_block`
- `manual_admin`
- `temporary_exhaustion`
- `repeated_soft_bounce`
- `recipient_unsubscribe` (preference-level for optional categories, not necessarily global physical suppression)
- `legal_or_policy` (reserved for explicit deployment policy)

Suppression scopes may be:

- address-global,
- provider-specific,
- site-wide,
- context/journal,
- category-specific.

The policy engine must determine which scope applies to which mail class.

---

## 9. Quota and capacity model

MailGuard uses capacity policy rather than assuming a provider is unlimited.

Example only:

```text
Provider daily limit:      300
Critical reserve:           20
Workflow reserve:           50
Bulk available initially:  230
```

Rules:

- Reserved capacity is protected from bulk.
- Unused reserve may optionally become releasable late in a quota window, but this is an explicit site policy, not automatic v1 behavior.
- Provider windows must use provider semantics when known; otherwise MailGuard uses its configured window/timezone.
- Queue depth does not equal send permission.
- OJS job chunking does not override MailGuard rate or quota policy.
- A provider `429`, temporary SMTP failure, or equivalent must be able to reduce immediate sending through backoff.

---

## 10. Interception strategy

MailGuard will use layered integration so the plugin remains maintainable across OJS versions.

### Layer A — Mailable decoration/classification

Use supported PKP/OJS hooks such as `Mailable::build` where available to identify, classify, add MailGuard data, and attach safe headers/footer behavior.

### Layer B — Producer adapters

High-volume producers such as new-issue publication require event/producer-aware adapters so MailGuard can create a campaign before native fan-out causes uncontrolled transport sends. The adapter must preserve OJS in-app notification behavior.

### Layer C — Delivery gateway

Controlled messages are persisted and released to the configured downstream transport only after policy evaluation.

### Layer D — Provider feedback

Webhook/API feedback updates delivery and suppression state independently from initial send acceptance.

### Compatibility rule

If a required interception guarantee cannot be achieved by a stable hook in a supported OJS release, that release gets a version-specific adapter. A core patch is considered only after a documented technical spike proves there is no supportable alternative.

---

## 11. Unknown mailables

MailGuard must not pretend it knows every current or future email.

Modes:

- **Observe:** record unknown mailable classes and native delivery intent without taking control.
- **Conservative control:** queue unknown mail at a non-bulk protected class until classified.
- **Native bypass:** allow explicitly approved unknown mail to use native behavior while logging the bypass.

Default for initial rollout should favor observability and safety over aggressive interception.

---

## 12. Failure behavior

### MailGuard database unavailable

- Bulk/subscription producer integrations must fail closed before an uncontrolled blast where feasible.
- Critical/workflow fallback behavior must be explicitly configurable and tested; the design target is no silent loss.

### Worker unavailable

Persisted mail remains queued. Administrators see queue age and health warnings.

### Provider unavailable

Temporary failures enter retry/backoff. Provider outage must not create duplicate logical deliveries.

### Webhook unavailable

Sending may continue according to policy, but provider-feedback health is shown degraded. Reconciliation must be possible where the provider supports event history/API lookup.

### Plugin disabled

Disablement semantics must be explicit during installation design. MailGuard must never strand OJS in a broken mail state. The uninstall/disable path must define whether native transport resumes immediately and how queued MailGuard messages are handled.

---

## 13. Security and privacy baseline

- Unsubscribe/preference tokens are signed, opaque, scoped, and non-enumerable.
- No raw email or user ID in public unsubscribe URLs.
- Webhooks require provider-specific authenticity verification where supported.
- Webhook events require replay/idempotency protection.
- Administrative actions require OJS authorization and CSRF protections appropriate to OJS APIs/UI.
- Provider secrets are never written to normal logs.
- Email addresses in logs/UI follow least-exposure rules; full visibility requires appropriate permission.
- Suppression/preferences changes have an audit trail.
- Retention settings exist for delivery-event history.
- MailGuard will not add tracking pixels by default.

---

## 14. Roles and permissions

### Site Administrator

Owns:

- MailGuard enablement/mode
- provider credentials/adapters
- global quotas/rates
- reserve policy
- emergency pause
- global suppressions
- retention
- webhook configuration
- compatibility/health diagnostics
- journal policy ceilings

### Journal Manager / permitted manager role

Within site ceilings:

- view journal campaigns/queue
- configure optional categories
- configure journal footer/preferences copy
- inspect journal suppressions where permitted
- deliberately resend/cancel eligible campaigns
- configure journal sending windows if allowed

### Editor

- publication flow preview
- publish + queue notification
- see high-level campaign status if permitted
- cannot change provider/site safety ceilings

### Reader/user

- manage permitted journal/category preferences
- use signed one-click unsubscribe
- cannot disable essential workflow mail through the optional-mail preference centre

---

## 15. Administration UX

MailGuard should feel native to OJS, not like a separate SaaS embedded inside it.

Primary screens:

1. **Overview** — sent/queued/deferred/suppressed/failed, capacity, provider health, oldest queued age.
2. **Queue** — filter by priority, context, campaign, state, reason.
3. **Campaigns** — intended vs eligible vs deduplicated vs suppressed vs sent vs failed.
4. **Suppressions** — reason, scope, source, first/last event, restore controls.
5. **Providers** — active transport, quotas, webhook state, last feedback event.
6. **Policies** — site ceilings, priorities, reserve, retry/backoff, unknown-mail behavior.
7. **Mail types** — discovered/registered mailable mapping and classification.
8. **Audit** — policy/admin events.
9. **Diagnostics** — scheduler/worker health, integration hooks, compatibility adapter state.

The new-issue publication UI should show a recipient preview when feasible:

```text
Subscribed OJS accounts:       1,204
Unique normalized addresses:   1,173
Preference opt-outs:              61
Suppressed addresses:               8
Other ineligible:                   0
Eligible deliveries:            1,104
Bulk capacity available now:      225
Expected remainder: queued
```

Numbers are explanatory and must be computed from the same policy code that creates the campaign, not duplicated UI logic.

---

## 16. External/provider architecture

### Generic SMTP

Baseline transport. MailGuard can control pacing/queue/retries but may have limited authoritative bounce/complaint feedback unless the deployment supplies another mechanism.

### Brevo

First feedback-capable provider adapter target. Adapter responsibilities:

- configured capacity metadata,
- authenticated webhook receiver,
- provider event normalization,
- bounce/block/complaint/unsubscribe mapping,
- provider message/event identifiers where available,
- health status.

MailGuard policy must not hard-code Brevo event names into core tables. Provider adapters normalize external events into MailGuard event types.

---

## 17. Extensibility contract

Future plugin-facing APIs should support concepts equivalent to:

```php
MailGuard::registerMailType(...);
MailGuard::registerPolicyProfile(...);
MailGuard::queue(...);
MailGuard::canDeliver(...);
MailGuard::getPreference(...);
MailGuard::suppress(...);
```

Exact PHP API names are **not frozen** in this planning document. The requirement is to expose a stable service/registry layer rather than requiring third-party plugins to write directly to MailGuard tables.

---

## 18. Versioning and compatibility

MailGuard uses semantic versioning for its own releases.

Compatibility policy direction:

- v1 primary target: OJS 3.5.x.
- OJS 3.6.x: tracked against upstream and supported through a dedicated compatibility adapter when stable/released for production use.
- OJS 3.4.x: legacy compatibility is a deliberate decision after technical spike/test cost is known.
- Never advertise compatibility based only on successful installation; mail lifecycle tests must pass.

Each OJS adapter must declare:

- supported OJS range,
- hook/producer integration points,
- known limitations,
- test fixture/version,
- last verified date/commit or release.

---

## 19. Product metrics

MailGuard success is operational, not based on vanity email opens.

Primary metrics:

- duplicate logical deliveries prevented,
- optional deliveries suppressed correctly,
- hard-bounced addresses not repeatedly attempted,
- critical/workflow messages delayed by bulk quota: target zero under correctly configured reserve policy,
- queue age by priority,
- retry recovery rate,
- provider temporary-failure rate,
- unknown/unclassified mailable count,
- webhook freshness,
- preference boundary violations: target zero,
- idempotency violations: target zero,
- silent loss incidents: target zero.

---

## 20. Pre-implementation gates

Runtime implementation may begin only when the planning branch contains and reconciles:

- [ ] Product Bible
- [ ] architecture and interception specification
- [ ] mail policy/state-machine specification
- [ ] database/data-retention specification
- [ ] admin/user UX specification
- [ ] provider/webhook contract
- [ ] security/threat model
- [ ] compatibility matrix
- [ ] automated test strategy
- [ ] release/update/uninstall strategy
- [ ] decision log
- [ ] risk register
- [ ] phased implementation plan
- [ ] technical spike list with explicit questions and pass/fail evidence required

After those are complete, the planning branch receives a **PLAN FROZEN / PHASE 0 READY** checkpoint. Only then should implementation branches begin.

---

## 21. Initial non-negotiable acceptance scenarios

Before a v1 release can be called production-ready, automated or reproducible tests must prove at least the following:

1. Two OJS user accounts with the same normalized address receive one new-issue subscription email.
2. Two distinct workflow messages for different submissions are not incorrectly collapsed merely because they share an address.
3. Re-executing the same campaign worker does not create a second logical delivery.
4. An explicit resend creates a new generation and is auditable.
5. Journal A opt-out does not suppress Journal B unless a site-wide preference explicitly says so.
6. A hard-bounced address is not attempted again after suppression policy activates.
7. A temporary provider error is retried without creating a duplicate logical delivery.
8. A subscription message contains valid preference/unsubscribe behavior even if the editor omitted a footer merge tag.
9. One-click unsubscribe modifies only the intended scope/category.
10. Bulk capacity exhaustion does not consume protected critical/workflow reserve.
11. Pausing bulk leaves critical/workflow delivery operational.
12. Pausing all controlled mail stops release without deleting queue state.
13. Malformed/forged provider webhooks do not mutate suppression state.
14. Replayed authentic webhook events are idempotent.
15. Disabling MailGuard follows the documented safe-degradation behavior.
16. OJS native in-app notification behavior remains intact for the new-issue integration.
17. Unknown mailables are observable and handled according to configured mode.
18. No public unsubscribe/preferences token exposes raw user ID or email.

---

## 22. Product north star

MailGuard is successful when an OJS administrator can confidently publish an issue to ten readers or ten thousand readers without having to understand SMTP queue internals, manually split lists, fear duplicate sends, sacrifice password/editorial mail capacity, or repeatedly mail addresses that providers have already declared unhealthy.

That is the product boundary against which future features should be judged.
