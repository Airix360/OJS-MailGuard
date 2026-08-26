# OJS MailGuard

**Status:** Product planning / specification

OJS MailGuard is a proposed outbound email control plane for Open Journal Systems (OJS). It is intended to govern how OJS-generated email is classified, queued, deduplicated, rate-limited, delivered, retried, suppressed, audited, and unsubscribed without replacing OJS's native editorial workflows or email-template system.

This repository is intentionally starting with a Product Bible before runtime implementation.

## Planning rule

No production plugin code should be implemented until the Product Bible defines and freezes the initial architecture, compatibility targets, policy model, data model, security boundaries, queue semantics, provider contract, unsubscribe behavior, test gates, and v1 scope.

The detailed Product Bible will be developed on a dedicated planning branch.
