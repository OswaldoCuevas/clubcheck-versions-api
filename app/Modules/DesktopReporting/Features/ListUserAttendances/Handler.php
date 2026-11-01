<?php

declare(strict_types=1);

namespace App\Modules\DesktopReporting\Features\ListUserAttendances;

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
        $this->reader->requireUser($query->customerId, $query->userId);
        [$from, $to] = $this->policy->range($query->parameters);
        [$page, $pageSize, $offset] = $this->policy->pagination($query->parameters);
        $items = $this->profiles->attendances(
            $query->customerId,
            $query->userId,
            $from,
            $to,
            $pageSize,
            $offset,
        );
        foreach ($items as &$item) {
            $item['Active'] = (int) $item['Active'];
        }
        unset($item);

        return [
            'items' => $items,
            'page' => $page,
            'pageSize' => $pageSize,
            'total' => $this->profiles->countAttendances(
                $query->customerId,
                $query->userId,
                $from,
                $to,
            ),
        ];
    }
}
