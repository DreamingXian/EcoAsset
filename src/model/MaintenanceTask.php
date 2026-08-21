<?php

namespace App\Models;

use JsonSerializable;

class MaintenanceTask implements JsonSerializable
{
    private ?string $taskId;
    private string $reportId;
    private ?string $assignedCrewId;
    private ?string $assignedAt;
    private ?string $slaExpiresAt;
    private ?string $completionPhotoUrl;
    private ?string $resolutionNotes;
    private ?string $declineReason;
    private ?string $dropReason;
    private ?string $flagInvalidPhotoUrl;
    private ?string $flagInvalidNotes;
    private ?string $completedAt;
    private ?string $createdAt;

    public function __construct(
        string $reportId,
        ?string $assignedCrewId = null,
        ?string $assignedAt = null,
        ?string $slaExpiresAt = null,
        ?string $completionPhotoUrl = null,
        ?string $resolutionNotes = null,
        ?string $declineReason = null,
        ?string $dropReason = null,
        ?string $flagInvalidPhotoUrl = null,
        ?string $flagInvalidNotes = null,
        ?string $completedAt = null,
        ?string $taskId = null,
        ?string $createdAt = null
    ) {
        $this->taskId = $taskId;
        $this->reportId = $reportId;
        $this->assignedCrewId = $assignedCrewId;
        $this->assignedAt = $assignedAt;
        $this->slaExpiresAt = $slaExpiresAt;
        $this->completionPhotoUrl = $completionPhotoUrl;
        $this->resolutionNotes = $resolutionNotes;
        $this->declineReason = $declineReason;
        $this->dropReason = $dropReason;
        $this->flagInvalidPhotoUrl = $flagInvalidPhotoUrl;
        $this->flagInvalidNotes = $flagInvalidNotes;
        $this->completedAt = $completedAt;
        $this->createdAt = $createdAt;
    }

    public function getTaskId(): ?string
    {
        return $this->taskId;
    }

    public function setTaskId(string $taskId): void
    {
        $this->taskId = $taskId;
    }

    public function getReportId(): string
    {
        return $this->reportId;
    }

    public function getAssignedCrewId(): ?string
    {
        return $this->assignedCrewId;
    }

    public function setAssignedCrewId(?string $crewId): void
    {
        $this->assignedCrewId = $crewId;
    }

    public function getAssignedAt(): ?string
    {
        return $this->assignedAt;
    }

    public function getSlaExpiresAt(): ?string
    {
        return $this->slaExpiresAt;
    }

    public function setSlaExpiresAt(?string $sla): void
    {
        $this->slaExpiresAt = $sla;
    }

    public function getCompletionPhotoUrl(): ?string
    {
        return $this->completionPhotoUrl;
    }

    public function setCompletionPhotoUrl(?string $url): void
    {
        $this->completionPhotoUrl = $url;
    }

    public function getResolutionNotes(): ?string
    {
        return $this->resolutionNotes;
    }

    public function setResolutionNotes(?string $notes): void
    {
        $this->resolutionNotes = $notes;
    }

    public function getDeclineReason(): ?string
    {
        return $this->declineReason;
    }

    public function setDeclineReason(?string $reason): void
    {
        $this->declineReason = $reason;
    }

    public function getDropReason(): ?string
    {
        return $this->dropReason;
    }

    public function setDropReason(?string $reason): void
    {
        $this->dropReason = $reason;
    }

    public function getFlagInvalidPhotoUrl(): ?string
    {
        return $this->flagInvalidPhotoUrl;
    }

    public function setFlagInvalidPhotoUrl(?string $url): void
    {
        $this->flagInvalidPhotoUrl = $url;
    }

    public function getFlagInvalidNotes(): ?string
    {
        return $this->flagInvalidNotes;
    }

    public function setFlagInvalidNotes(?string $notes): void
    {
        $this->flagInvalidNotes = $notes;
    }

    public function getCompletedAt(): ?string
    {
        return $this->completedAt;
    }

    public function setCompletedAt(?string $time): void
    {
        $this->completedAt = $time;
    }

    public function getCreatedAt(): ?string
    {
        return $this->createdAt;
    }

    public function jsonSerialize(): array
    {
        return [
            'task_id' => $this->taskId,
            'report_id' => $this->reportId,
            'assigned_crew_id' => $this->assignedCrewId,
            'assigned_at' => $this->assignedAt,
            'sla_expires_at' => $this->slaExpiresAt,
            'completion_photo_url' => $this->completionPhotoUrl,
            'resolution_notes' => $this->resolutionNotes,
            'decline_reason' => $this->declineReason,
            'drop_reason' => $this->dropReason,
            'flag_invalid_photo_url' => $this->flagInvalidPhotoUrl,
            'flag_invalid_notes' => $this->flagInvalidNotes,
            'completed_at' => $this->completedAt,
            'created_at' => $this->createdAt
        ];
    }
}
