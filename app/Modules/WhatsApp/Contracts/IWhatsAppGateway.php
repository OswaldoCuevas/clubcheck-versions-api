<?php

declare(strict_types=1);

namespace App\Modules\WhatsApp\Contracts;

interface IWhatsAppGateway
{
    public function isConfigured(?string $customerId = null): bool;

    public function sendSubscription(string $customerId, array $payload, ?array $limitDebug, ?string $permitError): array;

    public function sendWarning(string $customerId, array $payload, ?array $limitDebug, ?string $permitError): array;

    public function sendFinalized(string $customerId, array $payload, ?array $limitDebug, ?string $permitError): array;

    public function sendLastDay(string $customerId, array $payload, ?array $limitDebug, ?string $permitError): array;

    public function sendBulk(string $customerId, array $items): array;

    public function registerBusinessProfile(
        string $customerId,
        string $businessName,
        ?string $logoPath,
        array $additionalData,
    ): array;
}
