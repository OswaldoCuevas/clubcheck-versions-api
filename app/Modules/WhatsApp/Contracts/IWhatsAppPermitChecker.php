<?php

declare(strict_types=1);

namespace App\Modules\WhatsApp\Contracts;

interface IWhatsAppPermitChecker
{
    /** @return array{error: ?string, debug: ?array} */
    public function check(string $customerId): array;
}
