<?php

declare(strict_types=1);

namespace App\Modules\Announcements\Features\GetCurrentAnnouncement;

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

    public function handle(Query $query): JsonResult
    {
        $customerId = trim((string) $query->customerId);

        if ($customerId === '') {
            throw ApiException::preservingResponse(
                ApiErrorCode::Unauthorized,
                ['error' => 'No se pudo obtener el cliente del token'],
            );
        }

        $announcement = $this->announcements->findPendingForCustomer($customerId);

        if ($announcement === null) {
            return $this->emptyResult();
        }

        $clientVersion = $this->visibility->requestVersion(
            $query->version,
            $query->currentVersion,
            $query->headerVersion,
        );

        if ($clientVersion === null || $clientVersion === '0') {
            $clientVersion = $this->announcements->customerClientVersion($customerId);
        }

        if (!$this->visibility->isVisibleForVersion($announcement, $clientVersion)) {
            return $this->emptyResult();
        }

        return new JsonResult([
            'hasAnnouncement' => true,
            'announcement' => [
                'id' => $announcement['id'],
                'version' => $announcement['version'],
                'title' => $announcement['title'],
                'subtitle' => $announcement['subtitle'],
                'slides' => $announcement['slides'],
            ],
        ]);
    }

    private function emptyResult(): JsonResult
    {
        return new JsonResult([
            'hasAnnouncement' => false,
            'announcement' => null,
        ]);
    }
}
