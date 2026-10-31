<?php

declare(strict_types=1);

namespace App\Modules\Licenses\Features\ListLicenses;

use App\Http\JsonResult;
use App\Modules\Licenses\Contracts\ILicenseRepository;

final readonly class Handler
{
    public function __construct(private ILicenseRepository $licenses)
    {
    }

    public function handle(Query $query): JsonResult
    {
        $rows = $this->licenses->all();

        return new JsonResult([
            'count' => count($rows),
            'licenses' => $rows,
        ]);
    }
}
