<?php

declare(strict_types=1);

namespace App\Repository;

use App\Service\ObservabilityRuntime;
use PDO;
use Throwable;

final class OrderRepository
{
    private ?PDO $pdo = null;

    private bool $schemaReady = false;

    public function __construct(private readonly ObservabilityRuntime $observability)
    {
    }

    /**
     * @param array<string, mixed> $order
     * @return array<string, mixed>
     */
    public function create(array $order, string $scenario): array
    {
        $route = '/orders';
        $start = microtime(true);
        $orderNo = 'NO' . date('YmdHis') . random_int(1000, 9999);
        $amount = random_int(990, 19990) / 100;

        try {
            $statement = $this->connection()->prepare(
                'INSERT INTO orders (order_no, sku, quantity, amount, status) VALUES (:order_no, :sku, :quantity, :amount, :status)'
            );
            $statement->execute([
                'order_no' => $orderNo,
                'sku' => (string) $order['sku'],
                'quantity' => (int) $order['quantity'],
                'amount' => $amount,
                'status' => 'created',
            ]);
            $id = (int) $this->connection()->lastInsertId();
            $this->record('insert', $start, 'success', null, $scenario, $route);

            return $order + [
                'id' => $id,
                'order_no' => $orderNo,
                'amount' => $amount,
                'status' => 'created',
            ];
        } catch (Throwable $throwable) {
            $this->record('insert', $start, 'error', $throwable::class, $scenario, $route, [
                'error' => $throwable->getMessage(),
            ]);

            return $order + [
                'id' => random_int(1000, 9999),
                'order_no' => $orderNo,
                'amount' => $amount,
                'status' => 'db_fallback',
            ];
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function find(int $id, string $scenario): array
    {
        $route = '/orders/{id}';
        $start = microtime(true);

        try {
            $statement = $this->connection()->prepare(
                'SELECT id, order_no, sku, quantity, amount, status, created_at FROM orders WHERE id = :id LIMIT 1'
            );
            $statement->execute(['id' => $id]);
            $row = $statement->fetch() ?: null;
            $this->record('select', $start, 'success', null, $scenario, $route);

            if (is_array($row)) {
                return $this->normalizeRow($row);
            }
        } catch (Throwable $throwable) {
            $this->record('select', $start, 'error', $throwable::class, $scenario, $route, [
                'order_id' => $id,
                'error' => $throwable->getMessage(),
            ]);
        }

        return [
            'id' => $id,
            'sku' => 'SKU-MISSING',
            'quantity' => 0,
            'amount' => 0,
            'status' => 'missing',
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function recent(string $scenario): array
    {
        $route = '/orders';
        $start = microtime(true);

        try {
            $statement = $this->connection()->query(
                'SELECT id, order_no, sku, quantity, amount, status, created_at FROM orders ORDER BY id DESC LIMIT 5'
            );
            $rows = $statement ? $statement->fetchAll() : [];
            $this->record('select', $start, 'success', null, $scenario, $route);

            return array_map(fn (array $row): array => $this->normalizeRow($row), $rows);
        } catch (Throwable $throwable) {
            $this->record('select', $start, 'error', $throwable::class, $scenario, $route, [
                'error' => $throwable->getMessage(),
            ]);

            return [];
        }
    }

    public function updateStatus(int $id, string $status, string $scenario, string $route): void
    {
        $start = microtime(true);

        try {
            $statement = $this->connection()->prepare('UPDATE orders SET status = :status WHERE id = :id');
            $statement->execute([
                'id' => $id,
                'status' => $status,
            ]);
            $this->record('update', $start, 'success', null, $scenario, $route, [
                'order_status' => $status,
                'order_id' => $id,
            ]);
        } catch (Throwable $throwable) {
            $this->record('update', $start, 'error', $throwable::class, $scenario, $route, [
                'order_status' => $status,
                'order_id' => $id,
                'error' => $throwable->getMessage(),
            ]);
        }
    }

    private function connection(): PDO
    {
        if ($this->pdo instanceof PDO) {
            $this->ensureSchema();

            return $this->pdo;
        }

        $host = getenv('MYSQL_HOST') ?: 'mysql';
        $port = (int) (getenv('MYSQL_PORT') ?: 3306);
        $database = getenv('MYSQL_DATABASE') ?: 'demo';
        $user = getenv('MYSQL_USER') ?: 'root';
        $password = getenv('MYSQL_PASSWORD') ?: 'root';
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, $database);

        $this->pdo = new PDO($dsn, $user, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_TIMEOUT => 2,
        ]);
        $this->ensureSchema();

        return $this->pdo;
    }

    private function ensureSchema(): void
    {
        if ($this->schemaReady || ! $this->pdo instanceof PDO) {
            return;
        }

        $this->pdo->exec(
            "CREATE TABLE IF NOT EXISTS orders (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                order_no VARCHAR(64) NOT NULL,
                sku VARCHAR(64) NOT NULL,
                quantity INT UNSIGNED NOT NULL DEFAULT 1,
                amount DECIMAL(10, 2) NOT NULL,
                status VARCHAR(32) NOT NULL DEFAULT 'created',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )"
        );

        $columns = $this->pdo->query(
            "SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders'"
        );
        $existing = $columns ? $columns->fetchAll(PDO::FETCH_COLUMN) : [];

        foreach ([
            'sku' => "ALTER TABLE orders ADD COLUMN sku VARCHAR(64) NOT NULL DEFAULT 'SKU-UNKNOWN'",
            'quantity' => 'ALTER TABLE orders ADD COLUMN quantity INT UNSIGNED NOT NULL DEFAULT 1',
            'status' => "ALTER TABLE orders ADD COLUMN status VARCHAR(32) NOT NULL DEFAULT 'created'",
            'updated_at' => 'ALTER TABLE orders ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
        ] as $column => $sql) {
            if (! in_array($column, $existing, true)) {
                $this->pdo->exec($sql);
            }
        }

        $this->schemaReady = true;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function normalizeRow(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'order_no' => (string) $row['order_no'],
            'sku' => (string) $row['sku'],
            'quantity' => (int) $row['quantity'],
            'amount' => (float) $row['amount'],
            'status' => (string) $row['status'],
            'created_at' => (string) ($row['created_at'] ?? ''),
        ];
    }

    /**
     * @param array<string, mixed> $context
     */
    private function record(
        string $operation,
        float $start,
        string $result,
        ?string $errorClass,
        string $scenario,
        string $route,
        array $context = []
    ): void {
        $this->observability->mysql($operation, [
            'connection' => 'default',
            'database' => getenv('MYSQL_DATABASE') ?: 'demo',
            'operation' => $operation,
            'result' => $result,
        ], microtime(true) - $start, $result, $errorClass, $context + [
            'scenario' => $scenario,
            'table' => 'orders',
            'route' => $route,
        ]);
    }
}
