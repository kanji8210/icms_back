<?php

declare(strict_types=1);

namespace ICMS\Presentation\REST;

use ICMS\Presentation\Controllers\EtaController;

final class EtaRoutes
{
    private EtaController $controller;

    public function __construct(EtaController $controller)
    {
        $this->controller = $controller;
    }

    public function register(): void
    {
        register_rest_route('icms-back/v1', '/eta/webhook', [
            'methods' => 'POST',
            'callback' => [$this->controller, 'ingestWebhook'],
            'permission_callback' => [$this, 'canIngestWebhook'],
        ]);

        register_rest_route('icms-back/v1', '/eta/movements', [
            'methods' => 'POST',
            'callback' => [$this->controller, 'recordMovement'],
            'permission_callback' => [$this, 'canManageEta'],
        ]);

        register_rest_route('icms-back/v1', '/eta/monitor/run', [
            'methods' => 'POST',
            'callback' => [$this->controller, 'runMonitor'],
            'permission_callback' => [$this, 'canManageEta'],
        ]);

        register_rest_route('icms-back/v1', '/eta/officers', [
            'methods' => 'POST',
            'callback' => [$this->controller, 'registerOfficer'],
            'permission_callback' => [$this, 'canManageEta'],
        ]);

        register_rest_route('icms-back/v1', '/eta/sample/load', [
            'methods' => 'POST',
            'callback' => [$this->controller, 'loadSampleData'],
            'permission_callback' => [$this, 'canManageEta'],
        ]);

        register_rest_route('icms-back/v1', '/eta/sample/clear', [
            'methods' => 'POST',
            'callback' => [$this->controller, 'clearSampleData'],
            'permission_callback' => [$this, 'canManageEta'],
        ]);

        register_rest_route('icms-back/v1', '/eta/sample/stats', [
            'methods' => 'GET',
            'callback' => [$this->controller, 'sampleStats'],
            'permission_callback' => [$this, 'canManageEta'],
        ]);
    }

    public function canIngestWebhook(\WP_REST_Request $request): bool
    {
        $configuredSecret = $this->getConfiguredValue('icms_eta_webhook_secret', 'ICMS_ETA_WEBHOOK_SECRET');
        if ($configuredSecret === '') {
            return false;
        }

        $providedSecret = trim((string) $request->get_header('x-icms-eta-secret'));
        if ($providedSecret === '') {
            return false;
        }

        return hash_equals($configuredSecret, $providedSecret);
    }

    public function canManageEta(\WP_REST_Request $request): bool
    {
        if (is_user_logged_in()) {
            return current_user_can('icms_admin') || current_user_can('manage_options') || current_user_can('icms_read_cases');
        }

        $internalToken = $this->getConfiguredValue('icms_eta_internal_token', 'ICMS_ETA_INTERNAL_TOKEN');
        if ($internalToken === '') {
            return false;
        }

        $authHeader = trim((string) $request->get_header('authorization'));
        if ($authHeader !== '' && str_starts_with(strtolower($authHeader), 'bearer ')) {
            $providedToken = trim(substr($authHeader, 7));

            if ($providedToken !== '' && hash_equals($internalToken, $providedToken)) {
                return true;
            }
        }

        $providedToken = trim((string) $request->get_header('x-icms-internal-token'));

        return $providedToken !== '' && hash_equals($internalToken, $providedToken);
    }

    private function getConfiguredValue(string $optionKey, string $envKey): string
    {
        $optionValue = trim((string) get_option($optionKey, ''));
        if ($optionValue !== '') {
            return $optionValue;
        }

        $envValue = getenv($envKey);

        return is_string($envValue) ? trim($envValue) : '';
    }
}
