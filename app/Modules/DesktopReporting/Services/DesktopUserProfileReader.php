<?php

declare(strict_types=1);

namespace App\Modules\DesktopReporting\Services;

use App\Exceptions\ApiErrorCode;
use App\Exceptions\ApiException;
use App\Modules\DesktopReporting\Contracts\IDesktopUserProfileRepository;

final readonly class DesktopUserProfileReader
{
    public function __construct(
        private IDesktopUserProfileRepository $profiles,
    ) {
    }

    public function requireUser(string $customerId, string $userId): array
    {
        $user = $this->profiles->findUser($customerId, $userId);
        if ($user === null) {
            throw ApiException::preservingResponse(
                ApiErrorCode::DesktopUserProfileNotFound,
                ['error' => 'Socio no accesible'],
            );
        }

        return $user;
    }
}
