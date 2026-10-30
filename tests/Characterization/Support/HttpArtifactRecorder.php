<?php

declare(strict_types=1);

namespace Tests\Characterization\Support;

use Psr\Http\Message\ResponseInterface;
use RuntimeException;

final class HttpArtifactRecorder
{
    private string $directory;
    private string $runDirectory;

    public function __construct(string $root)
    {
        $this->directory = rtrim(str_replace('\\', '/', $root), '/') . '/tests/.artifacts/http-characterization/latest';
        if (!is_dir($this->directory) && !mkdir($this->directory, 0775, true) && !is_dir($this->directory)) {
            throw new RuntimeException('No se pudo crear el directorio de artefactos HTTP.');
        }
        foreach (glob($this->directory . '/*.json') ?: [] as $oldArtifact) {
            unlink($oldArtifact);
        }
        $this->runDirectory = dirname($this->directory) . '/runs/' . date('Ymd-His') . '-' . getmypid();
        if (!mkdir($this->runDirectory, 0775, true) && !is_dir($this->runDirectory)) {
            throw new RuntimeException('No se pudo crear el historial de la corrida HTTP.');
        }
    }

    /**
     * @param array<string, mixed> $request
     * @param array<string, mixed> $expected
     */
    public function record(string $caseId, array $request, ResponseInterface $response, array $expected): void
    {
        $body = (string) $response->getBody();
        $decoded = json_decode($body, true);
        $responseBody = json_last_error() === JSON_ERROR_NONE ? $decoded : $body;

        $artifact = [
            'caseId' => $caseId,
            'recordedAt' => date(DATE_ATOM),
            'request' => $this->redact($request),
            'expected' => $expected,
            'actual' => [
                'status' => $response->getStatusCode(),
                'headers' => $this->safeHeaders($response->getHeaders()),
                'body' => $this->redact($responseBody),
            ],
            'httpContractMatched' => $this->matchesExpected($response, $expected, $responseBody),
        ];

        $filename = preg_replace('/[^a-zA-Z0-9_.-]+/', '-', $caseId) . '.json';
        $json = json_encode($artifact, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        foreach ([$this->directory . '/' . $filename, $this->runDirectory . '/' . $filename] as $path) {
            if (file_put_contents($path, $json . PHP_EOL, LOCK_EX) === false) {
                throw new RuntimeException('No se pudo escribir el artefacto ' . $path);
            }
        }
    }

    /** @param array<string, list<string>> $headers */
    private function safeHeaders(array $headers): array
    {
        foreach (array_keys($headers) as $name) {
            if (in_array(strtolower($name), ['set-cookie', 'cookie', 'authorization'], true)) {
                $headers[$name] = ['<redacted>'];
            }
        }
        return $headers;
    }

    private function redact(mixed $value, ?string $key = null): mixed
    {
        if ($key !== null && preg_match('/password|secret|authorization|cookie|csrf|access.?key|license.?(?:token|file)|machine.?token/i', $key)) {
            return '<redacted>';
        }
        if (is_resource($value) || gettype($value) === 'resource (closed)') {
            return is_resource($value)
                ? '<resource:' . get_resource_type($value) . '>'
                : '<resource:closed>';
        }
        if (is_object($value)) {
            return '<object:' . $value::class . '>';
        }
        if (!is_array($value)) {
            return $value;
        }

        $redacted = [];
        foreach ($value as $childKey => $childValue) {
            $redacted[$childKey] = $this->redact($childValue, (string) $childKey);
        }
        return $redacted;
    }

    /** @param array<string, mixed> $expected */
    private function matchesExpected(ResponseInterface $response, array $expected, mixed $responseBody): bool
    {
        if (isset($expected['status']) && $response->getStatusCode() !== (int) $expected['status']) {
            return false;
        }
        $rawBody = (string) $response->getBody();
        foreach ($expected['bodyContains'] ?? [] as $needle) {
            if (!str_contains($rawBody, (string) $needle)) {
                return false;
            }
        }
        if (isset($expected['jsonSubset']) && !$this->isSubset($expected['jsonSubset'], $responseBody)) {
            return false;
        }
        return true;
    }

    private function isSubset(mixed $expected, mixed $actual): bool
    {
        if (!is_array($expected)) {
            return $expected === $actual;
        }
        if (!is_array($actual)) {
            return false;
        }
        foreach ($expected as $key => $value) {
            if (!array_key_exists($key, $actual) || !$this->isSubset($value, $actual[$key])) {
                return false;
            }
        }
        return true;
    }
}
