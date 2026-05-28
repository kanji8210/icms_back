<?php

declare(strict_types=1);

namespace ICMS\Domain\Entities;

use ICMS\Domain\Exceptions\DomainException;
use ICMS\Domain\ValueObjects\CaseStatus;

final class CaseEntity extends AbstractEntity
{
    private int $assignedOfficerId;
    private CaseStatus $status;
    private string $referralSource;
    /** @var array<string, mixed> */
    private array $payload;
    private ?\DateTimeImmutable $purgeAt;
    private \DateTimeImmutable $createdAt;
    private \DateTimeImmutable $updatedAt;

    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        string $id,
        int $assignedOfficerId,
        CaseStatus $status,
        string $referralSource,
        array $payload,
        ?\DateTimeImmutable $purgeAt,
        \DateTimeImmutable $createdAt,
        \DateTimeImmutable $updatedAt
    ) {
        parent::__construct($id);

        $this->assignedOfficerId = $assignedOfficerId;
        $this->status = $status;
        $this->referralSource = $referralSource;
        $this->payload = $payload;
        $this->purgeAt = $purgeAt;
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;
    }

    public function assignedOfficerId(): int
    {
        return $this->assignedOfficerId;
    }

    public function status(): CaseStatus
    {
        return $this->status;
    }

    public function referralSource(): string
    {
        return $this->referralSource;
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return $this->payload;
    }

    public function purgeAt(): ?\DateTimeImmutable
    {
        return $this->purgeAt;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function advanceStatus(CaseStatus $next): self
    {
        $newStatus = $this->status->transitionTo($next);

        return new self(
            $this->id,
            $this->assignedOfficerId,
            $newStatus,
            $this->referralSource,
            $this->payload,
            $this->purgeAt,
            $this->createdAt,
            new \DateTimeImmutable()
        );
    }

    public function archive(): self
    {
        $archived = new CaseStatus(CaseStatus::ARCHIVED);
        $newStatus = $this->status->transitionTo($archived);

        // Retention period: 7 years from archival — hard-coded per spec
        $purgeAt = (new \DateTimeImmutable())->modify('+7 years');

        return new self(
            $this->id,
            $this->assignedOfficerId,
            $newStatus,
            $this->referralSource,
            $this->payload,
            $purgeAt,
            $this->createdAt,
            new \DateTimeImmutable()
        );
    }

    public function isOwnedBy(int $officerId): bool
    {
        return $this->assignedOfficerId === $officerId;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id'                  => $this->id,
            'assigned_officer_id' => $this->assignedOfficerId,
            'status'              => $this->status->value(),
            'referral_source'     => $this->referralSource,
            'payload'             => $this->payload,
            'purge_at'            => $this->purgeAt?->format('Y-m-d H:i:s'),
            'created_at'          => $this->createdAt->format('Y-m-d H:i:s'),
            'updated_at'          => $this->updatedAt->format('Y-m-d H:i:s'),
        ];
    }
}
