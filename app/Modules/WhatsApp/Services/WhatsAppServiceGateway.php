<?php

declare(strict_types=1);

namespace App\Modules\WhatsApp\Services;

use App\Modules\WhatsApp\Contracts\IWhatsAppGateway;
use App\Services\WhatsAppService;

final class WhatsAppServiceGateway implements IWhatsAppGateway
{
    public function isConfigured(?string $customerId = null): bool
    {
        return $this->service($customerId)->isConfigured();
    }

    public function sendSubscription(
        string $customerId,
        array $payload,
        ?array $limitDebug,
        ?string $permitError,
    ): array {
        $service = $this->service($customerId, $limitDebug);

        return $service->sendSubscriptionTemplate(
            (string) $payload['phone'],
            (string) ($payload['firstName'] ?? 'Cliente'),
            (string) ($payload['startDate'] ?? ''),
            (string) ($payload['endDate'] ?? ''),
            $customerId,
            $this->nullableString($payload['userId'] ?? null),
            (string) $payload['subscriptionId'],
            $this->nullableString($payload['username'] ?? null),
            $permitError,
        );
    }

    public function sendWarning(
        string $customerId,
        array $payload,
        ?array $limitDebug,
        ?string $permitError,
    ): array {
        $days = $payload['days'] ?? 3;
        $daysText = $days == 1 ? 'un día' : $days . ' días';
        $service = $this->service($customerId, $limitDebug);

        return $service->sendWarningTemplate(
            (string) $payload['phone'],
            $daysText,
            $customerId,
            $this->nullableString($payload['userId'] ?? null),
            (string) $payload['subscriptionId'],
            $this->nullableString($payload['username'] ?? null),
            $permitError,
        );
    }

    public function sendFinalized(
        string $customerId,
        array $payload,
        ?array $limitDebug,
        ?string $permitError,
    ): array {
        return $this->service($customerId, $limitDebug)->sendFinalizedTemplate(
            (string) $payload['phone'],
            $customerId,
            $this->nullableString($payload['userId'] ?? null),
            (string) $payload['subscriptionId'],
            $this->nullableString($payload['username'] ?? null),
            $permitError,
        );
    }

    public function sendLastDay(
        string $customerId,
        array $payload,
        ?array $limitDebug,
        ?string $permitError,
    ): array {
        return $this->service($customerId, $limitDebug)->sendLastDayTemplate(
            (string) $payload['phone'],
            $customerId,
            $this->nullableString($payload['userId'] ?? null),
            (string) $payload['subscriptionId'],
            $this->nullableString($payload['username'] ?? null),
            $permitError,
        );
    }

    public function sendBulk(string $customerId, array $items): array
    {
        return $this->service($customerId)->sendBulk($items, $customerId);
    }

    public function registerBusinessProfile(
        string $customerId,
        string $businessName,
        ?string $logoPath,
        array $additionalData,
    ): array {
        return $this->service($customerId)->registerBusinessProfile(
            $businessName,
            $logoPath,
            $additionalData,
        );
    }

    private function service(?string $customerId, ?array $limitDebug = null): WhatsAppService
    {
        $service = new WhatsAppService($customerId);
        $service->setMessageLimitDebug($limitDebug);

        return $service;
    }

    private function nullableString(mixed $value): ?string
    {
        return $value === null ? null : (string) $value;
    }
}
