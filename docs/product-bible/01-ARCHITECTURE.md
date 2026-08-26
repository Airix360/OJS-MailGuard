# OJS MailGuard — Architecture

**State:** PLAN  
**Depends on:** `00-PRODUCT-BIBLE.md`

---

## 1. Architectural objective

MailGuard must control outbound delivery without turning OJS into a fork. The architecture therefore separates OJS-specific capture/adaptation from a version-independent MailGuard core.

```text
+------------------------------ OJS --------------------------------+
|                                                                    |
| business action -> native notification/job -> native Mailable      |
|       |                                  |                         |
|       | producer adapter                 | Mailable::build hook     |
+-------|----------------------------------|-------------------------+
        v                                  v
+--------------------------- MailGuard ------------------------------+
| Compatibility Adapter / Capture Layer                              |
|              |                                                     |
|              v                                                     |
| Mail Type Registry -> Classification -> Policy Context             |
|              |                                                     |
|              v                                                     |
| Campaign / Delivery Intent Builder                                 |
|              |                                                     |
|              v                                                     |
| Eligibility + Preference + Suppression + Dedup Engine              |
|              |                                                     |
|              v                                                     |
| Durable Spool / Priority Queue                                     |
|              |                                                     |
|              v                                                     |
| Capacity Scheduler / Releaser                                      |
|              |                                                     |
|              v                                                     |
| Renderer / Decorator -> Transport Gateway -> Provider Adapter      |
|                                         |                          |
|                                         v                          |
|                              SMTP / Brevo / future providers       |
|                                         |                          |
|                                         v                          |
| Provider Webhook -> Event Normalizer -> Delivery/Suppression State |
+--------------------------------------------------------------------+
```

---

## 2. Components

### A. Plugin bootstrap

Responsibilities:

- register MailGuard services;
- load the OJS compatibility adapter;
- register hooks/routes/permissions/scheduled tasks;
- expose health state;
- perform no irreversible runtime mutation merely because the plugin was loaded.

### B. Compatibility adapter

Purpose: isolate differences between supported OJS releases.

Conceptual interface:

```php
interface OjsCompatibilityAdapter
{
    public function supports(string $ojsVersion): bool;
    public function registerHooks(): void;
    public function capabilities(): array;
    public function diagnostics(): array;
}
```

The exact PHP contract is not frozen. Required behavior is.

Each adapter declares capabilities such as:

- generic mailable build hook available;
- new-issue producer interception available;
- mail transport replacement/wrapping available;
- OJS queue integration available;
- app encryption key available;
- one-click unsubscribe behavior present natively;
- supported public/API routing mechanism.

### C. Mail type registry

Maps native OJS/PKP or third-party mailable identities to MailGuard policy metadata.

Each registration includes at minimum:

- stable MailGuard type key;
- source class/event identity;
- MailGuard class (`critical`, `workflow`, etc.);
- context requirement;
- campaignable yes/no;
- unsubscribe eligibility;
- email-level dedup default;
- default priority;
- retry profile;
- provider quota class;
- whether native in-app notification state must be preserved;
- owning integration adapter.

Unknown types are never silently guessed into `subscription`.

### D. Capture layer

There are two capture modes.

#### 1. Generic mailable observation/decorating

The PKP `Mailable::build` hook is suitable for classification discovery, metadata injection, and decoration because it runs on PKP mailables before send construction completes.

This layer alone is **not assumed** to be sufficient for durable pre-send queue control. The technical spike must prove whether transport replacement/wrapping can reliably prevent downstream native send while preserving rendering semantics.

#### 2. Producer-aware capture

Bulk producers need integration before uncontrolled fan-out. New-issue publication is the first producer adapter.

The adapter must preserve:

- issue publication itself;
- native OJS in-app notifications;
- correct locale/template selection;
- sender semantics;
- permissions;
- journal context;
- publication UI behavior.

It must redirect only the email-delivery portion into MailGuard where possible.

### E. Campaign service

A campaign represents one logical bulk/optional communication event.

Examples:

- `issue-published:{contextId}:{issueId}:{generation}`
- `announcement:{contextId}:{announcementId}:{generation}`

Responsibilities:

- create deterministic campaign identity;
- record source object and initiating actor;
- snapshot policy version;
- resolve intended recipient identities;
- run eligibility/dedup;
- create durable delivery rows;
- expose counts and preview;
- support explicit pause/cancel/resend generation.

### F. Eligibility engine

Pure policy service wherever practical. Inputs:

- mail type;
- context;
- recipient/account identity;
- normalized delivery identity;
- preferences;
- suppressions;
- campaign state;
- policy version;
- provider policy.

Outputs:

- eligible / not eligible;
- reason code;
- dedup key;
- priority;
- earliest send time;
- provider route if more than one provider is supported later.

UI preview and actual queue creation must call the same engine.

### G. Durable spool

MailGuard-controlled mail is persisted before release.

The spool is not simply a copy of OJS's job queue. It stores business-level delivery state so that worker retries, process crashes, provider outages, and administrator pause/resume actions do not lose the logical message.

A queued delivery contains enough immutable/snapshotted information to reproduce the intended send under the frozen campaign policy while minimizing personal-data retention.

### H. Capacity scheduler / releaser

Runs from OJS scheduled-task/queue infrastructure where supported.

Responsibilities:

- choose eligible queued deliveries by priority and fairness;
- respect provider/site/context quota policy;
- enforce protected capacity;
- enforce cooldown and earliest-send times;
- enforce provider backoff;
- acquire concurrency/idempotency locks;
- dispatch transport attempts;
- avoid starvation.

Priority alone must not let a single journal monopolize all discretionary capacity indefinitely. v1 scheduler should include a simple fairness mechanism among same-priority contexts/campaigns.

### I. Renderer / decorator

MailGuard should preserve the native OJS mailable/template as the content authority where possible.

Responsibilities:

- instantiate or reconstruct the registered mailable safely;
- apply correct locale;
- supply MailGuard merge data;
- ensure required optional-mail footer behavior;
- ensure list unsubscribe headers for eligible classes;
- attach MailGuard correlation metadata where transport permits;
- never mutate stored OJS templates merely to inject an unsubscribe footer.

### J. Transport gateway

Single policy-controlled exit for MailGuard-managed sends.

Responsibilities:

- transform a release decision into one provider attempt;
- attach correlation/provider metadata;
- call transport/provider;
- normalize immediate result;
- write attempt state atomically enough to survive worker retries;
- never create a new logical delivery because an attempt is retried.

### K. Provider adapter

Conceptual responsibilities:

```text
capabilities()
sendOrConfigureTransport()
normalizeImmediateFailure()
verifyWebhook()
normalizeWebhookEvent()
quotaWindowMetadata()
health()
```

Generic SMTP may not implement feedback capabilities. Brevo is the first webhook-capable target.

### L. Webhook receiver

Responsibilities:

- provider-specific endpoint;
- authenticity verification before mutation;
- body-size/content-type controls;
- raw event fingerprint/idempotency key;
- provider-event persistence;
- normalization;
- delivery correlation;
- suppression update where policy says so;
- audit record;
- safe response on duplicates.

Webhook processing should be queued where feasible so the HTTP request remains quick and provider retries do not multiply business effects.

### M. Preference service

Provides context/category-safe read/write operations. It is the only supported path for MailGuard preference changes.

It may import/read native OJS settings for compatibility, but MailGuard's context-safe preference table becomes authoritative for MailGuard optional mail once enabled in enforcing mode.

### N. Suppression service

Central typed suppression authority with scopes, sources, and restoration rules.

### O. Audit/event service

Records state-changing administrative and policy events separately from verbose diagnostic logs.

---

## 3. Interception decisions still requiring proof

These are pre-implementation technical spikes, not invitations to redesign the product.

### SPIKE-A — Transport interception

**Question:** Can a plugin register/wrap the active Laravel/PKP mail transport in supported OJS versions so `Mail::send($mailable)` can be durably redirected without core patches?

Evidence required:

- working minimal plugin prototype on OJS 3.5.x;
- same experiment against current 3.6 branch/release target;
- confirmation that subject/body/attachments/headers/locale/sender/recipient semantics survive;
- proof that bypass/native mail can still be deliberately supported;
- proof that disabling plugin restores a valid mail path.

Pass outcome: gateway-level interception becomes the default path.

Fail outcome: use producer adapters for controlled mail types and document coverage limits; do not patch core automatically.

### SPIKE-B — New-issue producer interception

**Question:** What supported hook/event/controller extension seam can replace only the email fan-out while preserving native in-app notifications?

Evidence required:

- publish an issue with “send notification” enabled;
- native in-app notifications remain correct;
- outbound messages enter MailGuard once;
- no native duplicate emails escape;
- re-run/retry is idempotent.

### SPIKE-C — Queue/scheduler integration

**Question:** Which OJS queue/scheduled-task facilities are sufficiently stable for MailGuard releaser and webhook-processing jobs across supported versions?

Evidence required:

- cron/web task runner behavior;
- queue worker behavior;
- lock/concurrency semantics;
- recovery after worker termination.

### SPIKE-D — Encryption at rest

**Question:** Can supported OJS versions provide a stable application-key encryption facility appropriate for queued recipient snapshots/provider secrets?

Evidence required:

- OJS 3.5.x encryption API;
- key-rotation/upgrade implications;
- fallback decision for any legacy-supported release.

---

## 4. Native OJS unsubscribe interoperability

Current PKP mailables can already implement an `Unsubscribe` trait that adds an unsubscribe URL plus `List-Unsubscribe` and `List-Unsubscribe-Post` headers.

MailGuard must not duplicate headers blindly. Instead:

1. detect whether the native mailable already supplies valid unsubscribe behavior;
2. prefer MailGuard preference-scope URLs for MailGuard-controlled optional categories when needed;
3. ensure exactly one coherent set of list-unsubscribe semantics;
4. preserve native behavior for non-MailGuard mailables unless configured otherwise.

MailGuard's value is broader policy/scope/control, not pretending one-click unsubscribe does not already exist upstream.

---

## 5. New-issue adapter boundary

Current OJS new-issue publication performs two related actions:

- creates native in-app notifications for eligible users;
- sends email to the intersection of notification-eligible and email-eligible user IDs.

MailGuard must not collapse these into one state machine.

Target behavior:

```text
OJS publish
  |
  +--> native in-app notification path (preserved)
  |
  +--> intended email recipient identities
           |
           +--> MailGuard campaign
```

If the supported seam cannot split those paths cleanly, the adapter must reproduce the upstream semantics with regression tests against upstream behavior.

---

## 6. Concurrency model

MailGuard assumes multiple workers may act concurrently.

Required safeguards:

- database uniqueness for campaign source/generation;
- database uniqueness for logical delivery idempotency key;
- atomic claim/lease for queued delivery;
- attempt number monotonicity;
- webhook event fingerprint uniqueness;
- safe reprocessing after expired worker lease;
- no reliance on in-memory locks for correctness.

A worker may crash after provider acceptance but before local commit. This “unknown send result” window must be explicitly handled. Provider adapters with idempotency keys/message IDs should reduce risk where possible. For transports without provider idempotency, the state is `uncertain` and policy must avoid reckless automatic duplication.

---

## 7. Rendering strategy

Two candidate strategies are allowed for the spike:

### Strategy 1 — Persist reconstruction recipe

Store mailable type + source object IDs + locale + sender + recipient snapshot + template identity/version data required to reconstruct later.

Pros: less duplicated rendered PII in database.  
Risk: source/template may change before delayed send, reducing determinism.

### Strategy 2 — Persist rendered payload snapshot

Render subject/body/headers at queue creation, then persist encrypted payload for later transport.

Pros: deterministic campaign content.  
Risk: more sensitive data retained; attachments complicate storage.

### Planned hybrid

For campaign/bulk mail, prefer a deterministic snapshot of template/content version plus encrypted rendered message at or shortly before release, with explicit retention. For transactional mail, gateway behavior may render immediately. Exact behavior is frozen only after SPIKE-A and data-retention review.

---

## 8. Fairness and priority

Default priority ordering:

```text
P0 critical
P1 workflow/manual-high
P2 operational/system
P3 subscription/bulk
P4 retries that should not outrank fresh critical work
```

Within equal priority, scheduler should use a fair ordering such as weighted round-robin or oldest-eligible grouped by context/campaign rather than draining one huge campaign completely first.

Retry priority may be adjusted by message class: a temporarily failed critical message remains critical; `P4` is not a blanket demotion for all retries.

---

## 9. Safe modes

MailGuard supports operational modes:

### `observe`

- discover/classify/log;
- do not replace native delivery except explicit test integrations;
- no claim of duplicate/quota protection.

### `enforce_bulk`

- MailGuard controls registered subscription/bulk mail;
- critical/workflow remain native but are observed;
- recommended first production rollout.

### `enforce_registered`

- all explicitly registered mail types use MailGuard gateway;
- unknown types follow configured unknown-mail policy.

### `maintenance_pause_bulk`

- no new P3 release;
- P0-P2 continue.

### `maintenance_pause_all`

- no MailGuard-controlled transport release;
- queue remains durable.

Mode transitions are audited.

---

## 10. Upgrade strategy

Before an OJS upgrade:

1. diagnostics identify active compatibility adapter;
2. MailGuard can be placed into a safe pause/observe state;
3. database migrations are additive/backward-aware where practical;
4. post-upgrade health check verifies hooks/transport/worker/webhook routes;
5. enforcing mode is not assumed healthy until compatibility check passes.

A future unknown OJS version should fail toward `observe`/diagnostic behavior rather than silently claiming full enforcement.

---

## 11. Architecture acceptance gate

Architecture is ready for implementation only when SPIKE-A through SPIKE-D have documented results and the chosen path can satisfy these guarantees without a permanent core patch:

- durable pre-delivery persistence for controlled mail;
- no duplicate native escape for new-issue campaigns;
- correct native in-app notifications;
- safe plugin disable/upgrade path;
- reliable scheduler/worker recovery;
- secure token and queued-recipient handling.
