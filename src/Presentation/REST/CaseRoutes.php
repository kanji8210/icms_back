<?php

declare(strict_types=1);

namespace ICMS\Presentation\REST;

use ICMS\Presentation\Controllers\CaseController;

final class CaseRoutes
{
    private CaseController $controller;

    public function __construct(CaseController $controller)
    {
        $this->controller = $controller;
    }

    public function register(): void
    {
        register_rest_route('icms-back/v1', '/cases/(?P<id>[a-zA-Z0-9_-]+)', [
            'methods' => 'GET',
            'callback' => [$this->controller, 'getById'],
            'permission_callback' => [$this, 'canReadCases'],
        ]);
    }

    public function canReadCases(): bool
    {
        if (!is_user_logged_in()) {
            return false;
        }

        return current_user_can('icms_read_cases') || current_user_can('manage_options');
    }
}
