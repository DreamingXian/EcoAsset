<?php

namespace App\Models;

use JsonSerializable;

class DamageReport implements JsonSerializable
{
    private ?string $reportId;
    private ?string $userId;
    private IssueCategory $category;
    private SkillCategory $requiredSkill;
    private string $description;
    private float $latitude;
    private float $longitude;
    private string $photoUrl;
    private string $exifTimestamp;
    private int $upvoteCount;
    private float $priorityScore;
    private ReportStatus $status;
    private string $district;
    private ?string $createdAt;

    public function __construct(
        ?string $userId,
        IssueCategory $category,
        string $description,
        float $latitude,
        float $longitude,
        string $photoUrl,
        string $exifTimestamp,
        SkillCategory $requiredSkill = SkillCategory::STRUCTURAL,
        int $upvoteCount = 0,
        float $priorityScore = 0.0,
        ReportStatus $status = ReportStatus::OPEN_FOR_CLAIM,
        string $district = 'Kuala Lumpur',
        ?string $reportId = null,
        ?string $createdAt = null
    ) {
        $this->reportId = $reportId;
        $this->userId = $userId;
        $this->category = $category;
        $this->requiredSkill = $requiredSkill;
        $this->description = $description;
        $this->latitude = $latitude;
        $this->longitude = $longitude;
        $this->photoUrl = $photoUrl;
        $this->exifTimestamp = $exifTimestamp;
        $this->upvoteCount = $upvoteCount;
        $this->priorityScore = $priorityScore;
        $this->status = $status;
        $this->district = $district;
        $this->createdAt = $createdAt;
    }

    public function getReportId(): ?string
    {
        return $this->reportId;
    }

    public function setReportId(string $reportId): void
    {
        $this->reportId = $reportId;
    }

    public function getUserId(): ?string
    {
        return $this->userId;
    }

    public function getCategory(): IssueCategory
    {
        return $this->category;
    }

    public function setCategory(IssueCategory $category): void
    {
        $this->category = $category;
    }

    public function getRequiredSkill(): SkillCategory
    {
        return $this->requiredSkill;
    }

    public function setRequiredSkill(SkillCategory $requiredSkill): void
    {
        $this->requiredSkill = $requiredSkill;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): void
    {
        $this->description = $description;
    }

    public function getLatitude(): float
    {
        return $this->latitude;
    }

    public function getLongitude(): float
    {
        return $this->longitude;
    }

    public function getPhotoUrl(): string
    {
        return $this->photoUrl;
    }

    public function getExifTimestamp(): string
    {
        return $this->exifTimestamp;
    }

    public function getUpvoteCount(): int
    {
        return $this->upvoteCount;
    }

    public function setUpvoteCount(int $count): void
    {
        $this->upvoteCount = $count;
    }

    public function incrementUpvote(): void
    {
        $this->upvoteCount++;
    }

    public function getPriorityScore(): float
    {
        return $this->priorityScore;
    }

    public function setPriorityScore(float $score): void
    {
        $this->priorityScore = $score;
    }

    public function getStatus(): ReportStatus
    {
        return $this->status;
    }

    public function setStatus(ReportStatus $status): void
    {
        $this->status = $status;
    }

    public function getDistrict(): string
    {
        return $this->district;
    }

    public function setDistrict(string $district): void
    {
        $this->district = $district;
    }

    public function getCreatedAt(): ?string
    {
        return $this->createdAt;
    }

    public function jsonSerialize(): array
    {
        return [
            'report_id' => $this->reportId,
            'user_id' => $this->userId,
            'category' => $this->category->value,
            'required_skill' => $this->requiredSkill->value,
            'description' => $this->description,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'photo_url' => $this->photoUrl,
            'exif_timestamp' => $this->exifTimestamp,
            'upvote_count' => $this->upvoteCount,
            'priority_score' => $this->priorityScore,
            'status' => $this->status->value,
            'district' => $this->district,
            'created_at' => $this->createdAt
        ];
    }
}
