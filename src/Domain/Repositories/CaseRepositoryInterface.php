<?php

declare(strict_types=1);

namespace ICMS\Domain\Repositories;

interface CaseRepositoryInterface
{
    /**
     * @return array<string, mixed>|null
     */
    public function findById(string $id): ?array;

    /**
     * @param array<string, mixed> $payload
     */
    public function save(array $payload): void;
}
