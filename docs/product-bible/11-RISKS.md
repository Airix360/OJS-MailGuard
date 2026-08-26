# OJS MailGuard — Risk Register

**State:** PLAN

Legend:

- Likelihood: Low / Medium / High
- Impact: Low / Medium / High / Critical
- Status: Open / Mitigated / Accepted / Closed

---

## R-001 — No clean pre-send interception seam

**Likelihood:** Medium  
**Impact:** Critical  
**Status:** Open

If supported OJS versions cannot redirect controlled mail before native transport without core patches, MailGuard cannot honestly promise universal interception.

**Mitigation:** Phase 0 SPIKE-A; producer-specific adapters; reduce compatibility/coverage rather than silently patch core.

---

## R-002 — New-issue integration duplicates native mail

**Likelihood:** Medium  
**Impact:** Critical  
**Status:** Open

MailGuard may queue publication mail while native `IssuePublishedNotifyUsers` still sends it.

**Mitigation:** Phase 0 SPIKE-B; explicit regression test proving zero native email escape while preserving in-app notification behavior.

---

## R-003 — Provider accepted but local state unknown

**Likelihood:** Medium  
**Impact:** High  
**Status:** Open

Worker crash/network ambiguity after acceptance can cause duplicate retry.

**Mitigation:** `uncertain` state; provider correlation/idempotency where available; do not blind retry uncertain sends; reconciliation tooling.

---

## R-004 — Cross-journal preference leakage

**Likelihood:** High in native upstream path / Low in correctly isolated MailGuard path  
**Impact:** High  
**Status:** Open until tests pass

**Mitigation:** own context-safe preference model; explicit `context_id`; cross-journal fixtures; never rely solely on native exclusion query.

---

## R-005 — Address-level dedup changes personalization unexpectedly

**Likelihood:** Medium  
**Impact:** Medium

Two accounts share one email but have different names/locales/roles.

**Mitigation:** deterministic canonical account rule; record contributing accounts; only apply address-level dedup to campaign/optional profiles; preview/report duplicates.

---

## R-006 — Hard-bounce suppression blocks corrected address incorrectly

**Likelihood:** Low/Medium  
**Impact:** High

**Mitigation:** suppression keyed to normalized address snapshot, not forever to user ID; address change creates new delivery identity; restoration workflow with audit.

---

## R-007 — Provider webhook forgery creates false suppression

**Likelihood:** Medium if poorly implemented  
**Impact:** Critical

**Mitigation:** authenticity verification before mutation; replay control; correlation checks; no trust in unverified URL secrecy alone.

---

## R-008 — Brevo/provider behavior changes

**Likelihood:** High over product lifetime  
**Impact:** Medium/High

**Mitigation:** normalized adapter contract; configurable quotas; provider fixtures; source/version notes; no hard-coded plan assumptions.

---

## R-009 — Bulk queue starves transactional mail

**Likelihood:** High without capacity policy  
**Impact:** High

**Mitigation:** protected P0/P1 reserve; priority scheduler; tests with exhausted bulk capacity.

---

## R-010 — Scheduler/cron is not running

**Likelihood:** Medium  
**Impact:** High

Mail remains queued indefinitely.

**Mitigation:** heartbeat diagnostics; oldest-queue-age warning; installation checks; admin documentation; optional dashboard warning banner.

---

## R-011 — Quota accounting races under multiple workers

**Likelihood:** Medium  
**Impact:** High

**Mitigation:** database-atomic quota claims/reservations; concurrency tests on MySQL/MariaDB and PostgreSQL.

---

## R-012 — Queue tables grow indefinitely

**Likelihood:** High without retention  
**Impact:** Medium/High

**Mitigation:** explicit retention; scheduled purge; summary vs payload separation; indexes; performance tests.

---

## R-013 — Encrypted queued payloads become undecryptable after key change

**Likelihood:** Medium  
**Impact:** High

**Mitigation:** SPIKE-D key lifecycle; key-version metadata if needed; upgrade procedure; queue drain/pause before destructive key changes.

---

## R-014 — Persisted rendered mail creates unnecessary PII exposure

**Likelihood:** Medium  
**Impact:** High

**Mitigation:** hybrid rendering strategy; encryption; shortest practical retention; masked admin views; avoid raw payload in logs.

---

## R-015 — Plugin disable strands or duplicates queued mail

**Likelihood:** Medium  
**Impact:** Critical

**Mitigation:** explicit disable semantics; diagnostics; pause/drain guidance; tests; native fallback behavior proven in Phase 0.

---

## R-016 — OJS upgrade silently breaks adapter

**Likelihood:** High over time  
**Impact:** Critical

**Mitigation:** explicit tested version ranges; unknown-version safe mode; compatibility diagnostics; upstream file-change review; release matrix.

---

## R-017 — Subscription footer appears twice

**Likelihood:** Medium  
**Impact:** Low/Medium

**Mitigation:** detect native PKP unsubscribe/footer/header behavior; render tests; MailGuard footer only when required behavior is absent.

---

## R-018 — One-click unsubscribe scope is too broad

**Likelihood:** Medium if poorly designed  
**Impact:** High

**Mitigation:** token claims contain context/category; default narrow scope; multi-journal tests; no global unsubscribe from one journal link unless explicitly selected.

---

## R-019 — One-click unsubscribe is too narrow for provider compliance

**Likelihood:** Low/Medium  
**Impact:** Medium

Mail client/provider expects subscribed-message opt-out to be honored promptly and coherently.

**Mitigation:** category model aligned to the actual subscribed message; visible preference centre; standards-compliant POST; provider-specific deliverability review.

---

## R-020 — Provider unsubscribe feedback cannot be mapped to a category

**Likelihood:** Medium  
**Impact:** Medium

**Mitigation:** attach campaign/category correlation metadata where provider supports it; if mapping is ambiguous, avoid guessing a global preference change; surface reconciliation state.

---

## R-021 — Generic SMTP lacks bounce feedback

**Likelihood:** High  
**Impact:** Medium

**Mitigation:** clearly label capability; use immediate SMTP failures; encourage feedback-capable provider adapter; do not claim full bounce suppression without evidence.

---

## R-022 — Mass campaign creation consumes DB/memory

**Likelihood:** Medium  
**Impact:** High

**Mitigation:** chunked recipient resolution/inserts; streaming/pagination; appropriate indexes; performance tests to 10k/100k identities.

---

## R-023 — Recipient preview disagrees with actual campaign

**Likelihood:** Medium  
**Impact:** High

**Mitigation:** same eligibility engine; preview timestamp/version; re-evaluate or freeze on queue creation; label preview as changed if source state changed.

---

## R-024 — User changes email while campaign waits

**Likelihood:** Medium  
**Impact:** Medium

**Mitigation:** encrypted immutable campaign recipient snapshot by default; explicit re-resolve action rather than silent reroute.

---

## R-025 — User expects unsubscribe to disable editorial mail

**Likelihood:** Medium  
**Impact:** Medium

**Mitigation:** preference centre clearly separates optional publication mail from essential account/editorial workflow correspondence.

---

## R-026 — Admin uses manual suppression as punishment/incorrect policy

**Likelihood:** Low/Medium  
**Impact:** High

**Mitigation:** permissions, reason required, audit, restore path, no casual mass suppression UI in v1.

---

## R-027 — Provider credentials leak through configuration export/logs

**Likelihood:** Medium without safeguards  
**Impact:** Critical

**Mitigation:** encrypted secrets, redaction, write-only API semantics, no before/after secret audit values, security tests.

---

## R-028 — Dependency conflict with OJS Composer/Laravel stack

**Likelihood:** Medium  
**Impact:** High

**Mitigation:** minimal dependencies; prefer existing OJS/framework services; no provider SDK unless justified; compatibility CI.

---

## R-029 — Email headers altered by transport/provider

**Likelihood:** Medium  
**Impact:** Medium/High

**Mitigation:** end-to-end test via representative provider; inspect actual received headers; provider adapter capability notes.

---

## R-030 — Poor defaults damage deliverability

**Likelihood:** Medium  
**Impact:** High

**Mitigation:** conservative pacing; no unlimited default for bulk in enforcing mode without explicit admin choice; safe provider presets; warnings for missing limits.

---

## R-031 — “Accepted” mislabeled as “delivered”

**Likelihood:** Medium  
**Impact:** Medium

**Mitigation:** distinct terminology/state; provider delivered feedback only when available; generic SMTP UI explains limitation.

---

## R-032 — Old unsubscribe token has excessive bearer power

**Likelihood:** Medium  
**Impact:** High

**Mitigation:** narrow purpose/context/category token; preference-centre token may have separate expiry/authority; token versioning/revocation.

---

## R-033 — Retention purge removes evidence needed for active suppression

**Likelihood:** Medium  
**Impact:** High

**Mitigation:** separate long-lived suppression/preferences from short-lived provider payloads; purge tests.

---

## R-034 — Feature creep into newsletter/marketing suite

**Likelihood:** High  
**Impact:** Medium/High

**Mitigation:** Product Bible boundary; post-v1 backlog; reject visual builder/CRM/tracking features unless product direction is explicitly re-opened.

---

## R-035 — 3.4 legacy support consumes disproportionate effort

**Likelihood:** Medium/High  
**Impact:** Medium/High

**Mitigation:** no promise before Phase 0; allow limited/observe-only outcome; prioritize 3.5+ maintainability.

---

## Risk release rule

No production release with an unresolved **Critical** risk that directly threatens duplicate sending, unauthorized sending, silent loss, credential exposure, or cross-context preference corruption.
