<?php

declare(strict_types=1);

namespace ICMS\Application\UseCases;

use ICMS\Domain\Repositories\CaseRepositoryInterface;

final class ListOfficerCasesUseCase extends AbstractUseCase
{
    private CaseRepositoryInterface $caseRepository;

    public function __construct(CaseRepositoryInterface $caseRepository)
    {
        $this->caseRepository = $caseRepository;
    }

    public function execute(array $input): array
    {
        $officerId = isset($input['officer_id']) ? (int) $input['officer_id'] : 0;
        $limit = min((int) ($input['limit'] ?? 20), 100);
        $offset = max((int) ($input['offset'] ?? 0), 0);

        if ($officerId === 0) {
            return ['ok' => false, 'error' => 'Officer ID is required.'];
        }

        $cases = $this->caseRepository->findByOfficerId($officerId, $limit, $offset);
        $total = $this->caseRepository->countByOfficerId($officerId);

        return [
            'ok'     => true,
            'cases'  => $cases,
            'total'  => $total,
            'limit'  => $limit,
            'offset' => $offset,
        ];
    }
}
