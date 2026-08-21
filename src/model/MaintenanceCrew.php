<?php

namespace App\Models;

class MaintenanceCrew extends User
{
    private string $crewId;
    private array $skillTags; // Array of string / SkillCategory
    private AvailabilityState $availabilityStatus;
    private float $performanceScore;
    private ?float $currentLatitude;
    private ?float $currentLongitude;
    private ?string $stationRegion;

    public function __construct(
        string $email,
        string $passwordHash,
        string $fullName,
        array $skillTags = [],
        AvailabilityState $availabilityStatus = AvailabilityState::AVAILABLE,
        float $performanceScore = 100.0,
        ?float $currentLatitude = 3.139003,
        ?float $currentLongitude = 101.686855,
        ?string $stationRegion = 'Kuala Lumpur',
        ?string $userId = null,
        bool $isActive = true,
        ?string $createdAt = null
    ) {
        parent::__construct($email, $passwordHash, $fullName, UserRole::MAINTENANCE_CREW, $userId, $isActive, $createdAt);
        $this->crewId = $userId ?? '';
        $this->skillTags = $skillTags;
        $this->availabilityStatus = $availabilityStatus;
        $this->performanceScore = $performanceScore;
        $this->currentLatitude = $currentLatitude;
        $this->currentLongitude = $currentLongitude;
        $this->stationRegion = $stationRegion;
    }

    public function getCrewId(): string
    {
        return $this->crewId ?: ($this->userId ?? '');
    }

    public function getSkillTags(): array
    {
        return $this->skillTags;
    }

    public function setSkillTags(array $skillTags): void
    {
        $this->skillTags = $skillTags;
    }

    public function hasSkill(string $skill): bool
    {
        return in_array(strtoupper($skill), array_map('strtoupper', $this->skillTags), true);
    }

    public function getAvailabilityStatus(): AvailabilityState
    {
        return $this->availabilityStatus;
    }

    public function setAvailabilityStatus(AvailabilityState $status): void
    {
        $this->availabilityStatus = $status;
    }

    public function getPerformanceScore(): float
    {
        return $this->performanceScore;
    }

    public function setPerformanceScore(float $score): void
    {
        $this->performanceScore = max(0.0, $score);
    }

    public function applyPenalty(float $points = 5.0): void
    {
        $this->performanceScore = max(0.0, $this->performanceScore - $points);
    }

    public function getCurrentLatitude(): ?float
    {
        return $this->currentLatitude;
    }

    public function setCurrentLatitude(?float $lat): void
    {
        $this->currentLatitude = $lat;
    }

    public function getCurrentLongitude(): ?float
    {
        return $this->currentLongitude;
    }

    public function setCurrentLongitude(?float $lng): void
    {
        $this->currentLongitude = $lng;
    }

    public function getStationRegion(): ?string
    {
        return $this->stationRegion;
    }

    public function setStationRegion(?string $region): void
    {
        $this->stationRegion = $region;
    }

    public function jsonSerialize(): array
    {
        return array_merge(parent::jsonSerialize(), [
            'crew_id' => $this->getCrewId(),
            'skill_tags' => $this->skillTags,
            'availability_status' => $this->availabilityStatus->value,
            'performance_score' => $this->performanceScore,
            'current_latitude' => $this->currentLatitude,
            'current_longitude' => $this->currentLongitude,
            'station_region' => $this->stationRegion
        ]);
    }
}
