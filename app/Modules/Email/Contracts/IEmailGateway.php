<?php

declare(strict_types=1);

namespace App\Modules\Email\Contracts;

interface IEmailGateway
{
    public function configured(): bool;

    public function send(string $operation, array $payload, array $options): array;

    public function verify(string $operation, string $email, string $code, ?string $type): array;

    public function history(string $email, int $limit): array;

    public function stats(): array;

    public function cleanupExpired(): int;
}
