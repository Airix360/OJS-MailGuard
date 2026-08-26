<?php

/**
 * @file MailGuardPlugin.php
 *
 * Phase 0 integration spike for OJS MailGuard.
 *
 * This is intentionally NOT production-ready code. It exists to prove that
 * OJS mail can be classified at Mailable::build, durably captured immediately
 * before transport, and cancelled only after persistence succeeds.
 */

namespace APP\plugins\generic\mailGuard;

use APP\mail\mailables\IssuePublishedNotify;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use PKP\config\Config;
use PKP\mail\Mailable;
use PKP\observers\events\MessageSendingFromContext;
use PKP\plugins\GenericPlugin;
use PKP\plugins\Hook;
use PKP\plugins\interfaces\HasTaskScheduler;
use PKP\scheduledTask\PKPScheduler;
use Symfony\Component\Mime\Email;
use Throwable;

class MailGuardPlugin extends GenericPlugin implements HasTaskScheduler
{
    public const INTERNAL_TYPE_KEY = '__mailguard_type';
    public const INTERNAL_CONTROL_KEY = '__mailguard_control';
    public const MAIL_TYPE_ISSUE_PUBLISHED = 'ojs.issue_published';
    public const CONTROL_SUBSCRIPTION = 'subscription';
    public const SPOOL_TABLE = 'mailguard_spike_spool';

    /**
     * @copydoc Plugin::register()
     *
     * @param null|mixed $mainContextId
     */
    public function register($category, $path, $mainContextId = null)
    {
        if (!parent::register($category, $path, $mainContextId)) {
            return false;
        }

        require_once __DIR__ . '/MailGuardBypass.php';
        require_once __DIR__ . '/MailGuardMessageMetadata.php';
        require_once __DIR__ . '/MailGuardCaptureService.php';
        require_once __DIR__ . '/MailGuardSpoolRepository.php';
        require_once __DIR__ . '/MailGuardProbeSpoolTask.php';

        if ($this->getEnabled($mainContextId)) {
            // PKP emits this as the literal hook name "Mailable::build".
            Hook::add('Mailable::build', $this->decorateMailable(...), Hook::SEQUENCE_LATE);

            /** @var Dispatcher $events */
            $events = app(Dispatcher::class);
            $events->listen(
                MessageSendingFromContext::class,
                $this->onMessageSendingFromContext(...)
            );
        }

        return true;
    }

    /**
     * MailGuard is a site control-plane plugin. Journal/context policy comes later;
     * the Phase 0 registration itself is site-wide so interception is deterministic.
     */
    public function isSitePlugin()
    {
        return true;
    }

    public function getDisplayName()
    {
        return 'OJS MailGuard (Phase 0 Spike)';
    }

    public function getDescription()
    {
        return 'Phase 0 integration proof for the OJS MailGuard outbound mail control plane.';
    }

    /**
     * Classify only the motivating OJS bulk mailable during Phase 0.
     *
     * PKP's pre-send event is constructed without the mailable's view data, so
     * classification cannot be recovered from MessageSendingFromContext::$data.
     * Instead, this build hook resolves the native issueId, then attaches a
     * withSymfonyMessage() callback. Laravel invokes that callback after the
     * final Symfony Email exists and before the pre-send event. Metadata is
     * stored process-locally against that exact Email object; no internal
     * MailGuard headers are written into a message that might fail open.
     */
    public function decorateMailable(string $hookName, Mailable $mailable): bool
    {
        if (!$mailable instanceof IssuePublishedNotify) {
            return Hook::CONTINUE;
        }

        $data = $mailable->getData();
        $issueId = (int) ($data['issueId'] ?? 0);
        if ($issueId < 1) {
            error_log('[MailGuard Phase 0] IssuePublishedNotify build hook did not expose a valid issueId; leaving native delivery enabled.');
            return Hook::CONTINUE;
        }

        $metadata = [
            self::INTERNAL_TYPE_KEY => self::MAIL_TYPE_ISSUE_PUBLISHED,
            self::INTERNAL_CONTROL_KEY => self::CONTROL_SUBSCRIPTION,
            'issueId' => $issueId,
        ];

        $mailable->withSymfonyMessage(
            static function (Email $message) use ($metadata): void {
                MailGuardMessageMetadata::remember($message, $metadata);
            }
        );

        return Hook::CONTINUE;
    }

    /**
     * Cancellable pre-transport interception seam.
     *
     * Safety rules for the spike:
     * - disabled unless [mailguard] phase0_capture = On;
     * - only IssuePublishedNotify is controlled;
     * - bypass guard always wins;
     * - native delivery is cancelled ONLY when durable encrypted capture succeeds;
     * - persistence/encryption errors fail open to native OJS delivery;
     * - actual cancellation additionally requires phase0_intercept = On.
     *
     * Returning false is significant: PKP Mailer::shouldSendMessage() uses a
     * halting event dispatch and treats false as the transport cancellation
     * signal.
     */
    public function onMessageSendingFromContext(MessageSendingFromContext $event): ?bool
    {
        if (MailGuardBypass::active()) {
            MailGuardMessageMetadata::forget($event->message);
            return null;
        }

        if (!Config::getVar('mailguard', 'phase0_capture', false)) {
            MailGuardMessageMetadata::forget($event->message);
            return null;
        }

        $metadata = MailGuardMessageMetadata::take($event->message);
        if (($metadata[self::INTERNAL_TYPE_KEY] ?? null) !== self::MAIL_TYPE_ISSUE_PUBLISHED) {
            return null;
        }

        try {
            $captured = (new MailGuardCaptureService())->capture(
                $event->context,
                $event->message,
                $metadata
            );

            if (!$captured) {
                return null;
            }

            if (Config::getVar('mailguard', 'phase0_intercept', false)) {
                return false;
            }
        } catch (Throwable $e) {
            error_log('[MailGuard Phase 0] Capture failed; native OJS mail remains enabled: ' . $e->getMessage());
        }

        return null;
    }

    /**
     * Register the non-delivering spool probe. It only exercises lease/recovery
     * semantics; it never sends queued mail.
     */
    public function registerSchedules(PKPScheduler $scheduler): void
    {
        $scheduler
            ->addSchedule(new MailGuardProbeSpoolTask())
            ->everyMinute()
            ->name(MailGuardProbeSpoolTask::class)
            ->withoutOverlapping();
    }

    /**
     * Minimal durable-spool migration for Phase 0 proof only.
     */
    public function getInstallMigration()
    {
        return new class extends Migration {
            public function up(): void
            {
                if (Schema::hasTable(MailGuardPlugin::SPOOL_TABLE)) {
                    return;
                }

                Schema::create(MailGuardPlugin::SPOOL_TABLE, function (Blueprint $table): void {
                    $table->bigIncrements('capture_id');
                    $table->unsignedBigInteger('context_id');
                    $table->string('mail_type', 64);
                    $table->string('object_type', 32);
                    $table->unsignedBigInteger('object_id');
                    $table->unsignedInteger('generation')->default(0);
                    $table->char('recipient_hash', 64);
                    $table->longText('payload_encrypted');
                    $table->string('state', 24)->default('queued');
                    $table->string('lease_token', 64)->nullable();
                    $table->dateTime('lease_expires_at')->nullable();
                    $table->unsignedInteger('probe_count')->default(0);
                    $table->dateTime('last_probe_at')->nullable();
                    $table->timestamps();

                    $table->unique(
                        ['context_id', 'mail_type', 'object_type', 'object_id', 'generation', 'recipient_hash'],
                        'mailguard_spool_identity_uq'
                    );
                    $table->index(['state', 'lease_expires_at'], 'mailguard_spool_claim_idx');
                });
            }

            public function down(): void
            {
                Schema::dropIfExists(MailGuardPlugin::SPOOL_TABLE);
            }
        };
    }
}
