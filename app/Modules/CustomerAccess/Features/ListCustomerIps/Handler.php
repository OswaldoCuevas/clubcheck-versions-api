<?php

declare(strict_types=1);

namespace App\Modules\CustomerAccess\Features\ListCustomerIps;

use App\Http\JsonResult;
use App\Modules\CustomerAccess\Contracts\ICustomerAccessRepository;

final readonly class Handler
{
    public function __construct(private ICustomerAccessRepository $ips)
    {
    }
    public function handle(Query $query): JsonResult
    {
        $id = trim($query->customerId);
        if ($id === '') {
            return new JsonResult(['error' => 'customerId es obligatorio'], 422);
        }
        return new JsonResult([
            'success' => true,
            'customerId' => $id,
            'hasMultipleRecentIps' => $this->ips->hasMultipleRecentIps($id),
            'ips' => $this->ips->customerIps($id),
        ]);
    }
}
