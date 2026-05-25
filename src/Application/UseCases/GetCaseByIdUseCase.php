<?php

declare(strict_types=1);

namespace ICMS\Application\UseCases;

use ICMS\Domain\Repositories\CaseRepositoryInterface;

final class GetCaseByIdUseCase extends AbstractUseCase
{
    private CaseRepositoryInterface $repository;

    public function __construct(CaseRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function execute(array $input): array
    {
        $id = isset($input['id']) ? trim((string) $input['id']) : '';

        if ($id == '') {
            return ['ok' => false, 'error' => 'Case ID is required.'];
        }

        $case = $this->repository->findById($id);

        if ($case === null) {
            return ['ok' => false, 'error' => 'Case not found.'];
        }

        return ['ok' => true, 'case' => $case];
    }
}
