# OJS MailGuard — Decision Log

**State:** PLAN  
**Convention:** accepted decisions are binding until superseded by a later numbered decision.

---

## D-001 — Product boundary

**Decision:** MailGuard is an outbound mail control plane, not a replacement email-template or newsletter platform.

**Status:** ACCEPTED

**Reason:** OJS already owns editorial events, mailables, templates, users, and journal context. MailGuard adds delivery governance without duplicating that product surface.

---

## D-002 — OJS remains content/business-event authority

**Decision:** MailGuard preserves native OJS mailables/templates and business-event semantics wherever technically possible.

**Status:** ACCEPTED

---

## D-003 — No permanent core patch architecture

**Decision:** Core OJS file modifications are not an installation requirement or default integration method.

**Status:** ACCEPTED

**Fallback:** if a specific supported OJS version lacks a safe seam, compatibility may be reduced or a version adapter may reproduce behavior; a permanent patch requires an explicit later decision.

---

## D-004 — New issue is the first controlled integration

**Decision:** `IssuePublishedNotifyUsers` / issue-publication notification is the first reference campaign.

**Status:** ACCEPTED

**Reason:** It exercises high-volume fan-out, optional subscription preferences, dedup, queueing, quota, unsubscribe, and multi-journal concerns.

---

## D-005 — Preserve in-app notification semantics

**Decision:** Controlling issue-publication email must not remove or conflate OJS in-app notifications.

**Status:** ACCEPTED

---

## D-006 — Subscription dedup by delivery identity

**Decision:** For optional/bulk campaigns, default logical dedup is by conservative normalized email address within a campaign, not merely OJS `user_id`.

**Status:** ACCEPTED

**Exception:** transactional/workflow messages use business-event/account-aware idempotency keys and are not blindly collapsed by address.

---

## D-007 — Conservative email normalization

**Decision:** MailGuard does not strip Gmail dots, plus aliases, or apply mailbox-provider-specific equivalence by default.

**Status:** ACCEPTED

---

## D-008 — Durable spool before controlled send

**Decision:** MailGuard-managed delivery intent is persisted before transport release.

**Status:** ACCEPTED

---

## D-009 — Attempt is not delivery

**Decision:** Transport retries create new attempt records under one logical delivery; they do not create new logical deliveries.

**Status:** ACCEPTED

---

## D-010 — Explicit resend creates generation

**Decision:** A deliberate resend creates a new campaign generation. Completed campaigns are not casually reopened.

**Status:** ACCEPTED

---

## D-011 — Priority with protected capacity

**Decision:** Critical/workflow mail receives higher priority and protected provider/site capacity that bulk cannot consume.

**Status:** ACCEPTED

---

## D-012 — Provider quota is configuration

**Decision:** Provider plan limits are not hard-coded constants. Even known Brevo free-plan limits are editable provider configuration.

**Status:** ACCEPTED

---

## D-013 — MailGuard context-safe preferences

**Decision:** MailGuard maintains its own explicit context/category preference model for controlled optional email rather than trusting current native OJS notification-subscription queries as sole authority.

**Status:** ACCEPTED

**Reason:** Current upstream behavior has a known cross-context opt-out issue and current query shape can exclude blocked users without context scoping in the exclusion subquery.

---

## D-014 — Native preference interoperability

**Decision:** Existing OJS preferences may be imported/read as compatibility input, but once a MailGuard explicit preference exists for a scope, MailGuard resolution is authoritative for MailGuard-controlled optional delivery.

**Status:** ACCEPTED

---

## D-015 — Unsubscribe is category/context scoped

**Decision:** One-click unsubscribe from a new-issue message defaults to that journal's new-issue category, not every OJS email and not every journal.

**Status:** ACCEPTED

---

## D-016 — Essential mail is not preference-centre optional mail

**Decision:** Password/security/editorial/review/workflow messages are not disabled through the optional subscription preference centre.

**Status:** ACCEPTED

**Qualification:** physical invalid-address/hard-bounce suppression still prevents futile delivery attempts.

---

## D-017 — Interoperate with PKP native unsubscribe

**Decision:** MailGuard will detect/use/interoperate with existing PKP `Unsubscribe` trait/header behavior and must not blindly add duplicate/conflicting headers.

**Status:** ACCEPTED

---

## D-018 — Automatic fallback footer for subscription mail

**Decision:** If an eligible MailGuard-controlled subscription template does not explicitly place the MailGuard unsubscribe/preference variable, MailGuard appends a standard localized footer at render time without rewriting the stored OJS template.

**Status:** ACCEPTED

---

## D-019 — Generic SMTP baseline, Brevo first feedback adapter

**Decision:** Generic SMTP is the baseline outbound compatibility path; Brevo is the first provider-feedback/webhook adapter.

**Status:** ACCEPTED

---

## D-020 — No open/click tracking requirement

**Decision:** MailGuard v1 does not require or automatically enable open/click tracking or tracking pixels.

**Status:** ACCEPTED

---

## D-021 — Provider events normalized before policy

**Decision:** Core suppression/delivery policy consumes MailGuard event types, never Brevo-specific event names directly.

**Status:** ACCEPTED

---

## D-022 — Webhook verification before mutation

**Decision:** Provider webhook authenticity and replay handling happen before preference/suppression/delivery mutation.

**Status:** ACCEPTED

---

## D-023 — Address equality uses keyed hash

**Decision:** MailGuard should use a keyed HMAC of conservative normalized email for durable equality/dedup indexes, not plaintext email or unsalted plain hash.

**Status:** ACCEPTED IN PRINCIPLE / encryption spike must select framework implementation.

---

## D-024 — Queued address snapshot encrypted

**Decision:** Long-running controlled delivery needs a deterministic recipient address snapshot; this should be encrypted at rest using supported OJS/framework cryptography.

**Status:** ACCEPTED IN PRINCIPLE / SPIKE-D pending.

---

## D-025 — OJS 3.5 first-class v1 target

**Decision:** OJS 3.5.x is the first-class v1 production target. OJS 3.6 uses a separate tracked adapter. OJS 3.4 is a deliberate legacy-support decision after Phase 0 evidence.

**Status:** ACCEPTED

---

## D-026 — Unknown future OJS versions do not inherit support automatically

**Decision:** Unsupported/new OJS versions enter diagnostics/observe-safe behavior rather than silently claiming enforcement compatibility.

**Status:** ACCEPTED

---

## D-027 — Observe before enforce

**Decision:** Rollout supports `observe` then `enforce_bulk` before broader `enforce_registered` control.

**Status:** ACCEPTED

---

## D-028 — Unknown mailables are explicit

**Decision:** Unknown mailables are discovered/logged and handled by configured policy; they are not silently classified as subscription mail.

**Status:** ACCEPTED

---

## D-029 — No automatic cross-provider failover in v1

**Decision:** Automatic provider failover is deferred because uncertain send outcomes can create duplicates.

**Status:** ACCEPTED

---

## D-030 — Disable is different from uninstall

**Decision:** Plugin disable must preserve safe system behavior and data; uninstall is an explicitly destructive operation with clear consequences.

**Status:** ACCEPTED

---

## Open decisions

These are questions for Phase 0/implementation planning, not unresolved product goals.

### O-001 — Exact transport interception mechanism

Candidate: Laravel/PKP mail transport extension/wrapper. Must be proven on OJS 3.5/3.6.

### O-002 — Exact new-issue producer seam

Need supported way to redirect only email fan-out while preserving in-app notifications.

### O-003 — Queue content strategy

Freeze exact hybrid between reconstruction recipe and encrypted rendered payload after determinism/privacy testing.

### O-004 — Encryption/HMAC key lifecycle

Use OJS/framework capability; define rotation and legacy compatibility.

### O-005 — 3.4 support

Full / limited / observe-only / unsupported after Phase 0.

### O-006 — Same-address canonical account selection

Freeze deterministic personalization rule after fixture testing.

### O-007 — Default bulk expiry

Product Bible proposes configurable max queue age; default (e.g. 7 days for issue publication) requires review.

### O-008 — Provider webhook authentication details

Brevo adapter must use the strongest mechanism supported by current Brevo webhook configuration and document fallback accurately.

### O-009 — Policy snapshot representation

Versioned references vs compact frozen JSON or hybrid.

### O-010 — Admin REST/UI implementation pattern

Follow OJS 3.5/3.6 native component conventions after compatibility spike.

---

## Decision-change rule

A later decision may supersede an earlier one only by:

1. adding a new decision number;
2. naming the decision being superseded;
3. explaining the evidence/reason;
4. updating affected Product Bible documents and tests.

Do not silently edit away architectural history once implementation begins.
