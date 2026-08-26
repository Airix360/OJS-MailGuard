<?php

/**
 * Failure injection: prove MailGuard fails open when durable capture is
 * unavailable. The test temporarily renames the spool table, sends a would-be
 * controlled message, then restores the table. Native SMTP must still work.
 */

declare(strict_types=1);

$ojsRoot = dirname(__DIR__, 5);
require $ojsRoot . '/tools/bootstrap.php';

use APP\core\Application;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use PKP\context\Context;
use PKP\facades\Repo;
use PKP\mail\Mailable;
use APP\plugins\generic\mailGuard\MailGuardPlugin;
use Throwable;

function failOpenProbeFail(string $message): never
{
    fwrite(STDERR, "::error::[MailGuard Phase 0 fail-open probe] {$message}\n");
    exit(1);
}

final class MailGuardFailOpenProbeMailable extends Mailable
{
    public function __construct(Context $context, string $recipient, int $issueId)
    {
        parent::__construct([$context]);
        $this->to($recipient);
        $this->subject('MailGuard Phase 0 fail-open transport probe');
        $this->body('<p>Durable capture is unavailable; native OJS transport must continue.</p>');
        $this->addData([
            MailGuardPlugin::INTERNAL_TYPE_KEY => MailGuardPlugin::MAIL_TYPE_ISSUE_PUBLISHED,
            MailGuardPlugin::INTERNAL_CONTROL_KEY => MailGuardPlugin::CONTROL_SUBSCRIPTION,
            'issueId' => $issueId,
        ]);
    }
}

$pluginPath = 'plugins/generic/OJS-MailGuard';
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
$recipient = Repo::user()->get($recipientId);

if (!$context instanceof Context || !$recipient || $issueId < 1) {
    failOpenProbeFail('prepared OJS fixture could not be resolved');
}

$email = strtolower(trim((string) $recipient->getEmail()));
$holdTable = MailGuardPlugin::SPOOL_TABLE . '_phase0_hold';
$rowsBefore = DB::table(MailGuardPlugin::SPOOL_TABLE)->count();

if (Schema::hasTable($holdTable)) {
    Schema::drop($holdTable);
}

Schema::rename(MailGuardPlugin::SPOOL_TABLE, $holdTable);

try {
    // Capture will throw when it attempts insertOrIgnore on the missing spool.
    // MailGuard's listener must catch that failure and return null, allowing the
    // native OJS transport to send the message rather than silently dropping it.
    Mail::send(new MailGuardFailOpenProbeMailable($context, $email, $issueId));
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

fwrite(STDOUT, "[PASS] durable-capture failure returns control to native OJS transport\n");
fwrite(STDOUT, "MAILGUARD_PHASE0_FAIL_OPEN_PASS\n");
