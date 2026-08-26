# OJS MailGuard — Product Bible Index

**Branch:** `planning/product-bible`  
**Mode:** PLAN ONLY  
**Planning state:** REVIEW CANDIDATE  
**Runtime implementation:** NOT STARTED

This directory is the implementation contract for OJS MailGuard. It must be reviewed as one system, not as independent feature notes.

## Product Bible

1. [`00-PRODUCT-BIBLE.md`](00-PRODUCT-BIBLE.md) — product definition, principles, scope, user outcomes, v1 boundaries.
2. [`01-ARCHITECTURE.md`](01-ARCHITECTURE.md) — control-plane architecture, interception boundaries, adapters, queue and transport model.
3. [`02-MAIL-POLICY.md`](02-MAIL-POLICY.md) — mail classes, priorities, deduplication, cooldown, quota, retry, suppression and state rules.
4. [`03-DATA-MODEL.md`](03-DATA-MODEL.md) — proposed persistence model, identifiers, recipient identity, encryption and lifecycle rules.
5. [`04-UX-ADMIN.md`](04-UX-ADMIN.md) — site/journal administration, campaign preview, queue, preferences and operational controls.
6. [`05-PROVIDERS-WEBHOOKS.md`](05-PROVIDERS-WEBHOOKS.md) — provider abstraction, generic SMTP, Brevo, webhook normalization and delivery feedback.
7. [`06-SECURITY-PRIVACY.md`](06-SECURITY-PRIVACY.md) — threat model, secrets, signed actions, webhook security, PII and retention.
8. [`07-COMPATIBILITY.md`](07-COMPATIBILITY.md) — OJS version strategy and adapter commitments.
9. [`08-TEST-STRATEGY.md`](08-TEST-STRATEGY.md) — correctness, concurrency, failure, security and release test matrix.
10. [`09-ROADMAP.md`](09-ROADMAP.md) — gated execution plan from integration spikes through v1.
11. [`10-DECISIONS.md`](10-DECISIONS.md) — architecture/product decision log.
12. [`11-RISKS.md`](11-RISKS.md) — technical, operational, compatibility and deliverability risks.
13. [`12-UPSTREAM-EVIDENCE.md`](12-UPSTREAM-EVIDENCE.md) — upstream OJS/PKP evidence that anchors the plan.
14. [`13-PLAN-STATUS.md`](13-PLAN-STATUS.md) — current planning checkpoint and the exact gate before execution.

## Binding product rule

OJS remains the authority for editorial workflow and native mail content. MailGuard becomes the policy and delivery authority only for mail classes it explicitly controls. It must not silently break core OJS workflow mail.

The motivating first enforced use case is **new-issue publication email**. The architecture is intentionally broader so later OJS mail producers can register with MailGuard without each implementing their own queue, deduplication, quota, suppression and unsubscribe systems.

## Planning freeze rule

Do not begin feature implementation merely because the documents exist.

The next allowed technical work is **Phase 0 integration evidence gathering** as defined in `09-ROADMAP.md`. Phase 0 may create minimal disposable plugin/spike code solely to prove the interception, queue/recovery, disable/bypass and encryption seams. Feature implementation begins only after the spike evidence is reconciled back into this Bible and the plan is explicitly frozen.

## Definition of PLAN FROZEN / PHASE 0 READY

All of the following must be true:

- product scope and non-goals reviewed;
- Tier-1 OJS compatibility target accepted;
- open decisions that affect Phase 0 resolved or explicitly bounded;
- Phase 0 pass/fail questions fixed before coding;
- no permanent OJS core patch accepted as the baseline architecture;
- security/privacy assumptions for queued recipient data accepted;
- failure/idempotency guarantees accepted;
- test environment identified;
- planning PR reviewed and merged as the baseline specification.

Until then, repository status remains **PLAN ONLY**.