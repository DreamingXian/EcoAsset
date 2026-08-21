<?php

namespace App\Models;

class SiteRanger extends User
{
    private string $stationRegion;
    private string $employeeId;

    public function __construct(
        string $email,
        string $passwordHash,
        string $fullName,
        string $stationRegion = 'Kuala Lumpur',
        string $employeeId = '',
        ?string $userId = null,
        bool $isActive = true,
        ?string $createdAt = null
    ) {
        parent::__construct($email, $passwordHash, $fullName, UserRole::SITE_RANGER, $userId, $isActive, $createdAt);
        $this->stationRegion = $stationRegion;
        $this->employeeId = $employeeId;
    }

    public function getStationRegion(): string
    {
        return $this->stationRegion;
    }

    public function setStationRegion(string $stationRegion): void
    {
        $this->stationRegion = $stationRegion;
    }

    public function getEmployeeId(): string
    {
        return $this->employeeId;
    }

    public function setEmployeeId(string $employeeId): void
    {
        $this->employeeId = $employeeId;
    }

    public function jsonSerialize(): array
    {
        return array_merge(parent::jsonSerialize(), [
            'station_region' => $this->stationRegion,
            'employee_id' => $this->employeeId
        ]);
    }
}
