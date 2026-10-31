<?php

declare(strict_types=1);

namespace App\Modules\CustomerStats\Features\GetCustomerStats;

use App\Http\JsonResult;
use App\Modules\CustomerStats\Contracts\ICustomerStatsRepository;

final readonly class Handler
{
    public function __construct(private ICustomerStatsRepository $stats)
    {
    }
    public function handle(Query $query): JsonResult
    {
        $id = trim($query->customerId);
        if ($id === '') {
            return new JsonResult(['error' => 'customerId es obligatorio'], 422);
        }
        try {
            $customer = $this->stats->findCustomer($id);
            if (!$customer) {
                return new JsonResult(['error' => 'Cliente no encontrado'], 404);
            }
            return new JsonResult([
                'customer' => $customer,
                'stats' => $this->stats->forCustomer($id),
                'generatedAt' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            error_log('CustomerStatsDetailJson error: ' . $e->getMessage());

            return new JsonResult(
                [
                    'error' => 'Error al obtener estadísticas del cliente',
                    'debug' => $e->getMessage(),
                ],
                500,
            );
        }
    }
}
