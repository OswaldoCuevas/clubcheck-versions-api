<?php

declare(strict_types=1);

namespace App\Modules\PushNotifications\Services;

use App\Modules\PushNotifications\Contracts\IPushGateway;
use App\Modules\PushNotifications\Contracts\IPushTokenRepository;
use Throwable;

final readonly class PushDispatcher
{
    public function __construct(
        private IPushTokenRepository $tokens,
        private IPushGateway $gateway,
    ) {
    }

    public function sendToCustomer(
        string $customerId,
        string $title,
        string $body,
        array $data,
        array $webOptions,
        ?string $platform = null,
        bool $bestEffort = false,
    ): array {
        $devices = $this->tokens->forCustomer($customerId, $platform);
        $sent = 0;
        $failed = 0;
        $errors = [];
        $responses = [];

        foreach ($devices as $device) {
            try {
                $result = $this->gateway->send(
                    $device['Token'],
                    $title,
                    $body,
                    $data,
                    $webOptions,
                );
                $responses[] = [
                    'deviceId' => $device['Id'],
                    'httpStatus' => $result['httpStatus'],
                    'response' => $result['firebaseResponse'],
                ];

                if ($result['success']) {
                    ++$sent;
                    continue;
                }

                ++$failed;
                $code = (string) ($result['errorCode'] ?? 'UNKNOWN');
                $errors[$code] = ($errors[$code] ?? 0) + 1;
                if ($code === 'UNREGISTERED') {
                    $this->tokens->removeInvalid($device['Id'], $device['Token']);
                }
            } catch (Throwable $exception) {
                ++$failed;
                error_log('Firebase push: ' . $exception->getMessage());
                if (!$bestEffort) {
                    return [
                        'transportFailed' => true,
                        'sent' => $sent,
                        'failed' => $failed,
                        'firebaseResponses' => $responses,
                    ];
                }
            }
        }

        return [
            'transportFailed' => false,
            'sent' => $sent,
            'failed' => $failed,
            'errors' => $errors,
            'firebaseResponses' => $responses,
        ];
    }
}
