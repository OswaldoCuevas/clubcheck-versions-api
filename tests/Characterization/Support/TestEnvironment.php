<?php

declare(strict_types=1);

namespace Tests\Characterization\Support;

use RuntimeException;

final class TestEnvironment
{
    private static ?self $instance = null;

    private string $root;
    private TestDatabase $database;
    private TestServer $server;
    private HttpArtifactRecorder $recorder;
    private bool $closed = false;

    private function __construct()
    {
        $this->root = dirname(__DIR__, 3);
        $this->database = new TestDatabase($this->root);
        $this->writeMigrationReport();
        $this->server = new TestServer($this->root, $this->database->serverEnvironment());
        $this->recorder = new HttpArtifactRecorder($this->root);
        register_shutdown_function(fn () => $this->close());
    }

    public static function instance(): self
    {
        return self::$instance ??= new self();
    }

    public function database(): TestDatabase
    {
        return $this->database;
    }

    public function baseUri(): string
    {
        return $this->server->baseUri();
    }

    public function recorder(): HttpArtifactRecorder
    {
        return $this->recorder;
    }

    public function close(): void
    {
        if ($this->closed) {
            return;
        }
        $this->closed = true;
        $this->server->stop();
        $this->database->drop();
    }

    private function writeMigrationReport(): void
    {
        $directory = $this->root . '/tests/.artifacts/database';
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException('No se pudo crear el directorio de reporte de migraciones.');
        }
        file_put_contents(
            $directory . '/migration-report.json',
            json_encode([
                'schema' => $this->database->schema(),
                'files' => $this->database->migrationReport(),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL,
            LOCK_EX
        );
    }
}
