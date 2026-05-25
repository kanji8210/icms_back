<?php

declare(strict_types=1);

namespace ICMS\Presentation\Controllers;

use ICMS\Application\UseCases\GetCaseByIdUseCase;

final class CaseController extends AbstractController
{
    private GetCaseByIdUseCase $getCaseByIdUseCase;

    public function __construct(GetCaseByIdUseCase $getCaseByIdUseCase)
    {
        $this->getCaseByIdUseCase = $getCaseByIdUseCase;
    }

    public function getById(\WP_REST_Request $request): \WP_REST_Response
    {
        $result = $this->getCaseByIdUseCase->execute([
            'id' => $request->get_param('id'),
        ]);

        if (!($result['ok'] ?? false)) {
            $status = (($result['error'] ?? '') === 'Case not found.') ? 404 : 400;

            return $this->fail(['message' => (string) ($result['error'] ?? 'Invalid request.')], $status);
        }

        return $this->ok(['data' => $result['case']], 200);
    }
}
