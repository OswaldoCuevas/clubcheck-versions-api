<?php

declare(strict_types=1);

namespace App\Modules\DesktopReporting\Features\GetUserProfile;

use App\Modules\DesktopReporting\Contracts\IDesktopUserProfileRepository;
use App\Modules\DesktopReporting\Domain\DesktopUserProfileQueryPolicy;
use App\Modules\DesktopReporting\Services\DesktopUserProfileReader;

final readonly class Handler
{
    public function __construct(
        private IDesktopUserProfileRepository $profiles,
        private DesktopUserProfileReader $reader,
        private DesktopUserProfileQueryPolicy $policy,
    ) {
    }

    public function handle(Query $query): array
    {
        $user = $this->reader->requireUser($query->customerId, $query->userId);
        $user['photo'] = $this->profiles->photo($query->customerId, $query->userId);
        $subscription = $this->profiles->currentSubscription(
            $query->customerId,
            $query->userId,
        );
        $user['currentSubscription'] = $this->policy->normalizeSubscription($subscription);

        return $user;
    }
}
