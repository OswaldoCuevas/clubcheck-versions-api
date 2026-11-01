<?php

declare(strict_types=1);

namespace App\Modules\Isapi\Services;

use App\Modules\Isapi\Contracts\IIsapiCommandCatalog;
use App\Services\IsapiCommandService;

final readonly class IsapiCommandCatalog implements IIsapiCommandCatalog
{
    public function __construct(private IsapiCommandService $service)
    {
    }

    public function actions(): array
    {
        return $this->service->actions();
    }

    public function definition(string $action, array $parameters): ?array
    {
        return $this->service->definition($action, $parameters);
    }
}
