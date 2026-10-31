<?php

declare(strict_types=1);

namespace App\Modules\WhatsApp\Features\SendTestMessage;

use App\Modules\Customers\Contracts\ICustomerRepository;
use App\Modules\WhatsApp\Contracts\IWhatsAppAdminRepository;
use App\Services\StripeService;
use App\Services\WhatsAppService;
use DateTimeImmutable;

final readonly class Handler
{
    public function __construct(
        private ICustomerRepository $customers,
        private IWhatsAppAdminRepository $whatsAppData,
    )
    {
    }

    public function handle(Command $command): Result
    {
        $p = $command->payload;
        $expected = (string)($_SESSION['whatsapp_test_csrf'] ?? '');
        if ($expected === '' || !hash_equals($expected, (string)($p['csrf_token'] ?? ''))) {
            return Result::forbidden();
        }
        $customerId = trim((string)($p['customerId'] ?? ''));
        $phone = trim((string)($p['phone'] ?? ''));
        $firstName = trim((string)($p['firstName'] ?? ''));
        $template = (string)($p['template'] ?? '');
        if (
            $customerId === ''
            || $phone === ''
            || $firstName === ''
            || !in_array($template, ['subscription', 'warning', 'finalized', 'last_day'], true)
        ) {
            return $this->fail('Completa cliente, teléfono, nombre del socio y template.');
        }
        $customer = $this->customers->find($customerId);
        if (!$customer) {
            return $this->fail('El cliente seleccionado no existe.');
        }
        $startDate = (string)($p['startDate'] ?? '');
        $endDate = (string)($p['endDate'] ?? '');
        $start = null;
        $end = null;
        if ($template === 'subscription') {
            $start = DateTimeImmutable::createFromFormat('!Y-m-d', $startDate);
            $end = DateTimeImmutable::createFromFormat('!Y-m-d', $endDate);
            if (
                !$start
                || !$end
                || $start->format('Y-m-d') !== $startDate
                || $end->format('Y-m-d') !== $endDate
                || $end < $start
            ) {
                return $this->fail('Indica fechas de membresía válidas; la fecha final debe ser posterior o igual a la inicial.');
            }
        }
        $days = filter_var(
            $p['days'] ?? 3,
            FILTER_VALIDATE_INT,
            [
                'options' => [
                    'min_range' => 1,
                    'max_range' => 365,
                ],
            ],
        );
        if ($template === 'warning' && $days === false) {
            return $this->fail('Los días de aviso deben estar entre 1 y 365.');
        }
        $limitDebug = null;
        $limitError = null;
        try {
            $config = require dirname(__DIR__, 5) . '/config/stripe.php';
            $stripe = new StripeService($config['secret_key'], $config['test_clock_id'] ?? null);
            $item = $stripe->getCurrentPlan($customer['billingId'] ?? null, true);
            $plan = $item['plan'] ?? throw new \RuntimeException(
                'No se pudo obtener la información del plan actual del cliente',
            );
            $usage = $this->whatsAppData->monthlyMessageUsage($customerId);
            $count = (int)($usage['Total'] ?? 0);
            $max = (int)($plan['rules']['max_messages'] ?? 0);
            $reached = $count >= $max;
            $limitDebug = [
                'messages_counted' => $count,
                'messages_limit' => $max,
                'plan_lookup_key' => $plan['lookup_key'] ?? null,
                'subscription_status' => $plan['subscription_status'] ?? null,
                'limit_rule_present' => array_key_exists('max_messages', $plan['rules'] ?? []),
                'count_source' => 'database_current_date',
                'comparison_month' => $usage['ComparedMonth'] ?? date('Y-m'),
                'limit_reached' => $reached,
            ];
            if ($reached) {
                throw new \RuntimeException('Se ha alcanzado el límite de mensajes permitidos para este mes');
            }
        } catch (\Throwable $e) {
            $limitError = $e->getMessage();
        }
        try {
            $service = new WhatsAppService($customerId);
            $service->setIsDebug(true);
            $service->setMessageLimitDebug($limitDebug);
            $result = match ($template) {
                'subscription' => $service->sendSubscriptionTemplate(
                    $phone,
                    $firstName,
                    $start->format('d/m/Y'),
                    $end->format('d/m/Y'),
                    $customerId,
                    null,
                    null,
                    $firstName,
                    $limitError,
                ),
                'warning' => $service->sendWarningTemplate(
                    $phone,
                    (string)$days,
                    $customerId,
                    null,
                    null,
                    $firstName,
                    $limitError,
                ),
                'finalized' => $service->sendFinalizedTemplate(
                    $phone,
                    $customerId,
                    null,
                    null,
                    $firstName,
                    $limitError,
                ),
                'last_day' => $service->sendLastDayTemplate(
                    $phone,
                    $customerId,
                    null,
                    null,
                    $firstName,
                    $limitError,
                ),
            };
            $_SESSION['whatsapp_test_result'] = [
                'success' => (bool)($result['success'] ?? false),
                'message' => ($result['success'] ?? false)
                    ? 'Mensaje de prueba aceptado por WhatsApp. Revisa el historial para ver la comparación.'
                    : 'No se envió el mensaje de prueba: ' . ($result['errorMessage'] ?? 'Error desconocido'),
            ];
        } catch (\Throwable $e) {
            $_SESSION['whatsapp_test_result'] = [
                'success' => false,
                'message' => 'No se pudo procesar el mensaje de prueba: ' . $e->getMessage(),
            ];
        }

        return Result::redirect('/admin/whatsapp/messages?customerId=' . rawurlencode($customerId));
    }

    private function fail(string $message): Result
    {
        $_SESSION['whatsapp_test_result'] = [
            'success' => false,
            'message' => $message,
        ];

        return Result::redirect('/admin/whatsapp/messages');
    }
}
