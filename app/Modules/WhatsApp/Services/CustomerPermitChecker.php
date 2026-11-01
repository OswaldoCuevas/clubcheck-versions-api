<?php

declare(strict_types=1);

namespace App\Modules\WhatsApp\Services;

require_once dirname(__DIR__, 4) . '/utils/CustomerPermits.php';

use App\Modules\WhatsApp\Contracts\IWhatsAppPermitChecker;
use CustomerPermits;
use Throwable;

final class CustomerPermitChecker implements IWhatsAppPermitChecker
{
    public function check(string $customerId): array
    {
        $debug = null;

        try {
            (new CustomerPermits($customerId))->checkSendMessage(null, $debug);

            return [
                'error' => null,
                'debug' => $debug,
            ];
        } catch (Throwable $exception) {
            return [
                'error' => $exception->getMessage(),
                'debug' => $debug,
            ];
        }
    }
}
