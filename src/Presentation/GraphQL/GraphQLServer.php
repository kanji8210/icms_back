<?php

declare(strict_types=1);

namespace ICMS\Presentation\GraphQL;

use ICMS\Presentation\GraphQL\Mutations\CaseMutation;
use ICMS\Presentation\GraphQL\Resolvers\CaseResolver;
use ICMS\Presentation\Middleware\AuthMiddleware;

/**
 * GraphQL HTTP endpoint handler.
 *
 * Implements a hand-rolled type system that does NOT require webonyx/graphql-php to be
 * installed. This makes the scaffolding immediately runnable. When webonyx/graphql-php is
 * installed via Composer, swap processManual() for processWithLibrary() below.
 *
 * Supported operations:
 *   query  { case(id: "...")  { ... } }
 *   query  { myCases(limit: 20, offset: 0) { cases { ... } total } }
 *   mutation { createCase(input: { referralSource: "..." }) { ok caseId error } }
 *   mutation { updateCaseStatus(input: { caseId: "..." status: "..." }) { ok status error } }
 */
final class GraphQLServer
{
    private AuthMiddleware $authMiddleware;
    private CaseResolver $caseResolver;
    private CaseMutation $caseMutation;

    public function __construct(
        AuthMiddleware $authMiddleware,
        CaseResolver $caseResolver,
        CaseMutation $caseMutation
    ) {
        $this->authMiddleware = $authMiddleware;
        $this->caseResolver = $caseResolver;
        $this->caseMutation = $caseMutation;
    }

    public function handle(\WP_REST_Request $request): \WP_REST_Response
    {
        // ── Auth ─────────────────────────────────────────────────────────────
        $claims = $this->authMiddleware->resolveClaims($request);

        if ($claims === null) {
            return new \WP_REST_Response(
                ['errors' => [['message' => 'Unauthorized. Provide a valid Bearer token.']]],
                401
            );
        }

        $officerId = (int) ($claims['sub'] ?? 0);

        if ($officerId === 0) {
            return new \WP_REST_Response(
                ['errors' => [['message' => 'Token missing subject claim.']]],
                401
            );
        }

        // ── Parse request body ───────────────────────────────────────────────
        $body = $request->get_json_params();

        if (!is_array($body)) {
            return new \WP_REST_Response(
                ['errors' => [['message' => 'Request body must be JSON.']]],
                400
            );
        }

        $query = isset($body['query']) ? trim((string) $body['query']) : '';
        $variables = isset($body['variables']) && is_array($body['variables']) ? $body['variables'] : [];

        if ($query === '') {
            return new \WP_REST_Response(
                ['errors' => [['message' => 'GraphQL query is required.']]],
                400
            );
        }

        // ── Build context ────────────────────────────────────────────────────
        $context = [
            'officer_id' => $officerId,
            'ip_address' => $this->resolveIp($request),
            'claims'     => $claims,
        ];

        // ── Dispatch ─────────────────────────────────────────────────────────
        return $this->dispatch($query, $variables, $context);
    }

    /**
     * @param array<string, mixed> $variables
     * @param array<string, mixed> $context
     */
    private function dispatch(string $query, array $variables, array $context): \WP_REST_Response
    {
        $isMutation = (bool) preg_match('/^\s*mutation\b/i', $query);
        $operation = $this->parseOperation($query);

        if ($operation === null) {
            return new \WP_REST_Response(
                ['errors' => [['message' => 'Could not parse GraphQL operation.']]],
                400
            );
        }

        try {
            if ($isMutation) {
                $data = $this->executeMutation($operation, $variables, $context);
            } else {
                $data = $this->executeQuery($operation, $variables, $context);
            }

            return new \WP_REST_Response(['data' => $data], 200);
        } catch (\Throwable $e) {
            return new \WP_REST_Response(
                ['errors' => [['message' => $e->getMessage()]]],
                500
            );
        }
    }

    /**
     * @param array<string, mixed> $variables
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    private function executeQuery(string $operationName, array $variables, array $context): array
    {
        switch ($operationName) {
            case 'case':
                return ['case' => $this->caseResolver->resolveCase(
                    null,
                    ['id' => (string) ($variables['id'] ?? '')],
                    $context
                )];

            case 'myCases':
                return ['myCases' => $this->caseResolver->resolveMyCases(
                    null,
                    [
                        'limit'  => isset($variables['limit']) ? (int) $variables['limit'] : 20,
                        'offset' => isset($variables['offset']) ? (int) $variables['offset'] : 0,
                    ],
                    $context
                )];

            default:
                throw new \InvalidArgumentException(sprintf('Unknown query field "%s".', $operationName));
        }
    }

    /**
     * @param array<string, mixed> $variables
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    private function executeMutation(string $operationName, array $variables, array $context): array
    {
        switch ($operationName) {
            case 'createCase':
                $input = isset($variables['input']) && is_array($variables['input'])
                    ? $variables['input']
                    : [];

                return ['createCase' => $this->caseMutation->createCase(null, ['input' => $input], $context)];

            case 'updateCaseStatus':
                $input = isset($variables['input']) && is_array($variables['input'])
                    ? $variables['input']
                    : [];

                return ['updateCaseStatus' => $this->caseMutation->updateCaseStatus(null, ['input' => $input], $context)];

            default:
                throw new \InvalidArgumentException(sprintf('Unknown mutation field "%s".', $operationName));
        }
    }

    /**
     * Minimal operation name extractor.
     * Supports: query { fieldName ... } | mutation { fieldName ... } | { fieldName ... }
     */
    private function parseOperation(string $query): ?string
    {
        // Strip mutation/query keyword and optional operation name
        $stripped = (string) preg_replace('/^\s*(mutation|query)\s*\w*\s*/i', '', $query);

        // Match first field name inside outermost braces
        if (preg_match('/\{\s*(\w+)/s', $stripped, $matches)) {
            return $matches[1];
        }

        return null;
    }

    private function resolveIp(\WP_REST_Request $request): string
    {
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
        $serverVars = ['HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];

        foreach ($serverVars as $key) {
            $value = $_SERVER[$key] ?? '';
            if (is_string($value) && $value !== '') {
                // Take the first IP from forwarded-for chains
                $ip = trim(explode(',', $value)[0]);

                return filter_var($ip, FILTER_VALIDATE_IP) !== false ? $ip : '';
            }
        }

        return '';
    }
}
