<?php
namespace App\Controllers;

class DashboardController
{
    public static function initialize(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        if (!defined('BASE_PATH')) {
            define('BASE_PATH', dirname(__DIR__, 2));
            $documentRoot = realpath($_SERVER['DOCUMENT_ROOT'] ?? '');
            $appRoot = realpath(BASE_PATH);
            if ($documentRoot === false || $appRoot === false ||
                ($appRoot !== $documentRoot && !str_starts_with($appRoot, $documentRoot . DIRECTORY_SEPARATOR))) {
                throw new \RuntimeException('Unable to determine the application URL path.');
            }
            define(
                'APP_BASE_URL',
                $appRoot === $documentRoot
                    ? ''
                    : '/' . str_replace(DIRECTORY_SEPARATOR, '/', substr($appRoot, strlen($documentRoot) + 1))
            );

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
            '/roles' => 'RolesController.php',
            '/facilities' => 'FacilitiesController.php',
            '/branding' => 'BrandingController.php',
            '/audit-logs' => 'AuditLogsController.php',
            '/settings' => 'SettingsController.php',
        ];
        $normalizedPath = '/' . trim($path, '/');
        if (!isset($pages[$normalizedPath])) {
            throw new \InvalidArgumentException('Unknown application page: ' . $path);
        }

        return APP_BASE_URL . '/app/controller/' . $pages[$normalizedPath];
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
        if (empty($_SESSION['user'])) {
            self::redirect(self::url('/login'));
        }
    }

    public static function runPostAction(string $path, callable $action): void
    {
        if (!self::csrfValid()) {
            self::setFlash('error', 'Your session expired. Reload the page and try again.');
            self::redirect(self::url($path));
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
        self::redirect(self::url($path));
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
            'roles' => 'RolesView.php',
            'facilities' => 'FacilitiesView.php',
            'branding' => 'BrandingView.php',
            'audit-logs' => 'AuditLogsView.php',
            'settings' => 'SettingsView.php',
        ];
        $titles = [
            'overview' => 'Overview',
            'users' => 'Users',
            'roles' => 'Roles and permissions',
            'facilities' => 'Hospitals and barangays',
            'branding' => 'UI and branding',
            'audit-logs' => 'Audit logs',
            'settings' => 'System settings',
        ];
        if (!isset($viewFiles[$page], $titles[$page])) {
            throw new \InvalidArgumentException('Unknown dashboard page: ' . $page);
        }

        $navigation = [
            ['label' => 'Overview', 'path' => '/overview', 'group' => 'Manage', 'icon' => 'overview'],
            ['label' => 'Users', 'path' => '/users', 'group' => 'Manage', 'icon' => 'users'],
            ['label' => 'Roles and permissions', 'path' => '/roles', 'group' => 'Manage', 'icon' => 'roles'],
            ['label' => 'Hospital and barangays', 'path' => '/facilities', 'group' => 'Manage', 'icon' => 'facilities'],
            ['label' => 'UI and branding', 'path' => '/branding', 'group' => 'Design', 'icon' => 'branding'],
            ['label' => 'Audit Logs', 'path' => '/audit-logs', 'group' => 'System', 'icon' => 'audit'],
            ['label' => 'System Settings', 'path' => '/settings', 'group' => 'System', 'icon' => 'settings'],
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
