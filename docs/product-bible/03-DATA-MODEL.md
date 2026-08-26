# OJS MailGuard — Data Model

**State:** PLAN  
**Depends on:** `00-PRODUCT-BIBLE.md`, `01-ARCHITECTURE.md`, `02-MAIL-POLICY.md`

---

## 1. Goals

The data model must make correctness survive retries, concurrent workers, OJS restarts, provider outages, and administrator actions.

Key requirements:

- durable campaign/delivery identity;
- context-safe preferences;
- typed suppressions;
- immutable attempt history;
- provider-event idempotency;
- quota accounting;
- auditability;
- minimal and time-bounded retention of recipient/message content;
- no correctness dependency on transient in-memory state.

Prefix shown below is conceptual. Final table prefix must follow OJS plugin/database conventions.

---

## 2. Entity overview

```text
mg_mail_types
mg_policies
mg_provider_profiles
mg_campaigns
mg_deliveries
mg_delivery_accounts
mg_attempts
mg_preferences
mg_suppressions
mg_provider_events
mg_quota_buckets
mg_audit_events
mg_system_state
```

Optional later tables:

```text
mg_rendered_payloads
mg_domain_policies
mg_digest_memberships
```

---

## 3. `mg_mail_types`

Registry snapshot/overrides for discovered and registered mailable types.

Proposed fields:

- `id`
- `type_key` — stable unique MailGuard key
- `source_class`
- `source_adapter`
- `mail_class`
- `priority`
- `campaignable`
- `unsubscribe_mode`
- `dedup_mode`
- `retry_profile`
- `quota_class`
- `native_notification_behavior`
- `enabled`
- `first_seen_at`
- `last_seen_at`
- `metadata_json`
- timestamps

Unique:

- `type_key`

Index:

- `source_class`

Code registrations remain source-of-truth for built-in types; table state supports discovery, diagnostics, and approved overrides.

---

## 4. `mg_policies`

Versioned policy documents/settings.

Fields:

- `id`
- `scope_type` (`site`, `context`, `provider`, `mail_type`)
- `scope_id` nullable where site/global
- `policy_key`
- `policy_version`
- `config_json`
- `active_from`
- `retired_at`
- `created_by`
- timestamps

Do not mutate policy history in place once campaigns reference a version. New configuration creates a new version where the setting materially affects delivery reproducibility.

---

## 5. `mg_provider_profiles`

Provider/transport configuration metadata.

Fields direction:

- `id`
- `provider_key`
- `name`
- `adapter_class`
- `enabled`
- `transport_mode`
- encrypted credential/config reference or encrypted secret fields
- `quota_config_json`
- `webhook_config_json`
- `health_status`
- `last_health_check_at`
- timestamps

Secrets must not be stored in plain JSON if avoidable. Exact use of OJS/Laravel encryption is dependent on the encryption technical spike.

---

## 6. `mg_campaigns`

One logical bulk communication generation.

Fields:

- `id`
- `public_id` UUID/ULID-like non-sequential identifier
- `context_id`
- `type_key`
- `source_assoc_type`
- `source_assoc_id`
- `source_event_key`
- `generation`
- `idempotency_key`
- `status`
- `initiated_by_user_id`
- `sender_user_id` nullable
- `locale`
- `policy_snapshot_json` or policy-version references
- `intended_count`
- `eligible_count`
- `deduplicated_count`
- `suppressed_count`
- `queued_count`
- `accepted_count`
- `failed_count`
- `cancelled_count`
- `started_at`
- `completed_at`
- timestamps

Unique:

```text
(context_id, type_key, source_event_key, generation)
idempotency_key
```

Counts are cached operational summaries; correctness must come from delivery rows and periodic reconciliation.

---

## 7. `mg_deliveries`

One logical delivery intent.

Fields:

- `id`
- `public_id`
- `campaign_id` nullable for non-campaign mail
- `context_id`
- `type_key`
- `logical_event_key`
- `idempotency_key`
- `canonical_user_id` nullable
- `recipient_email_hash`
- `recipient_email_ciphertext` or secure snapshot reference
- `recipient_email_masked`
- `locale`
- `priority`
- `state`
- `reason_code`
- `provider_profile_id`
- `provider_route_key` nullable
- `earliest_send_at`
- `expires_at`
- `claimed_at`
- `claim_token` nullable
- `claim_expires_at`
- `attempt_count`
- `accepted_at`
- `last_attempt_at`
- `provider_message_id` nullable
- `delivered_feedback_at` nullable
- `payload_strategy`
- `payload_reference` nullable
- timestamps

Unique:

- `idempotency_key`

Campaign subscription dedup can additionally enforce a unique key equivalent to:

```text
(campaign_id, recipient_email_hash)
```

where applicable.

### Email equality and privacy

Preferred design:

- encrypted recipient snapshot for actual delayed delivery;
- keyed HMAC of conservative normalized email for equality/dedup indexes;
- masked display value for routine UI;
- never use unsalted plain SHA of email as the only privacy protection because email address spaces are enumerable.

The HMAC key lifecycle must be defined with the encryption strategy.

---

## 8. `mg_delivery_accounts`

Maps all OJS accounts that contributed to one canonical subscription delivery.

Fields:

- `delivery_id`
- `user_id`
- `is_canonical`
- `source_role/context metadata` if needed
- timestamps

Unique:

- `(delivery_id, user_id)`

Purpose:

- preserve traceability when duplicate addresses are collapsed;
- explain why a user did/did not receive a separate email;
- avoid stuffing account arrays into unqueryable JSON.

---

## 9. `mg_attempts`

Immutable transport attempt records.

Fields:

- `id`
- `delivery_id`
- `attempt_number`
- `provider_profile_id`
- `provider_request_id` nullable
- `provider_message_id` nullable
- `started_at`
- `finished_at`
- `result_type`
- `response_code` nullable
- `enhanced_status_code` nullable
- `retry_after_at` nullable
- `failure_reason_code` nullable
- `safe_response_excerpt` nullable
- `worker_correlation_id`
- `metadata_json`

Unique:

- `(delivery_id, attempt_number)`

Avoid storing raw provider responses if they can contain message body, secrets, or unnecessary PII.

---

## 10. `mg_preferences`

MailGuard's context-safe optional-email preferences.

Fields:

- `id`
- `user_id` nullable when an email-token-only identity must be supported later
- `recipient_email_hash` nullable
- `context_id` nullable only for explicit site-wide preference
- `category_key`
- `type_key` nullable for category-level setting
- `value` (`subscribed`, `unsubscribed`)
- `source` (`preference_center`, `one_click`, `admin`, `native_import`, `migration`)
- `source_event_id` nullable
- `changed_by_user_id` nullable
- `changed_at`
- timestamps

Unique policy should prevent multiple simultaneously active values for the same identity/scope/type.

A history/audit record captures prior value; the live table represents current resolution state.

---

## 11. `mg_suppressions`

Typed delivery-health/policy suppressions.

Fields:

- `id`
- `recipient_email_hash`
- encrypted/masked address metadata as required
- `scope_type` (`global`, `site`, `context`, `provider`, `category`)
- `scope_id` nullable
- `reason`
- `severity`
- `status` (`active`, `expired`, `restored`)
- `source` (`provider_webhook`, `smtp_result`, `admin`, `migration`)
- `source_provider_profile_id` nullable
- `source_event_id` nullable
- `first_seen_at`
- `last_seen_at`
- `expires_at` nullable
- `restored_at` nullable
- `restored_by_user_id` nullable
- `restore_reason` nullable
- timestamps

Indexes:

- `(recipient_email_hash, status)`
- `(scope_type, scope_id, status)`
- `reason`

Do not overload preferences into suppressions. An unsubscribe is principally preference state; a provider spam complaint can create a stronger suppression according to policy.

---

## 12. `mg_provider_events`

Raw-normalized provider feedback envelope with replay protection.

Fields:

- `id`
- `provider_profile_id`
- `provider_event_id` nullable
- `event_fingerprint`
- `event_type_normalized`
- `provider_event_type`
- `provider_message_id` nullable
- `recipient_email_hash` nullable
- `delivery_id` nullable
- `received_at`
- `provider_event_at` nullable
- `verification_status`
- `processing_status`
- `reason_code` nullable
- minimal sanitized `metadata_json`
- timestamps

Unique:

- `(provider_profile_id, event_fingerprint)`

Raw full webhook bodies should not be retained indefinitely by default. If diagnostic retention is offered, it must be short, access-controlled, and secret/PII-aware.

---

## 13. `mg_quota_buckets`

Durable accounting for capacity windows.

Fields:

- `id`
- `provider_profile_id`
- `scope_type`
- `scope_id` nullable
- `quota_key`
- `window_start`
- `window_end`
- `limit_value`
- `reserved_value`
- `consumed_value`
- `held_value` or reservation count if needed for concurrency
- timestamps

Unique:

```text
(provider_profile_id, scope_type, scope_id, quota_key, window_start)
```

Quota claim/release must be concurrency-safe. Implementation may use atomic update/transaction/lock semantics rather than naïve read-then-write.

---

## 14. `mg_audit_events`

Human/accountability audit trail for material state changes.

Fields:

- `id`
- `actor_user_id` nullable for system/provider
- `actor_type`
- `context_id` nullable
- `event_key`
- `subject_type`
- `subject_id`
- `before_json` sanitized
- `after_json` sanitized
- `reason` nullable
- `ip_hash_or_metadata` only if justified by privacy policy
- `created_at`

Examples:

- provider configuration changed;
- site quota changed;
- bulk paused/resumed;
- suppression created/restored manually;
- campaign cancelled/resend initiated;
- preference changed by admin;
- MailGuard mode changed.

---

## 15. `mg_system_state`

Small keyed state table for runtime control and diagnostics.

Examples:

- current MailGuard operational mode;
- last scheduler heartbeat;
- last successful provider webhook;
- active compatibility adapter;
- last reconciliation timestamps.

Do not use this table for high-volume delivery events.

---

## 16. Rendered payload storage

Not frozen until architecture spike.

If `mg_rendered_payloads` is required, fields should include:

- `delivery_id`
- encrypted subject/body/headers payload
- content hash
- template key/version/source reference
- rendered_at
- purge_after

Attachments should not be copied into the database by default. Store stable references where safe or use bounded encrypted spool storage with cleanup guarantees.

---

## 17. Retention policy direction

Retention must be configurable but have safe defaults.

Proposed categories:

### Long-lived

- active preferences;
- active suppressions;
- minimal restoration history;
- policy/version records required to explain past behavior.

### Medium-lived

- campaign summaries;
- delivery metadata without rendered body;
- attempt summaries;
- audit events.

### Short-lived

- rendered message payload/body snapshot;
- raw/sanitized webhook envelope;
- verbose provider response excerpts.

Example proposal, not frozen:

```text
rendered body/payload:       30 days after terminal state
provider raw diagnostic:      7 days
attempt/delivery metadata:  365 days
campaign summary:           730 days
preferences/suppressions:   while active + policy-defined history
```

Administrators should be able to choose stricter retention within operational requirements.

---

## 18. Deletion/anonymization behavior

When an OJS user is deleted/anonymized:

- active preferences tied only to user ID must be reconciled;
- address-level hard-bounce suppression may need to remain because it protects sender reputation independently of account existence;
- campaign/delivery records should detach or anonymize user references according to retention policy while preserving non-identifying operational counts;
- audit integrity should be maintained without retaining unnecessary recipient content.

This needs an explicit OJS user-deletion hook/adaptor test.

---

## 19. Multi-context boundaries

Every object whose meaning can differ by journal includes `context_id` explicitly instead of deriving it later from mutable relationships.

Particularly:

- campaigns;
- deliveries;
- preferences;
- context-scoped suppressions;
- policy snapshots;
- audit events.

A missing context on context-required mail is a policy error, not “site default journal.”

---

## 20. Migration philosophy

- additive migrations first;
- no destructive column drop in the same release that deprecates a field;
- migrations idempotent under OJS plugin upgrade expectations;
- large backfills chunked/scheduled where necessary;
- indexes created with supported DB engines in mind;
- support MySQL/MariaDB and PostgreSQL according to OJS supported database matrix;
- no SQLite-only assumptions;
- migration tests against each advertised DB family.

---

## 21. Uninstall behavior

Uninstall is destructive and must require explicit confirmation.

Before deleting MailGuard tables, UI must state that uninstalling removes:

- queue state;
- campaign history;
- MailGuard preferences;
- suppression registry;
- provider event history;
- audit history.

A safer **disable** path must exist and is different from uninstall.

Optional export of preferences/suppressions before uninstall should be considered for v1.x.

---

## 22. Data-model acceptance gate

Implementation cannot freeze migrations until tests/specs prove:

- one campaign generation is unique under concurrency;
- one subscription delivery per normalized address/campaign is database-enforced;
- provider webhook replay is database-idempotent;
- quota claim is concurrency-safe;
- context preferences cannot collide across journals;
- encrypted recipient snapshots can be decrypted through the intended lifecycle;
- retention purge cannot delete state required by active queued mail;
- user deletion/anonymization behavior is defined.
