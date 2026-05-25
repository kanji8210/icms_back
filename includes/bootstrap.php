<?php

declare(strict_types=1);

use ICMS\Presentation\REST\CaseRoutes;
use ICMS\Infrastructure\Providers\ServiceContainer;

if (file_exists(ICMS_BACK_PLUGIN_DIR . 'vendor/autoload.php')) {
    require_once ICMS_BACK_PLUGIN_DIR . 'vendor/autoload.php';
}

add_action('plugins_loaded', static function (): void {
    $container = new ServiceContainer();
    $container->registerDefaults();

    add_action('rest_api_init', static function () use ($container): void {
        /** @var CaseRoutes $routes */
        $routes = $container->get(CaseRoutes::class);
        $routes->register();
    });
});
