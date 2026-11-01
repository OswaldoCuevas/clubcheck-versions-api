<?php

declare(strict_types=1);

namespace App\Modules\Tools\Controllers;

use App\Application\Contracts\IMediator;
use App\Http\ViewResponse;
use App\Modules\Tools\Features\ShowCliPasswordGenerator\Query as ShowCliQuery;
use App\Modules\Tools\Features\ShowPasswordGenerator\Query as ShowPasswordGeneratorQuery;
use App\Modules\Tools\Features\ShowQuickHash\Query as ShowQuickHashQuery;

final readonly class ToolsController
{
    public function __construct(private IMediator $mediator)
    {
    }

    public function passwordGenerator(): void
    {
        ViewResponse::render(
            'tools/password-generator',
            $this->mediator->send(new ShowPasswordGeneratorQuery($_POST)),
        );
    }

    public function quickHash(): void
    {
        ViewResponse::render(
            'tools/quick-hash',
            $this->mediator->send(new ShowQuickHashQuery()),
        );
    }

    public function generatePassword(): void
    {
        ViewResponse::render(
            'tools/generate-password',
            $this->mediator->send(new ShowCliQuery()),
        );
    }
}
