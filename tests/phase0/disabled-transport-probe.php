<?php

/**
 * Prove disabling MailGuard restores the native OJS transport path.
 * Executed in a fresh PHP process after the main Phase 0 runtime probe.
 */

declare(strict_types=1);

$ojsRoot = dirname(__DIR__, 5);
require $ojsRoot . '/tools/bootstrap.php';

use APP\core\Application;
use APP\facades\Repo;
use APP\jobs\notifications\IssuePublishedNotifyUsers;
use Illuminate\Support\Facades\DB;
use PKP\context\Context;
use PKP\facades\Locale;
use APP\plugins\generic\mailGuard\MailGuardPlugin;

function disabledProbeFail(string $message): never
{
    fwrite(STDERR, "::error::[MailGuard Phase 0 disabled probe] {$message}\n");
    exit(1);
}

$pluginPath = 'plugins/generic/mailGuard';
/** @var MailGuardPlugin $plugin */
$plugin = require $ojsRoot . '/' . $pluginPath . '/index.php';

// Disable before registration in this fresh process. Therefore the plugin's
// build hook and cancellable MessageSendingFromContext listener are not attached.
$plugin->setEnabled(false);
if (!$plugin->register('generic', $pluginPath, Application::SITE_CONTEXT_ID)) {
    disabledProbeFail('disabled plugin shell did not register');
}

$contextId = (int) DB::table('journals')->orderBy('journal_id')->value('journal_id');
$issueId = (int) DB::table('issues')->where('journal_id', $contextId)->orderBy('issue_id')->value('issue_id');
$recipientId = (int) DB::table('users')->where('disabled', 0)->whereNotNull('email')->orderBy('user_id')->value('user_id');
$context = Application::getContextDAO()->getById($contextId);
$issue = Repo::issue()->get($issueId);
$recipient = Repo::user()->get($recipientId);

if (!$context instanceof Context || !$issue || !$recipient) {
    disabledProbeFail('prepared OJS fixture could not be resolved');
}

$spoolBefore = DB::table(MailGuardPlugin::SPOOL_TABLE)->count();

// Exercise the actual controlled OJS path while MailGuard is disabled.
$job = new IssuePublishedNotifyUsers(
    collect([$recipientId]),
    $contextId,
    $issue,
    Locale::getLocale(),
    $recipient
);
$job->handle();

$spoolAfter = DB::table(MailGuardPlugin::SPOOL_TABLE)->count();
if ($spoolAfter !== $spoolBefore) {
    disabledProbeFail('disabled plugin unexpectedly captured a real issue notification email');
}

fwrite(STDOUT, "[PASS] disabled MailGuard leaves real OJS issue mail on native transport\n");
fwrite(STDOUT, "MAILGUARD_PHASE0_DISABLED_TRANSPORT_PASS\n");
