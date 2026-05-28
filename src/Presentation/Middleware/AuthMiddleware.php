<?php

declare(strict_types=1);

namespace ICMS\Presentation\Middleware;

use ICMS\Infrastructure\Auth\JwtService;

/**
 * Extracts and validates a JWT access token from incoming requests.
 * Token is read from the Authorization: Bearer header.
 */
final class AuthMiddleware
{
    private JwtService $jwtService;

    public function __construct(JwtService $jwtService)
    {
        $this->jwtService = $jwtService;
    }

    /**
     * Returns the authenticated officer ID or null if the request is unauthenticated.
     */
    public function resolveOfficerId(\WP_REST_Request $request): ?int
    {
        $claims = $this->resolveClaims($request);

        if ($claims === null) {
            return null;
        }

        $officerId = isset($claims['sub']) ? (int) $claims['sub'] : 0;

        return $officerId > 0 ? $officerId : null;
    }

    /**
     * Returns true and populates context, or returns false for immediate rejection.
     */
    public function authenticate(\WP_REST_Request $request): bool
    {
        return $this->resolveClaims($request) !== null;
    }

    /**
     * Returns validated token claims or null.
     *
     * @return array<string, mixed>|null
     */
    public function resolveClaims(\WP_REST_Request $request): ?array
    {
        $token = $this->extractBearerToken($request);

        if ($token === null) {
            return null;
        }

        return $this->jwtService->validate($token, 'access');
    }

    private function extractBearerToken(\WP_REST_Request $request): ?string
    {
        $authHeader = $request->get_header('Authorization');

        if (!is_string($authHeader) || $authHeader === '') {
            return null;
        }

        if (strncasecmp($authHeader, 'Bearer ', 7) !== 0) {
            return null;
        }

        $token = trim(substr($authHeader, 7));

        return $token !== '' ? $token : null;
    }
}
