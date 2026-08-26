# OJS MailGuard — Phase 0 Integration Proof

**Branch:** `phase/0-integration-spikes`  
**Purpose:** prove the Product Bible's Tier-1 integration guarantees before feature implementation  
**Production readiness:** **NO — disposable spike code only**

---

## 1. Evidence rules

Every Phase 0 conclusion must use one of these states:

- **SOURCE-PROVEN** — directly verified against the relevant upstream OJS/PKP source line and protected by a source-contract CI assertion.
- **RUNTIME-PROVEN** — exercised successfully against a disposable installed OJS test environment with a real database and mail transport.
- **OPEN** — not yet demonstrated strongly enough to become a product guarantee.
- **FAILED** — executable evidence disproved the proposed architecture; implementation must stop until the Product Bible/compatibility promise is revised.

A source-level seam is not enough to mark a runtime guarantee passed.

---

## 2. Phase 0 questions

| ID | Question | Required evidence |
|---|---|---|
| P0-A1 | Can MailGuard stop a controlled message immediately before native transport without patching OJS core? | source + runtime |
| P0-A2 | Can capture fail open so OJS does not silently lose mail? | source/code contract + runtime failure injection before Phase 0 closes |
| P0-A3 | Can MailGuard deliberately bypass itself for its own/native send path? | runtime |
| P0-B1 | Can the real new-issue job be controlled without changing the producer? | runtime |
| P0-B2 | Are native in-app issue notifications preserved when email is intercepted? | runtime |
| P0-B3 | Are repeated attempts idempotent at delivery identity level? | runtime |
| P0-C1 | Can a plugin register scheduled spool work through native OJS scheduling? | source + runtime registration/probe |
| P0-C2 | Can a crashed worker's lease expire and be safely reclaimed? | runtime |
| P0-D1 | Can queued recipient/message data be encrypted with the OJS application encryption facility on the target line? | source + runtime decrypt round-trip |
| P0-D2 | Does disabling MailGuard restore native OJS delivery? | runtime in a fresh process |
| P0-V1 | Does the selected seam exist on OJS 3.5? | source + runtime |
| P0-V2 | Does the selected seam remain viable on OJS 3.6/current main? | source + runtime before compatibility freeze |
| P0-V3 | Can OJS 3.4 meet the same encryption/queue safety guarantees without disproportionate compatibility code? | OPEN; not a v1 promise |

---

## 3. Source-proven findings

### Cancellable pre-transport event

OJS/PKP 3.4, 3.5 and current main route context mail through `PKP\mail\Mailer::shouldSendMessage()`. That method dispatches `MessageSendingFromContext` with `Dispatcher::until(...)` and only proceeds when the returned value is not `false`.

**Result:** `MessageSendingFromContext` is the Phase 0 interception seam. The earlier `Email::send::before` hook is not used as the authoritative cancellation mechanism.

**State:** SOURCE-PROVEN.

### Mailable classification hook

OJS/PKP 3.5 and current main expose `Mailable::build`, which runs the plugin hook `Mailable::build` before delivery.

**Result:** MailGuard can classify/decorate native mailables without editing each OJS producer.

**State:** SOURCE-PROVEN.

### New-issue ordering

`IssuePublishedNotifyUsers::handle()` creates the OJS in-app notification before it creates/sends the issue-published mailable.

**Result:** a pre-transport email interception should not inherently suppress the native in-app notification.

**State:** SOURCE-PROVEN; runtime assertion still required.

### Plugin scheduler

OJS/PKP 3.5 loads plugins implementing `HasTaskScheduler` and invokes their `registerSchedules()` method through `PKPScheduler`.

**Result:** no custom daemon or cron patch is required for a MailGuard spool worker on 3.5.

**State:** SOURCE-PROVEN.

### Encryption

OJS/PKP 3.5 registers `PKPEncryptionServiceProvider` and binds Laravel's encrypter to the OJS application key/cipher configuration.

**Result:** 3.5 has a native application-encryption facility appropriate for the Phase 0 queued-payload proof.

**State:** SOURCE-PROVEN; runtime round-trip still required.

OJS/PKP `stable-3_4_0` does not expose the same `PKPEncryptionServiceProvider` file.

**Result:** 3.4 remains outside the v1 support promise until a separate secure strategy is proven.

**State:** SOURCE-PROVEN compatibility constraint.

---

## 4. Spike safety design

The Phase 0 shell deliberately has four independent safety gates:

1. plugin must be enabled;
2. `[mailguard] phase0_capture = On` must be configured;
3. only the classified `ojs.issue_published` type is captured;
4. native delivery is cancelled only after encrypted durable persistence succeeds and `[mailguard] phase0_intercept = On` is configured.

Capture/encryption/persistence exceptions log an error and return control to native OJS mail rather than cancelling it.

The scheduled Phase 0 task **never sends mail**. It only exercises lease/claim/recovery semantics.

---

## 5. Automated evidence

### Source contracts

Workflow: `.github/workflows/phase0-contracts.yml`

It checks:

- PHP syntax across supported spike PHP versions;
- the cancellable mail event on 3.4, 3.5 and current main;
- the `Mailable::build` hook on 3.5/current main;
- the real issue-published job path;
- native unsubscribe integration;
- plugin scheduler integration;
- OJS 3.5/current encryption provider presence;
- the deliberate absence of a 3.4 encryption guarantee.

### OJS runtime

Workflow: `.github/workflows/phase0-ojs-runtime.yml`

The initial runtime matrix uses official `pkp/pkp-github-actions@v1` environments for OJS `stable-3_5_0` on MySQL and PostgreSQL.

The runtime harness:

1. installs the plugin migration in a disposable OJS instance;
2. enables Phase 0 capture/interception;
3. resolves a real journal, issue and user from PKP's prepared dataset;
4. executes the actual `IssuePublishedNotifyUsers` job twice;
5. asserts OJS creates two in-app notifications;
6. asserts MailGuard stores one idempotent spool delivery;
7. decrypts and validates the queued payload;
8. asserts the address is not stored in plaintext identity columns/payload ciphertext;
9. leases the spool row, simulates worker death by expiring the lease, and reclaims it with a new ownership token;
10. sends one scoped-bypass message;
11. disables MailGuard in a fresh PHP process and sends one would-be controlled message;
12. checks Sendria received exactly two messages total from the test: bypass + disabled. The two intercepted new-issue sends must not reach SMTP.

---

## 6. Phase 0 close rule

Do **not** advance to Phase 1 merely because the spike compiles.

Phase 0 can close only when:

- OJS 3.5 runtime matrix is green;
- fail-open is failure-injection tested, not merely code-reviewed;
- native scheduler registration executes the spool probe in an OJS runtime;
- current OJS 3.6/main is runtime-tested or explicitly removed from the initial compatibility promise;
- evidence is reconciled into the Product Bible compatibility/decision/status documents;
- no permanent core patch is required.

Until then the branch remains an integration spike, not the beginning of production implementation.
