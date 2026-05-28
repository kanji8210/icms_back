<?php

declare(strict_types=1);

namespace ICMS\Domain\Repositories;

interface AuditRepositoryInterface
{
    /**
     * @param array<string, mixed> $details
     */
    public function append(
        string $caseId,
        int $officerId,
        string $action,
        array $details,
        string $ipAddress
    ): void;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findByCaseId(string $caseId): array;
}
