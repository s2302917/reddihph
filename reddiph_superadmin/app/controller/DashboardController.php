<?php
namespace App\Controllers;

class DashboardController
{
    private const SESSION_IDLE_TIMEOUT_SECONDS = 900;

    public static function initialize(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        if (!defined('BASE_PATH')) {
            define('BASE_PATH', dirname(__DIR__, 2));
            $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/index.php');
            $scriptDirectory = rtrim(dirname($scriptName), '/.');
            define('APP_BASE_URL', $scriptDirectory === '' ? '' : $scriptDirectory);

            spl_autoload_register(static function (string $class): void {
                $namespaces = [
                    'App\\Controllers\\' => BASE_PATH . '/app/controller/',
                    'App\\Models\\' => BASE_PATH . '/app/models/',
                ];
                foreach ($namespaces as $namespace => $directory) {
                    if (str_starts_with($class, $namespace)) {
                        $file = $directory . substr($class, strlen($namespace)) . '.php';
                        if (is_file($file)) {
                            require $file;
                        }
                        return;
                    }
                }
            });
        }
    }

    public static function url(string $path): string
    {
        $pages = [
            '/' => 'LoginController.php',
            '/login' => 'LoginController.php',
            '/logout' => 'LogoutController.php',
            '/overview' => 'OverviewController.php',
            '/users' => 'UsersController.php',
            '/facilities' => 'FacilitiesController.php',
            '/audit-logs' => 'AuditLogsController.php',
            '/session-activity' => 'SessionActivityController.php',
        ];
        $normalizedPath = '/' . trim($path, '/');
        if (!isset($pages[$normalizedPath])) {
            throw new \InvalidArgumentException('Unknown application page: ' . $path);
        }

        return APP_BASE_URL . ($normalizedPath === '/' ? '/' : $normalizedPath);
    }

    public static function redirect(string $url): never
    {
        header('Location: ' . $url);
        exit;
    }

    public static function csrfToken(): string
    {
        return $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
    }

    public static function csrfValid(): bool
    {
        return isset($_POST['csrf'], $_SESSION['csrf']) &&
            is_string($_POST['csrf']) &&
            is_string($_SESSION['csrf']) &&
            hash_equals($_SESSION['csrf'], $_POST['csrf']);
    }

    public static function postString(string $key, string $default = ''): string
    {
        $value = $_POST[$key] ?? $default;
        if (!is_string($value)) {
            throw new \InvalidArgumentException('Invalid form data. Please review the fields and try again.');
        }
        return $value;
    }

    public static function requireSuperAdmin(): void
    {
        if (!self::refreshAuthenticatedSession()) {
            self::redirect(self::url('/login'));
        }
    }

    public static function refreshAuthenticatedSession(): bool
    {
        if (empty($_SESSION['user']) || !is_array($_SESSION['user'])) {
            return false;
        }

        $lastActivity = filter_var(
            $_SESSION['last_activity'] ?? null,
            FILTER_VALIDATE_INT
        );
        if (
            is_int($lastActivity) &&
            time() - $lastActivity > self::SESSION_IDLE_TIMEOUT_SECONDS
        ) {
            self::expireAuthenticatedSession();
            return false;
        }

        $_SESSION['last_activity'] = time();
        return true;
    }

    public static function destroySession(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $cookie = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires' => time() - 42000,
                'path' => $cookie['path'],
                'domain' => $cookie['domain'],
                'secure' => $cookie['secure'],
                'httponly' => $cookie['httponly'],
                'samesite' => $cookie['samesite'] ?? 'Lax',
            ]);
        }
        session_destroy();
    }

    public static function sessionIdleTimeoutSeconds(): int
    {
        return self::SESSION_IDLE_TIMEOUT_SECONDS;
    }

    private static function expireAuthenticatedSession(): void
    {
        $userId = filter_var($_SESSION['user']['id'] ?? null, FILTER_VALIDATE_INT);
        if (is_int($userId) && $userId > 0) {
            try {
                \App\Models\AuditLogsModel::record($userId, 'auth.logout', 'user', (string) $userId, [
                    'reason' => 'idle_timeout',
                ]);
            } catch (\Throwable $exception) {
                error_log('Super Admin idle logout audit event could not be recorded: ' . $exception->getMessage());
            }
        }
        self::destroySession();
    }

    public static function runPostAction(string $path, callable $action, array $redirectParameters = []): void
    {
        $redirectUrl = self::url($path);
        if ($redirectParameters) {
            $redirectUrl .= '?' . http_build_query($redirectParameters);
        }
        if (!self::csrfValid()) {
            self::setFlash('error', 'Your session expired. Reload the page and try again.');
            self::redirect($redirectUrl);
        }
        try {
            $message = \App\Models\DatabaseModel::transaction($action);
            self::setFlash('success', is_string($message) ? $message : 'Changes saved.');
        } catch (\PDOException $exception) {
            error_log('Super Admin database action failed: ' . $exception->getMessage());
            self::setFlash('error', 'The database rejected this change. Check for duplicate values, assigned records, and that the updated schema is imported.');
        } catch (\InvalidArgumentException | \RuntimeException $exception) {
            self::setFlash('error', $exception->getMessage());
        }
        self::redirect($redirectUrl);
    }

    public static function setFlash(string $type, string $message): void
    {
        $_SESSION['flash_message'] = ['type' => $type, 'message' => $message];
    }

    public static function renderView(string $view, array $data = [], string $layout = 'AuthLayoutView.php'): void
    {
        extract($data, EXTR_SKIP);
        $config = require BASE_PATH . '/config/config.php';
        ob_start();
        require BASE_PATH . '/app/views/' . $view;
        $content = ob_get_clean();
        require BASE_PATH . '/app/views/' . $layout;
    }

    public static function renderDashboard(string $page, array $data = []): void
    {
        $viewFiles = [
            'overview' => 'OverviewView.php',
            'users' => 'UsersView.php',
            'facilities' => 'FacilitiesView.php',
            'audit-logs' => 'AuditLogsView.php',
        ];
        $titles = [
            'overview' => 'Overview',
            'users' => 'Users',
            'facilities' => 'Hospitals and barangays',
            'audit-logs' => 'Audit logs',
        ];
        if (!isset($viewFiles[$page], $titles[$page])) {
            throw new \InvalidArgumentException('Unknown dashboard page: ' . $page);
        }

        $navigation = [
            ['label' => 'Overview', 'path' => '/overview', 'group' => 'Manage', 'icon' => 'overview'],
            ['label' => 'Users', 'path' => '/users', 'group' => 'Manage', 'icon' => 'users'],
            ['label' => 'Hospital and barangays', 'path' => '/facilities', 'group' => 'Manage', 'icon' => 'facilities'],
            ['label' => 'Audit logs', 'path' => '/audit-logs', 'group' => 'Manage', 'icon' => 'audit'],
        ];
        foreach ($navigation as &$item) {
            $item['url'] = self::url($item['path']);
        }
        unset($item);

        $navigationGroups = [];
        foreach ($navigation as $item) {
            $navigationGroups[$item['group']][] = $item;
        }

        self::renderView($viewFiles[$page], array_merge($data, [
            'activePage' => $page,
            'navigation' => $navigation,
            'navigationGroups' => $navigationGroups,
            'pageTitle' => $titles[$page],
            'user' => $_SESSION['user'],
            'csrf' => self::csrfToken(),
            'flash' => $_SESSION['flash_message'] ?? null,
        ]), 'DashboardLayoutView.php');
        unset($_SESSION['flash_message']);
    }
}
