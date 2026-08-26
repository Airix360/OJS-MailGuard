# OJS MailGuard — Upstream Evidence

**Purpose:** capture the specific OJS/PKP behavior this Product Bible is designed around so implementation does not drift into assumptions.

This file is evidence, not a substitute for Phase 0 runtime proof. Upstream code can change between OJS branches and releases; the adapter layer exists because of that.

---

## 1. New-issue publication path

Current OJS `main` publishes issue notifications through `IssueGridHandler::publishIssue()`.

Observed behavior:

1. It checks `sendIssueNotification` and publishing mode.
2. It asks `NotificationSubscriptionSettingsDAO::getSubscribedUserIds()` for users eligible for notification and email.
3. It computes notification-only vs notification-plus-email recipients.
4. It chunks notification recipients by the notification chunk limit.
5. It chunks email recipients by `Mailer::BULK_EMAIL_SIZE_LIMIT`.
6. It dispatches `IssuePublishedNotifyUsers` jobs in a Laravel bus batch.

`IssuePublishedNotifyUsers::handle()` then:

1. loads the context and email template;
2. resolves each recipient by user ID;
3. creates the in-app published-issue notification;
4. when a sender is present, creates an `IssuePublishedNotify` mailable;
5. applies the locale; and
6. calls `Mail::send($mailable)`.

### Planning consequence

Native job chunking is **not** equivalent to MailGuard delivery control. It does not itself provide cross-campaign provider quotas, protected capacity for critical mail, address-level deduplication, durable campaign idempotency, bounce suppression, global pause, or recipient-history policy.

MailGuard must preserve creation of the native in-app notification while preventing a controlled bulk email from escaping through both the native path and MailGuard.

---

## 2. Recipient uniqueness behavior

`NotificationSubscriptionSettingsDAO::getSubscribedUserIds()` selects from `users` and uses `whereExists()` for active user-group membership. This means role membership does not create a join fan-out of duplicate user rows in the result.

### Planning consequence

The native recipient identity for this path is effectively `user_id`, not normalized email address.

Two OJS accounts that resolve to the same mailbox can therefore still represent two native delivery attempts. MailGuard's subscription/bulk policy must deduplicate at the normalized delivery-identity level while retaining the contributing OJS user identities for audit/explanation.

This rule is intentionally **not universal** for account/workflow mail because two distinct accounts can legitimately require distinct transactional messages even when their mailbox is shared.

---

## 3. Context-scoping weakness in native notification opt-outs

The current DAO method excludes blocked users in a `whereNotIn` subquery based on `setting_name`, `setting_value` and `user_id`; that exclusion does not constrain the blocked setting by `context_id`.

PKP issue `pkp-lib#12769`, opened 2026-05-19 and still open when this plan was prepared, describes the same defect: opting out in one context can exclude the user in another context.

### Planning consequence

MailGuard must implement a context-safe preference authority for the optional mail categories it governs.

Native OJS notification preferences remain an interoperability input, not the sole authoritative source for MailGuard recipient eligibility until upstream context behavior is proven correct on a supported release.

The plugin must never broaden consent because of an interoperability ambiguity. Ambiguous preference migration/import should fail conservatively and be auditable.

---

## 4. Generic mailable build seam

`PKP\mail\Mailable::build()` invokes the plugin hook:

`Mailable::build`

with the current mailable instance.

The base class also exposes mailable variables and view data and has explicit support for automated footer behavior.

### Planning consequence

This is a useful **classification/decoration observation seam** for supported versions. It can help MailGuard attach policy metadata, template variables, diagnostics and headers without editing every OJS template.

It must not be assumed, without Phase 0 proof, that this hook alone can provide durable pre-send capture or safely prevent the original transport from sending. Those are separate requirements.

---

## 5. Native unsubscribe support already exists

PKP's `mail/traits/Unsubscribe.php` can:

- add an `unsubscribeUrl` template variable;
- append a localized unsubscribe footer for opted-in mailables; and
- emit `List-Unsubscribe-Post: List-Unsubscribe=One-Click` plus `List-Unsubscribe: <...>` headers.

The new-issue mailable is created with `allowUnsubscribe($notification)` in `IssuePublishedNotifyUsers`.

### Planning consequence

MailGuard must **interoperate with and extend**, not blindly duplicate, native unsubscribe behavior.

For MailGuard-governed subscription mail, the product contract is richer than a single notification opt-out: context-safe category preferences, preference centre, stable signed actions, audit trail and provider-unsubscribe feedback. Where native unsubscribe semantics already satisfy a use case safely, MailGuard should reuse them or bridge them rather than create two conflicting links.

---

## 6. OJS version reality

Upstream OJS maintains multiple stable branches including `stable-3_4_0` and `stable-3_5_0`, while active development continues on `main` toward the next line.

Mail and notification implementation details have changed across OJS/PKP versions.

### Planning consequence

MailGuard is split into:

- a version-independent policy/data/domain core; and
- explicit OJS compatibility adapters that own version-sensitive hooks and producer interception.

The Product Bible currently treats OJS 3.5.x as the first-class v1 target. OJS 3.6 is tracked through its own adapter/test lane. OJS 3.4 support remains conditional on Phase 0 evidence rather than being promised prematurely.

---

## 7. Evidence references

Primary upstream references used during planning:

- `pkp/ojs` — `classes/controllers/grid/issues/IssueGridHandler.php`
- `pkp/ojs` — `jobs/notifications/IssuePublishedNotifyUsers.php`
- `pkp/pkp-lib` — `classes/notification/NotificationSubscriptionSettingsDAO.php`
- `pkp/pkp-lib` — `classes/mail/Mailable.php`
- `pkp/pkp-lib` — `classes/mail/traits/Unsubscribe.php`
- `pkp/pkp-lib#12769` — context-specific notification opt-out bug

Before Phase 0 starts, the exact tested OJS tags/commits must be recorded in spike evidence. Branch-head observations in this document are not sufficient to certify compatibility.

---

## 8. Facts not yet proven

The following remain Phase 0 questions and must not be represented as solved implementation facts:

- the safest supported way to prevent native transport escape for controlled mail without patching core;
- whether one transport interception strategy works unchanged across all target OJS versions;
- the precise queue/scheduler primitive to use for MailGuard's durable spool;
- the exact framework encryption API/key lifecycle that is safe to bind queued recipient snapshots to;
- safe plugin-disable behavior when controlled deliveries remain queued;
- exact crash boundary semantics around provider acceptance vs local success recording.

These uncertainties are deliberate inputs to Phase 0, not permission to begin broad feature implementation.