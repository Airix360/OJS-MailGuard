<?php

/**
 * Prove disabling MailGuard restores the native OJS transport path.
 * Executed in a fresh PHP process after the main Phase 0 runtime probe.
 */

declare(strict_types=1);

$ojsRoot = dirname(__DIR__, 5);
require $ojsRoot . '/tools/bootstrap.php';

use APP\core\Application;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use PKP\context\Context;
use PKP\mail\Mailable;
use PKP\facades\Repo;
use APP\plugins\generic\mailGuard\MailGuardPlugin;

function disabledProbeFail(string $message): never
{
    fwrite(STDERR, "::error::[MailGuard Phase 0 disabled probe] {$message}\n");
    exit(1);
}

final class MailGuardDisabledProbeMailable extends Mailable
{
    public function __construct(Context $context, string $recipient, int $issueId)
    {
        parent::__construct([$context]);
        $this->to($recipient);
        $this->subject('MailGuard Phase 0 disabled transport probe');
        $this->body('<p>MailGuard is disabled; this message must use native OJS transport.</p>');
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

// Disable before registration in this fresh process. Therefore the plugin's
// cancellable MessageSendingFromContext listener is never attached.
$plugin->setEnabled(false);
if (!$plugin->register('generic', $pluginPath, Application::SITE_CONTEXT_ID)) {
    disabledProbeFail('disabled plugin shell did not register');
}

$contextId = (int) DB::table('journals')->orderBy('journal_id')->value('journal_id');
$issueId = (int) DB::table('issues')->where('journal_id', $contextId)->orderBy('issue_id')->value('issue_id');
$recipientId = (int) DB::table('users')->where('disabled', 0)->whereNotNull('email')->orderBy('user_id')->value('user_id');
$context = Application::getContextDAO()->getById($contextId);
$recipient = Repo::user()->get($recipientId);

if (!$context instanceof Context || !$recipient || $issueId < 1) {
    disabledProbeFail('prepared OJS fixture could not be resolved');
}

$email = strtolower(trim((string) $recipient->getEmail()));
$spoolBefore = DB::table(MailGuardPlugin::SPOOL_TABLE)->count();

Mail::send(new MailGuardDisabledProbeMailable($context, $email, $issueId));

$spoolAfter = DB::table(MailGuardPlugin::SPOOL_TABLE)->count();
if ($spoolAfter !== $spoolBefore) {
    disabledProbeFail('disabled plugin unexpectedly captured a message');
}

fwrite(STDOUT, "[PASS] disabled MailGuard does not capture controlled mail\n");
fwrite(STDOUT, "MAILGUARD_PHASE0_DISABLED_TRANSPORT_PASS\n");
