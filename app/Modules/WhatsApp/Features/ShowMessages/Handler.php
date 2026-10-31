<?php

declare(strict_types=1);

namespace App\Modules\WhatsApp\Features\ShowMessages;

use App\Application\Contracts\ICurrentUserProvider;
use App\Modules\Customers\Contracts\ICustomerRepository;
use App\Modules\WhatsApp\Contracts\IWhatsAppAdminRepository;
use DateTimeImmutable;

final readonly class Handler
{
    public function __construct(
        private ICustomerRepository $customers,
        private IWhatsAppAdminRepository $messages,
        private ICurrentUserProvider $users,
    )
    {
    }
    public function handle(Query $query): array
    {
        if (empty($_SESSION['whatsapp_test_csrf'])) {
            $_SESSION['whatsapp_test_csrf'] = bin2hex(random_bytes(32));
        }
        $testResult = $_SESSION['whatsapp_test_result'] ?? null;
        unset($_SESSION['whatsapp_test_result']);
        $p = $query->parameters;
        $validDate = static function ($value): string {
            $value = trim((string)$value);
            $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
            return $date && $date->format('Y-m-d') === $value ? $value : '';
        };
        $filters = [
            'customerId' => trim((string)($p['customerId'] ?? '')),
            'status' => in_array($p['status'] ?? '', ['success', 'failed'], true)
                ? $p['status']
                : '',
            'isDebug' => in_array($p['isDebug'] ?? '', ['debug', 'normal'], true)
                ? $p['isDebug']
                : '',
            'from' => $validDate($p['from'] ?? ''),
            'to' => $validDate($p['to'] ?? ''),
            'error' => mb_substr(trim((string)($p['error'] ?? '')), 0, 200),
        ];
        $page = max(1, (int)($p['page'] ?? 1));
        $perPage = (int)($p['perPage'] ?? 50);
        if (!in_array($perPage, [25, 50, 100], true)) {
            $perPage = 50;
        }

        return [
            'currentUser' => $this->users->current(),
            'isAuthenticated' => true,
            'customers' => $this->customers->getAll(),
            'messages' => $this->messages->messages($filters, $page, $perPage),
            'filters' => $filters,
            'testCsrf' => $_SESSION['whatsapp_test_csrf'],
            'testResult' => $testResult,
        ];
    }
}
