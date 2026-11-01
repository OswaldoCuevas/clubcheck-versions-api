<?php

declare(strict_types=1);

namespace App\Modules\Downloads\Features\DownloadExecutable;

final readonly class Query
{
    /** @param array<string, mixed> $request */
    public function __construct(public array $request)
    {
    }
}
