<?php

declare(strict_types=1);

namespace ICMS\Infrastructure\Providers;

use ICMS\Application\UseCases\CreateCaseUseCase;
use ICMS\Application\UseCases\GetCaseByIdUseCase;
use ICMS\Application\UseCases\GetDatabaseOverviewUseCase;
use ICMS\Application\UseCases\ListOfficerCasesUseCase;
use ICMS\Application\UseCases\RepairDatabaseSchemaUseCase;
use ICMS\Application\UseCases\UpdateCaseStatusUseCase;
use ICMS\Domain\Repositories\AuditRepositoryInterface;
use ICMS\Domain\Repositories\CaseRepositoryInterface;
use ICMS\Infrastructure\Auth\JwtService;
use ICMS\Infrastructure\Persistence\Migrations\DatabaseManagementService;
use ICMS\Infrastructure\Persistence\Repositories\WpAuditRepository;
use ICMS\Infrastructure\Persistence\Repositories\WpCaseRepository;
use ICMS\Presentation\Admin\DatabaseManagementAdminPage;
use ICMS\Presentation\Admin\SecuritySettingsAdminPage;
use ICMS\Presentation\Controllers\CaseController;
use ICMS\Presentation\Controllers\DatabaseManagementController;
use ICMS\Presentation\GraphQL\GraphQLServer;
use ICMS\Presentation\GraphQL\Mutations\CaseMutation;
use ICMS\Presentation\GraphQL\Resolvers\CaseResolver;
use ICMS\Presentation\Middleware\AuthMiddleware;
use ICMS\Presentation\REST\CaseRoutes;
use ICMS\Presentation\REST\DatabaseManagementRoutes;
use ICMS\Presentation\REST\GraphQLRoute;

final class ServiceContainer
{
    /** @var array<string, callable> */
    private array $bindings = [];

    /** @var array<string, object> */
    private array $instances = [];

    public function registerDefaults(): void
    {
        $this->singleton('wpdb', static function (): object {
            global $wpdb;

            return $wpdb;
        });

        $this->singleton(CaseRepositoryInterface::class, static function (self $container): object {
            return new WpCaseRepository($container->get('wpdb'));
        });

        $this->singleton(AuditRepositoryInterface::class, static function (self $container): object {
            /** @var \wpdb $wpdb */
            $wpdb = $container->get('wpdb');

            return new WpAuditRepository($wpdb);
        });

        $this->singleton(JwtService::class, static function (): object {
            return new JwtService();
        });

        $this->singleton(AuthMiddleware::class, static function (self $container): object {
            /** @var JwtService $jwtService */
            $jwtService = $container->get(JwtService::class);

            return new AuthMiddleware($jwtService);
        });

        $this->singleton(DatabaseManagementService::class, static function (self $container): object {
            /** @var \wpdb $wpdb */
            $wpdb = $container->get('wpdb');

            return new DatabaseManagementService($wpdb);
        });

        $this->singleton(GetCaseByIdUseCase::class, static function (self $container): object {
            /** @var CaseRepositoryInterface $repository */
            $repository = $container->get(CaseRepositoryInterface::class);

            return new GetCaseByIdUseCase($repository);
        });

        $this->singleton(CreateCaseUseCase::class, static function (self $container): object {
            /** @var CaseRepositoryInterface $caseRepository */
            $caseRepository = $container->get(CaseRepositoryInterface::class);
            /** @var AuditRepositoryInterface $auditRepository */
            $auditRepository = $container->get(AuditRepositoryInterface::class);

            return new CreateCaseUseCase($caseRepository, $auditRepository);
        });

        $this->singleton(ListOfficerCasesUseCase::class, static function (self $container): object {
            /** @var CaseRepositoryInterface $repository */
            $repository = $container->get(CaseRepositoryInterface::class);

            return new ListOfficerCasesUseCase($repository);
        });

        $this->singleton(UpdateCaseStatusUseCase::class, static function (self $container): object {
            /** @var CaseRepositoryInterface $caseRepository */
            $caseRepository = $container->get(CaseRepositoryInterface::class);
            /** @var AuditRepositoryInterface $auditRepository */
            $auditRepository = $container->get(AuditRepositoryInterface::class);

            return new UpdateCaseStatusUseCase($caseRepository, $auditRepository);
        });

        $this->singleton(CaseController::class, static function (self $container): object {
            /** @var GetCaseByIdUseCase $useCase */
            $useCase = $container->get(GetCaseByIdUseCase::class);

            return new CaseController($useCase);
        });

        $this->singleton(GetDatabaseOverviewUseCase::class, static function (self $container): object {
            /** @var DatabaseManagementService $databaseManagementService */
            $databaseManagementService = $container->get(DatabaseManagementService::class);

            return new GetDatabaseOverviewUseCase($databaseManagementService);
        });

        $this->singleton(RepairDatabaseSchemaUseCase::class, static function (self $container): object {
            /** @var DatabaseManagementService $databaseManagementService */
            $databaseManagementService = $container->get(DatabaseManagementService::class);

            return new RepairDatabaseSchemaUseCase($databaseManagementService);
        });

        $this->singleton(DatabaseManagementController::class, static function (self $container): object {
            /** @var GetDatabaseOverviewUseCase $getDatabaseOverviewUseCase */
            $getDatabaseOverviewUseCase = $container->get(GetDatabaseOverviewUseCase::class);
            /** @var RepairDatabaseSchemaUseCase $repairDatabaseSchemaUseCase */
            $repairDatabaseSchemaUseCase = $container->get(RepairDatabaseSchemaUseCase::class);

            return new DatabaseManagementController($getDatabaseOverviewUseCase, $repairDatabaseSchemaUseCase);
        });

        $this->singleton(CaseRoutes::class, static function (self $container): object {
            /** @var CaseController $controller */
            $controller = $container->get(CaseController::class);

            return new CaseRoutes($controller);
        });

        $this->singleton(DatabaseManagementRoutes::class, static function (self $container): object {
            /** @var DatabaseManagementController $controller */
            $controller = $container->get(DatabaseManagementController::class);

            return new DatabaseManagementRoutes($controller);
        });

        $this->singleton(CaseResolver::class, static function (self $container): object {
            /** @var GetCaseByIdUseCase $getCaseByIdUseCase */
            $getCaseByIdUseCase = $container->get(GetCaseByIdUseCase::class);
            /** @var ListOfficerCasesUseCase $listOfficerCasesUseCase */
            $listOfficerCasesUseCase = $container->get(ListOfficerCasesUseCase::class);

            return new CaseResolver($getCaseByIdUseCase, $listOfficerCasesUseCase);
        });

        $this->singleton(CaseMutation::class, static function (self $container): object {
            /** @var CreateCaseUseCase $createCaseUseCase */
            $createCaseUseCase = $container->get(CreateCaseUseCase::class);
            /** @var UpdateCaseStatusUseCase $updateCaseStatusUseCase */
            $updateCaseStatusUseCase = $container->get(UpdateCaseStatusUseCase::class);

            return new CaseMutation($createCaseUseCase, $updateCaseStatusUseCase);
        });

        $this->singleton(GraphQLServer::class, static function (self $container): object {
            /** @var AuthMiddleware $authMiddleware */
            $authMiddleware = $container->get(AuthMiddleware::class);
            /** @var CaseResolver $caseResolver */
            $caseResolver = $container->get(CaseResolver::class);
            /** @var CaseMutation $caseMutation */
            $caseMutation = $container->get(CaseMutation::class);

            return new GraphQLServer($authMiddleware, $caseResolver, $caseMutation);
        });

        $this->singleton(GraphQLRoute::class, static function (self $container): object {
            /** @var GraphQLServer $server */
            $server = $container->get(GraphQLServer::class);

            return new GraphQLRoute($server);
        });

        $this->singleton(DatabaseManagementAdminPage::class, static function (self $container): object {
            /** @var GetDatabaseOverviewUseCase $getDatabaseOverviewUseCase */
            $getDatabaseOverviewUseCase = $container->get(GetDatabaseOverviewUseCase::class);
            /** @var RepairDatabaseSchemaUseCase $repairDatabaseSchemaUseCase */
            $repairDatabaseSchemaUseCase = $container->get(RepairDatabaseSchemaUseCase::class);

            return new DatabaseManagementAdminPage($getDatabaseOverviewUseCase, $repairDatabaseSchemaUseCase);
        });

        $this->singleton(SecuritySettingsAdminPage::class, static function (): object {
            return new SecuritySettingsAdminPage();
        });
    }

    public function bind(string $id, callable $factory): void
    {
        $this->bindings[$id] = $factory;
    }

    public function singleton(string $id, callable $factory): void
    {
        $this->bindings[$id] = function (self $container) use ($id, $factory): object {
            if (!isset($this->instances[$id])) {
                $this->instances[$id] = $factory($container);
            }

            return $this->instances[$id];
        };
    }

    public function get(string $id): object
    {
        if (!isset($this->bindings[$id])) {
            throw new \RuntimeException(sprintf('Service "%s" is not bound.', $id));
        }

        return ($this->bindings[$id])($this);
    }
}
