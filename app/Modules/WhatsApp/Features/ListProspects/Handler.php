<?php

declare(strict_types=1);

namespace App\Modules\WhatsApp\Features\ListProspects;

use App\Exceptions\ApiErrorCode;
use App\Exceptions\ApiException;
use App\Http\JsonResult;
use App\Modules\WhatsApp\Contracts\IWhatsAppProspectRepository;
use App\Modules\WhatsApp\Domain\WhatsAppProspectStatus;

final readonly class Handler
{
    public function __construct(private IWhatsAppProspectRepository $prospects)
    {
    }

    public function handle(Query $query): JsonResult
    {
        if (
            $query->status !== null
            && !in_array($query->status, WhatsAppProspectStatus::values(), true)
        ) {
            throw ApiException::fromErrorCode(
                ApiErrorCode::WhatsAppValidationFailed,
                'El filtro de estado comercial no es válido.',
            );
        }

        $limit = max(1, min($query->limit, 100));
        $offset = max(0, $query->offset);

        return new JsonResult([
            'prospects' => $this->prospects->listProspects($limit, $offset, $query->status),
            'limit' => $limit,
            'offset' => $offset,
        ]);
    }
}
