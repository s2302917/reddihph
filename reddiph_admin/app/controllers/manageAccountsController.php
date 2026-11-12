<?php

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../models/ManageAccountsModel.php';
require_once __DIR__ . '/../models/loginModel.php';

requireAuthenticated('hospital_admin');

final class ManageAccountsController
{
    private ManageAccountsModel $model;
    private int $adminId;
    private int $hospitalId;
    private string $hospitalName;

    public function __construct()
    {
        $this->model = new ManageAccountsModel();
        $this->adminId = (int) ($_SESSION['user_id'] ?? 0);
        $context = $this->model->getHospitalAdminContext($this->adminId);

        if ($context === null || (int) $context['hospital_id'] <= 0) {
            http_response_code(403);
            exit('A valid hospital assignment is required.');
        }

        $this->hospitalId = (int) $context['hospital_id'];
        $this->hospitalName = (string) $context['hospital_name'];
        $_SESSION['manage_accounts_csrf'] ??= bin2hex(random_bytes(32));
    }

    public function handleRequest(): void
    {
        $action = (string) ($_GET['action'] ?? '');

        if ($action === 'list') {
            $this->listAccounts();
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->handleMutation();
            return;
        }

        $profile = $this->model->getAdminProfile($this->adminId);
        $result = $this->model->getAccounts($this->hospitalId, [
            'search' => '',
            'role' => 'all',
            'status' => 'all',
            'page' => 1,
        ]);
        $hospitalName = $this->hospitalName;
        $coordinatorName = (string) ($profile['full_name'] ?? ($_SESSION['full_name'] ?? 'Hospital Administrator'));
        $coordinatorRole = 'Hospital Admin';
        $coordinatorInitials = $this->initials($coordinatorName);
        $coordinatorImage = $profile['profile_image'] ?? null;
        $csrfToken = (string) $_SESSION['manage_accounts_csrf'];

        require __DIR__ . '/../views/manageAccountsView.php';
    }

    private function listAccounts(): void
    {
        $role = (string) ($_GET['role'] ?? 'all');
        $status = (string) ($_GET['status'] ?? 'all');

        if (!in_array($role, ['all', 'doctor', 'nurse'], true)) {
            $this->jsonResponse(['success' => false, 'message' => 'Invalid role filter.'], 422);
        }
        if (!in_array($status, ['all', 'approved', 'pending', 'rejected'], true)) {
            $this->jsonResponse(['success' => false, 'message' => 'Invalid status filter.'], 422);
        }

        try {
            $result = $this->model->getAccounts($this->hospitalId, [
                'search' => trim(substr((string) ($_GET['search'] ?? ''), 0, 150)),
                'role' => $role,
                'status' => $status,
                'page' => max(1, (int) ($_GET['page'] ?? 1)),
            ]);
            $this->jsonResponse(['success' => true] + $result);
        } catch (Throwable $error) {
            error_log('[ManageAccountsController] Account list failed: ' . $error->getMessage());
            $this->jsonResponse(['success' => false, 'message' => 'Accounts could not be loaded. Please try again.'], 500);
        }
    }

    private function handleMutation(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        if (!hash_equals(
            (string) ($_SESSION['manage_accounts_csrf'] ?? ''),
            (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')
        )) {
            $this->jsonResponse(['success' => false, 'message' => 'Your session verification expired. Refresh the page and try again.'], 403);
        }

        $payload = json_decode((string) file_get_contents('php://input'), true);
        if (!is_array($payload)) {
            $this->jsonResponse(['success' => false, 'message' => 'Invalid request.'], 400);
        }

        try {
            if (($payload['action'] ?? '') === 'create') {
                $result = $this->model->createAccount(
                    $this->adminId,
                    $this->hospitalId,
                    $payload
                );
                $this->jsonResponse($result, $result['success'] ? 201 : ($result['status'] ?? 422));
            }

            if (($payload['action'] ?? '') === 'reset_password') {
                $accountId = filter_var($payload['account_id'] ?? null, FILTER_VALIDATE_INT);
                if (!$accountId || $accountId <= 0) {
                    $this->jsonResponse(['success' => false, 'message' => 'Select a valid account.'], 422);
                }

                $result = $this->model->resetPassword(
                    $this->adminId,
                    $this->hospitalId,
                    $accountId
                );
                $this->jsonResponse($result, $result['success'] ? 200 : ($result['status'] ?? 422));
            }

            $this->jsonResponse(['success' => false, 'message' => 'Unsupported action.'], 400);
        } catch (Throwable $error) {
            error_log('[ManageAccountsController] Account mutation failed: ' . $error->getMessage());
            $this->jsonResponse(['success' => false, 'message' => 'The request could not be completed. Please try again.'], 500);
        }
    }

    private function jsonResponse(array $data, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }

    private function initials(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        $initials = '';
        foreach (array_slice($parts, 0, 2) as $part) {
            if ($part !== '') {
                $initials .= strtoupper(substr($part, 0, 1));
            }
        }

        return $initials !== '' ? $initials : 'HA';
    }
}

try {
    (new ManageAccountsController())->handleRequest();
} catch (Throwable $error) {
    error_log('[ManageAccountsController] Request setup failed: ' . $error->getMessage());
    http_response_code(503);
    header('Cache-Control: no-store');

    if (($_GET['action'] ?? '') === 'list' || $_SERVER['REQUEST_METHOD'] === 'POST') {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'message' => 'Manage Accounts is temporarily unavailable. Please try again later.',
        ]);
    } else {
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Manage Accounts is temporarily unavailable. Please try again later.';
    }
}
