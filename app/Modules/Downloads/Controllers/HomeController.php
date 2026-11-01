<?php

declare(strict_types=1);

namespace App\Modules\Downloads\Controllers;

use App\Application\Contracts\IMediator;
use App\Http\ViewResponse;
use App\Modules\Downloads\Features\ShowHome\Query as ShowHomeQuery;
use App\Modules\Downloads\Features\UploadRelease\Command as UploadReleaseCommand;

final readonly class HomeController
{
    public function __construct(private IMediator $mediator)
    {
    }

    public function index(): void
    {
        ViewResponse::render(
            'home/index',
            $this->mediator->send(new ShowHomeQuery()),
        );
    }

    public function upload(): void
    {
        ViewResponse::render(
            'home/index',
            $this->mediator->send(
                new UploadReleaseCommand($_POST, $_FILES, $_SERVER),
            ),
        );
    }
}
