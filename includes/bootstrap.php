<?php

declare(strict_types=1);

use ICMS\Presentation\REST\CaseRoutes;
use ICMS\Presentation\REST\DatabaseManagementRoutes;
use ICMS\Presentation\REST\GraphQLRoute;
use ICMS\Presentation\Admin\DatabaseManagementAdminPage;
use ICMS\Presentation\Admin\SecuritySettingsAdminPage;
use ICMS\Infrastructure\Providers\ServiceContainer;

spl_autoload_register(static function (string $class): void {
    if (strncmp($class, 'ICMS\\', 5) !== 0) {
        return;
    }

    $relativeClass = substr($class, 5);
    $filePath = ICMS_BACK_PLUGIN_DIR . 'src/' . str_replace('\\', '/', $relativeClass) . '.php';

    if (file_exists($filePath)) {
        require_once $filePath;
    }
});

add_action('plugins_loaded', static function (): void {
    $schemaManagerClass = 'ICMS\\Infrastructure\\Persistence\\Migrations\\SchemaManager';
    if (!class_exists($schemaManagerClass)) {
        $schemaManagerPath = ICMS_BACK_PLUGIN_DIR . 'src/Infrastructure/Persistence/Migrations/SchemaManager.php';
        if (file_exists($schemaManagerPath)) {
            require_once $schemaManagerPath;
        }
    }

    if (class_exists($schemaManagerClass) && is_callable([$schemaManagerClass, 'maybeMigrate'])) {
        $schemaManagerClass::maybeMigrate();
    }

    $container = new ServiceContainer();
    $container->registerDefaults();

    add_action('rest_api_init', static function () use ($container): void {
        /** @var CaseRoutes $routes */
        $routes = $container->get(CaseRoutes::class);
        $routes->register();

        /** @var DatabaseManagementRoutes $databaseManagementRoutes */
        $databaseManagementRoutes = $container->get(DatabaseManagementRoutes::class);
        $databaseManagementRoutes->register();

        /** @var GraphQLRoute $graphqlRoute */
        $graphqlRoute = $container->get(GraphQLRoute::class);
        $graphqlRoute->register();
    });

    if (is_admin()) {
        /** @var DatabaseManagementAdminPage $databaseManagementAdminPage */
        $databaseManagementAdminPage = $container->get(DatabaseManagementAdminPage::class);
        $databaseManagementAdminPage->register();

        /** @var SecuritySettingsAdminPage $securitySettingsAdminPage */
        $securitySettingsAdminPage = $container->get(SecuritySettingsAdminPage::class);
        $securitySettingsAdminPage->register();
    }
});
