<?php

declare(strict_types=1);

namespace ICMS\Presentation\Controllers;

use ICMS\Application\UseCases\GetDatabaseOverviewUseCase;
use ICMS\Application\UseCases\RepairDatabaseSchemaUseCase;

final class DatabaseManagementController extends AbstractController
{
    private GetDatabaseOverviewUseCase $getDatabaseOverviewUseCase;

    private RepairDatabaseSchemaUseCase $repairDatabaseSchemaUseCase;

    public function __construct(
        GetDatabaseOverviewUseCase $getDatabaseOverviewUseCase,
        RepairDatabaseSchemaUseCase $repairDatabaseSchemaUseCase
    ) {
        $this->getDatabaseOverviewUseCase = $getDatabaseOverviewUseCase;
        $this->repairDatabaseSchemaUseCase = $repairDatabaseSchemaUseCase;
    }

    public function overview(): \WP_REST_Response
    {
        $overview = $this->getDatabaseOverviewUseCase->execute();

        return $this->ok(['data' => $overview], 200);
    }

    public function repair(): \WP_REST_Response
    {
        $result = $this->repairDatabaseSchemaUseCase->execute();

        return $this->ok(['data' => $result], 200);
    }
}