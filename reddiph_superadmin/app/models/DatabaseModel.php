<?php
namespace App\Models;

use PDO;

class DatabaseModel
{
    private static ?PDO $pdo = null;
    private static array $tableCache = [];
    private static array $columnCache = [];

    public static function connection(): PDO
    {
        if (!self::$pdo) {
            $config = self::databaseConfig();
            $localRootWithoutPassword = in_array($config['host'], ['localhost', '127.0.0.1', '::1'], true)
                && $config['username'] === 'root';
            if ($config['password'] === '' && !$localRootWithoutPassword) {
                throw new \RuntimeException('Set DB_PASS in the environment or config/database.local.php.');
            }

            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
                $config['host'],
                $config['port'],
                $config['database']
            );
            self::$pdo = new PDO($dsn, $config['username'], $config['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
            self::$pdo->exec("SET time_zone = '+08:00'");
            date_default_timezone_set('Asia/Manila');
        }
        return self::$pdo;
    }

    private static function databaseConfig(): array
    {
        $database = (require BASE_PATH . '/config/config.php')['db'];
        $config = [
            'host' => (string) ($database['host'] ?? '127.0.0.1'),
            'port' => (string) ($database['port'] ?? '3306'),
            'database' => (string) ($database['name'] ?? ''),
            'username' => (string) ($database['user'] ?? ''),
            'password' => (string) ($database['pass'] ?? ''),
        ];

        $localConfigFile = BASE_PATH . '/config/database.local.php';
        if (is_file($localConfigFile)) {
            $localConfig = require $localConfigFile;
            if (!is_array($localConfig)) {
                throw new \RuntimeException('config/database.local.php must return an array.');
            }
            foreach (['host', 'port', 'database', 'username', 'password'] as $key) {
                if (isset($localConfig[$key]) && is_scalar($localConfig[$key]) && (string) $localConfig[$key] !== '') {
                    $config[$key] = (string) $localConfig[$key];
                }
            }
        }

        foreach ([
            'host' => 'DB_HOST',
            'port' => 'DB_PORT',
            'database' => 'DB_NAME',
            'username' => 'DB_USER',
            'password' => 'DB_PASS',
        ] as $key => $environmentName) {
            $value = getenv($environmentName);
            if ($value === false && isset($_ENV[$environmentName]) && is_scalar($_ENV[$environmentName])) {
                $value = (string) $_ENV[$environmentName];
            }
            if ($value === false && isset($_SERVER[$environmentName]) && is_scalar($_SERVER[$environmentName])) {
                $value = (string) $_SERVER[$environmentName];
            }
            if ($value !== false) {
                $config[$key] = (string) $value;
            }
        }

        return $config;
    }

    public static function hasTable(string $table): bool
    {
        if (!preg_match('/^[a-z_]+$/', $table)) {
            throw new \InvalidArgumentException('Invalid database table name.');
        }
        if (!array_key_exists($table, self::$tableCache)) {
            $stmt = self::connection()->prepare(
                'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?'
            );
            $stmt->execute([$table]);
            self::$tableCache[$table] = (int) $stmt->fetchColumn() > 0;
        }

        return self::$tableCache[$table];
    }

    public static function requireTables(
        array $tables,
        string $remediation = 'Import config/migrations/20261022_portal_pages.sql into the shared database to enable this feature.'
    ): void
    {
        foreach ($tables as $table) {
            if (!self::hasTable($table)) {
                throw new \RuntimeException(
                    "The database is missing the $table table. $remediation"
                );
            }
        }
    }

    public static function hasColumn(string $table, string $column): bool
    {
        if (!preg_match('/^[a-z_]+$/', $table) || !preg_match('/^[a-z_]+$/', $column)) {
            throw new \InvalidArgumentException('Invalid database column name.');
        }
        $cacheKey = $table . '.' . $column;
        if (array_key_exists($cacheKey, self::$columnCache)) {
            return self::$columnCache[$cacheKey];
        }
        $stmt = self::connection()->prepare(
            'SELECT COUNT(*) FROM information_schema.columns
             WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?'
        );
        $stmt->execute([$table, $column]);
        self::$columnCache[$cacheKey] = (int) $stmt->fetchColumn() > 0;
        return self::$columnCache[$cacheKey];
    }

    public static function requireColumns(
        string $table,
        array $columns,
        string $remediation = 'Import config/migrations/20261022_portal_pages.sql into the shared database to enable this feature.'
    ): void
    {
        self::requireTables([$table], $remediation);
        foreach ($columns as $column) {
            if (!self::hasColumn($table, $column)) {
                throw new \RuntimeException(
                    "The database is missing the $table.$column column. $remediation"
                );
            }
        }
    }

    public static function transaction(callable $operation): mixed
    {
        $connection = self::connection();
        $connection->beginTransaction();
        try {
            $result = $operation();
            $connection->commit();
            return $result;
        } catch (\Throwable $exception) {
            if ($connection->inTransaction()) {
                $connection->rollBack();
            }
            throw $exception;
        }
    }
}
