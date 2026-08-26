# OJS MailGuard — Test Strategy and Release Gates

**State:** PLAN

---

## 1. Testing philosophy

MailGuard protects email reputation and workflow delivery. “It sent an email in my browser” is not sufficient testing.

Testing must prove:

- correctness under retries and concurrency;
- policy precedence;
- multi-journal isolation;
- no duplicate native escape;
- durable queue recovery;
- provider-event idempotency;
- safe disable/upgrade/uninstall behavior;
- no regression to OJS in-app notification semantics.

---

## 2. Test layers

### Unit tests

Pure services:

- normalization;
- idempotency-key generation;
- preference resolution;
- suppression precedence;
- quota math;
- retry/backoff;
- state transition guards;
- reason-code mapping;
- token claim construction/validation;
- provider event normalization.

### Database/integration tests

- migrations;
- unique constraints;
- concurrent campaign creation;
- concurrent delivery claim;
- quota claim/update;
- preference context uniqueness;
- provider-event replay uniqueness;
- retention purge.

### OJS integration tests

- plugin bootstrap;
- permissions/routes;
- Mailable hook;
- issue publication adapter;
- native notification preservation;
- template merge variables/footer;
- scheduled task/worker behavior;
- plugin disable/enable.

### Provider contract tests

Use fixtures and mocked HTTP/webhook calls for:

- Brevo events;
- generic SMTP temporary/permanent responses;
- malformed/auth-invalid events;
- correlation behavior.

### End-to-end tests

Representative installed OJS instance:

```text
publish issue -> create campaign -> queue -> release -> fake provider -> webhook -> suppression/preferences/audit UI
```

### Upgrade tests

- install previous MailGuard -> seed queue/preferences/suppressions -> upgrade -> resume -> verify no duplicates/loss;
- OJS supported-version upgrade with MailGuard installed;
- unknown OJS version enters documented safe state.

---

## 3. Core fixture set

### Users

- U1 `reader-a@example.test`
- U2 `reader-b@example.test`
- U3 shares `reader-a@example.test` with U1
- U4 hard-bounced
- U5 opted out of Journal A new issues
- U6 opted out site-wide where enabled
- U7 multi-role account
- U8 disabled/inactive account as OJS supports

### Contexts

- Journal A
- Journal B

### Issues

- A Issue 1
- A Issue 2
- B Issue 1

### Providers

- fake SMTP healthy
- fake SMTP temporary failure
- fake SMTP permanent failure
- fake Brevo webhook adapter

---

## 4. Mandatory product scenarios

### Dedup

1. U1 and U3 share an address and are both subscribed to A Issue 1 -> one subscription delivery.
2. U1 receives two distinct workflow messages for different submissions -> two logical deliveries.
3. Case/domain normalization does not create duplicate optional deliveries.
4. Plus aliases remain distinct by default.

### Idempotency

5. Same issue event captured twice concurrently -> one campaign generation.
6. Same campaign worker executes twice -> same logical delivery rows.
7. Same delivery worker retries -> next attempt, not next delivery.
8. Explicit resend -> generation +1 and auditable new campaign.

### Context isolation

9. U5 unsubscribed from Journal A new issues -> suppressed in A only.
10. U5 remains eligible for Journal B new issues.
11. Journal A manager cannot change Journal B policy/suppressions.

### Provider capacity

12. 300/day provider limit + protected reserve -> bulk stops at allowed discretionary capacity.
13. P0/P1 can still release while P3 is held.
14. quota reset resumes queued bulk without duplicate.
15. provider `429` imposes backoff.

### Suppression

16. hard bounce -> active suppression.
17. same hard-bounce webhook replay -> one suppression/event effect.
18. soft bounce below threshold -> no permanent suppression.
19. threshold reached -> temporary/repeated-soft-bounce suppression.
20. spam complaint -> complaint policy.
21. manual restoration requires permission/reason.

### Unsubscribe/preferences

22. eligible subscription mail includes correct one-click semantics.
23. template lacking explicit MailGuard URL receives standard footer.
24. one-click new-issue unsubscribe changes only Journal A/new-issues scope.
25. repeat one-click POST is idempotent.
26. tampered token rejected without preference mutation.
27. Journal A token cannot mutate Journal B preference.

### Native OJS behavior

28. new issue publication still creates correct in-app notifications.
29. `send notification` unchecked -> no MailGuard email campaign.
30. publishing mode rule remains aligned with OJS semantics.
31. locale/template/sender values match native expected output.

### Queue recovery

32. worker dies before claim -> another worker can claim.
33. worker dies after lease -> lease expires/recovery works.
34. provider accepted but local result uncertain -> no blind duplicate retry.
35. pause bulk retains rows and resumes safely.
36. pause all retains rows and processes feedback safely.

### Security

37. forged provider webhook rejected.
38. oversized/malformed webhook rejected safely.
39. provider secret never returned by config API.
40. full unsubscribe token not written to normal logs.
41. unauthorized journal role cannot access site provider settings.

### Retention

42. purge removes expired rendered payload but leaves active queue intact.
43. active suppression survives delivery-history purge.
44. user deletion/anonymization follows documented behavior.

---

## 5. Concurrency tests

Use real database transactions against both supported DB families where possible.

Tests:

- N workers attempt same delivery claim;
- N requests create same campaign source/generation;
- quota remaining = 1, N workers attempt release;
- same webhook posted concurrently;
- pause occurs while workers are claiming;
- cancel campaign while deliveries in-flight.

Expected property: database invariants, not timing luck, decide correctness.

---

## 6. Property/fuzz-style tests

Useful for pure policy components:

- arbitrary sequence of delivery state transitions never reaches forbidden transition;
- dedup key stable for equivalent conservative-normalized addresses;
- context preference resolution never returns another context's explicit setting;
- token tampering invalidates signature;
- reason code always present for terminal non-success state.

---

## 7. Provider webhook fixtures

Store sanitized fixture JSON for every supported provider event type used by policy.

Each fixture documents:

- source provider docs/date;
- event type;
- fields required for correlation;
- expected normalized event;
- expected delivery transition;
- expected suppression/preference mutation.

Never depend exclusively on provider sandbox availability for CI.

---

## 8. Mail rendering tests

Snapshot/structural tests should verify:

- subject variables render;
- body variables render;
- MailGuard footer appears once;
- native unsubscribe footer and MailGuard footer do not duplicate;
- one-click headers appear once;
- non-subscription/essential mail does not acquire inappropriate unsubscribe footer;
- locale is correct;
- HTML and text behavior remain valid where OJS supports both;
- attachments remain attached when gateway path controls them.

Avoid brittle snapshots of every whitespace difference; assert meaningful semantics.

---

## 9. Performance tests

At minimum simulate campaigns of:

- 10 recipients
- 1,000 recipients
- 10,000 recipients
- 100,000 recipient identities where test infrastructure permits

Measure:

- recipient resolution time;
- queue creation memory;
- DB query count;
- scheduler selection time;
- index performance;
- suppression lookup cost;
- admin campaign-page performance.

No production promise of 100k until measured, but schema must avoid obvious O(N²) design.

---

## 10. Failure injection

Tests intentionally break:

- DB connection during queue creation;
- provider timeout;
- SMTP temporary/permanent errors;
- worker process after provider call;
- webhook queue worker;
- encryption key/config unavailable;
- invalid provider credentials;
- OJS task runner disabled;
- MailGuard plugin disabled with queued deliveries.

Each failure must have visible, documented outcome.

---

## 11. Test environments

Per advertised OJS adapter:

```text
OJS version x DB family x supported PHP runtime
```

Initial matrix should include:

- OJS 3.5 latest + MySQL/MariaDB;
- OJS 3.5 latest + PostgreSQL;
- OJS 3.6 target + MySQL/MariaDB;
- OJS 3.6 target + PostgreSQL;
- 3.4 only if support decision is positive.

CI may optimize matrix frequency, but release candidate validation runs the full supported matrix.

---

## 12. Release gates

### BASELINE

- install/upgrade migrations pass;
- unit suite pass;
- static/lint standards pass;
- no known critical security defect.

### INTEGRATION PASS

- all mandatory OJS integration scenarios pass;
- both DB families pass;
- provider fixtures pass;
- no duplicate native new-issue email escape.

### FULL TEST PASS

- concurrency tests pass;
- failure injection pass;
- upgrade/disable tests pass;
- end-to-end provider simulation pass;
- retention/security checks pass.

### RELEASE READY

- full test pass on clean install and upgrade path;
- compatibility matrix updated;
- docs match behavior;
- changelog/version updated;
- distributable plugin package installs cleanly;
- checksum/artifact produced;
- no unresolved blocker risk;
- release candidate tested before GitHub release/tag.

---

## 13. No-fake-pass rule

A skipped test for an advertised capability is not a pass.

If a provider sandbox or OJS branch cannot be tested, release notes/compatibility state must say `unverified` rather than treating absence of evidence as success.
