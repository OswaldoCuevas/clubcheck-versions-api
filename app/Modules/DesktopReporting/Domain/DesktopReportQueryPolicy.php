<?php

declare(strict_types=1);

namespace App\Modules\DesktopReporting\Domain;

use App\Exceptions\ApiErrorCode;
use App\Exceptions\ApiException;

final readonly class DesktopReportQueryPolicy
{
    /**
     * @param array<string, mixed> $parameters
     * @return array<string, mixed>
     */
    public function validate(array $parameters): array
    {
        $range = strtolower(trim((string) ($parameters['range'] ?? 'today')));

        if ($range !== 'custom') {
            return $parameters;
        }

        $from = isset($parameters['from'])
            ? strtotime((string) $parameters['from'])
            : false;
        $to = isset($parameters['to'])
            ? strtotime((string) $parameters['to'])
            : false;

        if ($from === false || $to === false) {
            throw ApiException::preservingResponse(
                ApiErrorCode::CustomerValidationFailed,
                ['error' => 'from y to son obligatorios para range=custom'],
            );
        }

        if ($from > $to) {
            throw ApiException::preservingResponse(
                ApiErrorCode::CustomerValidationFailed,
                ['error' => 'from no puede ser mayor que to'],
            );
        }

        return $parameters;
    }
}
