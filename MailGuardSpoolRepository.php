<?php

namespace APP\plugins\generic\mailGuard;

use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Minimal lease/claim repository used only to prove Phase 0 recovery semantics.
 *
 * It intentionally does not send email. Claimed rows are returned to `queued`
 * after the probe and receive a counter/timestamp. If a worker dies after the
 * claim, the lease naturally becomes reclaimable after expiry.
 */
final class MailGuardSpoolRepository
{
    public const LEASE_SECONDS = 300;

    /**
     * Claim a batch using an atomic conditional update and then immediately
     * release it as a successful probe cycle.
     */
    public function probeCycle(int $limit = 10): int
    {
        $claim = $this->claimBatch($limit);
        if ($claim === null) {
            return 0;
        }

        return $this->releaseProbe($claim['token']);
    }

    /**
     * @return array{token:string,count:int}|null
     */
    public function claimBatch(int $limit): ?array
    {
        $limit = max(1, min($limit, 100));
        $now = Carbon::now();
        $expiresAt = $now->copy()->addSeconds(self::LEASE_SECONDS);
        $token = bin2hex(random_bytes(24));

        $candidateIds = DB::table(MailGuardPlugin::SPOOL_TABLE)
            ->select('capture_id')
            ->where(function (Builder $query) use ($now): void {
                $this->claimable($query, $now);
            })
            ->orderBy('capture_id')
            ->limit($limit)
            ->pluck('capture_id')
            ->all();

        if (!$candidateIds) {
            return null;
        }

        // Two workers may read the same candidate IDs. Only the first update
        // whose claimability predicate still matches can lease each row.
        DB::table(MailGuardPlugin::SPOOL_TABLE)
            ->whereIn('capture_id', $candidateIds)
            ->where(function (Builder $query) use ($now): void {
                $this->claimable($query, $now);
            })
            ->update([
                'state' => 'leased',
                'lease_token' => $token,
                'lease_expires_at' => $expiresAt,
                'updated_at' => $now,
            ]);

        $count = DB::table(MailGuardPlugin::SPOOL_TABLE)
            ->where('lease_token', $token)
            ->where('state', 'leased')
            ->count();

        if ($count < 1) {
            return null;
        }

        return ['token' => $token, 'count' => $count];
    }

    /**
     * Release only rows owned by this lease token. This is the Phase 0 stand-in
     * for a later delivery transition and provides visible proof that the
     * scheduler successfully claimed the durable spool.
     */
    public function releaseProbe(string $token): int
    {
        $now = Carbon::now();

        return DB::table(MailGuardPlugin::SPOOL_TABLE)
            ->where('lease_token', $token)
            ->where('state', 'leased')
            ->update([
                'state' => 'queued',
                'lease_token' => null,
                'lease_expires_at' => null,
                'probe_count' => DB::raw('probe_count + 1'),
                'last_probe_at' => $now,
                'updated_at' => $now,
            ]);
    }

    private function claimable(Builder $query, Carbon $now): void
    {
        $query
            ->where('state', 'queued')
            ->orWhere(function (Builder $query) use ($now): void {
                $query
                    ->where('state', 'leased')
                    ->whereNotNull('lease_expires_at')
                    ->where('lease_expires_at', '<=', $now);
            });
    }
}
