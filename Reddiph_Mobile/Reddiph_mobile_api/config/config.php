<?php

declare(strict_types=1);

/**
 * Database connection for the Reddiph mobile API.
 * Same Hostinger database as reddiph_admin. Set secrets in
 * config/database.local.php or environment variables. Environment values
 * override local settings. Store the optional TomTom key as
 * TOMTOM_API_KEY or tomtom_api_key in the private config file. Store the
 * Barangay Admin signup code as REDDIPH_BARANGAY_ADMIN_SIGNUP_CODE or
 * barangay_admin_signup_code; use a random value of at least 32 characters.
 * DB_HOST defaults to localhost, which is correct when this API runs on the
 * same Hostinger account as the database.
 */
final class Database
{
    private static $connection = null;

    public static function connection(): PDO
    {
        if (self::$connection instanceof PDO) {
            return self::$connection;
        }

        $config = self::loadConfig();

        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            $config['host'],
            $config['port'],
            $config['database']
        );

        try {
            self::$connection = new PDO($dsn, $config['username'], $config['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
            self::$connection->exec("SET time_zone = '+08:00'");
            date_default_timezone_set('Asia/Manila');

            return self::$connection;
        } catch (PDOException $e) {
            error_log('Mobile API database connection error: ' . $e->getMessage());
            throw new RuntimeException('Database connection failed.');
        }
    }

    /**
     * Server-only key for signing mobile session tokens. Derived from the database
     * password so no extra setting is needed; it never leaves the server.
     */
    public static function tokenSecret(): string
    {
        return hash_hmac('sha256', 'reddiph-mobile-session-token', self::loadConfig()['password']);
    }

    public static function tomtomApiKey(): ?string
    {
        $key = self::loadConfig()['tomtom_api_key'];

        return $key !== '' ? $key : null;
    }

    public static function barangayAdminSignupCode(): ?string
    {
        $code = self::loadConfig()['barangay_admin_signup_code'];

        return $code !== '' ? $code : null;
    }

    private static function loadConfig(): array
    {
        $config = [
            'host' => 'localhost',
            'port' => '3306',
            'database' => 'u672637579_Reddiph_backup',
            'username' => 'u672637579_Reddiph_backup',
            'password' => '',
            'tomtom_api_key' => '',
            'barangay_admin_signup_code' => '',
        ];

        $localConfigFile = __DIR__ . '/database.local.php';
        if (is_file($localConfigFile)) {
            $localConfig = require $localConfigFile;
            if (is_array($localConfig)) {
                foreach ([
                    'host',
                    'port',
                    'database',
                    'username',
                    'password',
                    'tomtom_api_key',
                    'barangay_admin_signup_code',
                ] as $key) {
                    if (isset($localConfig[$key]) && is_scalar($localConfig[$key]) && (string) $localConfig[$key] !== '') {
                        $config[$key] = (string) $localConfig[$key];
                    }
                }
            }
        }

        foreach ([
            'host' => 'DB_HOST',
            'port' => 'DB_PORT',
            'database' => 'DB_NAME',
            'username' => 'DB_USER',
            'password' => 'DB_PASS',
            'tomtom_api_key' => 'TOMTOM_API_KEY',
            'barangay_admin_signup_code' => 'REDDIPH_BARANGAY_ADMIN_SIGNUP_CODE',
        ] as $key => $environmentName) {
            $config[$key] = self::environmentValue($environmentName, $config[$key]);
        }

        if ($config['password'] === '') {
            error_log('Mobile API: database password is not configured.');
            throw new RuntimeException('Database connection failed.');
        }

        return $config;
    }

    private static function environmentValue(string $name, string $default = ''): string
    {
        $value = getenv($name);
        if ($value !== false) {
            return $value;
        }

        return isset($_SERVER[$name]) && is_scalar($_SERVER[$name]) ? (string) $_SERVER[$name] : $default;
    }
}
