<?php

namespace APP\plugins\generic\mailGuard;

use Symfony\Component\Mime\Email;
use WeakMap;

/**
 * Request/process-local metadata handoff for Phase 0.
 *
 * PKP's Mailable::build hook sees the OJS mailable before Laravel creates the
 * final Symfony Email. The hook therefore attaches a withSymfonyMessage()
 * callback which stores classification metadata here against that exact Email
 * object. MessageSendingFromContext later exposes the same Email object.
 *
 * A WeakMap is intentional: metadata is never serialized, never written into
 * headers, and disappears automatically if the message object is released.
 */
final class MailGuardMessageMetadata
{
    private static ?WeakMap $messages = null;

    public static function remember(Email $message, array $metadata): void
    {
        self::map()[$message] = $metadata;
    }

    public static function take(Email $message): ?array
    {
        $map = self::map();
        if (!isset($map[$message])) {
            return null;
        }

        $metadata = $map[$message];
        unset($map[$message]);

        return $metadata;
    }

    public static function forget(Email $message): void
    {
        $map = self::map();
        if (isset($map[$message])) {
            unset($map[$message]);
        }
    }

    private static function map(): WeakMap
    {
        return self::$messages ??= new WeakMap();
    }
}
