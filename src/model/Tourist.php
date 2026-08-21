<?php

namespace App\Models;

class Tourist extends User
{
    private int $reputationScore;

    public function __construct(
        string $email,
        string $passwordHash,
        string $fullName,
        ?string $userId = null,
        int $reputationScore = 100,
        bool $isActive = true,
        ?string $createdAt = null
    ) {
        parent::__construct($email, $passwordHash, $fullName, UserRole::TOURIST, $userId, $isActive, $createdAt);
        $this->reputationScore = $reputationScore;
    }

    public function getReputationScore(): int
    {
        return $this->reputationScore;
    }

    public function setReputationScore(int $score): void
    {
        $this->reputationScore = max(0, $score);
    }

    public function deductReputation(int $points): void
    {
        $this->reputationScore = max(0, $this->reputationScore - $points);
    }

    public function jsonSerialize(): array
    {
        return array_merge(parent::jsonSerialize(), [
            'reputation_score' => $this->reputationScore
        ]);
    }
}
