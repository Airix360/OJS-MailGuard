# OJS MailGuard — Security and Privacy

**State:** PLAN

---

## 1. Security objective

MailGuard handles outbound correspondence, recipient identities, delivery-health data, provider credentials, and public unsubscribe links. Security failures could leak addresses, suppress legitimate mail, send unauthorized bulk messages, or damage sender reputation.

The security model therefore treats MailGuard as a privileged OJS subsystem.

---

## 2. Assets

Sensitive assets include:

- recipient email addresses;
- OJS user/context relationships;
- queued message content;
- attachments/references;
- unsubscribe/preference tokens;
- provider/API credentials;
- webhook authentication secrets;
- suppression/complaint records;
- delivery history;
- audit history;
- provider message IDs/correlation IDs;
- administrator policy controls.

---

## 3. Threats in scope

### Unauthorized bulk send

Attacker or misconfigured actor triggers a large campaign or bypasses limits.

Controls:

- OJS permission checks;
- producer/action authorization;
- recipient explosion warnings;
- site ceilings;
- audit logs;
- no unauthenticated send endpoint;
- idempotency.

### Duplicate-send amplification

Worker retry, race condition, event replay, double publish, or webhook confusion creates duplicate deliveries.

Controls:

- database uniqueness;
- deterministic idempotency keys;
- attempt vs delivery separation;
- provider-event replay protection;
- explicit resend generations.

### Preference/suppression cross-context leakage

An opt-out in one journal incorrectly affects another.

Controls:

- explicit context IDs;
- context-aware unique keys;
- preference service as sole write path;
- regression tests across multiple journals.

### Forged unsubscribe request

Attacker guesses a user/email identifier and changes preferences.

Controls:

- opaque signed tokens;
- no raw IDs/emails in public URL;
- scoped token claims;
- constant-time signature verification where underlying library provides it;
- token versioning/key rotation strategy.

### Forged provider webhook

Attacker creates hard-bounce or complaint suppressions for arbitrary addresses.

Controls:

- provider authenticity verification;
- event replay fingerprint;
- correlation checks;
- sanitized rejected-event logs;
- no suppression mutation before verification.

### Credential leakage

API/SMTP/webhook secrets appear in logs, UI, exports, or database plaintext.

Controls:

- encryption at rest where supported;
- secret redaction;
- write-only UI fields after save;
- no secret values in audit before/after snapshots;
- least-privilege provider credentials.

### Public token leakage via referrer/logs

Preference URL could be exposed through third-party assets or analytics.

Controls:

- preference/unsubscribe pages avoid third-party assets where practical;
- `Referrer-Policy` appropriate to public token pages;
- no tracking pixels on token confirmation page;
- tokens scoped and revocable/versionable;
- avoid long-lived bearer power broader than needed.

### Queue/message-content exposure

Database compromise or over-privileged admin reveals delayed email bodies.

Controls:

- minimize persisted rendered content;
- encrypt message payload snapshots;
- short retention;
- permission-gated viewing;
- masked recipient lists by default.

### SSRF/provider callback abuse

Administrator-supplied URLs or provider webhook tools create arbitrary outbound requests.

Controls:

- MailGuard does not implement arbitrary webhook forwarding in v1;
- provider endpoints generated from known adapters;
- connection tests target configured provider endpoints only.

---

## 4. Authorization model

MailGuard permissions should be granular rather than one monolithic `manage mailguard` flag.

Proposed permissions:

```text
mailguard.view
mailguard.view_delivery_details
mailguard.manage_context_policy
mailguard.manage_site_policy
mailguard.manage_provider
mailguard.manage_suppression
mailguard.manage_campaign
mailguard.pause_bulk
mailguard.pause_all
mailguard.view_audit
mailguard.run_diagnostics
```

Exact OJS role mapping is adapter-dependent.

Sensitive actions require site-level permission even if a Journal Manager can inspect their own context.

---

## 5. CSRF and public endpoints

Authenticated administrative mutations use OJS/framework CSRF protections.

Public one-click unsubscribe is intentionally usable without an authenticated OJS session. It must use signed bearer authorization and support the protocol-required POST behavior rather than relying on CSRF cookies.

Preference-centre GET may show the scoped state only after token validation.

Tokens must not authorize unrelated account actions.

---

## 6. Token design

Conceptual claims:

```text
token_version
purpose
context_id
category_key/type_key
recipient_identity_reference
issued_at
optional_expiry
nonce/version
```

Do not place readable raw email or OJS user ID in the URL payload unless the payload itself is encrypted/authenticated and the final design proves exposure risk acceptable. Preferred external token is opaque or encrypted+signed.

### Expiry

One-click unsubscribe links in old subscription mail should remain useful for a reasonable period. A token can be long-lived but narrow in authority.

Preference-management tokens may have stronger expiry requirements if they expose more options.

### Revocation

Token version/key rotation must permit invalidating compromised generations without breaking every future message indefinitely.

---

## 7. Encryption

Preferred uses of application-level encryption:

- provider secrets;
- recipient address snapshots for delayed queueing;
- rendered payloads where persisted;
- sensitive provider metadata if necessary.

Equality/dedup uses keyed HMAC rather than decrypting every row or indexing plaintext.

Encryption implementation waits for the OJS encryption capability spike; MailGuard must not invent custom cryptography.

---

## 8. Logging

Application logs should include:

- MailGuard public/correlation IDs;
- mail type;
- context ID;
- state transition;
- reason code;
- provider profile ID;
- attempt number;
- masked address when necessary.

Do not normally log:

- full message body;
- full recipient list;
- API keys;
- unsubscribe tokens;
- raw authentication headers;
- full provider webhook payloads.

Debug mode that expands PII must be explicit, time-bounded, permission-restricted, and clearly warned.

---

## 9. Webhook security

Required sequence:

```text
receive
 -> size/type check
 -> identify provider profile
 -> verify authenticity
 -> compute replay fingerprint
 -> persist/recognize event
 -> correlate delivery
 -> normalize event
 -> mutate state/suppression
 -> audit material mutation
```

Do not correlate or suppress first and verify later.

Unknown/uncorrelated valid provider events may be stored minimally for reconciliation but must not create broad suppression unless recipient evidence and policy justify it.

---

## 10. Suppression abuse protection

Because suppressions can stop legitimate communication:

- manual global suppression requires elevated permission;
- restoration requires reason;
- mass suppression imports are not v1 unless separately specified;
- provider feedback source is recorded;
- unusually large suppression spikes trigger warnings;
- complaint/hard-bounce actions are explainable from source event.

---

## 11. Provider credentials

Rules:

- use least privilege;
- support rotating credentials without exposing previous value;
- test connection without logging secret;
- never return secret through REST/API after storage;
- configuration export must redact secrets;
- uninstall must remove MailGuard-stored secrets.

If OJS config already owns SMTP credentials, MailGuard should reference/use the configured transport instead of duplicating secrets unnecessarily.

---

## 12. Privacy

MailGuard does not need open/click tracking for its core purpose.

Default privacy position:

- no tracking pixel;
- no recipient behavioral profile;
- collect delivery-control events only;
- retain only what supports delivery safety, troubleshooting, preferences, and accountability;
- expose configurable retention;
- minimize recipient content in dashboards.

Provider may independently offer tracking. MailGuard should not enable it automatically.

---

## 13. Data-subject/user lifecycle

MailGuard must define behavior for:

- user email change;
- user account merge if OJS supports it;
- user deletion/anonymization;
- inactive/disabled account;
- reader unregistering from a journal;
- journal deletion.

Address health may outlive an OJS account when necessary to prevent repeated hard-bounce delivery, but retained state should be reduced to the minimum required identity hash/suppression metadata.

---

## 14. Email-address change

When a user changes address:

- old hard-bounce suppression does not automatically transfer to the new address;
- optional preferences can remain attached to user/context/category according to policy;
- queued delivery behavior must be explicit: campaign address snapshot normally remains immutable after queue creation unless an administrator re-resolves unsent recipients deliberately;
- transactional mail generated after the change uses the new address.

This avoids silently rerouting old campaign mail to an address that was not the intended target when the campaign was created.

---

## 15. Security headers/public preference page

Preference/unsubscribe responses should consider:

- strict transport security inherited from deployment;
- `Referrer-Policy: no-referrer` or similarly protective setting;
- restrictive content security policy compatible with OJS page architecture;
- no indexing by search engines;
- no caching of sensitive personalized pages by shared caches.

Exact headers depend on OJS routing/template constraints.

---

## 16. Abuse/rate limiting

Public endpoints:

- tolerate legitimate mail-client one-click requests;
- rate-limit obvious abuse by token/IP patterns without blocking bulk unsubscribe actions from mailbox providers;
- duplicate valid unsubscribe POST is idempotent and returns success-like response.

Admin test-send endpoint:

- permission required;
- limited recipient count;
- no arbitrary mass recipient parameter;
- audited.

---

## 17. Dependency/security update policy

MailGuard should minimize added dependencies.

Any new dependency must have:

- clear purpose;
- maintained upstream;
- compatible license;
- no overlap with OJS/Laravel capability without justification;
- version constraint;
- security-review/update plan.

Prefer framework/OJS primitives for signing, encryption, HTTP, queueing, and validation.

---

## 18. Threat-model acceptance gate

Before v1 implementation freeze, threat tests/specs must cover:

- guessed/tampered unsubscribe token;
- cross-journal token reuse;
- wrong category token reuse;
- webhook with invalid auth;
- webhook replay;
- malicious large webhook payload;
- admin without provider permission;
- journal manager attempting site-level pause/configuration;
- secret redaction in logs/audit/API;
- duplicate campaign race;
- queue worker race;
- user email change during long campaign;
- disabled plugin/upgrade state;
- retention purge safety;
- database backup exposure assumptions documented.
