<?php

declare(strict_types=1);

namespace App\Modules\Customers\Features\GetMonthlyMessageCount;

use App\Exceptions\ApiErrorCode;
use App\Exceptions\ApiException;
use App\Http\JsonResult;
use App\Modules\Customers\Contracts\ICustomerApiRepository;
use DateTimeImmutable;
use DateTimeZone;

final readonly class Handler
{
    public function __construct(private ICustomerApiRepository $customers)
    {
    }

    public function handle(Query $query): JsonResult
    {
        $customerId = $query->authenticatedCustomerId
            ?? $query->requestedCustomerId
            ?? '';

        if ($customerId === '') {
            throw ApiException::preservingResponse(
                ApiErrorCode::CustomerValidationFailed,
                ['error' => 'El campo customerId es obligatorio'],
            );
        }

        $now = new DateTimeImmutable(
            'now',
            new DateTimeZone('America/Mexico_City'),
        );
        $databaseMonth = new DateTimeImmutable(
            'now',
            new DateTimeZone('UTC'),
        );
        $total = $this->customers->countSuccessfulMessages(
            $customerId,
            (int) $databaseMonth->format('m'),
            (int) $databaseMonth->format('Y'),
        );

        return new JsonResult([
            'customerId' => $customerId,
            'total' => $total,
            'month' => $now->format('Y-m'),
        ]);
    }
}
