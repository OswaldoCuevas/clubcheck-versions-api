<?php

ini_set('max_execution_time', 0);
ini_set('memory_limit', '-1');

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

class Database extends mysqli
{
  private array $config;

  public function __construct()
  {
    $this->config = $this->loadConfig();

    try {
      parent::__construct(
        $this->config['host'],
        $this->config['username'],
        $this->config['password'],
        $this->config['database'],
        $this->config['port']
      );

      $this->set_charset($this->config['charset']);
    } catch (\Throwable $e) {
      throw new \RuntimeException('No fue posible conectar a la base de datos: ' . $e->getMessage(), (int) $e->getCode(), $e);
    }
  }

  private function loadConfig(): array
  {
    $configPath = __DIR__ . '/../config/database.php';

    if (!file_exists($configPath)) {
      throw new \RuntimeException('Archivo de configuración de base de datos no encontrado en config/database.php');
    }

    $config = require $configPath;

    return [
      'host' => $config['host'] ?? '127.0.0.1',
      'port' => $config['port'] ?? 3306,
      'database' => $config['database'] ?? 'clubcheck',
      'username' => $config['username'] ?? 'root',
      'password' => $config['password'] ?? '',
      'charset' => $config['charset'] ?? 'utf8mb4',
    ];
  }

  public function fetchAll(string $sql, array $params = []): array
  {
    $result = $this->execute_query($sql, $params);

    if ($result instanceof \mysqli_result) {
      return $result->fetch_all(MYSQLI_ASSOC);
    }

    return [];
  }

  public function fetchOne(string $sql, array $params = []): ?array
  {
    $result = $this->execute_query($sql, $params);

    if ($result instanceof \mysqli_result) {
      $row = $result->fetch_assoc();
      return $row === null ? null : $row;
    }

    return null;
  }

  public function insert(string $table, array $data): int
  {
    if (empty($data)) {
      throw new \InvalidArgumentException('No hay datos para insertar en la tabla ' . $table);
    }

    ksort($data);

    $fieldNames = implode(', ', array_keys($data));
    $placeholders = implode(', ', array_fill(0, count($data), '?'));

    $sql = "INSERT INTO {$table} ({$fieldNames}) VALUES ({$placeholders})";

    $this->execute_query($sql, array_values($data));

    return (int) $this->insert_id;
  }

  public function update(string $table, array $data, string $where, array $whereParams = []): bool
  {
    if (empty($data)) {
      throw new \InvalidArgumentException('No hay datos para actualizar en la tabla ' . $table);
    }

    ksort($data);

    $setClauses = [];
    foreach (array_keys($data) as $column) {
      $setClauses[] = sprintf('%s = ?', $column);
    }

    $sql = sprintf('UPDATE %s SET %s WHERE %s', $table, implode(', ', $setClauses), $where);

    $params = array_values($data);
    if (!empty($whereParams)) {
      $params = array_merge($params, $whereParams);
    }

    $this->execute_query($sql, $params);

    return $this->affected_rows >= 0;
  }

  public function delete(string $table, string $where, array $params = [], ?int $limit = 1): bool
  {
    $limitClause = $limit !== null ? ' LIMIT ' . (int) $limit : '';
    $sql = sprintf('DELETE FROM %s WHERE %s%s', $table, $where, $limitClause);

    $this->execute_query($sql, $params);

    return $this->affected_rows > 0;
  }

  public function begin(): void
  {
    $this->begin_transaction();
  }

  public function commitTransaction(): void
  {
    $this->commit();
  }

  public function rollbackTransaction(): void
  {
    $this->rollback();
  }
}