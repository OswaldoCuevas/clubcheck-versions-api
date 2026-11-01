<?php

declare(strict_types=1);

namespace App\Modules\DesktopReporting\Domain;

use App\Exceptions\ApiErrorCode;
use App\Exceptions\ApiException;

final class DesktopReportAccessPolicy
{
    public function customerId(?string $customerId): string
    {
        $customerId = trim((string) $customerId);

        if ($customerId === '') {
            throw ApiException::preservingResponse(
                ApiErrorCode::Unauthorized,
                ['error' => 'Token sin customerId'],
            );
        }

        return $customerId;
    }
}
