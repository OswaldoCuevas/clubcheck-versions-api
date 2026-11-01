<?php

declare(strict_types=1);

namespace App\Modules\Billing\Services;

final class PlanChangeProrationPolicy
{
    /** @return array{proration_behavior: string, billing_cycle_anchor: string} */
    public function settings(
        ?string $currentInterval,
        int $currentIntervalCount,
        ?string $newInterval,
        int $newIntervalCount,
    ): array
    {
        // Regla de negocio: el prorrateo solamente aplica cuando tanto el plan
        // actual como el nuevo son mensuales de un mes. Cualquier otro cambio
        // de periodo se realiza sin créditos ni cargos proporcionales.
        $monthlyToMonthly = $currentInterval === 'month'
            && $currentIntervalCount === 1
            && $newInterval === 'month'
            && $newIntervalCount === 1;
        $periodChanges = $currentInterval !== $newInterval
            || $currentIntervalCount !== $newIntervalCount;

        return [
            'proration_behavior' => $monthlyToMonthly
                ? 'create_prorations'
                : 'none',
            'billing_cycle_anchor' => $periodChanges ? 'now' : 'unchanged',
        ];
    }
}
