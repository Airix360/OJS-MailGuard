# OJS MailGuard — Plan Status

**Current mode:** PLAN ONLY  
**Current branch:** `planning/product-bible`  
**Planning checkpoint:** REVIEW CANDIDATE  
**Runtime implementation:** NOT STARTED  
**Next allowed execution phase:** Phase 0 integration proof, only after plan review/freeze

---

## 1. What is now specified

The planning branch contains implementation-level definitions for:

- product purpose, users, principles and v1 boundaries;
- mail classification and priority policy;
- new-issue campaign semantics;
- email-level deduplication for subscription/bulk mail;
- campaign and recipient idempotency;
- durable queue and worker ownership model;
- provider quotas, rate limits, cooldowns and protected capacity;
- pause/cancel/resend behavior;
- retry/backoff and uncertain-send handling;
- context-safe optional-mail preferences;
- unsubscribe and one-click unsubscribe behavior;
- mailable merge variables and automated footer behavior;
- suppression and bounce/complaint policy;
- provider abstraction, generic SMTP and Brevo direction;
- webhook normalization/authenticity/replay requirements;
- multi-journal site-vs-context administration;
- admin queue/campaign/health/preferences UX;
- proposed persistence model and migrations direction;
- recipient normalization, keyed hash and encrypted address snapshots;
- secret handling, signed actions, privacy and retention;
- compatibility adapters and initial OJS target matrix;
- unit/integration/concurrency/security/failure/release testing;
- phased roadmap, release gates, decision log and risk register;
- upstream OJS/PKP evidence anchoring the design.

---

## 2. Product decisions considered binding for Phase 0

Unless review changes them explicitly:

1. MailGuard is a control plane, not a replacement OJS mail-template/editorial system.
2. New-issue publication email is the first enforced bulk integration.
3. Native in-app notifications must continue to work when MailGuard controls the email side.
4. Subscription/bulk deduplication is by normalized delivery identity, not merely OJS user ID.
5. Transactional/workflow messages are not globally email-deduplicated by default.
6. Optional-mail preferences must be journal/context scoped.
7. Suppression is address-centric where delivery reputation demands it.
8. Queueing must be durable before native controlled mail is allowed to be suppressed.
9. Provider quota/rate policy is configurable data; no vendor plan limit is hard-coded as business logic.
10. Critical/workflow mail can have protected sending capacity ahead of bulk campaigns.
11. Explicit resend creates a new generation; retries do not create a new campaign identity.
12. Permanent OJS core patches are not an acceptable baseline architecture.
13. Version-specific OJS behavior belongs in compatibility adapters.
14. 3.5.x is the first-class v1 target until evidence changes the compatibility matrix.
15. Feature implementation does not begin during Phase 0; spikes prove seams and feed evidence back into the Bible.

---

## 3. Open questions intentionally left for Phase 0

These are not planning omissions; they require executable proof against real OJS versions:

- exact pre-send interception mechanism for controlled messages;
- whether the same mechanism safely spans 3.5 and 3.6;
- adapter boundary for new-issue mail without damaging in-app notification creation;
- best scheduler/claim primitive for the durable spool;
- safe handling of plugin disable while queued work exists;
- exact encryption/key lifecycle available in each supported line;
- crash boundary when provider acceptance occurs before local attempt state is committed;
- whether 3.4 can satisfy Tier-1 guarantees without disproportionate compatibility code.

No implementation task may silently decide one of these and proceed. The result must be recorded as spike evidence and, where architectural, added to the decision log.

---

## 4. Phase 0 pass/fail contract

### PASS requires evidence that MailGuard can, on the Tier-1 OJS line:

- observe/classify native mail without altering unrelated mail;
- capture a controlled new-issue email before delivery;
- preserve its native template/locale/recipient semantics;
- preserve native in-app published-issue notifications;
- ensure the controlled email is not also sent by the native path;
- persist the intended delivery before acknowledging control of it;
- safely resume/reclaim queued work after worker restart;
- prevent concurrent workers from independently claiming the same delivery;
- encrypt/decrypt queued recipient address snapshots using an acceptable key lifecycle;
- disable/bypass MailGuard without creating silent mail loss or duplicate escape;
- accomplish the above without a permanent core patch.

### FAIL means:

- stop before Phase 1;
- document the failing guarantee;
- revise architecture, scope or compatibility explicitly;
- re-review the affected Product Bible sections;
- rerun only the relevant spike after the decision is recorded.

A Phase 0 failure is useful evidence. It is not permission to hide a core patch or weaken a guarantee implicitly.

---

## 5. What has NOT been done

At this checkpoint there is intentionally:

- no installable OJS plugin package;
- no production PHP runtime code;
- no migrations applied anywhere;
- no live database changes;
- no SMTP interception in a deployed OJS instance;
- no provider credentials stored;
- no webhook endpoint deployed;
- no production/staging email sent;
- no release/tag created;
- no merge of the planning branch to `main`.

---

## 6. Review checklist

Before changing status to `PLAN FROZEN / PHASE 0 READY`:

- [ ] review `00-PRODUCT-BIBLE.md` for product scope and v1 boundaries;
- [ ] review `01-ARCHITECTURE.md` for no-core-patch and durability guarantees;
- [ ] review `02-MAIL-POLICY.md` for class/priority/dedup/suppression behavior;
- [ ] review `03-DATA-MODEL.md` for privacy, idempotency and state lifecycle;
- [ ] review `04-UX-ADMIN.md` for site-vs-journal authority;
- [ ] review `05-PROVIDERS-WEBHOOKS.md` for provider abstraction and Brevo scope;
- [ ] review `06-SECURITY-PRIVACY.md` for tokens, secrets and queued PII;
- [ ] accept or revise `07-COMPATIBILITY.md` Tier-1 target;
- [ ] accept mandatory scenarios in `08-TEST-STRATEGY.md`;
- [ ] freeze Phase 0 questions in `09-ROADMAP.md`;
- [ ] triage unresolved decisions in `10-DECISIONS.md`;
- [ ] accept/mitigate material risks in `11-RISKS.md`;
- [ ] confirm upstream evidence in `12-UPSTREAM-EVIDENCE.md` against the exact OJS tag used for Phase 0;
- [ ] merge the reviewed planning PR as the implementation baseline.

---

## 7. Status transition

The next legitimate status transition is:

`REVIEW CANDIDATE` → `PLAN FROZEN / PHASE 0 READY`

Only after review and merge should a new `phase/0-integration-spikes` branch be created from that reviewed baseline.

The next engineering prompt should therefore be a **Phase 0 integration-proof prompt**, not a request to build MailGuard end-to-end.