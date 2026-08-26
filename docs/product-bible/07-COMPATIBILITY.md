# OJS MailGuard — Compatibility Strategy

**State:** PLAN

---

## 1. Compatibility principle

MailGuard compatibility is defined by tested lifecycle guarantees, not by whether the plugin page loads.

A release is only advertised as supported when these behaviors pass on that OJS version:

- plugin install/upgrade/disable;
- mailable classification/decorating;
- new-issue integration;
- queue/scheduler operation;
- provider transport;
- preference/unsubscribe flow;
- webhook processing;
- context isolation;
- idempotency/concurrency;
- uninstall/rollback semantics.

---

## 2. Current upstream planning baseline

As of the planning baseline in August 2026:

- PKP/OJS has an active `stable-3_5_0` branch;
- OJS `main` identifies its package version as `3.6.0`;
- older stable branches including `stable-3_4_0` remain present upstream;
- current PKP main exposes `Mailable::build` as a plugin hook;
- current PKP mailables can use the `Unsubscribe` trait to add a body footer URL and `List-Unsubscribe` / `List-Unsubscribe-Post` headers;
- current new-issue publication dispatches `IssuePublishedNotifyUsers` jobs after resolving notification/email-eligible user IDs.

These observations are inputs to the design, not guarantees that every version behaves identically.

---

## 3. Proposed support tiers

### Tier 1 — OJS 3.5.x

Target for first production-ready MailGuard release.

Why:

- active stable line;
- modern PKP/Laravel mail infrastructure;
- current queue/scheduler capabilities;
- appropriate baseline for long-lived plugin architecture.

Required CI/fixture target:

- latest supported 3.5.x patch at release time;
- at least one earlier representative 3.5.x fixture if upstream changes affect mail hooks.

### Tier 2 — OJS 3.6.x

Tracked during development and supported when the release line is stable enough for production validation.

MailGuard must keep a separate adapter even when implementation initially appears identical to 3.5, so future divergence is contained.

### Tier 3 — OJS 3.4.x legacy

Not promised automatically.

Decision criteria:

- actual Airix/target deployment demand;
- OJS upstream support status at MailGuard release time;
- availability of required mail/queue/encryption extension points;
- cost of maintaining tests and security fixes;
- whether safe functionality requires reduced feature mode.

Possible outcome:

- full support;
- `enforce_bulk` only;
- observe-only compatibility;
- unsupported.

The product must state which, rather than silently degrading.

---

## 4. Adapter capability matrix

Each adapter records:

| Capability | 3.5 adapter | 3.6 adapter | 3.4 legacy |
|---|---|---|---|
| `Mailable::build` hook | verify | verify | verify |
| Native unsubscribe trait behavior | verify | verify | verify |
| New-issue producer integration | spike/test | spike/test | spike/test |
| Transport wrapping/interception | spike/test | spike/test | spike/test |
| OJS queue integration | verify | verify | verify |
| Scheduled-task integration | verify | verify | verify |
| App-key encryption | verify | verify | legacy decision |
| Public signed route support | verify | verify | verify |
| Webhook API/controller pattern | verify | verify | verify |
| Disable/uninstall recovery | test | test | test if supported |

No cell moves from `verify/spike` to supported without executable evidence.

---

## 5. Database compatibility

MailGuard should follow OJS-supported database families for each advertised version.

Initial test target:

- MySQL/MariaDB family;
- PostgreSQL.

Requirements:

- no vendor-only schema feature without abstraction/fallback;
- indexes tested on both families;
- concurrent quota/delivery claims tested on both families;
- JSON usage compatible with OJS/Laravel support level or stored in portable representation;
- migration rollback/uninstall behavior tested.

---

## 6. PHP/runtime compatibility

MailGuard declares PHP requirements through OJS compatibility rather than inventing a broader range.

At release time, package metadata and installation checks must reflect the intersection of:

- supported OJS PHP range;
- any MailGuard dependency range;
- provider SDK range if an SDK is used.

Preferred approach is to avoid provider SDKs where normal framework HTTP clients are sufficient, reducing version conflicts.

---

## 7. OJS mailables compatibility registry

MailGuard should maintain a versioned fixture of known built-in mailables for each adapter.

For each known mailable:

```text
source class
MailGuard type key
class/policy category
context availability
recipient source
unsubscribe capability
attachments possible?
manual or automated?
producer/job path
control status
```

This registry supports:

- discovery diff after OJS upgrade;
- unknown mailable warnings;
- regression tests;
- documentation.

---

## 8. Upgrade detection

On plugin load/diagnostic run:

1. detect OJS version;
2. choose exact compatible adapter;
3. compare against tested range;
4. if unknown newer release, do not claim enforcing guarantees;
5. surface `WARN/FAIL` with action;
6. allow site administrator to enter observe mode safely.

A future OJS version must not inherit the 3.6 adapter purely because its class names happen to match.

---

## 9. Compatibility fixtures

Required scenarios per supported OJS version:

- single journal;
- multi-journal installation;
- two users sharing one email address;
- user with multiple roles;
- opted-out notification/email setting;
- journal A opt-out + journal B subscribed;
- issue publication with send notification checked;
- issue publication with send notification unchecked;
- publication mode where notification should not send;
- generic manual/editorial mail;
- queue runner enabled;
- queue runner interrupted/restarted.

---

## 10. Upstream change monitoring

Files/areas to watch across OJS/PKP upgrades:

```text
OJS IssueGridHandler publication flow
OJS IssuePublishedNotifyUsers
PKP NotificationSubscriptionSettingsDAO
PKP Mailable
PKP Unsubscribe trait
PKP Mailer / mail transport configuration
PKP queue/job infrastructure
PKP scheduled-task infrastructure
email template API/UI
notification settings UI/API
user/context deletion hooks
```

A release checklist should compare relevant upstream changes since MailGuard's last verified baseline.

---

## 11. Compatibility acceptance gate

Before v1 implementation is called phase-ready:

- adapter interface frozen;
- 3.5 target environment reproducible;
- 3.6 tracking environment reproducible;
- 3.4 decision explicitly recorded;
- DB test matrix defined;
- mailables discovery fixture design defined;
- upgrade unknown-version behavior defined;
- no “works on all OJS 3.x” claim permitted.
