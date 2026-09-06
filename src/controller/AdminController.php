<?php

namespace App\Controllers;

use App\DAO\UserDAO;
use App\DAO\TaskDAO;
use App\DAO\AuditLogDAO;
use App\Utils\JWTUtil;
use App\Utils\ResponseUtil;
use Exception;

class AdminController
{
    private UserDAO $userDAO;
    private TaskDAO $taskDAO;
    private AuditLogDAO $auditLogDAO;

    public function __construct()
    {
        $this->userDAO = new UserDAO();
        $this->taskDAO = new TaskDAO();
        $this->auditLogDAO = new AuditLogDAO();
    }


    /**
     * Authenticate and authorize Administrator access.
     */
    private function requireAdmin(): ?array
    {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (!preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            ResponseUtil::error('Authentication required.', 401);
            return null;
        }

        $token = $matches[1];
        $payload = JWTUtil::validateToken($token);

        if (!$payload || !isset($payload['user_id'])) {
            ResponseUtil::error('Invalid or expired session token.', 401);
            return null;
        }

        if (($payload['role'] ?? '') !== 'ADMINISTRATOR') {
            ResponseUtil::error('Forbidden: Administrator access required.', 403);
            return null;
        }

        return $payload;
    }

    /**
     * Retrieve all users/staff with filtering (GET /api/v1/admin/users)
     */
    public function getUsers(): void
    {
        $admin = $this->requireAdmin();
        if (!$admin) return;

        $search = strtolower(trim($_GET['search'] ?? ''));
        $roleFilter = strtoupper(trim($_GET['role'] ?? ''));
        $statusFilter = strtolower(trim($_GET['status'] ?? ''));

        try {
            $allUsers = $this->userDAO->getAllUsers();

            // Reverse order by created_at ASC to generate predictable sequential IDs U001, U002...
            $orderedUsers = array_reverse($allUsers);
            $formattedUsers = [];

            $index = 1;
            foreach ($orderedUsers as $user) {
                $userCode = sprintf("U%03d", $index++);
                $role = strtoupper($user['role'] ?? 'TOURIST');
                $isActive = (bool)$user['is_active'];

                // Filter by role
                if (!empty($roleFilter) && $roleFilter !== 'ALL' && $roleFilter !== 'ALL ROLES') {
                    if ($role !== $roleFilter) {
                        continue;
                    }
                }

                // Filter by status
                if ($statusFilter === 'active' && !$isActive) {
                    continue;
                } elseif ($statusFilter === 'inactive' && $isActive) {
                    continue;
                }

                // Filter by search query (name or email or ID)
                if (!empty($search)) {
                    $fullName = strtolower($user['full_name'] ?? '');
                    $email = strtolower($user['email'] ?? '');
                    $code = strtolower($userCode);

                    if (strpos($fullName, $search) === false && 
                        strpos($email, $search) === false && 
                        strpos($code, $search) === false) {
                        continue;
                    }
                }

                $formattedUsers[] = [
                    'id'          => $userCode,
                    'user_id'     => $user['user_id'],
                    'full_name'   => $user['full_name'],
                    'email'       => $user['email'],
                    'role'        => $role,
                    'role_label'  => self::getRoleLabel($role),
                    'is_active'   => $isActive,
                    'created_at'  => $user['created_at']
                ];
            }

            // Return in latest order
            $formattedUsers = array_reverse($formattedUsers);

            ResponseUtil::success([
                'users' => $formattedUsers,
                'count' => count($formattedUsers)
            ], 'Staff roster retrieved successfully.');

        } catch (Exception $e) {
            ResponseUtil::error('Failed to retrieve user roster: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Create a new staff account (POST /api/v1/admin/users)
     */
    public function createUser(): void
    {
        $admin = $this->requireAdmin();
        if (!$admin) return;

        $rawInput = file_get_contents('php://input');
        $data = json_decode($rawInput, true) ?? $_POST;

        $fullName = trim($data['full_name'] ?? '');
        $email = strtolower(trim($data['email'] ?? ''));
        $role = strtoupper(trim($data['role'] ?? 'SITE_RANGER'));
        $password = $data['password'] ?? 'Pass1234!';

        $errors = [];

        if (empty($fullName)) {
            $errors['full_name'] = 'Full name is required.';
        }

        if (empty($email)) {
            $errors['email'] = 'Email address is required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Please enter a valid email address.';
        } elseif ($this->userDAO->getUserByEmail($email)) {
            $errors['email'] = 'This email address is already registered.';
        }

        $validRoles = ['SITE_RANGER', 'MAINTENANCE_CREW', 'ADMINISTRATOR', 'TOURIST'];
        if (!in_array($role, $validRoles, true)) {
            $errors['role'] = 'Invalid role selected.';
        }

        if (!empty($password) && strlen($password) < 8) {
            $errors['password'] = 'Password must be at least 8 characters long.';
        }

        if (!empty($errors)) {
            ResponseUtil::error('Validation failed.', 400, ['errors' => $errors]);
            return;
        }

        try {
            $newUserId = $this->userDAO->createUser($email, $password, $fullName, $role, ['must_change_password' => true]);
            if (!$newUserId) {
                throw new Exception('Database insertion failed.');
            }


            try {
                $this->auditLogDAO->log($admin['user_id'], 'USER_CREATED', "Admin created account for {$email} ({$role})");
            } catch (Exception $e) {
                // Non-blocking
            }

            ResponseUtil::success([
                'user_id' => $newUserId,
                'email'   => $email,
                'role'    => $role
            ], 'Staff account created successfully.');

        } catch (Exception $e) {
            ResponseUtil::error('Unable to create account: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Update user details (PUT /api/v1/admin/users/{id})
     */
    public function updateUser(string $userId): void
    {
        $admin = $this->requireAdmin();
        if (!$admin) return;

        $existingUser = $this->userDAO->getUserById($userId);
        if (!$existingUser) {
            ResponseUtil::error('User not found.', 404);
            return;
        }

        $rawInput = file_get_contents('php://input');
        $data = json_decode($rawInput, true) ?? $_POST;

        $fullName = trim($data['full_name'] ?? $existingUser['full_name']);
        $email = strtolower(trim($data['email'] ?? $existingUser['email']));
        $role = strtoupper(trim($data['role'] ?? $existingUser['role']));

        $errors = [];

        if (empty($fullName)) {
            $errors['full_name'] = 'Full name is required.';
        }

        if (empty($email)) {
            $errors['email'] = 'Email address is required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Please enter a valid email address.';
        } elseif (strtolower($existingUser['email']) !== $email) {
            $otherUser = $this->userDAO->getUserByEmail($email);
            if ($otherUser && $otherUser['user_id'] !== $userId) {
                $errors['email'] = 'This email is already in use by another user.';
            }
        }

        $validRoles = ['SITE_RANGER', 'MAINTENANCE_CREW', 'ADMINISTRATOR', 'TOURIST'];
        if (!in_array($role, $validRoles, true)) {
            $errors['role'] = 'Invalid role selected.';
        }

        if (!empty($errors)) {
            ResponseUtil::error('Validation failed.', 400, ['errors' => $errors]);
            return;
        }

        try {
            $updated = $this->userDAO->updateUserByAdmin($userId, $fullName, $email, $role);
            if (!$updated) {
                throw new Exception('Database update failed.');
            }

            try {
                $this->auditLogDAO->log($admin['user_id'], 'USER_UPDATED', "Admin updated user {$email} details");
            } catch (Exception $e) {
                // Non-blocking
            }

            ResponseUtil::success(null, 'User details updated successfully.');

        } catch (Exception $e) {
            ResponseUtil::error('Unable to update user: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Activate or Deactivate user (PATCH /api/v1/admin/users/{id}/status)
     */
    public function updateStatus(string $userId): void
    {
        $admin = $this->requireAdmin();
        if (!$admin) return;

        $existingUser = $this->userDAO->getUserById($userId);
        if (!$existingUser) {
            ResponseUtil::error('User not found.', 404);
            return;
        }

        $rawInput = file_get_contents('php://input');
        $data = json_decode($rawInput, true) ?? $_POST;

        $isActive = isset($data['is_active']) ? (bool)$data['is_active'] : !$existingUser['is_active'];

        // Prevent admin self-deactivation
        if ($admin['user_id'] === $userId && !$isActive) {
            ResponseUtil::error('You cannot deactivate your own administrator account.', 400);
            return;
        }

        try {
            $updated = $this->userDAO->updateUserStatus($userId, $isActive);
            if (!$updated) {
                throw new Exception('Status update failed.');
            }

            $actionText = $isActive ? 'ACTIVATED' : 'DEACTIVATED';
            try {
                $this->auditLogDAO->log($admin['user_id'], 'USER_STATUS_CHANGE', "Admin {$actionText} user {$existingUser['email']}");
            } catch (Exception $e) {
                // Non-blocking
            }

            ResponseUtil::success([
                'user_id'   => $userId,
                'is_active' => $isActive
            ], $isActive ? 'User account activated.' : 'User account deactivated.');

        } catch (Exception $e) {
            ResponseUtil::error('Unable to change user status: ' . $e->getMessage(), 500);
        }
    }

    private static function getRoleLabel(string $role): string
    {
        switch ($role) {
            case 'SITE_RANGER': return 'Site Ranger';
            case 'MAINTENANCE_CREW': return 'Maintenance Crew';
            case 'ADMINISTRATOR': return 'Administrator';
            case 'TOURIST': return 'Tourist';
            default: return ucfirst(strtolower($role));
        }
    }

    // =========================================================================
    // MODULE 3: ADMIN TASK CONTROL DASHBOARD ENDPOINTS
    // =========================================================================


    /**
     * Retrieve complete tasks overview with status counters, fleet status, and filtered tasks (GET /api/v1/admin/tasks)
     */
    public function getTasks(): void
    {
        $admin = $this->requireAdmin();
        if (!$admin) return;

        $status = $_GET['status'] ?? null;
        $search = $_GET['search'] ?? null;
        $category = $_GET['category'] ?? null;
        $skill = $_GET['skill'] ?? null;

        try {
            $data = $this->taskDAO->getAdminTasksOverview($status, $search, $category, $skill);
            ResponseUtil::success($data, 'Admin task control overview retrieved.');
        } catch (Exception $e) {
            ResponseUtil::error('Failed to retrieve task control data: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Retrieve dynamic crew candidates with live distances from a task location (GET /api/v1/admin/tasks/{id}/candidates)
     */
    public function getCrewCandidatesForTask(string $taskId): void
    {
        $admin = $this->requireAdmin();
        if (!$admin) return;

        try {
            $data = $this->taskDAO->getCrewsFleetForTask($taskId);
            ResponseUtil::success($data, 'Crew candidates retrieved with distances.');
        } catch (Exception $e) {
            ResponseUtil::error('Failed to retrieve crew candidates: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Run algorithm preview recommendation for single task (POST /api/v1/admin/tasks/{id}/auto-dispatch-preview)
     */
    public function previewSingleTaskAutoDispatch(string $taskId): void
    {
        $admin = $this->requireAdmin();
        if (!$admin) return;

        try {
            $result = $this->taskDAO->previewSingleTaskAutoDispatch($taskId);
            ResponseUtil::success($result, 'Single-task auto-dispatch recommendation evaluated.');
        } catch (Exception $e) {
            ResponseUtil::error('Failed to evaluate auto-dispatch algorithm: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Assign or Add-On assign a task to a maintenance crew (POST /api/v1/admin/tasks/{id}/assign)
     */
    public function assignCrew(string $taskId): void
    {
        $admin = $this->requireAdmin();
        if (!$admin) return;

        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $crewId = trim($input['crew_id'] ?? ($_POST['crew_id'] ?? ''));
        $isAddOn = !empty($input['is_addon']) || !empty($_POST['is_addon']);

        if (empty($crewId)) {
            ResponseUtil::error('Maintenance technician crew_id is required.', 400);
            return;
        }

        try {
            $result = $this->taskDAO->manualAssignTask($taskId, $crewId, $admin['user_id'], $isAddOn);
            if (!$result['success']) {
                ResponseUtil::error($result['error'] ?? 'Assignment failed.', 400);
                return;
            }

            try {
                $actionName = $isAddOn ? 'TASK_ADDON_ASSIGNED' : 'TASK_MANUALLY_ASSIGNED';
                $this->auditLogDAO->log($admin['user_id'], $actionName, "Admin assigned task {$taskId} to crew {$result['assigned_crew_name']}");
            } catch (Exception $e) {
                // Non-blocking
            }

            ResponseUtil::success($result, "Task successfully assigned to {$result['assigned_crew_name']} (24-hour acceptance window).");
        } catch (Exception $e) {
            ResponseUtil::error('Failed to assign task: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Run batch auto-dispatch across all unassigned Open Pool tasks (POST /api/v1/admin/tasks/batch-dispatch)
     */
    public function runBatchDispatch(): void
    {
        $admin = $this->requireAdmin();
        if (!$admin) return;

        try {
            $result = $this->taskDAO->runAutoDispatch();

            try {
                $this->auditLogDAO->log(
                    $admin['user_id'], 
                    'BATCH_AUTO_DISPATCH_TRIGGERED', 
                    "Batch auto-dispatch ran: {$result['assigned_count']} assigned, {$result['unassigned_count']} unassigned."
                );
            } catch (Exception $e) {
                // Non-blocking
            }

            ResponseUtil::success($result, "Batch auto-dispatch completed: {$result['assigned_count']} tasks dispatched.");
        } catch (Exception $e) {
            ResponseUtil::error('Batch auto-dispatch failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Admin approves a PRECOMPLETE work order (POST /api/v1/admin/tasks/{id}/approve-complete)
     */
    public function approveCompletion(string $taskId): void
    {
        $admin = $this->requireAdmin();
        if (!$admin) return;

        try {
            $result = $this->taskDAO->approveTaskCompletion($taskId, $admin['user_id']);

            try {
                $this->auditLogDAO->log(
                    $admin['user_id'],
                    'TASK_COMPLETED_APPROVED',
                    "Admin approved completion for task {$taskId}. +5.0 performance points awarded to technician {$result['crew_name']}."
                );
            } catch (Exception $e) {
                // Non-blocking
            }

            ResponseUtil::success($result, "Work order completion approved! +5.0 performance points awarded to {$result['crew_name']}.");
        } catch (Exception $e) {
            ResponseUtil::error('Failed to approve completion: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Admin rejects a PRECOMPLETE submission and returns it for rework (POST /api/v1/admin/tasks/{id}/reject-complete)
     */
    public function rejectCompletion(string $taskId): void
    {
        $admin = $this->requireAdmin();
        if (!$admin) return;

        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $reason = trim($input['return_reason'] ?? ($_POST['return_reason'] ?? ''));

        if (empty($reason)) {
            ResponseUtil::error('A return reason / remarks is required when rejecting completion.', 400);
            return;
        }

        try {
            $result = $this->taskDAO->rejectTaskCompletion($taskId, $admin['user_id'], $reason);

            try {
                $this->auditLogDAO->log(
                    $admin['user_id'],
                    'TASK_COMPLETED_REJECTED',
                    "Admin returned task {$taskId} to technician {$result['crew_name']} for rework: {$reason}"
                );
            } catch (Exception $e) {
                // Non-blocking
            }

            ResponseUtil::success($result, "Work order returned to technician {$result['crew_name']} for rework.");
        } catch (Exception $e) {
            ResponseUtil::error('Failed to return task for rework: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Admin returns a DROPPED task back to Open Pool (POST /api/v1/admin/tasks/{id}/return-open-pool)
     */
    public function returnToOpenPool(string $taskId): void
    {
        $admin = $this->requireAdmin();
        if (!$admin) return;

        try {
            $result = $this->taskDAO->returnTaskToOpenPool($taskId, $admin['user_id']);

            try {
                $this->auditLogDAO->log(
                    $admin['user_id'],
                    'TASK_RETURNED_TO_OPEN_POOL',
                    "Admin returned task {$taskId} (District: {$result['district']}) back to Open Maintenance Pool."
                );
            } catch (Exception $e) {
                // Non-blocking
            }

            ResponseUtil::success($result, "Work order successfully returned to Open Maintenance Pool.");
        } catch (Exception $e) {
            ResponseUtil::error('Failed to return task to Open Pool: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Admin approves a PRE_INVALID flag (POST /api/v1/admin/tasks/{id}/approve-invalid)
     */
    public function approveInvalid(string $taskId): void
    {
        $admin = $this->requireAdmin();
        if (!$admin) return;

        try {
            $result = $this->taskDAO->approveInvalidTask($taskId, $admin['user_id']);

            try {
                $this->auditLogDAO->log(
                    $admin['user_id'],
                    'TASK_INVALID_APPROVED',
                    "Admin approved invalid flag for task {$taskId}. -20 reputation points deducted from citizen {$result['reporter_name']}."
                );
            } catch (Exception $e) {
                // Non-blocking
            }

            ResponseUtil::success($result, "Incident flagged as INVALID_RESOLVED. -20 reputation points deducted from {$result['reporter_name']}.");
        } catch (Exception $e) {
            ResponseUtil::error('Failed to approve invalid flag: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Admin rejects a PRE_INVALID flag and returns task for rework (POST /api/v1/admin/tasks/{id}/reject-invalid)
     */
    public function rejectInvalid(string $taskId): void
    {
        $admin = $this->requireAdmin();
        if (!$admin) return;

        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $reason = trim($input['return_reason'] ?? ($_POST['return_reason'] ?? ''));

        if (empty($reason)) {
            ResponseUtil::error('A rejection reason / remarks is required when returning an invalid flag for rework.', 400);
            return;
        }

        try {
            $result = $this->taskDAO->rejectInvalidTask($taskId, $admin['user_id'], $reason);

            try {
                $this->auditLogDAO->log(
                    $admin['user_id'],
                    'TASK_INVALID_REJECTED',
                    "Admin rejected invalid flag for task {$taskId} and returned to technician {$result['crew_name']}: {$reason}"
                );
            } catch (Exception $e) {
                // Non-blocking
            }

            ResponseUtil::success($result, "Invalid flag rejected. Work order returned to technician {$result['crew_name']} for rework.");
        } catch (Exception $e) {
            ResponseUtil::error('Failed to reject invalid flag: ' . $e->getMessage(), 500);
        }
    }
}

>>>>>>> origin/main
