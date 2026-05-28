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
     * @return array<int, array<string, mixed>>
     */
    public function findByOfficerId(int $officerId, int $limit, int $offset): array;

    public function countByOfficerId(int $officerId): int;

    /**
     * @param array<string, mixed> $payload
     */
    public function create(array $payload): void;

    public function updateStatus(string $id, string $status): void;

    /**
     * @param array<string, mixed> $payload
     */
    public function save(array $payload): void;
}
