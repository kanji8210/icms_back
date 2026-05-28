<?php

declare(strict_types=1);

namespace ICMS\Presentation\REST;

use ICMS\Presentation\GraphQL\GraphQLServer;

/**
 * Registers the single POST /wp-json/icms-back/v1/graphql endpoint.
 *
 * Auth is handled inside GraphQLServer — no WP user session required.
 * The permission_callback always returns true; the server validates the Bearer token.
 */
final class GraphQLRoute
{
    private GraphQLServer $server;

    public function __construct(GraphQLServer $server)
    {
        $this->server = $server;
    }

    public function register(): void
    {
        register_rest_route('icms-back/v1', '/graphql', [
            [
                'methods'             => 'POST',
                'callback'            => [$this->server, 'handle'],
                'permission_callback' => '__return_true',
            ],
            [
                // Introspection / OPTIONS preflight
                'methods'             => 'GET',
                'callback'            => static function (): \WP_REST_Response {
                    return new \WP_REST_Response([
                        'endpoint' => '/wp-json/icms-back/v1/graphql',
                        'method'   => 'POST',
                        'auth'     => 'Bearer JWT (Authorization header)',
                    ], 200);
                },
                'permission_callback' => '__return_true',
            ],
        ]);
    }
}
