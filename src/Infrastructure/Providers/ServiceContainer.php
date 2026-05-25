<?php

declare(strict_types=1);

namespace ICMS\Infrastructure\Providers;

use ICMS\Application\UseCases\GetCaseByIdUseCase;
use ICMS\Domain\Repositories\CaseRepositoryInterface;
use ICMS\Infrastructure\Persistence\Repositories\WpCaseRepository;
use ICMS\Presentation\Controllers\CaseController;
use ICMS\Presentation\REST\CaseRoutes;

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

        $this->singleton(GetCaseByIdUseCase::class, static function (self $container): object {
            /** @var CaseRepositoryInterface $repository */
            $repository = $container->get(CaseRepositoryInterface::class);

            return new GetCaseByIdUseCase($repository);
        });

        $this->singleton(CaseController::class, static function (self $container): object {
            /** @var GetCaseByIdUseCase $useCase */
            $useCase = $container->get(GetCaseByIdUseCase::class);

            return new CaseController($useCase);
        });

        $this->singleton(CaseRoutes::class, static function (self $container): object {
            /** @var CaseController $controller */
            $controller = $container->get(CaseController::class);

            return new CaseRoutes($controller);
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
