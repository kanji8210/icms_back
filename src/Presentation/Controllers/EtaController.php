<?php

declare(strict_types=1);

namespace ICMS\Presentation\Controllers;

use ICMS\Infrastructure\Services\EtaMonitoringService;

final class EtaController extends AbstractController
{
    private EtaMonitoringService $etaMonitoringService;

    public function __construct(EtaMonitoringService $etaMonitoringService)
    {
        $this->etaMonitoringService = $etaMonitoringService;
    }

    public function ingestWebhook(\WP_REST_Request $request): \WP_REST_Response
    {
        $payload = $request->get_json_params();
        if (!is_array($payload)) {
            return $this->fail(['message' => 'JSON payload is required.'], 400);
        }

        $result = $this->etaMonitoringService->ingestRawEta($payload);
        if (!($result['ok'] ?? false)) {
            return $this->fail(['message' => (string) ($result['error'] ?? 'Invalid ETA payload')], 400);
        }

        return $this->ok(['data' => $result], 200);
    }

    public function recordMovement(\WP_REST_Request $request): \WP_REST_Response
    {
        $payload = $request->get_json_params();
        if (!is_array($payload)) {
            return $this->fail(['message' => 'JSON payload is required.'], 400);
        }

        $result = $this->etaMonitoringService->recordMovement($payload);
        if (!($result['ok'] ?? false)) {
            return $this->fail(['message' => (string) ($result['error'] ?? 'Invalid movement payload')], 400);
        }

        return $this->ok(['data' => $result], 201);
    }

    public function runMonitor(\WP_REST_Request $request): \WP_REST_Response
    {
        $result = $this->etaMonitoringService->runMonitor();

        return $this->ok(['data' => $result], 200);
    }

    public function registerOfficer(\WP_REST_Request $request): \WP_REST_Response
    {
        $payload = $request->get_json_params();
        if (!is_array($payload)) {
            return $this->fail(['message' => 'JSON payload is required.'], 400);
        }

        $result = $this->etaMonitoringService->registerOfficer($payload);
        if (!($result['ok'] ?? false)) {
            return $this->fail(['message' => (string) ($result['error'] ?? 'Invalid officer payload')], 400);
        }

        return $this->ok(['data' => $result], 201);
    }

    public function loadSampleData(\WP_REST_Request $request): \WP_REST_Response
    {
        $payload = $request->get_json_params();
        if (!is_array($payload)) {
            $payload = [];
        }

        $result = $this->etaMonitoringService->loadSampleData($payload);
        if (!($result['ok'] ?? false)) {
            return $this->fail(['message' => (string) ($result['error'] ?? 'Failed to load sample ETA data')], 400);
        }

        return $this->ok(['data' => $result], 200);
    }

    public function clearSampleData(\WP_REST_Request $request): \WP_REST_Response
    {
        $result = $this->etaMonitoringService->clearSampleData();
        if (!($result['ok'] ?? false)) {
            return $this->fail(['message' => (string) ($result['error'] ?? 'Failed to clear sample ETA data')], 400);
        }

        return $this->ok(['data' => $result], 200);
    }

    public function sampleStats(\WP_REST_Request $request): \WP_REST_Response
    {
        $result = $this->etaMonitoringService->getSampleDataStats();
        if (!($result['ok'] ?? false)) {
            return $this->fail(['message' => (string) ($result['error'] ?? 'Failed to fetch sample ETA stats')], 400);
        }

        return $this->ok(['data' => $result], 200);
    }
}
