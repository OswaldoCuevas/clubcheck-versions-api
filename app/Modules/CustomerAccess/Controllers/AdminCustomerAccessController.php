<?php

declare(strict_types=1);

namespace App\Modules\CustomerAccess\Controllers;

use App\Application\Contracts\IMediator;
use App\Http\JsonResponse;
use App\Http\JsonResult;
use App\Http\ViewResponse;
use App\Modules\CustomerAccess\Features\CreateToken\Command as CreateTokenCommand;
use App\Modules\CustomerAccess\Features\FlagIp\Command as FlagIpCommand;
use App\Modules\CustomerAccess\Features\ListCustomerIps\Query as ListCustomerIpsQuery;
use App\Modules\CustomerAccess\Features\ListLoginAttempts\Query as ListLoginAttemptsQuery;
use App\Modules\CustomerAccess\Features\ListTokens\Query as ListTokensQuery;
use App\Modules\CustomerAccess\Features\RevokeToken\Command as RevokeTokenCommand;
use App\Modules\CustomerAccess\Features\ShowLoginAttempts\Query as ShowLoginAttemptsQuery;
use App\Modules\CustomerAccess\Features\ShowTokens\Query as ShowTokensQuery;

final readonly class AdminCustomerAccessController
{
    public function __construct(private IMediator $mediator)
    {
    }
    public function customerLoginAttempts(): void
    {
        ViewResponse::render('admin/customer-login-attempts', $this->mediator->send(new ShowLoginAttemptsQuery($_GET)));
    }
    public function customerLoginAttemptsJson(): void
    {
        $this->send($this->mediator->send(new ListLoginAttemptsQuery($_GET)));
    }
    public function jwtTokens(): void
    {
        ViewResponse::render('admin/jwt-tokens', $this->mediator->send(new ShowTokensQuery()));
    }
    public function jwtTokensJson(): void
    {
        $this->send($this->mediator->send(new ListTokensQuery()));
    }
    public function createJwtToken(): void
    {
        $this->send($this->mediator->send(new CreateTokenCommand($this->jsonBody())));
    }
    public function revokeJwtToken(): void
    {
        $this->send($this->mediator->send(new RevokeTokenCommand($this->jsonBody())));
    }
    public function customerIpsJson(string $customerId): void
    {
        $this->send($this->mediator->send(new ListCustomerIpsQuery($customerId)));
    }
    public function flagIp(string $id): void
    {
        $this->send($this->mediator->send(new FlagIpCommand($id, $this->jsonBody())));
    }
    private function send(JsonResult $result): never
    {
        JsonResponse::send($result->data, $result->statusCode);
    }
    private function jsonBody(): array
    {
        $body = json_decode(file_get_contents('php://input'), true);
        return is_array($body) ? $body : [];
    }
}
