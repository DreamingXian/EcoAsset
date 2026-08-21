<?php

namespace App\Models;

use JsonSerializable;

class SystemAuditLog implements JsonSerializable
{
    private ?string $logId;
    private ?string $actorId;
    private string $actionType;
    private string $details;
    private ?string $timestamp;

    public function __construct(
        ?string $actorId,
        string $actionType,
        string $details,
        ?string $timestamp = null,
        ?string $logId = null
    ) {
        $this->logId = $logId;
        $this->actorId = $actorId;
        $this->actionType = $actionType;
        $this->details = $details;
        $this->timestamp = $timestamp;
    }

    public function getLogId(): ?string
    {
        return $this->logId;
    }

    public function getActorId(): ?string
    {
        return $this->actorId;
    }

    public function getActionType(): string
    {
        return $this->actionType;
    }

    public function getDetails(): string
    {
        return $this->details;
    }

    public function getTimestamp(): ?string
    {
        return $this->timestamp;
    }

    public function jsonSerialize(): array
    {
        return [
            'log_id' => $this->logId,
            'actor_id' => $this->actorId,
            'action_type' => $this->actionType,
            'details' => $this->details,
            'timestamp' => $this->timestamp
        ];
    }
}
