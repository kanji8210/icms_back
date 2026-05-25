<?php

declare(strict_types=1);

namespace ICMS\Domain\Traits;

trait HasTimestamps
{
    protected \DateTimeImmutable $createdAt;
    protected \DateTimeImmutable $updatedAt;

    protected function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
