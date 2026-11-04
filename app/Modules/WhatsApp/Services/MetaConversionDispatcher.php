<?php

declare(strict_types=1);

namespace App\Modules\WhatsApp\Services;

use App\Modules\WhatsApp\Contracts\IWhatsAppProspectRepository;

final readonly class MetaConversionDispatcher
{
    public function __construct(
        private IWhatsAppProspectRepository $prospects,
        private MetaConversionsApi $conversionsApi,
    ) {
    }

    /** @return array{sent: int, pending: int, failed: int} */
    public function dispatch(): array
    {
        $events = $this->prospects->pendingConversions(50);
        $result = [
            'sent' => 0,
            'pending' => 0,
            'failed' => 0,
        ];

        foreach ($events as $event) {
            $delivery = $this->conversionsApi->send($event);
            $attemptCount = (int) $event['AttemptCount'] + 1;
            $success = $delivery['success'];
            $isTerminalFailure = !$success
                && (!$delivery['retryable'] || $attemptCount >= 8);
            $nextAttemptAt = null;

            if (!$success && !$isTerminalFailure) {
                $delaySeconds = min(3600, 60 * (2 ** min($attemptCount - 1, 6)));
                $nextAttemptAt = gmdate('Y-m-d H:i:s', time() + $delaySeconds);
            }

            $responseJson = json_encode(
                $delivery['response'],
                JSON_THROW_ON_ERROR
                    | JSON_UNESCAPED_SLASHES
                    | JSON_INVALID_UTF8_SUBSTITUTE,
            );

            $this->prospects->recordConversionAttempt(
                (string) $event['Id'],
                [
                    'delivery_status' => $success
                        ? 'sent'
                        : ($isTerminalFailure ? 'failed' : 'pending'),
                    'next_attempt_at' => $nextAttemptAt,
                    'last_error' => $delivery['error'],
                    'response_json' => $responseJson,
                ],
            );

            if ($success) {
                $result['sent']++;
            } elseif ($isTerminalFailure) {
                $result['failed']++;
            } else {
                $result['pending']++;
            }
        }

        return $result;
    }
}
