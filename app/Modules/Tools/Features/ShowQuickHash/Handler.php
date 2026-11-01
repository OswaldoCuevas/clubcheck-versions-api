<?php

declare(strict_types=1);

namespace App\Modules\Tools\Features\ShowQuickHash;

final readonly class Handler
{
    public function handle(Query $query): array
    {
        return [
            'title' => 'Hash Rápido - ClubCheck',
        ];
    }
}
