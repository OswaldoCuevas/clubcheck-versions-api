<?php

namespace App\Services;

require_once __DIR__ . '/FirebasePushService.php';
require_once __DIR__ . '/../Models/CustomerPushTokenModel.php';

use Models\CustomerPushTokenModel;

class PermissionRequestPushService
{
    /** Notifications are best-effort: persistence remains successful if FCM is unavailable. */
    public function notifyWeb(string $customerId, string $title, string $body, array $data): array
    {
        $sent = 0;
        $failed = 0;

        try {
            $config = require __DIR__ . '/../../config/firebase.php';
            $webOptions = [
                'iconUrl' => (string) ($config['web_icon_url'] ?? ''),
                'link' => (string) ($config['web_link'] ?? ''),
            ];
            if ($webOptions['link'] !== '') {
                // También queda disponible para onMessage o service workers personalizados.
                $data['link'] = $webOptions['link'];
            }
            $tokens = new CustomerPushTokenModel();
            $devices = $tokens->forCustomerPlatform($customerId, 'web');
            if ($devices === []) {
                return ['sent' => 0, 'failed' => 0];
            }
            $push = new FirebasePushService();
        } catch (\Throwable $e) {
            error_log('Permission request push initialization: ' . $e->getMessage());
            return ['sent' => 0, 'failed' => 1];
        }

        foreach ($devices as $device) {
            try {
                $result = $push->send($device['Token'], $title, $body, $data, $webOptions);
                if ($result['success']) {
                    ++$sent;
                    continue;
                }

                ++$failed;
                if (($result['errorCode'] ?? null) === 'UNREGISTERED') {
                    $tokens->removeInvalid($device['Id'], $device['Token']);
                }
            } catch (\Throwable $e) {
                ++$failed;
                error_log('Permission request Firebase push: ' . $e->getMessage());
            }
        }

        return ['sent' => $sent, 'failed' => $failed];
    }
}
