# OJS MailGuard — Implementation Roadmap

**State:** PLAN ONLY — execution has not started

---

## 1. Rule of execution

No runtime feature work begins until this Product Bible reaches **PLAN FROZEN / PHASE 0 READY**.

The first execution phase is not feature implementation. It is a set of small technical spikes designed to prove the integration seams on real supported OJS versions.

No spike result is allowed to quietly redesign product guarantees. If a guarantee cannot be met, the architecture/compatibility promise must be revised explicitly in the decision log before implementation proceeds.

---

## 2. Branch strategy

Proposed development flow:

```text
main
  <- reviewed/frozen planning baseline

planning/product-bible
  <- current specification work only

phase/0-integration-spikes
phase/1-plugin-foundation
phase/2-policy-data-core
phase/3-new-issue-observe
phase/4-bulk-enforcement
phase/5-preferences-unsubscribe
phase/6-provider-feedback
phase/7-admin-ux
phase/8-hardening-release
```

Feature branches may branch from the active phase branch. No large direct implementation on `main`.

---

## 3. Phase 0 — Integration proof

### Objective

Prove the architecture can meet core guarantees without patching OJS core.

### Work

#### SPIKE-A — transport interception/wrapping

- minimal MailGuard plugin shell only as needed for experiment;
- intercept/wrap controlled mail path;
- prove native render semantics survive;
- prove deliberate native bypass possible;
- prove plugin disable restores safe mail path.

#### SPIKE-B — new-issue producer integration

- publish issue;
- preserve in-app notifications;
- prevent native email escape;
- capture intended email recipients once;
- prove retry/idempotency boundary.

#### SPIKE-C — scheduler/queue

- register MailGuard scheduled work;
- persist sample delivery;
- claim/release with worker restart;
- prove no duplicate claim under concurrency.

#### SPIKE-D — encryption

- identify supported OJS/framework encryption APIs;
- encrypt/decrypt queued recipient snapshot;
- document key lifecycle/upgrade implications.

### Deliverables

- spike code in disposable/isolated branch or `spikes/` area;
- evidence notes;
- selected architecture path;
- updated compatibility matrix;
- decision records.

### Exit gate

`PHASE 0 PASS` requires all Tier-1 guarantees to be technically achievable without a permanent core patch.

If not, stop and revise scope/compatibility before Phase 1.

---

## 4. Phase 1 — Plugin foundation

### Objective

Build installable, upgradeable, observable MailGuard shell with no bulk enforcement yet.

### Work

- plugin metadata/versioning;
- install/upgrade lifecycle;
- compatibility adapter loader;
- permissions;
- service registration;
- routes/API shell;
- diagnostics shell;
- scheduled-task registration;
- operational mode state;
- test harness/CI matrix foundation.

### Exit gate

- clean install/uninstall/disable;
- supported OJS version detected;
- adapter selected;
- diagnostics meaningful;
- no change to production mail behavior in default observe mode.

---

## 5. Phase 2 — Policy and data core

### Objective

Implement the version-independent correctness core.

### Work

- migrations;
- mail type registry;
- preference service;
- suppression service;
- policy engine;
- campaign service;
- delivery/attempt state machines;
- idempotency keys;
- recipient normalization/HMAC;
- encrypted address snapshot;
- quota buckets;
- queue claim/lease;
- audit events;
- retention primitives.

### Exit gate

Unit/database/concurrency tests pass before any real outbound enforcement depends on these services.

---

## 6. Phase 3 — New issue in observe mode

### Objective

Integrate the motivating OJS flow without changing actual transport outcome yet.

### Work

- register `ojs.issue_published`;
- capture/compute intended recipients;
- model campaign preview;
- compute normalized unique delivery identities;
- compare MailGuard eligibility against native OJS send behavior;
- report deltas;
- detect duplicate-email accounts;
- validate multi-journal preference behavior.

### Exit gate

On staging fixtures, MailGuard can explain exactly what native OJS would send and what MailGuard would change, with no unintended mail interception.

---

## 7. Phase 4 — Enforced bulk queue

### Objective

Make MailGuard authoritative for registered subscription/bulk email, beginning with new-issue notifications.

### Work

- durable pre-send capture;
- prevent native duplicate email escape;
- priority scheduler;
- quota/rate/cooldown;
- protected capacity;
- generic SMTP gateway;
- retries/backoff;
- pause bulk/all;
- campaign cancel;
- explicit resend generation;
- uncertain-send handling.

### Exit gate

Full new-issue mandatory test scenarios pass including provider-capacity exhaustion and worker crash.

Recommended first production rollout mode after staging validation: `enforce_bulk`.

---

## 8. Phase 5 — Preferences and unsubscribe

### Objective

Give optional mail correct recipient control independent of current OJS cross-context weaknesses.

### Work

- context-safe MailGuard preference table/service;
- signed token service;
- one-click endpoint;
- preference centre;
- merge variables;
- automatic subscription footer;
- native PKP unsubscribe interoperability;
- list-unsubscribe headers;
- audit preference changes;
- migration/import rules from native OJS settings.

### Exit gate

Cross-journal isolation, token security, one-click idempotency, and template rendering tests pass.

---

## 9. Phase 6 — Provider feedback and suppression

### Objective

Close the loop between sending and delivery health.

### Work

- provider adapter interface;
- Brevo adapter;
- webhook receiver;
- authenticity verification;
- event replay protection;
- normalized provider events;
- correlation metadata;
- hard/soft bounce policy;
- invalid/blocked/spam/unsubscribe mapping;
- provider health;
- suppression UI/backend;
- reconciliation utilities.

### Exit gate

All provider fixtures, forgery/replay tests, and suppression lifecycle tests pass.

---

## 10. Phase 7 — Administration UX

### Objective

Expose the control plane safely to administrators/editors/readers.

### Work

- overview;
- queue;
- campaigns;
- publish preview;
- suppressions;
- provider configuration;
- policies;
- mail types;
- audit;
- diagnostics;
- preference centre polish;
- localization/accessibility.

### Exit gate

Role/permission tests and agreed UX acceptance scenarios pass.

---

## 11. Phase 8 — Hardening and v1 release

### Objective

Turn working software into a supportable plugin release.

### Work

- full compatibility matrix;
- performance tests;
- failure injection;
- upgrade tests;
- retention cleanup;
- security review;
- documentation;
- README/install/configuration;
- provider setup guide;
- troubleshooting;
- changelog;
- package build;
- artifact/checksum;
- release candidate staging test.

### Release sequence direction

```text
0.1.0-alpha  foundation/policy internal
0.2.0-alpha  new issue observe
0.3.0-alpha  enforced bulk
0.4.0-beta   preferences/unsubscribe
0.5.0-beta   provider feedback
0.9.0-rc     complete v1 candidate
1.0.0        production-ready after full release gate
```

Version sequence is a planning proposal, not yet frozen.

---

## 12. Post-v1 candidates

Prioritized only after v1 production evidence:

- continuous-publication article notifications;
- daily/weekly digests;
- announcement campaign integration;
- additional provider adapters;
- multi-provider routing;
- provider quota API discovery;
- optional recipient frequency caps;
- adaptive domain throttling;
- export/import of suppression/preferences;
- third-party MailGuard service API stabilization;
- admin notifications for provider reputation/queue anomalies.

---

## 13. Things explicitly deferred until proven necessary

- custom message broker;
- Redis requirement;
- external worker daemon separate from OJS;
- provider-specific SDK dependency;
- open/click analytics;
- marketing automation;
- visual email builder;
- automatic multi-provider failover;
- AI-based deliverability decisions.

MailGuard should first be boring, deterministic, observable infrastructure.

---

## 14. Execution handoff checklist

Before the first Phase 0 coding prompt/task is issued:

- [ ] Product Bible reviewed
- [ ] open decisions triaged
- [ ] risks accepted/mitigated
- [ ] Phase 0 spike pass/fail questions frozen
- [ ] Tier-1 OJS test environment identified
- [ ] branch created from reviewed planning baseline
- [ ] no unrelated feature implementation mixed into spikes
- [ ] spike evidence format defined

The next action after planning is **Phase 0 evidence gathering**, not “build the whole plugin.”
