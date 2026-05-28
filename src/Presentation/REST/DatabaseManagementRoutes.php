<?php

declare(strict_types=1);

namespace ICMS\Presentation\REST;

use ICMS\Presentation\Controllers\DatabaseManagementController;

final class DatabaseManagementRoutes
{
    private DatabaseManagementController $controller;

    public function __construct(DatabaseManagementController $controller)
    {
        $this->controller = $controller;
    }

    public function register(): void
    {
        register_rest_route('icms-back/v1', '/database/overview', [
            'methods' => 'GET',
            'callback' => [$this->controller, 'overview'],
            'permission_callback' => [$this, 'canManageDatabase'],
        ]);

        register_rest_route('icms-back/v1', '/database/repair', [
            'methods' => 'POST',
            'callback' => [$this->controller, 'repair'],
            'permission_callback' => [$this, 'canManageDatabase'],
        ]);
    }

    public function canManageDatabase(): bool
    {
        if (!is_user_logged_in()) {
            return false;
        }

        return current_user_can('icms_admin') || current_user_can('manage_options');
    }
}