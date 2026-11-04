<?php

declare(strict_types=1);

namespace App\Modules\WhatsApp\Services;

use App\Modules\WhatsApp\Contracts\IWhatsAppProspectRepository;
use JsonException;

final readonly class WhatsAppWebhookProcessor
{
    public function __construct(
        private IWhatsAppProspectRepository $prospects,
        private MetaWhatsAppSettings $settings,
    ) {
    }

    /** @param array<string, mixed> $payload */
    public function process(array $payload): int
    {
        $processed = 0;
        $entries = $payload['entry'] ?? [];

        if (!is_array($entries)) {
            return $processed;
        }

        foreach ($entries as $entry) {
            if (!is_array($entry) || !is_array($entry['changes'] ?? null)) {
                continue;
            }

            foreach ($entry['changes'] as $change) {
                if (!is_array($change) || !is_array($change['value'] ?? null)) {
                    continue;
                }

                $value = $change['value'];

                if (!$this->matchesPhoneNumber($value)) {
                    continue;
                }

                $contacts = $this->contactsByWaId($value['contacts'] ?? []);

                $messages = is_array($value['messages'] ?? null) ? $value['messages'] : [];

                foreach ($messages as $message) {
                    if (!is_array($message)) {
                        continue;
                    }

                    $this->storeMessage($message, $contacts, 'incoming');
                    $processed++;
                }

                $echoes = is_array($value['smb_message_echoes'] ?? null)
                    ? $value['smb_message_echoes']
                    : [];

                foreach ($echoes as $message) {
                    if (!is_array($message)) {
                        continue;
                    }

                    $this->storeMessage($message, $contacts, 'outgoing');
                    $processed++;
                }

                $statuses = is_array($value['statuses'] ?? null) ? $value['statuses'] : [];

                foreach ($statuses as $status) {
                    if (!is_array($status)) {
                        continue;
                    }

                    $this->storeStatus($status);
                    $processed++;
                }
            }
        }

        return $processed;
    }

    /** @param array<string, mixed> $value */
    private function matchesPhoneNumber(array $value): bool
    {
        $expectedPhoneNumberId = $this->settings->phoneNumberId;
        $metadata = is_array($value['metadata'] ?? null) ? $value['metadata'] : [];
        $receivedPhoneNumberId = $metadata['phone_number_id'] ?? null;

        return $expectedPhoneNumberId === ''
            || $receivedPhoneNumberId === null
            || (string) $receivedPhoneNumberId === $expectedPhoneNumberId;
    }

    /**
     * @param mixed $contacts
     * @return array<string, string>
     */
    private function contactsByWaId(mixed $contacts): array
    {
        $names = [];

        if (!is_array($contacts)) {
            return $names;
        }

        foreach ($contacts as $contact) {
            if (!is_array($contact)) {
                continue;
            }

            $waId = $this->optionalString($contact['wa_id'] ?? null) ?? '';
            $profile = is_array($contact['profile'] ?? null) ? $contact['profile'] : [];
            $name = $profile['name'] ?? null;

            if ($waId !== '' && is_string($name) && $name !== '') {
                $names[$waId] = $name;
            }
        }

        return $names;
    }

    /**
     * @param array<string, mixed> $message
     * @param array<string, string> $contacts
     * @throws JsonException
     */
    private function storeMessage(array $message, array $contacts, string $direction): void
    {
        $waId = $this->optionalString(
            $direction === 'incoming'
                ? ($message['from'] ?? null)
                : ($message['to'] ?? null),
        ) ?? '';

        if ($waId === '') {
            return;
        }

        $referral = $message['referral'] ?? null;
        $attribution = is_array($referral) && $referral !== [] ? $referral : null;
        $messageTimestamp = $this->timestamp($message['timestamp'] ?? null);
        $messageType = $this->optionalString($message['type'] ?? null) ?? 'unknown';

        $this->prospects->recordMessage([
            'wa_id' => $waId,
            'phone_number' => $waId,
            'name' => $contacts[$waId] ?? null,
            'direction' => $direction,
            'wamid' => $this->optionalString($message['id'] ?? $message['message_id'] ?? null),
            'message_type' => $messageType,
            'content' => $this->messageContent($message),
            'message_at' => $messageTimestamp,
            'delivery_status' => $direction === 'incoming' ? 'received' : 'sent',
            'origin' => $attribution === null ? 'unknown' : 'click_to_whatsapp',
            'ad_id' => $this->optionalString($attribution['source_id'] ?? null),
            'campaign_id' => $this->optionalString($attribution['campaign_id'] ?? null),
            'attribution_json' => $attribution === null
                ? null
                : json_encode($attribution, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
            'event_json' => json_encode($message, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
        ]);
    }

    /**
     * @param array<string, mixed> $status
     * @throws JsonException
     */
    private function storeStatus(array $status): void
    {
        $this->prospects->recordMessageStatus([
            'wamid' => $this->optionalString($status['id'] ?? null),
            'wa_id' => $this->optionalString($status['recipient_id'] ?? null),
            'delivery_status' => $this->optionalString($status['status'] ?? null) ?? 'unknown',
            'status_at' => $this->timestamp($status['timestamp'] ?? null),
            'event_json' => json_encode($status, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
        ]);
    }

    /** @param array<string, mixed> $message */
    private function messageContent(array $message): ?string
    {
        $textMessage = is_array($message['text'] ?? null) ? $message['text'] : [];
        $button = is_array($message['button'] ?? null) ? $message['button'] : [];
        $interactive = is_array($message['interactive'] ?? null)
            ? $message['interactive']
            : [];
        $buttonReply = is_array($interactive['button_reply'] ?? null)
            ? $interactive['button_reply']
            : [];
        $listReply = is_array($interactive['list_reply'] ?? null)
            ? $interactive['list_reply']
            : [];
        $text = $textMessage['body'] ?? null;

        if (!is_string($text)) {
            $text = $button['text'] ?? null;
        }

        if (!is_string($text)) {
            $text = $buttonReply['title'] ?? null;
        }

        if (!is_string($text)) {
            $text = $listReply['title'] ?? null;
        }

        if (is_string($text)) {
            return $text;
        }

        return null;
    }

    private function timestamp(mixed $value): string
    {
        if (is_scalar($value) && ctype_digit((string) $value)) {
            return gmdate('Y-m-d H:i:s', (int) $value);
        }

        return gmdate('Y-m-d H:i:s');
    }

    private function optionalString(mixed $value): ?string
    {
        return is_scalar($value) && (string) $value !== ''
            ? (string) $value
            : null;
    }
}
