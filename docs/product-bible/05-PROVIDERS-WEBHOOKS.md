# OJS MailGuard — Providers and Webhooks

**State:** PLAN

---

## 1. Provider philosophy

MailGuard core owns policy. Provider adapters translate provider-specific sending/feedback behavior into MailGuard's normalized model.

No core policy table should contain Brevo-only event names, payload shapes, or quota assumptions.

---

## 2. Provider capability model

An adapter declares capabilities, for example:

```text
smtp_transport                  yes/no
api_send                         yes/no
message_id_correlation           yes/no
custom_header_correlation        yes/no
webhook_feedback                 yes/no
webhook_authentication           yes/no
hard_bounce_feedback             yes/no
soft_bounce_feedback             yes/no
complaint_feedback               yes/no
unsubscribe_feedback             yes/no
blocked_feedback                 yes/no
invalid_address_feedback         yes/no
delivered_feedback               yes/no
quota_api                        yes/no
provider_idempotency             yes/no
```

MailGuard UI and policy must adapt to actual capability instead of pretending generic SMTP has provider-grade feedback.

---

## 3. Generic SMTP adapter

Baseline mode.

Responsibilities:

- use OJS-configured SMTP/transport where safely possible;
- obey MailGuard queue/rate/capacity policy before send;
- normalize immediate SMTP outcome;
- record enhanced status codes when available;
- apply temporary/permanent failure policy conservatively.

Limitations:

- `accepted` generally means accepted by the next SMTP hop, not delivered to inbox;
- asynchronous bounce/complaint information may be unavailable;
- provider quota may need manual configuration;
- reconciliation after uncertain send may be impossible.

UI must disclose these limitations.

---

## 4. Brevo adapter — v1 target

Initial mode direction:

```text
Outbound: existing OJS SMTP/Brevo SMTP OR supported Brevo route
Feedback: Brevo transactional webhooks
```

MailGuard should not force API-send mode merely to get feedback if SMTP + webhook correlation provides the required guarantees.

### Relevant Brevo transactional events

Normalize at minimum:

```text
sent/request        -> provider_sent
delivered           -> delivered
soft_bounce         -> soft_bounce
hard_bounce         -> hard_bounce
invalid             -> invalid_address
blocked             -> provider_block
spam                -> spam_complaint
deferred            -> provider_deferred
unsubscribed         -> provider_unsubscribe
error                -> provider_error
```

Open/click/proxy-open events are **not required** for MailGuard v1. Deliverability control must not depend on tracking opens.

### Correlation

Preferred correlation hierarchy:

1. MailGuard delivery/correlation ID in provider-supported custom metadata/header/tag;
2. provider message ID mapped from immediate send response if available;
3. recipient + bounded time + campaign metadata only as a last-resort reconciliation aid, never as primary identity.

Provider webhook payloads containing MailGuard correlation metadata must still pass authenticity verification.

---

## 5. Quota behavior

Provider quotas are configuration, not eternal constants.

The Brevo free-plan example that motivated the product currently uses a 300-send daily allowance, but MailGuard must never hard-code `300` into its Brevo adapter. Plans and provider policies change.

Configuration sources may be:

- administrator-entered limit;
- provider API when available and trustworthy;
- product preset presented as a convenience but stored as editable configuration.

The provider profile records where a limit came from and when it was last confirmed.

---

## 6. Webhook endpoint design

Conceptual route:

```text
/mailguard/webhooks/{provider}/{endpointToken?}
```

Exact OJS route is adapter/framework-dependent.

Requirements:

- POST only unless provider requires otherwise;
- HTTPS requirement documented;
- bounded request body size;
- content type validation where possible;
- provider authenticity verification;
- replay fingerprint before business mutation;
- immediate `2xx` on already-processed valid duplicate;
- avoid expensive synchronous work;
- sanitized logging;
- rate-limit abuse without blocking legitimate provider retry bursts.

An opaque endpoint token can be defense-in-depth but does not replace cryptographic/provider authentication where available.

---

## 7. Authenticity and replay

Each provider adapter must document how webhook authenticity is established.

Possible mechanisms:

- signature header and shared secret;
- basic/custom auth configured at webhook creation;
- provider IP ranges only as supplemental control;
- opaque endpoint secret only when provider offers no better mechanism.

MailGuard must not claim “verified” when only an unguessable URL is used.

Replay key should prefer a provider event ID. If provider IDs are not unique per event, compute a stable fingerprint from normalized immutable event attributes plus provider identity.

---

## 8. Normalized event schema

All providers map to:

```text
provider_profile_id
provider_event_id
fingerprint
normalized_type
provider_type
provider_message_id
mailguard_delivery_id (if correlated)
recipient_email_hash
provider_event_at
received_at
reason_code
safe_metadata
verification_status
```

Core suppression policy reads `normalized_type`, not provider-specific `event` strings.

---

## 9. Provider feedback -> policy

Default direction:

| Normalized event | Delivery impact | Suppression impact |
|---|---|---|
| `delivered` | mark feedback delivered | none |
| `soft_bounce` | record; maybe retry/threshold | threshold-based temporary suppression |
| `hard_bounce` | terminal where correlated | active hard-bounce suppression |
| `invalid_address` | terminal | active invalid-address suppression |
| `spam_complaint` | record terminal optional-mail failure | strong complaint suppression |
| `provider_block` | route/provider-dependent | provider-scoped or stronger based on evidence |
| `provider_deferred` | temporary | backoff, no permanent suppression |
| `provider_unsubscribe` | update optional preference where mapping is trustworthy | preference, not automatically physical hard suppression |
| `provider_error` | classify from detail | depends on normalized reason |

Exact mappings require provider fixtures/tests.

---

## 10. One-click unsubscribe headers

For subscription mail, MailGuard must ensure standards-compliant behavior equivalent to:

```text
List-Unsubscribe: <https://journal.example/...signed-token...>
List-Unsubscribe-Post: List-Unsubscribe=One-Click
```

The endpoint accepts the standards-defined POST without requiring authentication cookies.

MailGuard must interoperate with PKP's existing unsubscribe trait and avoid duplicated/conflicting headers.

The visible body still includes an unsubscribe/preference link because one-click headers do not replace a human-visible preference path.

---

## 11. Sender authentication diagnostics

MailGuard does not manage DNS in v1, but diagnostics may help administrators understand missing prerequisites.

Possible checks/instructions:

- configured From domain;
- SPF informational check (future/network-capable diagnostic);
- DKIM/provider configuration status where provider API exposes it;
- DMARC informational status;
- From vs authenticated sender alignment warning.

These are advisory unless a provider makes them mandatory for sending.

---

## 12. Multiple providers / route policy

v1 architecture should not prevent multiple provider profiles even if the first UI supports one active route.

Future routing may allow:

```text
critical/workflow -> Provider A
bulk/subscription -> Provider B
```

or failover.

Failover is dangerous for idempotency because a first provider may have accepted before failing locally. Automatic cross-provider retry is therefore **not** a casual v1 feature. It needs reconciliation rules and explicit provider idempotency evidence.

---

## 13. Provider health

Health model:

```text
healthy
warning
degraded
unavailable
unknown
```

Signals:

- authentication test;
- recent transport failures;
- recent webhook freshness;
- deferred/rate-limit spike;
- configuration validation;
- provider API health if available.

Do not infer that “no webhook recently” is unhealthy when no mail was sent. Health logic must consider expected activity.

---

## 14. Provider configuration changes

Changing credentials, provider, quota, or route is audited.

Queued deliveries should retain policy identity but may use the currently active route according to an explicit migration rule. Do not silently reroute uncertain/in-flight deliveries.

Provider removal must be blocked while unreconciled deliveries depend on it unless administrator acknowledges the consequence.

---

## 15. Webhook testing

Required fixtures per provider:

- valid delivered;
- valid hard bounce;
- valid soft bounce;
- invalid address;
- blocked;
- spam complaint;
- unsubscribe;
- deferred;
- malformed payload;
- forged authentication;
- duplicate/replayed event;
- unknown message ID;
- known recipient but no correlation ID;
- batched webhook payload if provider supports batching.

---

## 16. Brevo-specific acceptance scenarios

1. A hard-bounce webhook correlated by MailGuard metadata creates one suppression and remains idempotent on replay.
2. A spam event does not disable unrelated essential mail by merely writing a generic OJS preference row.
3. An unsubscribe event updates the intended optional preference scope only when campaign/category correlation is known.
4. A deferred event imposes retry/backoff, not a permanent invalid-address suppression.
5. MailGuard can operate with a configured 300/day example limit without assuming Brevo itself will safely queue the overflow.
6. If the administrator changes the configured plan limit, scheduler behavior changes without code changes.
