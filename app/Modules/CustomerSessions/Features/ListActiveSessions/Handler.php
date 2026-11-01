<?php

declare(strict_types=1);

namespace App\Modules\CustomerSessions\Features\ListActiveSessions;

use App\Http\JsonResult;
use App\Modules\CustomerSessions\Contracts\ICustomerSessionRepository;
use App\Modules\CustomerSessions\Services\CustomerSessionConfiguration;

final readonly class Handler
{
    public function __construct(
        private ICustomerSessionRepository $sessions,
        private CustomerSessionConfiguration $configuration,
    ) {
    }

    public function handle(Query $query): JsonResult
    {
        $this->sessions->purgeExpired($this->configuration->gracePeriod);
        $sessions = $this->sessions->list(
            $query->customerId,
            $this->configuration->gracePeriod,
        );

        return new JsonResult([
            'count' => count($sessions),
            'sessions' => $sessions,
            'heartbeatInterval' => $this->configuration->heartbeatInterval,
            'gracePeriod' => $this->configuration->gracePeriod,
        ]);
    }
}
