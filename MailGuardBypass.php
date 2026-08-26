<?php

namespace APP\plugins\generic\mailGuard;

use Throwable;

/**
 * Process-local, scoped bypass for MailGuard's own future delivery path.
 *
 * A static depth counter is intentional: nested bypass scopes remain safe and
 * the try/finally block guarantees the guard is restored after exceptions.
 */
final class MailGuardBypass
{
    private static int $depth = 0;

    public static function active(): bool
    {
        return self::$depth > 0;
    }

    /**
     * Execute work while MailGuard interception is bypassed.
     *
     * @template T
     * @param callable():T $callback
     * @return T
     * @throws Throwable
     */
    public static function run(callable $callback): mixed
    {
        self::$depth++;

        try {
            return $callback();
        } finally {
            self::$depth--;
        }
    }
}
