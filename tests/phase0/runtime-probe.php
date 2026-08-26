<?php

/**
 * Phase 0 runtime integration probe.
 *
 * This script is executed only inside the disposable PKP GitHub Actions test
 * installation. It proves the real OJS new-issue job can be intercepted after
 * its in-app notification is created, persisted idempotently and encrypted,
 * and that lease recovery works. It also emits exactly one bypass email so the
 * surrounding shell harness can prove the native transport remains reachable.
 */

declare(strict_types=1);

$ojsRoot = dirname(__DIR__, 5);
require $ojsRoot . '/tools/bootstrap.php';

use APP\core\Application;
use APP\jobs\notifications\IssuePublishedNotifyUsers;
use APP\notification\Notification;
use Carbon\Carbon;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use PKP\facades\Locale;
use PKP\facades\Repo;
use PKP\mail\Mailable;
use PKP\context\Context;
use APP\plugins\generic\mailGuard\MailGuardBypass;
use APP\plugins\generic\mailGuard\MailGuardPlugin;
use APP\plugins\generic\mailGuard\MailGuardProbeSpoolTask;
use APP\plugins\generic\mailGuard\MailGuardSpoolRepository;

function phase0Fail(string $message): never
{
    fwrite(STDERR, "::error::[MailGuard Phase 0] {$message}\n");
    exit(1);
}

function phase0Assert(bool $condition, string $message): void
{
    if (!$condition) {
        phase0Fail($message);
    }
    fwrite(STDOUT, "[PASS] {$message}\n");
}

/**
 * Tiny context-bearing mailable used only for bypass/transport reachability.
 */
final class MailGuardTransportProbeMailable extends Mailable
{
    public function __construct(Context $context, string $recipient)
    {
        parent::__construct([$context]);
        $this->to($recipient);
        $this->subject('MailGuard Phase 0 bypass transport probe');
        $this->body('<p>MailGuard Phase 0 bypass transport probe.</p>');
    }
}

$pluginPath = 'plugins/generic/OJS-MailGuard';
/** @var MailGuardPlugin $plugin */
$plugin = require $ojsRoot . '/' . $pluginPath . '/index.php';

// First registration establishes plugin path/category while it is disabled.
phase0Assert(
    $plugin->register('generic', $pluginPath, Application::SITE_CONTEXT_ID),
    'plugin shell registers in OJS'
);
$plugin->setEnabled(true);

// Second registration attaches the Phase 0 build hook and cancellable event
// listener now that the site-level plugin setting is enabled.
phase0Assert(
    $plugin->register('generic', $pluginPath, Application::SITE_CONTEXT_ID),
    'enabled plugin attaches Phase 0 integration seams'
);

phase0Assert(
    DB::getSchemaBuilder()->hasTable(MailGuardPlugin::SPOOL_TABLE),
    'Phase 0 durable spool migration is installed'
);

// Resolving OJS's native Schedule in a CLI lifecycle causes PKPScheduler to
// load enabled HasTaskScheduler plugins. Assert the MailGuard probe is present
// in that real schedule rather than merely calling registerSchedules() by hand.
/** @var Schedule $schedule */
$schedule = app(Schedule::class);
$scheduleSummaries = collect($schedule->events())
    ->map(static fn ($event): string => $event->getSummaryForDisplay())
    ->all();
phase0Assert(
    in_array(MailGuardProbeSpoolTask::class, $scheduleSummaries, true),
    'native OJS scheduler discovers and registers the MailGuard spool task'
);

$contextId = (int) DB::table('journals')
    ->orderBy('journal_id')
    ->value('journal_id');
phase0Assert($contextId > 0, 'prepared OJS dataset contains a journal');

$issueId = (int) DB::table('issues')
    ->where('journal_id', $contextId)
    ->orderBy('issue_id')
    ->value('issue_id');
phase0Assert($issueId > 0, 'prepared OJS dataset contains an issue for the journal');

$recipientId = (int) DB::table('users')
    ->where('disabled', 0)
    ->whereNotNull('email')
    ->orderBy('user_id')
    ->value('user_id');
phase0Assert($recipientId > 0, 'prepared OJS dataset contains an active recipient');

$senderId = $recipientId;
$context = Application::getContextDAO()->getById($contextId);
$issue = Repo::issue()->get($issueId);
$recipient = Repo::user()->get($recipientId);
$sender = Repo::user()->get($senderId);

phase0Assert($context instanceof Context, 'journal context resolves through OJS DAO');
phase0Assert($issue !== null, 'issue resolves through OJS repository');
phase0Assert($recipient !== null && $sender !== null, 'sender and recipient resolve through OJS repository');

$recipientEmail = strtolower(trim((string) $recipient->getEmail()));
phase0Assert((bool) filter_var($recipientEmail, FILTER_VALIDATE_EMAIL), 'runtime recipient has a valid email address');

$notificationIdentity = [
    'context_id' => $contextId,
    'user_id' => $recipientId,
    'type' => Notification::NOTIFICATION_TYPE_PUBLISHED_ISSUE,
    'assoc_type' => Application::ASSOC_TYPE_ISSUE,
    'assoc_id' => $issueId,
];

$notificationsBefore = DB::table('notifications')->where($notificationIdentity)->count();
$spoolBefore = DB::table(MailGuardPlugin::SPOOL_TABLE)
    ->where('context_id', $contextId)
    ->where('mail_type', MailGuardPlugin::MAIL_TYPE_ISSUE_PUBLISHED)
    ->where('object_type', 'issue')
    ->where('object_id', $issueId)
    ->count();

phase0Assert($spoolBefore === 0, 'runtime probe starts with no matching MailGuard spool delivery');

// Execute the actual OJS producer path twice. OJS should create two in-app
// notifications, while MailGuard should persist one delivery identity and
// suppress both native transport attempts after durable capture succeeds.
for ($i = 0; $i < 2; $i++) {
    $job = new IssuePublishedNotifyUsers(
        collect([$recipientId]),
        $contextId,
        $issue,
        Locale::getLocale(),
        $sender
    );
    $job->handle();
}

$notificationsAfter = DB::table('notifications')->where($notificationIdentity)->count();
phase0Assert(
    $notificationsAfter === $notificationsBefore + 2,
    'MailGuard interception preserves OJS in-app issue notifications'
);

$spoolRows = DB::table(MailGuardPlugin::SPOOL_TABLE)
    ->where('context_id', $contextId)
    ->where('mail_type', MailGuardPlugin::MAIL_TYPE_ISSUE_PUBLISHED)
    ->where('object_type', 'issue')
    ->where('object_id', $issueId)
    ->get();

phase0Assert(
    $spoolRows->count() === 1,
    'two native issue-mail attempts collapse to one idempotent delivery identity'
);

$row = $spoolRows->first();
phase0Assert(
    is_string($row->recipient_hash) && strlen($row->recipient_hash) === 64 && $row->recipient_hash !== $recipientEmail,
    'recipient delivery identity is keyed/hashed rather than plaintext email'
);
phase0Assert(
    is_string($row->payload_encrypted) && !str_contains($row->payload_encrypted, $recipientEmail),
    'queued message payload does not expose recipient email in plaintext'
);

$decrypted = Crypt::decryptString($row->payload_encrypted);
$payload = json_decode($decrypted, true, 512, JSON_THROW_ON_ERROR);
phase0Assert(($payload['mailType'] ?? null) === MailGuardPlugin::MAIL_TYPE_ISSUE_PUBLISHED, 'encrypted payload decrypts with expected mail type');
phase0Assert((int) ($payload['objectId'] ?? 0) === $issueId, 'encrypted payload decrypts with expected issue identity');
phase0Assert(
    strtolower((string) ($payload['message']['to'][0]['address'] ?? '')) === $recipientEmail,
    'encrypted payload contains the intended recipient only after decryption'
);

// Lease and crash-recovery proof: claim without releasing, force expiry, then
// verify another claim can recover the exact row with a new ownership token.
$spool = new MailGuardSpoolRepository();
$firstClaim = $spool->claimBatch(1);
phase0Assert($firstClaim !== null && $firstClaim['count'] === 1, 'spool worker can lease a queued delivery');
$firstToken = $firstClaim['token'];

DB::table(MailGuardPlugin::SPOOL_TABLE)
    ->where('lease_token', $firstToken)
    ->update(['lease_expires_at' => Carbon::now()->subMinute()]);

$secondClaim = $spool->claimBatch(1);
phase0Assert($secondClaim !== null && $secondClaim['count'] === 1, 'expired worker lease is reclaimable after simulated crash');
phase0Assert($secondClaim['token'] !== $firstToken, 'recovered lease receives a new ownership token');
phase0Assert($spool->releaseProbe($secondClaim['token']) === 1, 'recovered lease can be safely released by its owner');

// Verify the scoped bypass does not recurse into MailGuard capture and leaves
// the native OJS transport reachable. The shell harness asserts Sendria sees
// exactly this one message and none of the two intercepted issue messages.
$transportProbe = new MailGuardTransportProbeMailable($context, $recipientEmail);
MailGuardBypass::run(static function () use ($transportProbe): void {
    Mail::send($transportProbe);
});

phase0Assert(
    DB::table(MailGuardPlugin::SPOOL_TABLE)
        ->where('context_id', $contextId)
        ->where('object_id', $issueId)
        ->count() === 1,
    'MailGuard bypass does not recursively recapture its own/native send path'
);

fwrite(STDOUT, "MAILGUARD_PHASE0_RUNTIME_PASS\n");
