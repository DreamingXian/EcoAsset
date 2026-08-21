<?php

namespace App\Models;

use JsonSerializable;

abstract class User implements JsonSerializable
{
    protected ?string $userId;
    protected string $email;
    protected string $passwordHash;
    protected string $fullName;
    protected UserRole $role;
    protected bool $isActive;
    protected ?string $createdAt;

    public function __construct(
        string $email,
        string $passwordHash,
        string $fullName,
        UserRole $role,
        ?string $userId = null,
        bool $isActive = true,
        ?string $createdAt = null
    ) {
        $this->userId = $userId;
        $this->email = $email;
        $this->passwordHash = $passwordHash;
        $this->fullName = $fullName;
        $this->role = $role;
        $this->isActive = $isActive;
        $this->createdAt = $createdAt;
    }

    public function getUserId(): ?string
    {
        return $this->userId;
    }

    public function setUserId(string $userId): void
    {
        $this->userId = $userId;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): void
    {
        $this->email = $email;
    }

    public function getPasswordHash(): string
    {
        return $this->passwordHash;
    }

    public function setPasswordHash(string $passwordHash): void
    {
        $this->passwordHash = $passwordHash;
    }

    public function getFullName(): string
    {
        return $this->fullName;
    }

    public function setFullName(string $fullName): void
    {
        $this->fullName = $fullName;
    }

    public function getRole(): UserRole
    {
        return $this->role;
    }

    public function setRole(UserRole $role): void
    {
        $this->role = $role;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): void
    {
        $this->isActive = $isActive;
    }

    public function getCreatedAt(): ?string
    {
        return $this->createdAt;
    }

    public function jsonSerialize(): array
    {
        return [
            'user_id' => $this->userId,
            'email' => $this->email,
            'full_name' => $this->fullName,
            'role' => $this->role->value,
            'is_active' => $this->isActive,
            'created_at' => $this->createdAt
        ];
    }
}
