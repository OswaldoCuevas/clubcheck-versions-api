<?php
include_once __DIR__ . '/../utils/database.php';
require_once __DIR__ . '/../app/Services/StripeService.php';
require_once __DIR__ . '/../app/Exceptions/ApiException.php';
require_once __DIR__ . '/../app/Exceptions/NotFoundException.php';  

use App\Services\StripeService;
use App\Exceptions\ApiException;
use App\Exceptions\NotFoundException;


class CustomerPermits
{
    private Database $db;
    private $customer;

    public function __construct($customerId)
    {
        $this->db = new Database();
        $this->customer = $this->db->fetchOne("SELECT * FROM Customers WHERE Id = ?", [$customerId]);

        if (!$this->customer) {
            throw new ApiException('Cliente no encontrado con ID: ' . $customerId);
        }
    }
    public function checkSendMessage($totalMessagesSentThisMonth = null)
    {
        $plan = $this->getCurrentPlan();

        $rules = $plan['rules'] ?? [];
        $maxMessages = $rules['max_messages'] ?? 0;

        if ($totalMessagesSentThisMonth === null) {
            $row = $this->db->fetchOne("SELECT COUNT(*) as Total FROM MessageSent WHERE Successful = 1 AND CustomerApiId = ? AND MONTH(DateSent) = MONTH(CURRENT_DATE()) AND YEAR(DateSent) = YEAR(CURRENT_DATE())", [$this->customer['Id']]);
            $totalMessagesSentThisMonth = $row['Total'] ?? 0;
        }

        if ($totalMessagesSentThisMonth >= $maxMessages) {
            throw new ApiException('Se ha alcanzado el límite de mensajes permitidos para este mes');
        }
    }

    private function getCurrentPlan()
    {
        $config = require __DIR__ . '/../config/stripe.php';
        
        $stripeService = new StripeService(
            $config['secret_key'],
            $config['test_clock_id'] ?? null
        );

        $itemPlan = $stripeService->getCurrentPlan($this->customer['BillingId']);

        if (!$itemPlan) {
            throw new NotFoundException('No se pudo determinar el plan actual del cliente');
        }

        $plan = $itemPlan['plan'] ?? null;
        if (!$plan) {
            throw new NotFoundException('No se pudo obtener la información del plan actual del cliente');
        }

        return $plan;
    }



}