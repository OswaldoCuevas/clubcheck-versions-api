<?php

declare(strict_types=1);

namespace App\Modules\Customers\Features\ListDesktopTableData;

use App\Exceptions\ApiErrorCode;
use App\Exceptions\ApiException;
use App\Http\JsonResult;
use App\Modules\Customers\Contracts\IDesktopSyncRepository;
use App\Modules\Customers\Domain\DesktopTableCatalog;
use Throwable;

final readonly class Handler
{
    public function __construct(
        private IDesktopSyncRepository $desktopData,
        private DesktopTableCatalog $tables,
    ) {
    }

    public function handle(Query $query): JsonResult
    {
        $table = $this->tables->find($query->tableKey);

        if ($table === null) {
            throw ApiException::preservingResponse(
                ApiErrorCode::NotFound,
                ['error' => 'Tabla no encontrada'],
            );
        }

        if ($query->customerApiId === '') {
            throw ApiException::preservingResponse(
                ApiErrorCode::CustomerValidationFailed,
                ['error' => 'customerApiId es requerido'],
            );
        }

        try {
            $records = $this->desktopData->pullForAdmin(
                $table->bulkName,
                $query->customerApiId,
                true,
            );
        } catch (Throwable $exception) {
            throw ApiException::preservingResponse(
                ApiErrorCode::InternalError,
                ['error' => 'Error al obtener datos: ' . $exception->getMessage()],
                $exception,
            );
        }

        return new JsonResult([
            'success' => true,
            'table' => $table->name,
            'customerApiId' => $query->customerApiId,
            'count' => count($records),
            'records' => $records,
        ]);
    }
}
