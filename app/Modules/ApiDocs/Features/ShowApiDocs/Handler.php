<?php

declare(strict_types=1);

namespace App\Modules\ApiDocs\Features\ShowApiDocs;

use App\Application\Contracts\ICurrentUserProvider;
use App\Modules\ApiDocs\Services\ApiDocumentation;

final readonly class Handler
{
    public function __construct(
        private ApiDocumentation $documentation,
        private ICurrentUserProvider $users,
    )
    {
    }

    public function handle(Query $query): array
    {
        return [
            'currentUser' => $this->users->current(),
            'title' => 'API Endpoints - ClubCheck',
            'sections' => $this->documentation->sections(),
            'isAuthenticated' => true,
        ];
    }
}
