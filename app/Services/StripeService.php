<?php

namespace App\Services;

/**
 * Servicio de Stripe para manejo de tarjetas, clientes y suscripciones
 */
class StripeService
{
    private \Stripe\StripeClient $stripe;
    private string $offlineMessage = 'Verifique su conexión a internet';

    private ?string $testClockId = null;

    public function __construct(string $apiKey, ?string $testClockId = null)
    {
        \Stripe\Stripe::setApiKey($apiKey);
        $this->stripe = new \Stripe\StripeClient($apiKey);
        $this->testClockId = $testClockId;
    }

    // ==================== TOKENS ====================

    /**
     * Genera un token de tarjeta
     */
    public function generateToken(array $cardData): array
    {
        try {
            $token = $this->stripe->tokens->create([
                'card' => [
                    'number'    => $cardData['number'],
                    'exp_month' => $cardData['exp_month'],
                    'exp_year'  => $cardData['exp_year'],
                    'cvc'       => $cardData['cvc'],
                    'name'      => $cardData['name'] ?? null,
                ]
            ]);

            return [
                'success' => true,
                'token_id' => $token->id
            ];
        } catch (\Stripe\Exception\CardException $e) {
            return [
                'success' => false,
                'error' => $this->mapCardError($e->getStripeCode())
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $this->offlineMessage
            ];
        }
    }

    // ==================== TARJETAS ====================

    /**
     * Agrega una tarjeta a un cliente usando un token
     */
    public function addCard(string $customerId, string $tokenId): array
    {
        try {
            $card = $this->stripe->customers->createSource($customerId, [
                'source' => $tokenId
            ]);

            if ($card->cvc_check === 'fail') {
                $this->deleteCard($customerId, $card->id);
                return [
                    'success' => false,
                    'error' => 'CVV incorrecto'
                ];
            }

            return [
                'success' => true,
                'card_id' => $card->id,
                'last4' => $card->last4,
                'brand' => $card->brand
            ];
        } catch (\Stripe\Exception\CardException $e) {
            return [
                'success' => false,
                'error' => $this->mapCardError($e->getStripeCode(), $e->getDeclineCode())
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $this->offlineMessage
            ];
        }
    }

    /**
     * Elimina una tarjeta de un cliente
     */
    public function deleteCard(string $customerId, string $cardId): bool
    {
        try {
            $this->stripe->customers->deleteSource($customerId, $cardId);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Lista todas las tarjetas de un cliente
     */
    public function listCards(string $customerId): array
    {
        try {
            $customer = $this->stripe->customers->retrieve($customerId);
            $defaultSource = $customer->default_source;

            $paymentMethods = $this->stripe->paymentMethods->all([
                'customer' => $customerId,
                'type' => 'card'
            ]);

            $cards = [];
            foreach ($paymentMethods->data as $pm) {
                $cards[] = [
                    'id' => $pm->id,
                    'last4' => $pm->card->last4,
                    'brand' => $pm->card->brand,
                    'exp_month' => $pm->card->exp_month,
                    'exp_year' => $pm->card->exp_year,
                    'name' => $pm->billing_details->name,
                    'is_default' => $pm->id === $defaultSource
                ];
            }

            return [
                'success' => true,
                'cards' => $cards
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $this->offlineMessage
            ];
        }
    }

    /**
     * Cambia la tarjeta por defecto de un cliente
     */
    public function setDefaultCard(string $customerId, string $cardId): array
    {
        try {
            $customer = $this->stripe->customers->update($customerId, [
                'default_source' => $cardId
            ]);

            return [
                'success' => true,
                'customer_id' => $customer->id
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $this->offlineMessage
            ];
        }
    }

    // ==================== CLIENTES ====================

    /**
     * Crea un nuevo cliente en Stripe
     */
    public function createCustomer(string $name, string $email, ?string $phone = null): array
    {
        try {
            $options = [
                'description' => $name,
                'email' => $email,
                'phone' => $phone
            ];

            // Si hay test clock configurado, usarlo
            if ($this->testClockId !== null) {
                $options['test_clock'] = $this->testClockId;
            }

            $customer = $this->stripe->customers->create($options);

            return [
                'success' => true,
                'customer' => $customer
            ];
        } catch (\Stripe\Exception\InvalidRequestException $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $this->offlineMessage,
                'debug' => $e->getMessage()
            ];
        }
    }

    /**
     * Obtiene un cliente por ID
     */
    public function getCustomer(string $customerId): array
    {
        try {
            $customer = $this->stripe->customers->retrieve($customerId);

            return [
                'success' => true,
                'customer' => [
                    'id' => $customer->id,
                    'email' => $customer->email,
                    'phone' => $customer->phone,
                    'description' => $customer->description,
                    'default_source' => $customer->default_source
                ]
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $this->offlineMessage
            ];
        }
    }

    /**
     * Actualiza los datos de un cliente
     */
    public function updateCustomer(string $customerId, ?string $name = null, ?string $email = null, ?string $phone = null): array
    {
        try {
            $updateData = [];
            if ($name !== null) $updateData['description'] = $name;
            if ($email !== null) $updateData['email'] = $email;
            if ($phone !== null) $updateData['phone'] = $phone;

            $customer = $this->stripe->customers->update($customerId, $updateData);

            return [
                'success' => true,
                'customer_id' => $customer->id
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $this->offlineMessage
            ];
        }
    }

    /**
     * Elimina un cliente
     */
    public function deleteCustomer(string $customerId): bool
    {
        try {
            $this->stripe->customers->delete($customerId);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    // ==================== SUSCRIPCIONES ====================

    /**
     * Crea una suscripción para un cliente
     * NOTA: El test_clock se hereda automáticamente del cliente, no se pasa aquí
     * 
     * @param string $customerId ID del cliente en Stripe
     * @param string $priceId ID del precio/plan
     * @param int $trialDays Días de prueba (0 = sin trial)
     * @param string $paymentBehavior 'default_incomplete' (requiere confirmación) o 'error_if_incomplete' (cobra automáticamente o falla)
     */
    public function createSubscription(string $customerId, string $priceId, int $trialDays = 0, string $paymentBehavior = 'error_if_incomplete'): array
    {
        try {
            $options = [
                'customer' => $customerId,
                'items' => [['price' => $priceId]],
                'payment_behavior' => $paymentBehavior, // 'error_if_incomplete' cobra automáticamente si hay tarjeta
                'expand' => ['latest_invoice.payment_intent', 'items.data.price']
            ];

            // if ($trialDays > 0) {
            //     $options['trial_period_days'] = $trialDays;
            // }

            // NO se pasa test_clock aquí - se hereda automáticamente del cliente

            $subscription = $this->stripe->subscriptions->create($options);

            // Verificar si requiere autenticación 3DS
            $paymentIntent = $subscription->latest_invoice->payment_intent ?? null;
            if ($paymentIntent && in_array($paymentIntent->status, ['requires_action', 'requires_payment_method'])) {
                return [
                    'success' => false,
                    'requires_action' => true,
                    'client_secret' => $paymentIntent->client_secret,
                    'error' => 'Pago pendiente de autenticación'
                ];
            }

            return [
                'success' => true,
                'subscription_id' => $subscription->id,
                'status' => $subscription->status,
                'current_period_end' => $subscription->current_period_end
            ];
        } catch (\Stripe\Exception\InvalidRequestException $e) {
            // Error específico de test_clock mismatch
            if (strpos($e->getMessage(), 'test clock') !== false) {
                return [
                    'success' => false,
                    'error' => 'El cliente debe estar asociado al mismo test clock. Crea un nuevo cliente en modo DEV.',
                    'stripe_error' => $e->getMessage()
                ];
            }
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        } catch (\Stripe\Exception\CardException $e) {
            return [
                'success' => false,
                'error' => $this->mapCardError($e->getStripeCode(), $e->getDeclineCode())
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $this->offlineMessage,
                'debug' => $e->getMessage()
            ];
        }
    }

    /**
     * Obtiene la suscripción activa de un cliente, si no se encuentra devuelve la ultima suscripción aunque esté cancelada
     */
    public function getActiveSubscription(string $customerId): array
    {
        try {
            $subscriptions = $this->stripe->subscriptions->all([
                'customer' => $customerId,
                'limit' => 10,
                'expand' => ['data.items.data.price']
            ]);

            $active = null;
            foreach ($subscriptions->data as $sub) {
                if (in_array($sub->status, ['active', 'trialing'])) {
                    $active = $sub;
                    break;
                }
            }

            if (!$active) {
                if(count($subscriptions->data) === 0) {
                    return [
                        'success' => true,
                        'has_subscription' => false,
                        'subscription' => null
                    ];
                    
                }
                $last = end($subscriptions->data);
                return [
                    'success' => true,
                    'has_subscription' => false,
                    'subscription' => $last ? [
                        'id' => $last->id,
                        'status' => $last->status,
                        'lookup_key' => $last->items->data[0]->price->lookup_key ?? null,
                        'unit_amount' => $last->items->data[0]->price->unit_amount ?? null,
                        'cancel_at_period_end' => $last->cancel_at_period_end,
                        'current_period_end' => $last->current_period_end,
                        'price_name' => $last->items->data[0]->price->nickname ?? null
                    ] : null
                ];
            }

            $price = $active->items->data[0]->price ?? null;
            

            return [
                'success' => true,
                'has_subscription' => true,
                'subscription' => [
                    'id' => $active->id,
                    'status' => $active->status,
                    'lookup_key' => $price->lookup_key ?? null,
                    'unit_amount' => $price->unit_amount ?? null,
                    'cancel_at_period_end' => $active->cancel_at_period_end,
                    'current_period_end' => $active->current_period_end,
                    'price_name' => $price->nickname ?? null

                ]
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $this->offlineMessage
            ];
        }
    }

    /**
     * Actualiza una suscripción (cancelar al final del período o reactivar)
     */
    public function updateSubscription(string $subscriptionId, bool $cancelAtPeriodEnd): array
    {
        try {
            $subscription = $this->stripe->subscriptions->update($subscriptionId, [
                'cancel_at_period_end' => $cancelAtPeriodEnd
            ]);

            return [
                'success' => true,
                'subscription_id' => $subscription->id
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $this->offlineMessage
            ];
        }
    }

    /**
     * Cambia el plan de una suscripción
     */
    public function changePlan(string $subscriptionId, string $newPriceId): array
    {
        try {
            $subscription = $this->stripe->subscriptions->retrieve($subscriptionId, [
                'expand' => ['items.data.price']
            ]);

            $currentItemId = $subscription->items->data[0]->id ?? null;

            $updateOptions = [
                'proration_behavior' => 'none',
                'cancel_at_period_end' => false,
                'items' => []
            ];

            if ($currentItemId) {
                $updateOptions['items'][] = [
                    'id' => $currentItemId,
                    'price' => $newPriceId
                ];
            } else {
                $updateOptions['items'][] = ['price' => $newPriceId];
            }

            $updated = $this->stripe->subscriptions->update($subscriptionId, $updateOptions);

            return [
                'success' => true,
                'subscription_id' => $updated->id
            ];
        } catch (\Stripe\Exception\CardException $e) {
            return [
                'success' => false,
                'error' => $this->mapCardError($e->getStripeCode(), $e->getDeclineCode())
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $this->offlineMessage
            ];
        }
    }

    // ==================== PRECIOS/PRODUCTOS ====================

    /**
     * Obtiene el ID de un precio por su lookup_key
     */
    public function getPriceIdByLookupKey(string $lookupKey): ?string
    {
        try {
            $prices = $this->stripe->prices->all([
                'lookup_keys' => [$lookupKey],
                'active' => true
            ]);

            return $prices->data[0]->id ?? null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Lista todos los precios activos de un producto
     */
    public function getProductPrices(string $productId): array
    {
        try {
            $prices = $this->stripe->prices->all([
                'product' => $productId,
                'active' => true,
                'expand' => ['data.product']
            ]);

            $result = [];
            foreach ($prices->data as $price) {
                $result[] = [
                    'id' => $price->id,
                    'lookup_key' => $price->lookup_key,
                    'unit_amount' => $price->unit_amount,
                    'currency' => $price->currency,
                    'nickname' => $price->nickname,
                    'recurring' => $price->recurring ? [
                        'interval' => $price->recurring->interval,
                        'interval_count' => $price->recurring->interval_count
                    ] : null
                ];
            }

            return [
                'success' => true,
                'prices' => $result
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $this->offlineMessage
            ];
        }
    }

    // ==================== HELPERS ====================

    /**
     * Mapea códigos de error de tarjeta a mensajes en español
     */
    private function mapCardError(string $code, ?string $declineCode = null): string
    {
        $errors = [
            'card_declined' => 'La tarjeta fue rechazada',
            'expired_card' => 'La tarjeta ha expirado',
            'incorrect_cvc' => 'El código de seguridad es incorrecto',
            'incorrect_number' => 'El número de tarjeta es incorrecto',
            'invalid_expiry_month' => 'El mes de vencimiento es inválido',
            'invalid_expiry_year' => 'El año de vencimiento es inválido',
            'invalid_number' => 'El número de tarjeta es inválido',
            'invalid_cvc' => 'El código de seguridad es inválido',
            'processing_error' => 'Error durante el procesamiento del pago'
        ];

        if ($code === 'card_declined' && $declineCode) {
            $declineErrors = [
                'generic_decline' => 'La tarjeta fue rechazada por un motivo desconocido',
                'insufficient_funds' => 'La tarjeta no tiene fondos suficientes',
                'lost_card' => 'La tarjeta figura como perdida',
                'stolen_card' => 'La tarjeta figura como robada',
                'call_issuer' => 'Contacte al emisor de la tarjeta'
            ];
            return $declineErrors[$declineCode] ?? "Tarjeta rechazada: {$declineCode}";
        }

        return $errors[$code] ?? "Error de Stripe: {$code}";
    }

    /**
     * Resuelve el nombre legible de un plan
     */
    public function resolvePlanDisplayName(?string $lookupKey): string
    {
        $plans = [
            'essential_monthly' => 'Plan Básico',
            'intermediate_monthly' => 'Plan Intermedio',
            'professional_monthly' => 'Plan Profesional'
        ];

        return $plans[$lookupKey] ?? 'Plan actual';
    }
    /**
 * Obtiene una vista previa del cobro al cambiar de plan (proration)
 * 
 * @param string $subscriptionId ID de la suscripción actual
 * @param string $newPriceId ID del nuevo precio/plan
 * @return array Resultado con el preview del cobro
 */
public function previewPlanChange(string $subscriptionId, string $newPriceId): array
{
    try {
        // Obtener la suscripción actual
        $subscription = $this->stripe->subscriptions->retrieve($subscriptionId);
        
        if (!$subscription || $subscription->status === 'canceled') {
            return ['success' => false, 'error' => 'Suscripción no encontrada o cancelada'];
        }

        // Obtener el item actual de la suscripción
        $currentItem = $subscription->items->data[0] ?? null;
        if (!$currentItem) {
            return ['success' => false, 'error' => 'No se encontró el plan actual'];
        }

        // Obtener el precio actual y el nuevo para comparar
        $currentPrice = $this->stripe->prices->retrieve($currentItem->price->id);
        $newPrice = $this->stripe->prices->retrieve($newPriceId);

        $currentAmount = $currentPrice->unit_amount ?? 0;
        $newAmount = $newPrice->unit_amount ?? 0;
        $isUpgrade = $newAmount > $currentAmount;

        // Crear una invoice preview (upcoming invoice) con el nuevo precio
        // Nota: upcoming() es un método especial que no está en StripeClient, usar la clase estática
        $invoice = $this->stripe->invoices->createPreview([
            'customer' => $subscription->customer,
            'subscription' => $subscriptionId,
            'subscription_details' => [
                'items' => [
                    [
                        'id' => $currentItem->id,
                        'price' => $newPriceId,
                    ],
                ],
                'proration_behavior' => 'none',
            ],
        ]);

        // Calcular el monto del prorrateo
        $prorationAmount = 0;
        foreach ($invoice->lines->data as $line) {
            if ($line->proration) {
                $prorationAmount += $line->amount;
            }
        }

        $currency = strtoupper($invoice->currency ?? 'mxn');
        $amountDue = $invoice->amount_due ?? 0;

        return [
            'success' => true,
            'preview' => [
                'amount_due' => $amountDue,
                'amount_due_formatted' => $this->formatMoney($amountDue, $currency),
                'currency' => $currency,
                'proration_amount' => $prorationAmount,
                'proration_formatted' => $this->formatMoney($prorationAmount, $currency),
                'credit_balance' => abs($invoice->starting_balance ?? 0),
                'new_plan_amount' => $newAmount,
                'new_plan_formatted' => $this->formatMoney($newAmount, $currency),
                'current_plan_amount' => $currentAmount,
                'current_plan_formatted' => $this->formatMoney($currentAmount, $currency),
                'billing_date' => date('Y-m-d', $invoice->next_payment_attempt ?? time()),
                'is_upgrade' => $isUpgrade,
                'immediate_charge' => $isUpgrade && $amountDue > 0,
                'new_plan_name' => $newPrice->nickname ?? $newPrice->lookup_key ?? 'Plan',
            ]
        ];

    } catch (\Stripe\Exception\ApiErrorException $e) {
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    } catch (\Exception $e) {
        return [
            'success' => false,
            'error' => $this->offlineMessage
        ];
    }
}

/**
 * Obtiene una vista previa del cobro para crear una nueva suscripción
 * 
 * @param string $customerId ID del cliente
 * @param string $priceId ID del precio/plan
 * @param int $trialDays Días de prueba gratuita
 * @return array Resultado con el preview del cobro
 */
public function previewNewSubscription(string $customerId, string $priceId, int $trialDays = 0): array
{
    try {
        $price = $this->stripe->prices->retrieve($priceId);
        
        if (!$price) {
            return ['success' => false, 'error' => 'Precio no encontrado'];
        }

        $amount = $price->unit_amount ?? 0;
        $currency = strtoupper($price->currency ?? 'mxn');
        
        // Si hay días de prueba, el cobro inicial es 0
        $amountDue = $amount;
        $billingDate = date('Y-m-d');

        return [
            'success' => true,
            'preview' => [
                'amount_due' => $amountDue,
                'amount_due_formatted' => $this->formatMoney($amountDue, $currency),
                'currency' => $currency,
                'proration_amount' => 0,
                'proration_formatted' => $this->formatMoney(0, $currency),
                'credit_balance' => 0,
                'new_plan_amount' => $amount,
                'new_plan_formatted' => $this->formatMoney($amount, $currency),
                'current_plan_amount' => 0,
                'current_plan_formatted' => $this->formatMoney(0, $currency),
                'billing_date' => $billingDate,
                'is_upgrade' => true,
                'immediate_charge' => false,
                'new_plan_name' => $price->nickname ?? $price->lookup_key ?? 'Plan',
            ]
        ];

    } catch (\Stripe\Exception\ApiErrorException $e) {
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    } catch (\Exception $e) {
        return [
            'success' => false,
            'error' => $this->offlineMessage
        ];
    }
}

/**
 * Formatea un monto en centavos a formato de moneda legible
 */
private function formatMoney(int $amountInCents, string $currency = 'MXN'): string
{
    $amount = $amountInCents / 100;
    return '$' . number_format($amount, 2) . ' ' . $currency;
}

/**
 * Obtiene el paquete actual del cliente basándose en su suscripción activa
 * Si no tiene suscripción activa, devuelve 'free'
 * 
 * @param string $customerId ID del cliente en Stripe
 * @return array Información del paquete actual con sus reglas
 */
public function getCurrentPlan(string $customerId): array
{
    try {
        $config = require __DIR__ . '/../../config/stripe.php';
        $plans = $config['plans'] ?? [];
        
        // Obtener suscripción activa
        $subscriptionResult = $this->getActiveSubscription($customerId);
        
        if (!$subscriptionResult['success']) {
            return [
                'success' => false,
                'error' => $subscriptionResult['error'] ?? 'Error al obtener suscripción'
            ];
        }
        
        // Si no tiene suscripción activa, devolver paquete free
        if (!$subscriptionResult['has_subscription']) {
            $freeplan = $plans['free'] ?? null;
            if (!$freeplan) {
                return [
                    'success' => true,
                    'plan' => [
                        'name' => 'Free',
                        'lookup_key' => 'free',
                        'is_free' => true,
                        'rules' => []
                    ]
                ];
            }
            
            return [
                'success' => true,
                'plan' => [
                    'name' => $freeplan['name'],
                    'lookup_key' => 'free',
                    'is_free' => true,
                    'rules' => $freeplan['rules'] ?? []
                ]
            ];
        }
        
        // Obtener lookup_key de la suscripción activa
        $subscription = $subscriptionResult['subscription'];
        $lookupKey = $subscription['lookup_key'] ?? null;
        
        // Buscar el paquete correspondiente
        $currentplan = $plans[$lookupKey] ?? null;
        
        if (!$currentplan) {
            // Si no se encuentra el paquete, devolver info básica
            return [
                'success' => true,
                'plan' => [
                    'name' => $subscription['price_name'] ?? $lookupKey ?? 'Plan Desconocido',
                    'lookup_key' => $lookupKey,
                    'is_free' => false,
                    'subscription_status' => $subscription['status'],
                    'current_period_end' => $subscription['current_period_end'],
                    'rules' => []
                ]
            ];
        }
        
        return [
            'success' => true,
            'plan' => [
                'name' => $currentplan['name'],
                'lookup_key' => $lookupKey,
                'is_free' => false,
                'subscription_id' => $subscription['id'],
                'subscription_status' => $subscription['status'],
                'cancel_at_period_end' => $subscription['cancel_at_period_end'],
                'current_period_end' => $subscription['current_period_end'],
                'unit_amount' => $subscription['unit_amount'],
                'rules' => $currentplan['rules'] ?? []
            ]
        ];
        
    } catch (\Exception $e) {
        return [
            'success' => false,
            'error' => $this->offlineMessage
        ];
    }
}
}
