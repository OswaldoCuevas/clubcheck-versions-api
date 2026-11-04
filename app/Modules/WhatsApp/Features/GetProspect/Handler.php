<?php

declare(strict_types=1);

namespace App\Modules\WhatsApp\Features\GetProspect;

use App\Exceptions\ApiErrorCode;
use App\Exceptions\ApiException;
use App\Http\JsonResult;
use App\Modules\WhatsApp\Contracts\IWhatsAppProspectRepository;

final readonly class Handler
{
    public function __construct(private IWhatsAppProspectRepository $prospects)
    {
    }

    public function handle(Query $query): JsonResult
    {
        $prospect = $this->prospects->findProspect($query->id);

        if ($prospect === null) {
            throw ApiException::fromErrorCode(ApiErrorCode::NotFound);
        }

        return new JsonResult([
            'prospect' => $prospect,
            'messages' => $this->prospects->listMessages(
                $query->id,
                max(1, min($query->messageLimit, 100)),
                max(0, $query->messageOffset),
            ),
            'status_history' => $this->prospects->listStatusHistory($query->id),
        ]);
    }
}
