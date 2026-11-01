<?php

declare(strict_types=1);

namespace App\Modules\Announcements\Features\MarkCurrentAnnouncementViewed;

use App\Exceptions\ApiErrorCode;
use App\Exceptions\ApiException;
use App\Http\JsonResult;
use App\Modules\Announcements\Contracts\IAnnouncementRepository;
use App\Modules\Announcements\Domain\AnnouncementVisibilityPolicy;

final readonly class Handler
{
    public function __construct(
        private IAnnouncementRepository $announcements,
        private AnnouncementVisibilityPolicy $visibility,
    ) {
    }

    public function handle(Command $command): JsonResult
    {
        $customerId = trim((string) $command->customerId);

        if ($customerId === '') {
            throw ApiException::preservingResponse(
                ApiErrorCode::Unauthorized,
                ['error' => 'No se pudo obtener el cliente del token'],
            );
        }

        $announcement = $this->announcements->findActive();
        $clientVersion = $this->announcements->customerClientVersion($customerId);

        if (
            $announcement === null
            || !$this->visibility->isVisibleForVersion($announcement, $clientVersion)
        ) {
            throw ApiException::preservingResponse(
                ApiErrorCode::AnnouncementNotFound,
                ['error' => 'No hay anuncio activo para este cliente'],
            );
        }

        $this->announcements->recordView(
            $announcement['id'],
            $customerId,
            $clientVersion,
            $command->ipAddress,
            $command->userAgent !== null
                ? substr($command->userAgent, 0, 255)
                : null,
        );

        return new JsonResult([
            'success' => true,
            'announcementId' => $announcement['id'],
        ]);
    }
}
