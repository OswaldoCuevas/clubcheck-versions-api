<?php

declare(strict_types=1);

namespace App\Modules\Isapi\Contracts;

interface IIsapiCommandCatalog
{
    public function actions(): array;

    public function definition(string $action, array $parameters): ?array;
}
