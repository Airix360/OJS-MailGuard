<?php

/**
 * Failure injection: prove MailGuard fails open when durable capture is
 * unavailable. The test temporarily renames the spool table, executes the real
 * OJS new-issue producer, then restores the table. Native SMTP must still work.
 */

declare(strict_types=1);

$ojsRoot = dirname(__DIR__, 5);
require $ojsRoot . '/tools/bootstrap.php';

use APP\core\Application;
use APP\facades\Repo;
use APP\jobs\notifications\IssuePublishedNotifyUsers;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PKP\context\Context;
use PKP\facades\Locale;
use APP\plugins\generic\mailGuard\MailGuardPlugin;
use Throwable;

function failOpenProbeFail(string $message): never
{
    fwrite(STDERR, "::error::[MailGuard Phase 0 fail-open probe] {$message}\n");
    exit(1);
}

$pluginPath = 'plugins/generic/mailGuard';
/** @var MailGuardPlugin $plugin */
$plugin = require $ojsRoot . '/' . $pluginPath . '/index.php';

// The preceding runtime probe leaves the site plugin enabled. Registration in
// this fresh process must therefore attach the real interception listener.
if (!$plugin->register('generic', $pluginPath, Application::SITE_CONTEXT_ID)) {
    failOpenProbeFail('enabled plugin shell did not register');
}
if (!$plugin->getEnabled(Application::SITE_CONTEXT_ID)) {
    failOpenProbeFail('plugin was expected to remain enabled before failure injection');
}

$contextId = (int) DB::table('journals')->orderBy('journal_id')->value('journal_id');
$issueId = (int) DB::table('issues')->where('journal_id', $contextId)->orderBy('issue_id')->value('issue_id');
$recipientId = (int) DB::table('users')->where('disabled', 0)->whereNotNull('email')->orderBy('user_id')->value('user_id');
$context = Application::getContextDAO()->getById($contextId);
$issue = Repo::issue()->get($issueId);
$recipient = Repo::user()->get($recipientId);

if (!$context instanceof Context || !$issue || !$recipient) {
    failOpenProbeFail('prepared OJS fixture could not be resolved');
}

$holdTable = MailGuardPlugin::SPOOL_TABLE . '_phase0_hold';
$rowsBefore = DB::table(MailGuardPlugin::SPOOL_TABLE)->count();

if (Schema::hasTable($holdTable)) {
    Schema::drop($holdTable);
}

Schema::rename(MailGuardPlugin::SPOOL_TABLE, $holdTable);

try {
    // The real IssuePublishedNotify mailable is classified by MailGuard, but
    // durable capture will throw when insertOrIgnore reaches the missing spool.
    // The listener must catch that error and return control to native OJS SMTP.
    $job = new IssuePublishedNotifyUsers(
        collect([$recipientId]),
        $contextId,
        $issue,
        Locale::getLocale(),
        $recipient
    );
    $job->handle();
} catch (Throwable $e) {
    failOpenProbeFail('capture failure escaped MailGuard and broke native send: ' . $e->getMessage());
} finally {
    if (!Schema::hasTable(MailGuardPlugin::SPOOL_TABLE) && Schema::hasTable($holdTable)) {
        Schema::rename($holdTable, MailGuardPlugin::SPOOL_TABLE);
    }
}

if (!Schema::hasTable(MailGuardPlugin::SPOOL_TABLE)) {
    failOpenProbeFail('spool table was not restored after failure injection');
}

$rowsAfter = DB::table(MailGuardPlugin::SPOOL_TABLE)->count();
if ($rowsAfter !== $rowsBefore) {
    failOpenProbeFail('failure injection unexpectedly changed durable spool row count');
}

fwrite(STDOUT, "[PASS] real OJS issue-mail capture failure returns control to native transport\n");
fwrite(STDOUT, "MAILGUARD_PHASE0_FAIL_OPEN_PASS\n");
