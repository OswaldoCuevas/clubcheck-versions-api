<?php

declare(strict_types=1);

namespace App\Modules\DesktopReporting\Domain;

use App\Exceptions\ApiErrorCode;
use App\Exceptions\ApiException;
use DateTimeImmutable;

final class DesktopUserProfileQueryPolicy
{
    public function range(array $parameters, bool $required = false): array
    {
        $range = [];
        foreach (['from', 'to'] as $key) {
            $value = $parameters[$key] ?? null;
            if ($value === null && !$required) {
                $range[] = null;
                continue;
            }

            $date = is_string($value)
                ? DateTimeImmutable::createFromFormat('!Y-m-d', $value)
                : false;
            if (
                $date === false
                || $date->format('Y-m-d') !== $value
                || $value < '1000-01-01'
            ) {
                $this->validationError($key . ' debe ser una fecha YYYY-MM-DD');
            }
            $range[] = $value;
        }

        if ($range[0] !== null && $range[1] !== null && $range[0] > $range[1]) {
            $this->validationError('from no puede ser mayor que to');
        }

        return $range;
    }

    public function pagination(array $parameters): array
    {
        $page = $this->positiveInteger($parameters, 'page', 1, 1000000);
        $pageSize = $this->positiveInteger($parameters, 'pageSize', 25, 100);

        return [$page, $pageSize, ($page - 1) * $pageSize];
    }

    public function normalizeSubscription(?array $subscription): ?array
    {
        if ($subscription === null) {
            return null;
        }

        $subscription['Payment'] = $subscription['Payment'] === null
            ? null
            : (float) $subscription['Payment'];
        $subscription['SubscriptionPeriodDays'] = $subscription['SubscriptionPeriodDays'] === null
            ? null
            : (int) $subscription['SubscriptionPeriodDays'];

        foreach (['Warning', 'Finished', 'Registered'] as $flag) {
            $subscription[$flag] = $subscription[$flag] === null
                ? null
                : (bool) $subscription[$flag];
        }

        return $subscription;
    }

    public function statistics(
        string $userId,
        string $from,
        string $to,
        array $summary,
        array $latestDailyAttendances,
    ): array {
        $summary = array_map('intval', $summary);
        $daily = [];
        $weekdays = [];
        $hours = [];

        foreach (
            ['Domingo', 'Lunes', 'Martes', 'Miercoles', 'Jueves', 'Viernes', 'Sabado']
            as $index => $label
        ) {
            $weekdays[$index + 1] = [
                'weekday' => $index + 1,
                'label' => $label,
                'total' => 0,
            ];
        }
        for ($hour = 0; $hour < 24; ++$hour) {
            $hours[$hour] = [
                'hour' => $hour,
                'label' => sprintf('%02d:00', $hour),
                'total' => 0,
            ];
        }

        $end = new DateTimeImmutable($to);
        for (
            $day = new DateTimeImmutable($from);
            $day <= $end;
            $day = $day->modify('+1 day')
        ) {
            $key = $day->format('Y-m-d');
            $daily[$key] = [
                'date' => $key,
                'total' => 0,
                'allowed' => 0,
                'denied' => 0,
            ];
        }

        foreach ($latestDailyAttendances as $attendance) {
            $date = $attendance['date'];
            ++$daily[$date]['total'];
            if ((int) $attendance['Active'] === 1) {
                ++$daily[$date]['allowed'];
                ++$weekdays[(int) $attendance['weekday']]['total'];
                ++$hours[(int) $attendance['hour']]['total'];
            } elseif ((int) $attendance['Active'] === 0) {
                ++$daily[$date]['denied'];
            }
        }

        return [
            'userId' => $userId,
            'range' => [
                'from' => $from . ' 00:00:00',
                'to' => $to . ' 23:59:59',
            ],
            'summary' => $summary,
            'countMode' => 'latest_attempt_per_user_per_day',
            'daily' => array_values($daily),
            'byWeekday' => array_values($weekdays),
            'byHour' => array_values($hours),
        ];
    }

    private function positiveInteger(
        array $parameters,
        string $key,
        int $default,
        int $maximum,
    ): int {
        $value = $parameters[$key] ?? $default;
        if (
            (!is_string($value) && !is_int($value))
            || !preg_match('/^[1-9][0-9]*$/D', (string) $value)
            || strlen((string) $value) > 7
            || (int) $value > $maximum
        ) {
            $this->validationError($key . ' debe estar entre 1 y ' . $maximum);
        }

        return (int) $value;
    }

    private function validationError(string $message): never
    {
        throw ApiException::preservingResponse(
            ApiErrorCode::DesktopUserProfileValidationFailed,
            ['error' => $message],
        );
    }
}
