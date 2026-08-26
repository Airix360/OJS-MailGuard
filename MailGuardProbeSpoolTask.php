<?php

namespace APP\plugins\generic\mailGuard;

use PKP\scheduledTask\ScheduledTask;
use Throwable;

/**
 * Non-delivering scheduler probe.
 *
 * This task exercises the durable claim/lease/release path only. It MUST NOT be
 * expanded into production sending logic on the Phase 0 branch.
 */
final class MailGuardProbeSpoolTask extends ScheduledTask
{
    public function getName(): string
    {
        return 'OJS MailGuard Phase 0 spool probe';
    }

    protected function executeActions(): bool
    {
        try {
            $count = (new MailGuardSpoolRepository())->probeCycle(10);
            $this->addExecutionLogEntry("MailGuard Phase 0 probed {$count} spool row(s). No email was sent.");
            return true;
        } catch (Throwable $e) {
            $this->addExecutionLogEntry('MailGuard Phase 0 spool probe failed: ' . $e->getMessage());
            error_log('[MailGuard Phase 0] Spool probe failed: ' . $e->getMessage());
            return false;
        }
    }
}
