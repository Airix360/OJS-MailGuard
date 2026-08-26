<?php

namespace APP\plugins\generic\mailGuard;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use JsonException;
use PKP\context\Context;
use PKP\core\Core;
use RuntimeException;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/**
 * Phase 0 durable capture service.
 *
 * The database keeps only a keyed recipient identity in plaintext. The actual
 * address and rendered message are encrypted as one payload with OJS/PKP's
 * application encrypter.
 */
final class MailGuardCaptureService
{
    /**
     * Persist one fully-rendered new-issue message.
     *
     * Returns true when either a new row was persisted or the exact idempotent
     * row already exists. Both cases are safe grounds for the interception
     * listener to suppress a duplicate native transport attempt.
     */
    public function capture(Context $context, Email $message, array $data): bool
    {
        $mailType = $data[MailGuardPlugin::INTERNAL_TYPE_KEY] ?? null;
        if ($mailType !== MailGuardPlugin::MAIL_TYPE_ISSUE_PUBLISHED) {
            return false;
        }

        $issueId = (int) ($data['issueId'] ?? 0);
        if ($issueId < 1) {
            throw new RuntimeException('IssuePublishedNotify reached interception without a valid issueId.');
        }

        $recipients = $message->getTo();
        if (count($recipients) !== 1) {
            throw new RuntimeException(
                'Phase 0 expects IssuePublishedNotify to contain exactly one To recipient; got ' . count($recipients) . '.'
            );
        }

        $recipient = $recipients[0];
        $normalizedEmail = $this->normalizeEmail($recipient->getAddress());
        $recipientHash = $this->recipientHash($normalizedEmail);
        $now = Core::getCurrentDate();

        $payload = [
            'schema' => 1,
            'capturedAt' => $now,
            'contextId' => $context->getId(),
            'mailType' => $mailType,
            'controlClass' => $data[MailGuardPlugin::INTERNAL_CONTROL_KEY] ?? null,
            'objectType' => 'issue',
            'objectId' => $issueId,
            'generation' => 0,
            'message' => [
                'subject' => $message->getSubject(),
                'from' => $this->addresses($message->getFrom()),
                'sender' => $message->getSender() ? $this->addresses([$message->getSender()]) : [],
                'replyTo' => $this->addresses($message->getReplyTo()),
                'to' => $this->addresses($message->getTo()),
                'cc' => $this->addresses($message->getCc()),
                'bcc' => $this->addresses($message->getBcc()),
                'textBody' => $message->getTextBody(),
                'htmlBody' => $message->getHtmlBody(),
                'headers' => $message->getHeaders()->toString(),
            ],
        ];

        try {
            $json = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        } catch (JsonException $e) {
            throw new RuntimeException('Unable to encode captured mail payload.', 0, $e);
        }

        $encryptedPayload = Crypt::encryptString($json);

        $identity = [
            'context_id' => $context->getId(),
            'mail_type' => $mailType,
            'object_type' => 'issue',
            'object_id' => $issueId,
            'generation' => 0,
            'recipient_hash' => $recipientHash,
        ];

        $inserted = DB::table(MailGuardPlugin::SPOOL_TABLE)->insertOrIgnore([
            ...$identity,
            'payload_encrypted' => $encryptedPayload,
            'state' => 'queued',
            'lease_token' => null,
            'lease_expires_at' => null,
            'probe_count' => 0,
            'last_probe_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        if ($inserted === 1) {
            return true;
        }

        // insertOrIgnore also returns 0 for an idempotent duplicate. Verify that
        // the intended row actually exists before allowing native cancellation.
        return DB::table(MailGuardPlugin::SPOOL_TABLE)
            ->where($identity)
            ->exists();
    }

    private function normalizeEmail(string $email): string
    {
        $normalized = strtolower(trim($email));
        if ($normalized === '' || !filter_var($normalized, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Invalid recipient email encountered during MailGuard capture.');
        }
        return $normalized;
    }

    private function recipientHash(string $normalizedEmail): string
    {
        $appKey = config('app.key');
        if (!is_string($appKey) || $appKey === '') {
            throw new RuntimeException('OJS application encryption key is unavailable; refusing MailGuard capture.');
        }

        // Domain-separate the keyed identity from direct use of the application
        // key. Production may rotate to a MailGuard-specific key after Phase 0.
        $derivedKey = hash_hmac('sha256', 'ojs-mailguard-delivery-identity-v1', $appKey, true);
        return hash_hmac('sha256', $normalizedEmail, $derivedKey);
    }

    /**
     * @param Address[] $addresses
     */
    private function addresses(array $addresses): array
    {
        return array_map(
            static fn (Address $address): array => [
                'address' => $address->getAddress(),
                'name' => $address->getName(),
            ],
            $addresses
        );
    }
}
