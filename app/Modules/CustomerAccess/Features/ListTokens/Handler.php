<?php

declare(strict_types=1);

namespace App\Modules\CustomerAccess\Features\ListTokens;

use App\Http\JsonResult;
use App\Modules\CustomerAccess\Contracts\ICustomerAccessRepository;

final readonly class Handler
{
    public function __construct(private ICustomerAccessRepository $access)
    {
    }
    public function handle(Query $query): JsonResult
    {
        return new JsonResult([
            'success' => true,
            'stats' => $this->access->jwtStats(),
            'customers' => $this->access->customerIpSummary(),
        ]);
    }
}
