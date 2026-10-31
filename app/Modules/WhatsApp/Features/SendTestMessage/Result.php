<?php

declare(strict_types=1);

namespace App\Modules\WhatsApp\Features\SendTestMessage;

final readonly class Result
{
    public function __construct(public ?int $statusCode = null, public string $body = '', public string $redirectTo = '')
    {
    }
    public static function forbidden(): self
    {
        return new self(403, 'Solicitud no autorizada');
    }
    public static function redirect(string $url): self
    {
        return new self(null, '', $url);
    }
}
