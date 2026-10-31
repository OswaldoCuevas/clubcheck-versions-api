<?php

declare(strict_types=1);

namespace App\Application\Contracts;

interface ICurrentUserProvider
{
    /** @return array<string, mixed> */
    public function current(): array;
    public function username(): ?string;
}
