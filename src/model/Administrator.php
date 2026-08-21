<?php

namespace App\Models;

class Administrator extends User
{
    public function __construct(
        string $email,
        string $passwordHash,
        string $fullName,
        ?string $userId = null,
        bool $isActive = true,
        ?string $createdAt = null
    ) {
        parent::__construct($email, $passwordHash, $fullName, UserRole::ADMINISTRATOR, $userId, $isActive, $createdAt);
    }
}
