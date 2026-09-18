<?php

namespace App\Modules\Customers\Requests;

use App\Exceptions\ValidationException;
use Core\JsonRequestDto;

final class ReportCustomerErrorRequest extends JsonRequestDto
{
    public readonly string $errorType;
    public readonly string $severity;
    public readonly string $message;
    public readonly ?string $stackTrace;
    public readonly ?array $context;
    public readonly ?string $clientVersion;
    public readonly ?string $deviceName;

    public function __construct()
    {
        parent::__construct(['POST']);

        $this->errorType = $this->enumString('errorType', ['client', 'server', 'internal'], 'client');
        $this->severity = $this->enumString('severity', ['info', 'warning', 'error', 'critical'], 'error');
        $this->message = $this->requiredString('message', 2000, 'El mensaje del error es obligatorio');
        $this->stackTrace = $this->optionalString('stackTrace', 60000);
        $this->context = $this->optionalContext();
        $this->clientVersion = $this->optionalString('clientVersion', 80);
        $this->deviceName = $this->optionalString('deviceName', 160);
    }

    public function getAttributes(): array
    {
        return [
            'errorType' => $this->errorType,
            'severity' => $this->severity,
            'message' => $this->message,
            'stackTrace' => $this->stackTrace,
            'context' => $this->context,
            'clientVersion' => $this->clientVersion,
            'deviceName' => $this->deviceName,
        ];
    }

    private function enumString(string $field, array $allowed, string $default): string
    {
        $value = $this->optionalString($field, 40) ?? $default;
        if (!in_array($value, $allowed, true)) {
            throw new ValidationException("El campo {$field} no es valido");
        }

        return $value;
    }

    private function optionalContext(): ?array
    {
        if (!$this->has('context')) {
            return null;
        }

        $context = $this->value('context');
        if ($context === null) {
            return null;
        }

        if (!is_array($context)) {
            throw new ValidationException('El campo context debe ser un objeto');
        }

        $encoded = json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($encoded === false || strlen($encoded) > 60000) {
            throw new ValidationException('El campo context es demasiado grande');
        }

        return $context;
    }
}
