#!/usr/bin/env bash
set -euo pipefail

fail() {
  echo "::error::$*" >&2
  exit 1
}

fetch() {
  local repo="$1" branch="$2" path="$3"
  curl -fsSL "https://raw.githubusercontent.com/${repo}/${branch}/${path}"
}

verify_mailer_interception() {
  local branch="$1"
  echo "Checking cancellable pre-send seam on pkp-lib:${branch}"
  local mailer
  mailer="$(fetch pkp/pkp-lib "$branch" classes/mail/Mailer.php)"
  grep -Fq -- 'new MessageSendingFromContext' <<<"$mailer" || fail "${branch}: MessageSendingFromContext missing"
  grep -Fq -- '->until(new MessageSendingFromContext' <<<"$mailer" || fail "${branch}: Dispatcher::until context send gate missing"
  grep -Fq -- '!== false' <<<"$mailer" || fail "${branch}: false-return cancellation contract missing"
}

verify_mailable_metadata_handoff() {
  local branch="$1"
  echo "Checking Mailable build/object-metadata handoff contracts on pkp-lib:${branch}"

  local mailable event mailer style_trait
  mailable="$(fetch pkp/pkp-lib "$branch" classes/mail/Mailable.php)"
  event="$(fetch pkp/pkp-lib "$branch" classes/observers/events/MessageSendingFromContext.php)"
  mailer="$(fetch pkp/pkp-lib "$branch" classes/mail/Mailer.php)"
  style_trait="$(fetch pkp/pkp-lib "$branch" classes/mail/traits/AddsStyleToSymfonyMessage.php)"

  grep -Fq -- "Hook::run('Mailable::build'" <<<"$mailable" || fail "${branch}: literal Mailable::build hook missing"
  grep -Fq -- 'withSymfonyMessage' <<<"$style_trait" || fail "${branch}: withSymfonyMessage callback integration missing"
  grep -Fq -- 'public function __construct(Context $context, SymfonyEmail $message, array $data = [])' <<<"$event" || fail "${branch}: MessageSendingFromContext constructor shape changed"

  # PKP currently constructs this event with only context + Symfony message.
  # Therefore MailGuard must not depend on inherited Illuminate event data for
  # classification; it uses process-local metadata keyed by the Email object.
  grep -Fq -- 'new MessageSendingFromContext($context, $message)' <<<"$mailer" || fail "${branch}: pre-send event construction contract changed; re-review metadata handoff"
}

verify_issue_path() {
  local branch="$1"
  echo "Checking new-issue mailable path and stable issue identity on ojs:${branch}"
  local job issue_variable
  job="$(fetch pkp/ojs "$branch" jobs/notifications/IssuePublishedNotifyUsers.php)"
  issue_variable="$(fetch pkp/ojs "$branch" classes/mail/variables/IssueEmailVariable.php)"

  grep -Fq -- 'IssuePublishedNotify' <<<"$job" || fail "${branch}: IssuePublishedNotify mailable missing"
  grep -Fq -- 'createNotification(' <<<"$job" || fail "${branch}: native issue notification creation missing"
  grep -Fq -- 'Mail::send($mailable)' <<<"$job" || fail "${branch}: expected mailable send call missing"
  grep -Fq -- 'allowUnsubscribe' <<<"$job" || fail "${branch}: native unsubscribe integration missing"
  grep -Fq -- "public const ISSUE_ID = 'issueId'" <<<"$issue_variable" || fail "${branch}: stable issueId variable missing"
  grep -Fq -- 'static::ISSUE_ID => $this->issue->getId()' <<<"$issue_variable" || fail "${branch}: issueId no longer resolves from native Issue identity"
}

verify_scheduler() {
  local branch="$1"
  echo "Checking plugin scheduler contract on pkp-lib:${branch}"
  local scheduler
  scheduler="$(fetch pkp/pkp-lib "$branch" classes/scheduledTask/PKPScheduler.php)"
  grep -Fq -- 'HasTaskScheduler' <<<"$scheduler" || fail "${branch}: HasTaskScheduler integration missing"
  grep -Fq -- 'registerSchedules($this)' <<<"$scheduler" || fail "${branch}: plugin schedule registration missing"
}

verify_encryption() {
  local branch="$1"
  echo "Checking PKP encryption provider on pkp-lib:${branch}"
  local container
  container="$(fetch pkp/pkp-lib "$branch" classes/core/PKPContainer.php)"
  grep -Fq -- 'PKPEncryptionServiceProvider' <<<"$container" || fail "${branch}: PKP encryption provider is not registered"
}

for branch in stable-3_4_0 stable-3_5_0 main; do
  verify_mailer_interception "$branch"
done

for branch in stable-3_5_0 main; do
  verify_mailable_metadata_handoff "$branch"
  verify_issue_path "$branch"
  verify_scheduler "$branch"
  verify_encryption "$branch"
done

# 3.4 deliberately remains outside the Phase-0 encryption guarantee. Its
# cancellable mail event is useful evidence, but MailGuard does not claim v1
# support until a secure queue-encryption strategy is separately proven there.
if curl -fsSL --output /dev/null \
  "https://raw.githubusercontent.com/pkp/pkp-lib/stable-3_4_0/classes/core/PKPEncryptionServiceProvider.php"; then
  echo "3.4 now exposes PKPEncryptionServiceProvider; compatibility evidence should be re-reviewed."
else
  echo "Expected: stable-3_4_0 has no PKPEncryptionServiceProvider file."
fi

echo "Phase 0 upstream source contracts verified."
