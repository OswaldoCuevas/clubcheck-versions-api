<?php

namespace App\Services\WhatsApp;

require_once __DIR__ . '/../../../utils/GlobalFunctions.php';

use GlobalFunctions;

class WhatsAppTemplateComponentBuilder
{
    public function build(array $componentDefinitions, array $parameters): array
    {
        $components = [];

        foreach ($componentDefinitions as $component) {
            $values = $this->buildParameters($component, $parameters);

            if (!empty($values)) {
                $components[] = [
                    'type' => strtolower((string) ($component['type'] ?? 'body')),
                    'parameters' => $values,
                ];
            }
        }

        return $components;
    }

    private function buildParameters(array $component, array $parameters): array
    {
        $values = [];

        foreach (($component['variables'] ?? []) as $variable) {
            $value = $this->formatValue(
                $parameters[$variable] ?? '',
                $component['formats'][$variable] ?? ($component['format'] ?? 'text')
            );

            if (!empty($component['suffix'])) {
                $value .= $component['suffix'];
            }

            $values[] = ['type' => 'text', 'text' => $value];
        }

        return $values;
    }

    private function formatValue(mixed $value, string $format = 'text'): string
    {
        return $format === 'name'
            ? GlobalFunctions::FormatName((string) $value)
            : GlobalFunctions::sanitize((string) $value);
    }
}
