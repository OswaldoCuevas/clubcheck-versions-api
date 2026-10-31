<?php

declare(strict_types=1);

namespace App\Modules\CustomerStats\Features\ListCustomerStats;

use App\Http\JsonResult;
use App\Modules\CustomerStats\Contracts\ICustomerStatsRepository;

final readonly class Handler
{
    public function __construct(private ICustomerStatsRepository $stats)
    {
    }
    public function handle(Query $query): JsonResult
    {
        try {
            return new JsonResult([
                'global' => $this->stats->global(),
                'customers' => $this->stats->all(),
                'generatedAt' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            error_log('CustomerStatsJson error: ' . $e->getMessage());

            return new JsonResult(
                [
                    'error' => 'Error al obtener estadísticas',
                    'debug' => $e->getMessage(),
                ],
                500,
            );
        }
    }
}
