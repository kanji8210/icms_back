<?php

declare(strict_types=1);

namespace ICMS\Presentation\Controllers;

abstract class AbstractController
{
    /**
     * @param array<string, mixed> $data
     */
    protected function ok(array $data = [], int $status = 200): \WP_REST_Response
    {
        return new \WP_REST_Response($data, $status);
    }

    /**
     * @param array<string, mixed> $errors
     */
    protected function fail(array $errors, int $status = 400): \WP_REST_Response
    {
        return new \WP_REST_Response(['errors' => $errors], $status);
    }
}
