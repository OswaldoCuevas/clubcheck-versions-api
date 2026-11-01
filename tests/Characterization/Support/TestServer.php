<?php

declare(strict_types=1);

namespace Tests\Characterization\Support;

use RuntimeException;

final class TestServer
{
    /** @var resource|null */
    private $process = null;
    private int $port;
    private string $root;
    private string $artifactsDirectory;
    private string $sessionsDirectory;

    /** @param array<string, string> $environment */
    public function __construct(string $root, array $environment)
    {
        $this->root = rtrim(str_replace('\\', '/', $root), '/');
        $this->artifactsDirectory = $this->root . '/tests/.artifacts/server';
        $this->sessionsDirectory = $this->root . '/tests/.sessions';
        $this->ensureDirectory($this->artifactsDirectory);
        $this->ensureDirectory($this->sessionsDirectory);
        $this->port = $this->findFreePort();

        $command = [
            PHP_BINARY,
            '-d', 'xdebug.mode=off',
            '-d', 'variables_order=EGPCS',
            '-d', 'session.save_path=' . $this->sessionsDirectory,
            '-d', 'display_errors=0',
            '-d', 'upload_max_filesize=10M',
            '-d', 'post_max_size=12M',
            '-S', '127.0.0.1:' . $this->port,
            '-t', $this->root . '/public',
            $this->root . '/tests/Characterization/Support/http-router.php',
        ];

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['file', $this->artifactsDirectory . '/stdout.log', 'a'],
            2 => ['file', $this->artifactsDirectory . '/stderr.log', 'a'],
        ];

        $processEnvironment = array_replace($this->currentEnvironment(), $environment, [
            'CLUBCHECK_CHARACTERIZATION_SERVER' => '1',
        ]);

        $this->process = proc_open($command, $descriptors, $pipes, $this->root, $processEnvironment);
        if (!is_resource($this->process)) {
            throw new RuntimeException('No se pudo iniciar el servidor HTTP de caracterización.');
        }
        fclose($pipes[0]);

        $this->waitUntilReady();
    }

    public function baseUri(): string
    {
        return 'http://127.0.0.1:' . $this->port;
    }

    public function stop(): void
    {
        if (!is_resource($this->process)) {
            return;
        }

        $status = proc_get_status($this->process);
        if ($status['running']) {
            proc_terminate($this->process);
        }
        proc_close($this->process);
        $this->process = null;
    }

    private function waitUntilReady(): void
    {
        $deadline = microtime(true) + 10.0;
        do {
            $status = proc_get_status($this->process);
            if (!$status['running']) {
                throw new RuntimeException(
                    'El servidor HTTP terminó antes de iniciar. Revisa tests/.artifacts/server/stderr.log.'
                );
            }

            $socket = @stream_socket_client(
                'tcp://127.0.0.1:' . $this->port,
                $errorCode,
                $errorMessage,
                0.2
            );
            if (is_resource($socket)) {
                fclose($socket);
                return;
            }
            usleep(50_000);
        } while (microtime(true) < $deadline);

        throw new RuntimeException('El servidor HTTP no quedó listo en 10 segundos.');
    }

    private function findFreePort(): int
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0', $errorCode, $errorMessage);
        if (!is_resource($socket)) {
            throw new RuntimeException('No se pudo reservar un puerto local: ' . $errorMessage);
        }
        $address = stream_socket_get_name($socket, false);
        fclose($socket);

        $port = (int) substr(strrchr((string) $address, ':'), 1);
        if ($port <= 0) {
            throw new RuntimeException('No se pudo determinar el puerto local reservado.');
        }
        return $port;
    }

    /** @return array<string, string> */
    private function currentEnvironment(): array
    {
        $environment = getenv();
        if (!is_array($environment)) {
            return [];
        }
        return array_map(static fn ($value): string => (string) $value, $environment);
    }

    private function ensureDirectory(string $directory): void
    {
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException('No se pudo crear ' . $directory);
        }
    }
}
