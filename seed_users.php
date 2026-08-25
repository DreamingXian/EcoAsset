<?php
require_once __DIR__ . '/src/autoload.php';

use App\DAO\UserDAO;
use App\Config\Database;

header('Content-Type: application/json');

$userDAO = new UserDAO();
$db = Database::getConnection();

$seedUsers = [
    [
        'email' => 'admin@ecoasset.my',
        'password' => 'Pass1234!',
        'full_name' => 'Zarina Aziz',
        'role' => 'ADMINISTRATOR',
        'extra' => [],
        'is_active' => true
    ],
    [
        'email' => 'admin@dbkl.gov.my',
        'password' => 'Pass1234!',
        'full_name' => 'Zarina Aziz',
        'role' => 'ADMINISTRATOR',
        'extra' => [],
        'is_active' => true
    ],
    [
        'email' => 'hafiz@dbkl.gov.my',
        'password' => 'Pass1234!',
        'full_name' => 'Hafiz Roslan',
        'role' => 'SITE_RANGER',
        'extra' => [
            'station_region' => 'Kuala Lumpur',
            'employee_id' => 'RNG-1001'
        ],
        'is_active' => true
    ],
    [
        'email' => 'boonhuat@contractor.my',
        'password' => 'Pass1234!',
        'full_name' => 'Tan Boon Huat',
        'role' => 'MAINTENANCE_CREW',
        'extra' => [
            'skill_tags' => ['STRUCTURAL', 'SANITATION'],
            'current_latitude' => 3.139003,
            'current_longitude' => 101.686855,
            'station_region' => 'Kuala Lumpur'
        ],
        'is_active' => true
    ],
    [
        'email' => 'selvam@contractor.my',
        'password' => 'Pass1234!',
        'full_name' => 'Selvam Krishnan',
        'role' => 'MAINTENANCE_CREW',
        'extra' => [
            'skill_tags' => ['ELECTRICAL'],
            'current_latitude' => 3.149000,
            'current_longitude' => 101.696800,
            'station_region' => 'Kuala Lumpur'
        ],
        'is_active' => true
    ],
    [
        'email' => 'nurulain@dbkl.gov.my',
        'password' => 'Pass1234!',
        'full_name' => 'Nurul Ain',
        'role' => 'SITE_RANGER',
        'extra' => [
            'station_region' => 'Kuala Lumpur',
            'employee_id' => 'RNG-1002'
        ],
        'is_active' => false
    ],
    [
        'email' => 'kaiming@contractor.my',
        'password' => 'Pass1234!',
        'full_name' => 'Wong Kai Ming',
        'role' => 'MAINTENANCE_CREW',
        'extra' => [
            'skill_tags' => ['STRUCTURAL'],
            'current_latitude' => 3.159000,
            'current_longitude' => 101.706800,
            'station_region' => 'Kuala Lumpur'
        ],
        'is_active' => true
    ],
    [
        'email' => 'ranger@ecoasset.my',
        'password' => 'Pass1234!',
        'full_name' => 'Officer Roslan',
        'role' => 'SITE_RANGER',
        'extra' => [
            'station_region' => 'Kuala Lumpur',
            'employee_id' => 'RNG-1003'
        ],
        'is_active' => true
    ],
    [
        'email' => 'crew@ecoasset.my',
        'password' => 'Pass1234!',
        'full_name' => 'Tech Ahmad',
        'role' => 'MAINTENANCE_CREW',
        'extra' => [
            'skill_tags' => ['STRUCTURAL', 'ELECTRICAL'],
            'current_latitude' => 3.139003,
            'current_longitude' => 101.686855,
            'station_region' => 'Kuala Lumpur'
        ],
        'is_active' => true
    ],
    [
        'email' => 'tourist@ecoasset.my',
        'password' => 'Pass1234!',
        'full_name' => 'Tourist John',
        'role' => 'TOURIST',
        'extra' => [
            'reputation_score' => 100
        ],
        'is_active' => true
    ],
    [
        'email' => 'byebye@email.com',
        'password' => 'Pass1234!',
        'full_name' => 'Deactivated User',
        'role' => 'TOURIST',
        'extra' => [

            'reputation_score' => 80
        ],
        'is_active' => false
    ]
];

$summary = [];

foreach ($seedUsers as $userData) {
    try {
        $existing = $userDAO->getUserByEmail($userData['email']);
        
        if ($existing) {
            // Update password & active status
            $hash = password_hash($userData['password'], PASSWORD_BCRYPT);
            $stmt = $db->prepare("UPDATE users SET password_hash = :hash, is_active = :active, full_name = :name WHERE user_id = :id");
            $stmt->execute([
                'hash' => $hash,
                'active' => $userData['is_active'] ? 'TRUE' : 'FALSE',
                'name' => $userData['full_name'],
                'id' => $existing['user_id']
            ]);
            $summary[] = [
                'email' => $userData['email'],
                'role' => $userData['role'],
                'action' => 'UPDATED',
                'is_active' => $userData['is_active']
            ];
        } else {
            // Create user
            $newId = $userDAO->createUser(
                $userData['email'],
                $userData['password'],
                $userData['full_name'],
                $userData['role'],
                $userData['extra']
            );
            if (!$userData['is_active']) {
                $db->prepare("UPDATE users SET is_active = FALSE WHERE user_id = :id")->execute(['id' => $newId]);
            }
            $summary[] = [
                'email' => $userData['email'],
                'role' => $userData['role'],
                'action' => 'CREATED',
                'is_active' => $userData['is_active']
            ];
        }
    } catch (Exception $e) {
        $summary[] = [
            'email' => $userData['email'],
            'action' => 'ERROR',
            'error' => $e->getMessage()
        ];
    }
}

echo json_encode([
    'status' => 'SUCCESS',
    'message' => 'Supabase user accounts seeded successfully.',
    'users' => $summary
], JSON_PRETTY_PRINT);
