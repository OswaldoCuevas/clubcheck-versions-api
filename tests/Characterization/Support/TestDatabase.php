<?php

declare(strict_types=1);

namespace Tests\Characterization\Support;

use mysqli;
use mysqli_sql_exception;
use RuntimeException;

final class TestDatabase
{
    private const SAFE_SCHEMA_PATTERN = '/^clubcheck_admin_test_[a-f0-9]{16}$/D';

    private mysqli $server;
    private mysqli $database;
    private string $schema;
    private string $host;
    private int $port;
    private string $username;
    private string $password;
    private string $root;
    private string $adminPasswordHash;
    private string $uploaderPasswordHash;
    private string $licensePrivateKey;
    private string $licensePublicKey;
    private array $migrationReport = [];

    public function __construct(string $root)
    {
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

        $this->root = rtrim(str_replace('\\', '/', $root), '/');
        $this->host = getenv('CLUBCHECK_TEST_DB_HOST') ?: '127.0.0.1';
        $this->port = (int) (getenv('CLUBCHECK_TEST_DB_PORT') ?: 3306);
        $this->username = getenv('CLUBCHECK_TEST_DB_USERNAME') ?: 'root';
        $this->password = getenv('CLUBCHECK_TEST_DB_PASSWORD') ?: '';
        $this->schema = 'clubcheck_admin_test_' . bin2hex(random_bytes(8));
        $this->adminPasswordHash = password_hash('admin-test-password', PASSWORD_BCRYPT);
        $this->uploaderPasswordHash = password_hash('uploader-test-password', PASSWORD_BCRYPT);
        [$this->licensePrivateKey, $this->licensePublicKey] = $this->createLicenseKeyPair();
        $this->assertSafeSchema($this->schema);

        $this->server = new mysqli($this->host, $this->username, $this->password, '', $this->port);
        $this->server->set_charset('utf8mb4');
        $this->server->query(sprintf(
            'CREATE DATABASE `%s` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci',
            $this->schema
        ));

        $this->database = new mysqli($this->host, $this->username, $this->password, $this->schema, $this->port);
        $this->database->set_charset('utf8mb4');

        try {
            $this->applySchemaAndMigrations();
            $this->setKnownAdminPassword();
        } catch (\Throwable $error) {
            $this->drop();
            throw $error;
        }
    }

    public function schema(): string
    {
        return $this->schema;
    }

    public function connection(): mysqli
    {
        return $this->database;
    }

    /** @return array<string, string> */
    public function serverEnvironment(): array
    {
        return [
            'APP_MODE' => 'TEST',
            'APP_DEBUG' => 'false',
            'TEST_DB_HOST' => $this->host,
            'TEST_DB_PORT' => (string) $this->port,
            'TEST_DB_DATABASE' => $this->schema,
            'TEST_DB_USERNAME' => $this->username,
            'TEST_DB_PASSWORD' => $this->password,
            'ACCESS_KEY_SECRET' => 'characterization-only-access-key-secret-64-characters-minimum-value',
            'JWT_SECRET_KEY' => 'characterization-only-jwt-secret-key-64-characters-minimum-value',
            'LICENSE_PRIVATE_KEY' => base64_encode($this->licensePrivateKey),
            'LICENSE_PUBLIC_KEY' => base64_encode($this->licensePublicKey),
        ];
    }

    /** @return list<array<string, mixed>> */
    public function migrationReport(): array
    {
        return $this->migrationReport;
    }

    public function resetFixtures(): void
    {
        $mutable = [
            'loginattempts',
            'customers',
            'customerprivacyconsents',
            'customertermsandconditionsconsents',
            'customerloginattempts',
            'customerwebloginattempts',
            'customersessions',
            'customerpushtokens',
            'customeriplogs',
            'announcements',
            'announcementviews',
            'downloadlogs',
            'licenselogs',
            'whatsappconfigurations',
            'customerwhatsapptemplates',
            'customerwhatsapptemplateevents',
            'messagesent',
            'usersdesktop',
            'subscriptionsdesktop',
            'attendancesdesktop',
            'administratorsdesktop',
            'sendemailsadmindesktop',
            'historyoperationsdesktop',
            'infomysubscriptiondesktop',
            'whatsappdesktop',
            'appsettingsdesktop',
            'sentmessagesdesktop',
            'productdesktop',
            'productpricedesktop',
            'productstockdesktop',
            'cashregisterdesktop',
            'saleticketdesktop',
            'saleticketitemdesktop',
            'subscriptionperioddesktop',
            'syncstatusdesktop',
            'accessdevicesdesktop',
            'operationsaccessdevicesdesktop',
            'useraccessdevicesdesktop',
            'accessusersdesktop',
            'classcatalogdesktop',
            'instructorsdesktop',
            'classinstructorsdesktop',
            'classschedulegroupsdesktop',
            'classschedulesdesktop',
            'classenrollmentsdesktop',
            'classreservationsdesktop',
            'classscheduleinstructorsdesktop',
            'classschedulereschedulesdesktop',
            'saleclassesdesktop',
            'saleclassschedulesdesktop',
            'migrationsdesktop',
            'barcodelookupcachedesktop',
        ];
        $result = $this->database->query(
            "SELECT TABLE_NAME FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE'"
        );

        $this->database->query('SET FOREIGN_KEY_CHECKS = 0');
        try {
            while ($row = $result->fetch_assoc()) {
                $table = (string) $row['TABLE_NAME'];
                if (in_array(strtolower($table), $mutable, true)) {
                    $this->database->query('TRUNCATE TABLE `' . str_replace('`', '``', $table) . '`');
                }
            }
        } finally {
            $this->database->query('SET FOREIGN_KEY_CHECKS = 1');
        }

        $this->setKnownAdminPassword();
    }

    public function insertCustomer(array $overrides = []): array
    {
        $row = array_replace([
            'Id' => 'customer-test-1',
            'BillingId' => null,
            'PlanCode' => 'free',
            'Name' => 'Cliente de prueba',
            'CodeAccess' => 'CLIENTE-TEST-1',
            'Email' => 'cliente1@example.test',
            'Phone' => '+526141000001',
            'DeviceName' => 'TEST-PC',
            'Token' => 'machine-token-test',
            'AccessKeyHash' => hash('sha512', 'access-key-test'),
            'IsActive' => 1,
            'WaitingForToken' => 0,
        ], $overrides);

        $columns = array_keys($row);
        $sql = sprintf(
            'INSERT INTO Customers (`%s`) VALUES (%s)',
            implode('`, `', $columns),
            implode(', ', array_fill(0, count($columns), '?'))
        );
        $this->database->execute_query($sql, array_values($row));

        return $row;
    }

    public function fetchOne(string $sql, array $params = []): ?array
    {
        $result = $this->database->execute_query($sql, $params);
        if (!$result instanceof \mysqli_result) {
            return null;
        }
        $row = $result->fetch_assoc();
        return $row ?: null;
    }

    public function drop(): void
    {
        if (!isset($this->schema)) {
            return;
        }
        $this->assertSafeSchema($this->schema);

        if (isset($this->database)) {
            $this->database->close();
        }
        if (isset($this->server)) {
            $this->server->query('DROP DATABASE IF EXISTS `' . $this->schema . '`');
            $this->server->close();
        }
    }

    private function applySchemaAndMigrations(): void
    {
        $schemaPath = $this->root . '/database/schema.sql';
        $this->applyFile($schemaPath, false);

        $migrationPaths = glob($this->root . '/database/migrations/*.sql') ?: [];
        usort($migrationPaths, static function (string $left, string $right): int {
            // MessageSent debe existir antes de las migraciones 009 y 010 que
            // agregan sus columnas de diagnóstico.
            $priority = static fn (string $path): int => basename($path) === 'alter_messages.sql' ? -100 : 0;
            $byPriority = $priority($left) <=> $priority($right);
            return $byPriority !== 0
                ? $byPriority
                : strnatcasecmp(basename($left), basename($right));
        });
        foreach ($migrationPaths as $path) {
            $this->applyFile($path, true);
        }
    }

    private function applyFile(string $path, bool $allowAlreadyApplied): void
    {
        $sql = file_get_contents($path);
        if ($sql === false) {
            throw new RuntimeException('No se pudo leer ' . $path);
        }

        $sql = preg_replace('/CREATE\s+DATABASE\s+IF\s+NOT\s+EXISTS\s+`clubcheck`[^;]*;/i', '', $sql) ?? $sql;
        $sql = preg_replace('/\bUSE\s+`clubcheck`\s*;/i', '', $sql) ?? $sql;
        $report = ['file' => basename($path), 'applied' => 0, 'alreadyApplied' => 0];

        foreach (SqlScript::statements($sql) as $statement) {
            // Algunas migraciones fueron escritas para MariaDB. MySQL 8 no
            // acepta IF EXISTS en DROP FOREIGN KEY, pero el error 1091 nos
            // permite reconocer de forma segura que ya estaba aplicado.
            $statement = preg_replace(
                '/DROP\s+FOREIGN\s+KEY\s+IF\s+EXISTS/i',
                'DROP FOREIGN KEY',
                $statement
            ) ?? $statement;
            $statement = preg_replace(
                '/ADD\s+COLUMN\s+IF\s+NOT\s+EXISTS/i',
                'ADD COLUMN',
                $statement
            ) ?? $statement;
            try {
                $this->database->query($statement);
                $report['applied']++;
            } catch (mysqli_sql_exception $error) {
                if ($allowAlreadyApplied && $this->isAlreadyAppliedError($error)) {
                    $report['alreadyApplied']++;
                    continue;
                }
                throw new RuntimeException(
                    sprintf('Falló %s: %s\nSQL: %s', basename($path), $error->getMessage(), $statement),
                    (int) $error->getCode(),
                    $error
                );
            }
        }

        $this->migrationReport[] = $report;
    }

    private function isAlreadyAppliedError(mysqli_sql_exception $error): bool
    {
        return in_array((int) $error->getCode(), [1050, 1060, 1061, 1091, 1826], true);
    }

    private function setKnownAdminPassword(): void
    {
        $this->database->execute_query(
            'UPDATE Users SET PasswordHash = ?, IsActive = 1 WHERE Username = ?',
            [$this->adminPasswordHash, 'admin']
        );
        $this->database->execute_query(
            'UPDATE Users SET PasswordHash = ?, IsActive = 1 WHERE Username = ?',
            [$this->uploaderPasswordHash, 'uploader']
        );
    }

    private function assertSafeSchema(string $schema): void
    {
        if (!preg_match(self::SAFE_SCHEMA_PATTERN, $schema)) {
            throw new RuntimeException('Nombre de esquema de pruebas inseguro: ' . $schema);
        }
    }

    /** @return array{0: string, 1: string} */
    private function createLicenseKeyPair(): array
    {
        $key = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        if ($key === false || !openssl_pkey_export($key, $privateKey)) {
            throw new RuntimeException('No se pudo generar la clave privada temporal de licencias.');
        }
        $details = openssl_pkey_get_details($key);
        if (!is_array($details) || empty($details['key'])) {
            throw new RuntimeException('No se pudo generar la clave pública temporal de licencias.');
        }
        return [$privateKey, $details['key']];
    }
}
