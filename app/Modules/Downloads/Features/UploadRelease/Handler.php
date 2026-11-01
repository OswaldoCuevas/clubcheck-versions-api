<?php

declare(strict_types=1);

namespace App\Modules\Downloads\Features\UploadRelease;

use App\Modules\Downloads\Services\HomePageOperations;

final readonly class Handler
{
    public function __construct(private HomePageOperations $operations)
    {
    }

    public function handle(Command $command): array
    {
        return $this->operations->upload(
            $command->fields,
            $command->files,
            $command->server,
        );
    }
}
