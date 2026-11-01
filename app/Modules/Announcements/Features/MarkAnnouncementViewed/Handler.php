<?php

declare(strict_types=1);

namespace App\Modules\Announcements\Features\MarkAnnouncementViewed;

use App\Exceptions\ApiErrorCode;
use App\Exceptions\ApiException;
use App\Http\JsonResult;
use App\Modules\Announcements\Contracts\IAnnouncementRepository;

final readonly class Handler
{
    public function __construct(private IAnnouncementRepository $announcements)
    {
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

        if ($this->announcements->find($command->announcementId) === null) {
            throw ApiException::preservingResponse(
                ApiErrorCode::AnnouncementNotFound,
                ['error' => 'Anuncio no encontrado'],
            );
        }

        $this->announcements->recordView(
            $command->announcementId,
            $customerId,
            $this->announcements->customerClientVersion($customerId),
            $command->ipAddress,
            $command->userAgent !== null
                ? substr($command->userAgent, 0, 255)
                : null,
        );

        return new JsonResult(['success' => true]);
    }
}
