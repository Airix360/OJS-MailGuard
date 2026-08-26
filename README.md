# OJS MailGuard

**Status:** Product Bible review candidate — PLAN ONLY

OJS MailGuard is a proposed outbound email control plane for Open Journal Systems (OJS). It is intended to govern how OJS-generated email is classified, queued, deduplicated, rate-limited, delivered, retried, suppressed, audited, and unsubscribed without replacing OJS's native editorial workflows or email-template system.

The motivating first integration is **new-issue publication email**, but the architecture is designed as an OJS-wide mail policy and delivery layer.

## Product Bible

The implementation specification is in [`docs/product-bible/`](docs/product-bible/README.md).

It covers:

- product scope and v1 boundaries;
- interception/control-plane architecture;
- mail classes, priority, deduplication, idempotency, cooldown and quota policy;
- durable queue/retry semantics;
- context-safe preferences and unsubscribe behavior;
- suppression/bounce/complaint handling;
- generic SMTP and Brevo provider direction;
- provider webhooks and delivery feedback;
- site/journal administration UX;
- persistence, encryption, security, privacy and retention;
- OJS compatibility adapters;
- test/release gates;
- phased roadmap, decisions, risks and upstream evidence.

## Planning rule

No production feature implementation should begin merely because the Product Bible exists.

The next permitted engineering stage is **Phase 0 integration proof** after the plan is reviewed and frozen. Phase 0 is limited to minimal technical spikes that prove interception, new-issue capture, queue recovery/concurrency, disable/bypass behavior and encryption against real OJS versions.

No runtime plugin has been implemented, deployed or released from this planning branch.