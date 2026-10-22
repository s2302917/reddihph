<?php

if (!function_exists('str_starts_with')) {
    function str_starts_with(string $haystack, string $needle): bool
    {
        return $needle === '' || strpos($haystack, $needle) === 0;
    }
}

if (!function_exists('str_ends_with')) {
    function str_ends_with(string $haystack, string $needle): bool
    {
        return $needle === '' || substr($haystack, -strlen($needle)) === $needle;
    }
}

function assetUrl(string $path): string
{
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '/';
    $applicationRoot = preg_replace('#/app/controllers/[^/]+$#', '', $scriptName);

    if ($applicationRoot === null || $applicationRoot === $scriptName) {
        $applicationRoot = rtrim(dirname($scriptName), '/\\');
    }

    return rtrim($applicationRoot, '/') . '/' . ltrim($path, '/');
}

function userProfileImageUrl(?string $image): ?string
{
    if (!$image) {
        return null;
    }

    if (preg_match('#^(https?:)?//#i', $image) || str_starts_with($image, '/')) {
        return $image;
    }

    $fileName = basename(str_replace('\\', '/', $image));
    return $fileName !== '' ? assetUrl('assets/images/profiles/' . rawurlencode($fileName)) : null;
}

function sendNoCacheHeaders(): void
{
    if (headers_sent()) {
        return;
    }

    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Cache-Control: post-check=0, pre-check=0', false);
    header('Pragma: no-cache');
    header('Expires: 0');
}

function startApplicationSession(?string $role = null): void
{
    if (session_status() !== PHP_SESSION_NONE) {
        return;
    }

    $role ??= $_GET['role'] ?? $_POST['role'] ?? null;
    $roles = ['hospital_admin', 'doctor', 'nurse', 'resident', 'responder', 'volunteer'];

    if (is_string($role) && in_array($role, $roles, true)) {
        session_name('REDDIPH_' . strtoupper($role) . '_SID');
    }

    session_start();
}

function requireAuthenticated(?string $role = null): void
{
    sendNoCacheHeaders();

    startApplicationSession($role);

    if (empty($_SESSION['logged_in'])) {
        header('Location: ' . assetUrl('app/controllers/portalController.php'), true, 302);
        exit;
    }

    $userId = filter_var($_SESSION['user_id'] ?? null, FILTER_VALIDATE_INT);
    try {
        $stmt = Database::connection()->prepare(
            'SELECT role, banned_until,
                    (banned_until IS NOT NULL AND banned_until > NOW()) AS is_banned
             FROM user_type WHERE id = ? LIMIT 1'
        );
        $stmt->execute([$userId]);
        $account = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $error) {
        error_log('Authenticated account status check failed: ' . $error->getMessage());
        http_response_code(503);
        exit('Account status could not be verified. Please try again.');
    }

    if (!$account || $account['role'] !== ($_SESSION['role'] ?? null)) {
        invalidateApplicationSession();
        header('Location: ' . assetUrl('app/controllers/portalController.php') . '?reason=account_unavailable', true, 302);
        exit;
    }

    if ((int) $account['is_banned'] === 1) {
        invalidateApplicationSession((int) $userId);
        header('Location: ' . assetUrl('app/controllers/portalController.php') . '?reason=account_suspended', true, 302);
        exit;
    }

    if (
        !str_ends_with($_SERVER['SCRIPT_NAME'] ?? '', 'passwordChangeController.php') &&
        !empty($_SESSION['must_change_password'])
    ) {
        $passwordChangeUrl = assetUrl('app/controllers/passwordChangeController.php');
        header('Location: ' . $passwordChangeUrl . '?role=' . rawurlencode((string) $_SESSION['role']), true, 302);
        exit;
    }

    if ($role !== null && ($_SESSION['role'] ?? null) !== $role) {
        http_response_code(403);
        exit('Access denied.');
    }
}

function invalidateApplicationSession(?int $userId = null): void
{
    if (session_status() === PHP_SESSION_NONE) {
        startApplicationSession();
    }
    $userId ??= (int) ($_SESSION['user_id'] ?? 0);
    if ($userId > 0) {
        Database::connection()->prepare(
            'UPDATE user_type SET last_login_at = NULL WHERE id = ?'
        )->execute([$userId]);
    }
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }
    session_destroy();
}

sendNoCacheHeaders();

/**
 * Hostinger database connection.
 *
 * Set the password in database.local.php or DB_PASS in the PHP environment.
 * Environment variables override the local file.
 */
final class Database
{
    private static $connection = null;

    public static function connection(): PDO
    {
        if (self::$connection instanceof PDO) {
            return self::$connection;
        }

        $config = [
            'host' => 'localhost',
            'port' => '3306',
            'database' => 'u672637579_Reddiph_backup',
            'username' => 'u672637579_Reddiph_backup',
            'password' => '',
        ];

        $localConfigFile = __DIR__ . '/database.local.php';
        if (is_file($localConfigFile)) {
            $localConfig = require $localConfigFile;
            if (!is_array($localConfig)) {
                throw new RuntimeException('config/database.local.php must return an array.');
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
            $config[$key] = self::environmentValue($environmentName, $config[$key]);
        }

        if ($config['password'] === '') {
            throw new RuntimeException('Set DB_PASS in the environment or config/database.local.php.');
        }

        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            $config['host'],
            $config['port'],
            $config['database']
        );

        try {
            self::$connection = new PDO(
                $dsn,
                $config['username'],
                $config['password'],
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );

            self::$connection->exec("SET time_zone = '+08:00'");
            date_default_timezone_set('Asia/Manila');

            return self::$connection;
        } catch (PDOException $e) {
            error_log('Database connection error: ' . $e->getMessage());
            throw new RuntimeException('Database connection failed: ' . $e->getMessage());
        }
    }

    private static function environmentValue(string $name, string $default = ''): string
    {
        $value = getenv($name);
        if ($value !== false) {
            return $value;
        }

        if (isset($_ENV[$name]) && is_scalar($_ENV[$name])) {
            return (string) $_ENV[$name];
        }

        return isset($_SERVER[$name]) && is_scalar($_SERVER[$name])
            ? (string) $_SERVER[$name]
            : $default;
    }
}