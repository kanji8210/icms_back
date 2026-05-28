<?php

declare(strict_types=1);

namespace ICMS\Application\UseCases;

use ICMS\Infrastructure\Persistence\Migrations\DatabaseManagementService;

final class RepairDatabaseSchemaUseCase
{
    private DatabaseManagementService $databaseManagementService;

    public function __construct(DatabaseManagementService $databaseManagementService)
    {
        $this->databaseManagementService = $databaseManagementService;
    }

    /**
     * @return array<string, mixed>
     */
    public function execute(): array
    {
        return $this->databaseManagementService->repairSchema();
    }
}