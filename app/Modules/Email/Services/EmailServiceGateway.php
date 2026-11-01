<?php

declare(strict_types=1);

namespace App\Modules\Email\Services;

use App\Modules\Email\Contracts\IEmailGateway;
use App\Services\EmailService;

final readonly class EmailServiceGateway implements IEmailGateway
{
    public function __construct(private EmailService $service)
    {
    }

    public function configured(): bool
    {
        return $this->service->isConfigured();
    }

    public function send(string $operation, array $payload, array $options): array
    {
        $email = (string) $payload['email'];

        return match ($operation) {
            'normal' => $this->service->send(
                $email,
                (string) $payload['subject'],
                (string) $payload['body'],
                'NORMAL',
                $options,
            ),
            'password-reset' => $this->service->sendPasswordReset($email, $options),
            'email-confirmation' => $this->service->sendEmailConfirmation($email, $options),
            'welcome' => $this->service->sendWelcome($email, $options),
            'notification' => $this->service->sendNotification(
                $email,
                (string) $payload['subject'],
                (string) $payload['body'],
                $options,
            ),
        };
    }

    public function verify(
        string $operation,
        string $email,
        string $code,
        ?string $type,
    ): array {
        return match ($operation) {
            'password-reset' => $this->service->verifyPasswordResetCode($email, $code),
            'email-confirmation' => $this->service->verifyEmailConfirmationCode($email, $code),
            default => $this->service->verifyCode($email, $code, strtoupper((string) $type)),
        };
    }

    public function history(string $email, int $limit): array
    {
        return $this->service->getHistory($email, $limit);
    }

    public function stats(): array
    {
        return $this->service->getStats();
    }

    public function cleanupExpired(): int
    {
        return $this->service->cleanupExpired();
    }
}
