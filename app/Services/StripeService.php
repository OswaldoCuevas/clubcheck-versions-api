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
     * @param string|null $couponCode Código de cupón opcional
     */
    public function createSubscription(string $customerId, string $priceId, int $trialDays = 0, string $paymentBehavior = 'error_if_incomplete', ?string $couponCode = null): array
    {
        try {
            // Obtener información del precio para verificar su tipo
            $price = $this->stripe->prices->retrieve($priceId);
            
            // Si es un precio one-time (permanente), usar createOneTimePayment
            if ($price->type === 'one_time') {
                return $this->createOneTimePayment($customerId, $priceId, $couponCode);
            }

            // Validar cupón si se proporcionó
            $couponInfo = null;
            if ($couponCode !== null && $couponCode !== '') {
                $couponValidation = $this->validateCoupon($couponCode);
                if (!$couponValidation['success']) {
                    return $couponValidation; // Retornar el error del cupón
                }
                $couponInfo = $couponValidation['coupon'];
            }

            // si se mando un cupon y el precio es anual entonces no se acepta
                if($couponInfo && $priceId ){
                    $price = $this->stripe->prices->retrieve($priceId);
                    if($price->recurring && $price->recurring->interval === 'year') {
                        return [
                            'success' => false,
                            'error' => 'Los cupones no son aplicables a planes anuales'
                        ];
                    }
                }

            $options = [
                'customer' => $customerId,
                'items' => [['price' => $priceId]],
                'payment_behavior' => $paymentBehavior, // 'error_if_incomplete' cobra automáticamente si hay tarjeta
                'expand' => ['latest_invoice.payment_intent', 'items.data.price']
            ];

            // Aplicar cupón si es válido (usar 'discounts' para compatibilidad con billing_mode flexible)
            if ($couponInfo) {
                if ($couponInfo['is_promotion_code']) {
                    // Si es un promotion code, usar el promotion_code_id
                    $options['discounts'] = [['promotion_code' => $couponInfo['promotion_code_id']]];
                } else {
                    // Si es un cupón directo, usar el coupon_id
                    $options['discounts'] = [['coupon' => $couponInfo['coupon_id']]];
                }
            }

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
                'current_period_end' => $subscription->current_period_end ?? $subscription->items->data[0]->current_period_end ?? null
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
     * Obtiene por el lookup_key las reglas y límites de un plan para aplicar las restricciones correspondientes en la aplicación
     */
    public function getPlanRulesByLookupKey(string $lookupKey): ?array
    {
        $config = require __DIR__ . '/../../config/stripe.php';
        $plansConfig = $config['plans'] ?? [];
        return $plansConfig[$lookupKey] ?? null;
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

            $permanentLicense = null;
            try {
                $customer = $this->stripe->customers->retrieve($customerId);
                $permanentLicensePriceId = $customer->metadata->permanent_license_price_id ?? null;
                if ($permanentLicensePriceId) {
                    $permPrice = $this->stripe->prices->retrieve($permanentLicensePriceId);
                    $permanentLicense = [
                        'id' => null,
                        'status' => 'active',
                        'lookup_key' => $permPrice->lookup_key ?? null,
                        'unit_amount' => $permPrice->unit_amount ?? null,
                        'cancel_at_period_end' => false,
                        'current_period_end' => null,
                        'price_name' => $permPrice->nickname ?? null,
                        'is_permanent_license' => true,
                    ];
                }
            } catch (\Exception $e) {
                // No interrumpir si falla la consulta de licencia permanente
            }

            if (!$active) {
                if(count($subscriptions->data) === 0) {
                    return [
                        'success' => true,
                        'has_subscription' => false,
                        'subscription' => null,
                        'permanent_license' => $permanentLicense
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
                        'current_period_end' => $last->current_period_end ?? $last->items->data[0]->current_period_end ?? null,
                        'price_name' => $last->items->data[0]->price->nickname ?? null
                    ] : null,
                    'permanent_license' => $permanentLicense
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
                    'current_period_end' => $active->current_period_end ?? $active->items->data[0]->current_period_end ?? null,
                    'price_name' => $price->nickname ?? null,
                    'permanent_license' => $permanentLicense

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
                'subscription_id' => $subscription->id,
                'current_period_end' => $subscription->current_period_end ?? $subscription->items->data[0]->current_period_end ?? null,
                'status' => $subscription->status,
                'cancel_at_period_end' => $subscription->cancel_at_period_end
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
     * 
     * @param string $subscriptionId ID de la suscripción
     * @param string $newPriceId ID del nuevo precio/plan
     * @param string|null $couponCode Código de cupón opcional
     */
    public function changePlan(string $subscriptionId, string $newPriceId, ?string $couponCode = null): array
    {
        try {
            // Obtener información del nuevo precio
            $newPrice = $this->stripe->prices->retrieve($newPriceId);
            
            // Si es un precio one-time, no se puede "cambiar" a él desde una suscripción
            // Se debe cancelar la suscripción y crear un pago único por separado
            if ($newPrice->type === 'one_time') {
                return [
                    'success' => false,
                    'error' => 'No se puede cambiar una suscripción a un plan permanente. Debe cancelar la suscripción y adquirir el plan permanente por separado.',
                    'requires_separate_purchase' => true,
                    'price_id' => $newPriceId
                ];
            }

            // Validar cupón si se proporcionó
            $couponInfo = null;
            if ($couponCode !== null && $couponCode !== '') {
                $couponValidation = $this->validateCoupon($couponCode);
                if (!$couponValidation['success']) {
                    return $couponValidation; // Retornar el error del cupón
                }
                $couponInfo = $couponValidation['coupon'];
            }

              // si se mando un cupon y el precio es anual entonces no se acepta
                if($couponInfo && $newPriceId ){
                    $price = $this->stripe->prices->retrieve($newPriceId);
                    if($price->recurring && $price->recurring->interval === 'year') {
                        return [
                            'success' => false,
                            'error' => 'Los cupones no son aplicables a planes anuales'
                        ];
                    }
                }

            $subscription = $this->stripe->subscriptions->retrieve($subscriptionId, [
                'expand' => ['items.data.price']
            ]);

            $currentItemId = $subscription->items->data[0]->id ?? null;

            // Determinar si el intervalo de facturación cambia (ej. mensual → anual).
            // Stripe solo acepta 'unchanged' cuando el intervalo NO cambia.
            // Si el intervalo cambia se usa 'now' para iniciar el nuevo ciclo inmediatamente.
            $currentInterval = $subscription->items->data[0]->price->recurring->interval ?? null;
            $newPrice = $this->stripe->prices->retrieve($newPriceId);
            $newInterval = $newPrice->recurring->interval ?? null;
            $intervalChanges = $currentInterval !== $newInterval;

            $updateOptions = [
                'proration_behavior' => $intervalChanges ? 'create_prorations' : 'none',
                'cancel_at_period_end' => false,
                'billing_cycle_anchor' => $intervalChanges ? 'now' : 'unchanged',
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

            // Aplicar cupón si es válido (usar 'discounts' para compatibilidad con billing_mode flexible)
            if ($couponInfo) {
                if ($couponInfo['is_promotion_code']) {
                    // Si es un promotion code, usar el promotion_code_id
                    $updateOptions['discounts'] = [['promotion_code' => $couponInfo['promotion_code_id']]];
                } else {
                    // Si es un cupón directo, usar el coupon_id
                    $updateOptions['discounts'] = [['coupon' => $couponInfo['coupon_id']]];
                }
            }

            $updated = $this->stripe->subscriptions->update($subscriptionId, $updateOptions);

            return [
                'success' => true,
                'subscription_id' => $updated->id,
                'current_period_end' => $updated->current_period_end ?? $updated->items->data[0]->current_period_end ?? null,
                'status' => $updated->status
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
                'limit' => 100,
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

            $stripeConfig = require __DIR__ . '/../../config/stripe.php';
            $plansConfig = $stripeConfig['plans'] ?? [];

            //remover los precios que no estén configurados en el config/stripe.php
            $result = array_filter($result, function($price) use ($plansConfig) {
                foreach ($plansConfig as $plan) {
                    if (($plan['lookup_key'] ?? null) === $price['lookup_key']) {
                        return true;
                    }
                }
                return false;
            });

            //agregar al precio showBillingIds que vbiene del config/stripe.php para saber si se muestra o no en la app dependiendo del billingId del cliente
            $result = array_map(function($price) use ($plansConfig) {
                foreach ($plansConfig as $plan) {
                    if (($plan['lookup_key'] ?? null) === $price['lookup_key'] && isset($plan['showBillingIds'])) {
                        $price['showBillingIds'] = $plan['showBillingIds'] ?? [];
                        break;
                    }
                }
                return $price;
            }, $result);

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

    // ==================== CUPONES ====================

    /**
     * Valida si un cupón existe y está activo
     * 
     * @param string $couponCode Código del cupón a validar (puede ser un promotion code o un coupon ID)
     * @return array Información del cupón o error
     */
    public function validateCoupon(string $couponCode): array
    {
        try {
            $coupon = null;
            $promotionCode = null;
            
            // Primero intentar buscar como Promotion Code (lo más común para usuarios)
            try {
                $promotionCodes = $this->stripe->promotionCodes->all([
                    'code' => $couponCode,
                    'active' => true,
                    'limit' => 1
                ]);
                
                if (!empty($promotionCodes->data)) {
                    $promotionCode = $promotionCodes->data[0];
                    
                    // Obtener el ID del cupón del promotion code
                    // Puede estar en 'coupon' directamente o en 'promotion.coupon' dependiendo de la versión de Stripe
                    $couponId = null;
                    
                    if (isset($promotionCode->coupon)) {
                        $couponId = $promotionCode->coupon;
                    } elseif (isset($promotionCode->promotion) && is_object($promotionCode->promotion)) {
                        $couponId = $promotionCode->promotion->coupon ?? null;
                    }
                    
                    if (!empty($couponId) && is_string($couponId)) {
                        // Hacer retrieve directo del cupón usando su ID para obtener toda su información
                        $coupon = $this->stripe->coupons->retrieve($couponId);
                    }
                }
            } catch (\Exception $e) {
                // Si falla, intentar como cupón directo
            }
            
            // Si no se encontró como promotion code, intentar como cupón directo
            if (!$coupon) {
                $coupon = $this->stripe->coupons->retrieve($couponCode);
            }

            if (!$coupon->valid) {
                return [
                    'success' => false,
                    'error' => 'El cupón no es válido o ha expirado'
                ];
            }

            // Validar si el promotion code está activo y no ha expirado
            if ($promotionCode) {
                if (!$promotionCode->active) {
                    return [
                        'success' => false,
                        'error' => 'El código promocional no está activo'
                    ];
                }
                
                if ($promotionCode->expires_at && $promotionCode->expires_at < time()) {
                    return [
                        'success' => false,
                        'error' => 'El código promocional ha expirado'
                    ];
                }
                
                // Verificar límites de uso del promotion code
                if ($promotionCode->max_redemptions) {
                    if ($promotionCode->times_redeemed >= $promotionCode->max_redemptions) {
                        return [
                            'success' => false,
                            'error' => 'El código promocional ha alcanzado su límite de usos'
                        ];
                    }
                }
            }

            // Preparar información del descuento
            $discountInfo = [];
            
            if ($coupon->percent_off !== null) {
                $discountInfo['type'] = 'percentage';
                $discountInfo['percent_off'] = $coupon->percent_off;
                $discountInfo['description'] = $coupon->percent_off . '% de descuento';
            } elseif ($coupon->amount_off !== null) {
                $discountInfo['type'] = 'fixed';
                $discountInfo['amount_off'] = $coupon->amount_off;
                $discountInfo['currency'] = strtoupper($coupon->currency ?? 'mxn');
                $discountInfo['description'] = $this->formatMoney($coupon->amount_off, $discountInfo['currency']) . ' de descuento';
            }

            // Información de duración
            $durationInfo = [
                'duration' => $coupon->duration
            ];
            
            if ($coupon->duration === 'repeating' && $coupon->duration_in_months) {
                $durationInfo['duration_in_months'] = $coupon->duration_in_months;
                $durationInfo['description'] = 'Válido por ' . $coupon->duration_in_months . ' meses';
            } elseif ($coupon->duration === 'once') {
                $durationInfo['description'] = 'Válido solo por el primer pago';
            } elseif ($coupon->duration === 'forever') {
                $durationInfo['description'] = 'Válido indefinidamente';
            }

            // Información de expiración (del promotion code o del cupón)
            $expirationInfo = [];
            if ($promotionCode && $promotionCode->expires_at) {
                $expirationInfo['expires_at'] = $promotionCode->expires_at;
                $expirationInfo['expires_at_formatted'] = date('Y-m-d H:i:s', $promotionCode->expires_at);
            } elseif ($coupon->redeem_by) {
                $expirationInfo['redeem_by'] = $coupon->redeem_by;
                $expirationInfo['redeem_by_formatted'] = date('Y-m-d H:i:s', $coupon->redeem_by);
            }else{
                $expirationInfo = null;
            }

            // Límites de uso (del promotion code tiene prioridad)
            $usageInfo = [];
            if ($promotionCode) {
                if ($promotionCode->max_redemptions) {
                    $usageInfo['max_redemptions'] = $promotionCode->max_redemptions;
                    $usageInfo['times_redeemed'] = $promotionCode->times_redeemed;
                    $usageInfo['remaining'] = $promotionCode->max_redemptions - $promotionCode->times_redeemed;
                }
            } elseif ($coupon->max_redemptions) {
                $usageInfo['max_redemptions'] = $coupon->max_redemptions;
                $usageInfo['times_redeemed'] = $coupon->times_redeemed;
                $usageInfo['remaining'] = $coupon->max_redemptions - $coupon->times_redeemed;
                
                if ($usageInfo['remaining'] <= 0) {
                    return [
                        'success' => false,
                        'error' => 'El cupón ha alcanzado su límite de usos'
                    ];
                }
            }

            return [
                'success' => true,
                'coupon' => [
                    'id' => $coupon->id,
                    'code' => $promotionCode ? $promotionCode->code : $coupon->id,
                    'name' => $coupon->name,
                    'valid' => $coupon->valid,
                    'discount' => $discountInfo,
                    'duration' => $durationInfo,
                    'expiration' => $expirationInfo,
                    'usage' => $usageInfo,
                    'is_promotion_code' => $promotionCode !== null,
                    'promotion_code_id' => $promotionCode ? $promotionCode->id : null,
                    'coupon_id' => $coupon->id
                ]
            ];
        } catch (\Stripe\Exception\InvalidRequestException $e) {
            return [
                'success' => false,
                'error' => $this->mapCouponError($e->getStripeCode(), $e->getMessage())
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
     * Mapea códigos de error de cupones a mensajes en español
     */
    private function mapCouponError(string $code, string $originalMessage = ''): string
    {
        $errors = [
            'resource_missing' => 'El cupón no existe',
            'coupon_expired' => 'El cupón ha expirado',
            'invalid_coupon' => 'El cupón no es válido',
            'coupon_not_valid' => 'El cupón no es válido para este producto'
        ];

        // Si el mensaje original contiene información sobre expiración
        if (stripos($originalMessage, 'expired') !== false) {
            return 'El cupón ha expirado';
        }

        return $errors[$code] ?? 'El cupón no es válido';
    }

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
 * @param string|null $couponCode Código de cupón opcional
 * @return array Resultado con el preview del cobro
 */
public function previewPlanChange(string $subscriptionId, string $newPriceId, ?string $couponCode = null): array
{
    try {
        // Obtener información del nuevo precio para verificar su tipo
        $newPrice = $this->stripe->prices->retrieve($newPriceId);
        
        // Si es un precio one-time, no se puede cambiar a él desde una suscripción
        if ($newPrice->type === 'one_time') {
            return [
                'success' => false,
                'error' => 'No se puede cambiar una suscripción a un plan permanente. Debe cancelar la suscripción y adquirir el plan permanente por separado.',
                'requires_separate_purchase' => true,
                'price_id' => $newPriceId
            ];
        }

        // Validar cupón si se proporcionó
        $couponInfo = null;
        if ($couponCode !== null && $couponCode !== '') {
            $couponValidation = $this->validateCoupon($couponCode);
            if (!$couponValidation['success']) {
                return $couponValidation; // Retornar el error del cupón
            }
            $couponInfo = $couponValidation['coupon'];
        }

        // si se mando un cupon y el precio es anual entonces no se acepta
        if($couponInfo && $newPriceId ){
            $price = $this->stripe->prices->retrieve($newPriceId);
            if($price->recurring && $price->recurring->interval === 'year') {
                return [
                    'success' => false,
                    'error' => 'Los cupones no son aplicables a planes anuales'
                ];
            }
        }
        
        // Obtener la suscripción actual
        $subscription = $this->stripe->subscriptions->retrieve($subscriptionId, [
            'expand' => ['items.data.price']
        ]);
        
        if (!$subscription || $subscription->status === 'canceled') {
            return ['success' => false, 'error' => 'Suscripción no encontrada o cancelada'];
        }

        // Obtener el item actual de la suscripción
        $currentItem = $subscription->items->data[0] ?? null;
        if (!$currentItem) {
            return ['success' => false, 'error' => 'No se encontró el plan actual'];
        }

        // Obtener el precio actual para comparar (newPrice ya se obtuvo al inicio)
        $currentPrice = $this->stripe->prices->retrieve($currentItem->price->id);

        $currentAmount = $currentPrice->unit_amount ?? 0;
        $newAmount = $newPrice->unit_amount ?? 0;
        $isUpgrade = $newAmount > $currentAmount;

        // Si la suscripción está marcada para cancelar, no hay upcoming invoice
        // En ese caso, devolvemos un preview manual basado en el nuevo precio
        if ($subscription->cancel_at_period_end) {
            $currency = strtoupper($newPrice->currency ?? 'mxn');
            
            // Calcular descuento si hay cupón
            $discountAmount = 0;
            $amountDue = $newAmount;
            
            if ($couponInfo) {
                if ($couponInfo['discount']['type'] === 'percentage') {
                    $discountAmount = (int)(($newAmount * $couponInfo['discount']['percent_off']) / 100);
                } elseif ($couponInfo['discount']['type'] === 'fixed') {
                    $discountAmount = $couponInfo['discount']['amount_off'];
                }
                $amountDue = max(0, $newAmount - $discountAmount);
            }
            
            $response = [
                'success' => true,
                'preview' => [
                    'amount_due' => $amountDue,
                    'amount_due_formatted' => $this->formatMoney($amountDue, $currency),
                    'currency' => $currency,
                    'proration_amount' => 0,
                    'proration_formatted' => $this->formatMoney(0, $currency),
                    'credit_balance' => 0,
                    'new_plan_amount' => $newAmount,
                    'new_plan_formatted' => $this->formatMoney($newAmount, $currency),
                    'current_plan_amount' => $currentAmount,
                    'current_plan_formatted' => $this->formatMoney($currentAmount, $currency),
                    'billing_date' => date('Y-m-d', $subscription->current_period_end ?? $subscription->items->data[0]->current_period_end ??  time()),
                    'is_upgrade' => $isUpgrade,
                    'immediate_charge' => false,
                    'new_plan_name' => $newPrice->nickname ?? $newPrice->lookup_key ?? 'Plan',
                    'note' => 'La facturación se reactivará al cambiar de plan'
                ]
            ];
            
            // Agregar información del descuento si hay cupón
            if ($couponInfo) {
                $response['preview']['discount'] = [
                    'coupon_code' => $couponInfo['code'],
                    'coupon_name' => $couponInfo['name'],
                    'discount_amount' => $discountAmount,
                    'discount_amount_formatted' => $this->formatMoney($discountAmount, $currency),
                    'discount_description' => $couponInfo['discount']['description']
                ];
            }
            
            return $response;
        }

        // Crear una invoice preview (upcoming invoice) con el nuevo precio
        // Nota: upcoming() es un método especial que no está en StripeClient, usar la clase estática
        // Si el intervalo cambia (ej. mensual → anual), el ciclo se ancla en 'now'.
        // Si es el mismo intervalo, se mantiene la fecha actual de facturación.
        $currentInterval = $currentItem->price->recurring->interval ?? null;
        $newInterval = $newPrice->recurring->interval ?? null;
        $intervalChanges = $currentInterval !== $newInterval;

        $invoicePreviewOptions = [
            'customer' => $subscription->customer,
            'subscription' => $subscriptionId,
            'subscription_details' => [
                'items' => [
                    [
                        'id' => $currentItem->id,
                        'price' => $newPriceId,
                    ],
                ],
                'proration_behavior' => $intervalChanges ? 'create_prorations' : 'none',
                'billing_cycle_anchor' => $intervalChanges ? 'now' : 'unchanged',
            ],
        ];

        // Aplicar cupón si es válido
        if ($couponInfo) {
            if ($couponInfo['is_promotion_code']) {
                // Si es un promotion code, usar el promotion_code_id
                $invoicePreviewOptions['discounts'] = [['promotion_code' => $couponInfo['promotion_code_id']]];
            } else {
                // Si es un cupón directo, usar el coupon_id
                $invoicePreviewOptions['discounts'] = [['coupon' => $couponInfo['coupon_id']]];
            }
        }

        $invoice = $this->stripe->invoices->createPreview($invoicePreviewOptions);

        // Calcular el monto del prorrateo
        $prorationAmount = 0;
        foreach ($invoice->lines->data as $line) {
            if ($line->proration) {
                $prorationAmount += $line->amount;
            }
        }

        $currency = strtoupper($invoice->currency ?? 'mxn');
        $amountDue = $invoice->amount_due ?? 0;
        
        // Calcular el descuento total aplicado
        $discountAmount = 0;
        if ($invoice->total_discount_amounts) {
            foreach ($invoice->total_discount_amounts as $discount) {
                $discountAmount += $discount->amount;
            }
        }

        $response = [
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
                // Si el intervalo cambia, el cobro es inmediato (billing_cycle_anchor='now')
                // Si el intervalo es el mismo, se cobra en la próxima fecha de renovación
                'billing_date' => $intervalChanges
                    ? date('Y-m-d')
                    : date('Y-m-d', $invoice->next_payment_attempt ?? time()),
                'is_upgrade' => $isUpgrade,
                'immediate_charge' => $intervalChanges && $amountDue > 0,
                'new_plan_name' => $newPrice->nickname ?? $newPrice->lookup_key ?? 'Plan',
            ]
        ];
        
        // Agregar información del descuento si hay cupón
        if ($couponInfo && $discountAmount > 0) {
            $response['preview']['discount'] = [
                'coupon_code' => $couponInfo['code'],
                'coupon_name' => $couponInfo['name'],
                'discount_amount' => $discountAmount,
                'discount_amount_formatted' => $this->formatMoney($discountAmount, $currency),
                'discount_description' => $couponInfo['discount']['description']
            ];
        }

        return $response;

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
 * @param string|null $couponCode Código de cupón opcional
 * @return array Resultado con el preview del cobro
 */
public function previewNewSubscription(string $customerId, string $priceId, int $trialDays = 0, ?string $couponCode = null): array
{
    try {
        // Validar cupón si se proporcionó
        $couponInfo = null;
        if ($couponCode !== null && $couponCode !== '') {
            $couponValidation = $this->validateCoupon($couponCode);
            if (!$couponValidation['success']) {
                return $couponValidation; // Retornar el error del cupón
            }
            $couponInfo = $couponValidation['coupon'];
        }

         // si se mando un cupon y el precio es anual entonces no se acepta
        if($couponInfo && $priceId ){
            $price = $this->stripe->prices->retrieve($priceId);
            if($price->recurring && $price->recurring->interval === 'year') {
                return [
                    'success' => false,
                    'error' => 'Los cupones no son aplicables a planes anuales'
                ];
            }
        }
        
        $price = $this->stripe->prices->retrieve($priceId);
        
        if (!$price) {
            return ['success' => false, 'error' => 'Precio no encontrado'];
        }

        // Detectar si es un precio one-time (permanente)
        $isOneTime = $price->type === 'one_time';

        $amount = $price->unit_amount ?? 0;
        $currency = strtoupper($price->currency ?? 'mxn');
        
        // Calcular el descuento si hay cupón
        $discountAmount = 0;
        $amountDue = $amount;
        
        if ($couponInfo) {
            if ($couponInfo['discount']['type'] === 'percentage') {
                // Descuento por porcentaje
                $discountAmount = (int)(($amount * $couponInfo['discount']['percent_off']) / 100);
            } elseif ($couponInfo['discount']['type'] === 'fixed') {
                // Descuento por cantidad fija
                $discountAmount = $couponInfo['discount']['amount_off'];
            }
            $amountDue = max(0, $amount - $discountAmount); // No puede ser negativo
        }
        
        $billingDate = date('Y-m-d');

        $response = [
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
                'immediate_charge' => true,
                'new_plan_name' => $price->nickname ?? $price->lookup_key ?? 'Plan',
                'is_one_time_payment' => $isOneTime,
            ]
        ];
        
        // Agregar información del descuento si hay cupón
        if ($couponInfo) {
            $response['preview']['discount'] = [
                'coupon_code' => $couponInfo['code'],
                'coupon_name' => $couponInfo['name'],
                'discount_amount' => $discountAmount,
                'discount_amount_formatted' => $this->formatMoney($discountAmount, $currency),
                'discount_description' => $couponInfo['discount']['description']
            ];
        }

        return $response;

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

    /**
     * Crea un pago único (one-time) para licencias permanentes
     * 
     * @param string $customerId ID del cliente en Stripe
     * @param string $priceId ID del precio one-time
     * @param string|null $couponCode Código de cupón opcional
     * @return array Resultado del pago
     */
    public function createOneTimePayment(string $customerId, string $priceId, ?string $couponCode = null): array
    {
        try {
            // Validar cupón si se proporcionó
            // $couponInfo = null;
            // if ($couponCode !== null && $couponCode !== '') {
            //     $couponValidation = $this->validateCoupon($couponCode);
            //     if (!$couponValidation['success']) {
            //         return $couponValidation;
            //     }
            //     $couponInfo = $couponValidation['coupon'];
            // }

            // Obtener información del precio
            $price = $this->stripe->prices->retrieve($priceId);
            
            if (!$price || $price->type !== 'one_time') {
                return [
                    'success' => false,
                    'error' => 'El precio especificado no es de tipo one-time'
                ];
            }

            // Usar PaymentIntent en lugar de Invoice para pagos one-time
            $paymentIntentOptions = [
                'amount' => $price->unit_amount,
                'currency' => $price->currency,
                'customer' => $customerId,
                'description' => $price->nickname ?? 'Licencia Permanente - ' . ($price->lookup_key ?? ''),
                'confirm' => true, // Confirmar automáticamente el pago
                'off_session' => true, // Usar la tarjeta guardada del cliente
                'metadata' => [
                    'price_id' => $priceId,
                    'lookup_key' => $price->lookup_key ?? '',
                    'is_permanent_license' => 'true'
                ]
            ];

            // Aplicar cupón si es válido
            // if ($couponInfo) {
            //     if ($couponInfo['is_promotion_code']) {
            //         $paymentIntentOptions['discounts'] = [['promotion_code' => $couponInfo['promotion_code_id']]];
            //     } else {
            //         $paymentIntentOptions['discounts'] = [['coupon' => $couponInfo['coupon_id']]];
            //     }
            // }

            $paymentIntent = $this->stripe->paymentIntents->create($paymentIntentOptions);

            // Verificar el estado del pago
            if ($paymentIntent->status !== 'succeeded') {
                return [
                    'success' => false,
                    'error' => 'El pago no se completó. Estado: ' . $paymentIntent->status,
                    'payment_intent' => $paymentIntent
                ];
            }

            // Guardar el price_id en metadata del cliente para referencia futura
            $this->stripe->customers->update($customerId, [
                'metadata' => [
                    'permanent_license_price_id' => $priceId,
                    'permanent_license_purchased_at' => date('Y-m-d H:i:s')
                ]
            ]);

            // Cancelar todas las suscripciones activas del cliente
            // ya que la licencia permanente no requiere facturación periódica
            $canceledSubscriptions = [];
            try {
                $subscriptions = $this->stripe->subscriptions->all([
                    'customer' => $customerId,
                    'limit' => 500,
                ]);

                foreach ($subscriptions->data as $subscription) {
                    // Cancelar solo suscripciones activas o en período de prueba
                    if (in_array($subscription->status, ['active', 'trialing'])) {
                        $canceledSub = $this->stripe->subscriptions->cancel($subscription->id);
                        $canceledSubscriptions[] = $canceledSub->id;
                    }
                }
            } catch (\Exception $e) {
                // No interrumpir el flujo si falla la cancelación de suscripciones
                // El pago ya se procesó exitosamente
            }

            return [
                'success' => true,
                'payment_intent_id' => $paymentIntent->id,
                'status' => $paymentIntent->status,
                'amount_paid' => $paymentIntent->amount,
                'is_permanent_license' => true,
                'price_id' => $priceId,
                'lookup_key' => $price->lookup_key ?? null,
                'canceled_subscriptions' => $canceledSubscriptions,
            ];

        } catch (\Stripe\Exception\CardException $e) {
            return [
                'success' => false,
                'error' => $this->mapCardError($e->getStripeCode(), $e->getDeclineCode())
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
}
