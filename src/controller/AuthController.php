<?php

namespace App\Controllers;

use App\DAO\UserDAO;
use App\DAO\AuditLogDAO;
use App\Utils\JWTUtil;
use App\Utils\ResponseUtil;
use Exception;

class AuthController
{
    private UserDAO $userDAO;
    private AuditLogDAO $auditLogDAO;

    public function __construct()
    {
        $this->userDAO = new UserDAO();
        $this->auditLogDAO = new AuditLogDAO();
    }

    /**
     * Handle user login (POST /api/v1/auth/login)
     */
    public function login(): void
    {
        // 1. Parse JSON input
        $rawInput = file_get_contents('php://input');
        $data = json_decode($rawInput, true) ?? $_POST;

        $email = trim($data['email'] ?? '');
        $password = $data['password'] ?? '';

        // 2. Client / Field Validation
        if (empty($email) && empty($password)) {
            ResponseUtil::error('Email address and password are required.', 400, [
                'errors' => [
                    'email' => 'Email address is required.',
                    'password' => 'Password is required.'
                ]
            ]);
            return;
        }

        if (empty($email)) {
            ResponseUtil::error('Email address is required.', 400, [
                'errors' => ['email' => 'Email address is required.']
            ]);
            return;
        }

        if (empty($password)) {
            ResponseUtil::error('Password is required.', 400, [
                'errors' => ['password' => 'Password is required.']
            ]);
            return;
        }

        // Validate email format
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            ResponseUtil::error('Please enter a valid email address (e.g. you@example.com).', 400, [
                'errors' => ['email' => 'Please enter a valid email address (e.g. you@example.com).']
            ]);
            return;
        }

        try {
            // 3. Lookup user by email
            $user = $this->userDAO->getUserByEmail($email);

            if (!$user) {
                ResponseUtil::error('Incorrect email or password. Please try again.', 401, [
                    'errors' => ['general' => 'Incorrect email or password. Please try again.']
                ]);
                return;
            }

            // 4. Check if account is active
            if (isset($user['is_active']) && ($user['is_active'] === false || $user['is_active'] === 0 || $user['is_active'] === 'f')) {
                ResponseUtil::error('Oops, your account has been deactivated and cannot login.', 403, [
                    'errors' => ['email' => 'Oops, your account has been deactivated and cannot login.']
                ]);
                return;
            }

            // 5. Verify password hash
            if (!password_verify($password, $user['password_hash'])) {
                ResponseUtil::error('Incorrect email or password. Please try again.', 401, [
                    'errors' => ['general' => 'Incorrect email or password. Please try again.']
                ]);
                return;
            }

            // 6. Generate signed JWT token
            $payload = [
                'user_id'   => $user['user_id'],
                'email'     => $user['email'],
                'full_name' => $user['full_name'],
                'role'      => $user['role']
            ];
            $token = JWTUtil::generateToken($payload);

            // 7. Audit log login event
            try {
                $this->auditLogDAO->log($user['user_id'], 'USER_LOGIN', "User {$user['email']} successfully logged in.");
            } catch (Exception $e) {
                // Non-blocking audit logging
            }

            // 8. Return response payload
            ResponseUtil::success([
                'token' => $token,
                'user'  => [
                    'user_id'             => $user['user_id'],
                    'email'               => $user['email'],
                    'full_name'           => $user['full_name'],
                    'role'                => $user['role'],
                    'reputation_score'    => isset($user['reputation_score']) ? (int)$user['reputation_score'] : null,
                    'ranger_station'      => $user['ranger_station'] ?? null,
                    'ranger_emp_id'       => $user['ranger_emp_id'] ?? null,
                    'skill_tags'          => $user['skill_tags'] ?? [],
                    'availability_status' => $user['availability_status'] ?? null,
                    'performance_score'   => isset($user['performance_score']) ? (float)$user['performance_score'] : null
                ]
            ], 'Login successful.');

        } catch (Exception $e) {
            ResponseUtil::error('Authentication service error: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Handle user registration / signup (POST /api/v1/auth/signup)
     */
    public function register(): void
    {
        $rawInput = file_get_contents('php://input');
        $data = json_decode($rawInput, true) ?? $_POST;

        $fullName = trim($data['full_name'] ?? '');
        $email = trim($data['email'] ?? '');
        $password = $data['password'] ?? '';

        $errors = [];

        // 1. Validate Full Name
        if (empty($fullName)) {
            $errors['full_name'] = 'Full name is required.';
        }

        // 2. Validate Email
        if (empty($email)) {
            $errors['email'] = 'Email address is required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Please enter a valid email address (e.g. you@example.com).';
        }

        // 3. Validate Password
        if (empty($password)) {
            $errors['password'] = 'Password is required.';
        } elseif (strlen($password) < 8) {
            $errors['password'] = 'Password must be at least 8 characters.';
        }

        if (!empty($errors)) {
            ResponseUtil::error('Validation failed.', 400, ['errors' => $errors]);
            return;
        }

        try {
            // 4. Check if user email already exists
            $existingUser = $this->userDAO->getUserByEmail($email);

            if ($existingUser) {
                if (isset($existingUser['is_active']) && ($existingUser['is_active'] === false || $existingUser['is_active'] === 0 || $existingUser['is_active'] === 'f')) {
                    ResponseUtil::error('Account exists but has been deactivated, please try again with another email address.', 409, [
                        'errors' => [
                            'email' => 'Account exists but has been deactivated, please try again with another email address.'
                        ],
                        'deactivated' => true
                    ]);
                    return;
                } else {
                    ResponseUtil::error('Email exists, please click on login instead.', 409, [
                        'errors' => [
                            'email' => 'Email exists, please click on login instead.'
                        ],
                        'exists' => true,
                        'go_to_login' => true
                    ]);
                    return;
                }
            }

            // 5. Create new Tourist User in Supabase
            $newUserId = $this->userDAO->createUser(
                $email,
                $password,
                $fullName,
                'TOURIST',
                ['reputation_score' => 100]
            );

            if (!$newUserId) {
                ResponseUtil::error('Failed to create account. Please try again.', 500);
                return;
            }

            // 6. Generate signed JWT token
            $payload = [
                'user_id'   => $newUserId,
                'email'     => strtolower($email),
                'full_name' => $fullName,
                'role'      => 'TOURIST'
            ];
            $token = JWTUtil::generateToken($payload);

            // 7. Audit log registration event
            try {
                $this->auditLogDAO->log($newUserId, 'USER_SIGNUP', "New tourist account registered: {$email}");
            } catch (Exception $e) {
                // Non-blocking
            }

            // 8. Return response
            ResponseUtil::success([
                'token' => $token,
                'user'  => [
                    'user_id'          => $newUserId,
                    'email'            => strtolower($email),
                    'full_name'        => $fullName,
                    'role'             => 'TOURIST',
                    'reputation_score' => 100
                ]
            ], 'Account created successfully!', 201);

        } catch (Exception $e) {
            ResponseUtil::error('Registration service error: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get current authenticated user session (GET /api/v1/auth/me)
     */
    public function me(): void

    {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (!preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            ResponseUtil::error('Authentication token required.', 401);
            return;
        }

        $token = $matches[1];
        $payload = JWTUtil::validateToken($token);

        if (!$payload || !isset($payload['user_id'])) {
            ResponseUtil::error('Invalid or expired session token.', 401);
            return;
        }

        $user = $this->userDAO->getUserById($payload['user_id']);
        if (!$user) {
            ResponseUtil::error('User account not found.', 404);
            return;
        }

        unset($user['password_hash']);
        ResponseUtil::success(['user' => $user], 'Session active.');
    }
}
