<?php

declare(strict_types=1);

namespace App\Modules\Downloads\Features\UploadRelease;

final readonly class Command
{
    public function __construct(
        public array $fields,
        public array $files,
        public array $server,
    ) {
    }
}
