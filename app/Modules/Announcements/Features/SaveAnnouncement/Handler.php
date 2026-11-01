<?php

declare(strict_types=1);

namespace App\Modules\Announcements\Features\SaveAnnouncement;

use App\Exceptions\ApiErrorCode;
use App\Exceptions\ApiException;
use App\Modules\Announcements\Contracts\IAnnouncementRepository;
use App\Modules\Announcements\Contracts\ICurrentUserProvider;
use App\Modules\Announcements\Domain\AnnouncementWritePolicy;
use InvalidArgumentException;
use Throwable;

final readonly class Handler
{
    public function __construct(
        private IAnnouncementRepository $announcements,
        private ICurrentUserProvider $currentUser,
        private AnnouncementWritePolicy $policy,
    ) {
    }

    /** @return array<string, mixed> */
    public function handle(Command $command): array
    {
        try {
            $announcement = $this->policy->normalize($command->payload);

            return $this->announcements->save(
                $announcement,
                $this->currentUser->username(),
            );
        } catch (InvalidArgumentException $exception) {
            throw ApiException::fromErrorCode(
                ApiErrorCode::AnnouncementValidationFailed,
                $exception->getMessage(),
                previous: $exception,
            );
        } catch (Throwable $exception) {
            error_log('Announcement save error: ' . $exception->getMessage());
            throw ApiException::fromErrorCode(
                ApiErrorCode::AnnouncementSaveFailed,
                previous: $exception,
            );
        }
    }
}
