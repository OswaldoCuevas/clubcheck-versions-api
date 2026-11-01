<?php

declare(strict_types=1);

namespace App\Modules\AdminAccess\Domain;

final readonly class LoginPageResult
{
    public function __construct(
        public string $csrfToken,
        public string $error = '',
        public ?string $redirectTo = null,
    ) {
    }

    /** @return array<string, mixed> */
    public function viewData(): array
    {
        return [
            'title' => 'Iniciar Sesión - ClubCheck',
            'error' => $this->error,
            'csrf_token' => $this->csrfToken,
        ];
    }
}
