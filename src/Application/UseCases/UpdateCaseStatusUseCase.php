<?php

declare(strict_types=1);

namespace ICMS\Application\UseCases;

use ICMS\Domain\Exceptions\DomainException;
use ICMS\Domain\Repositories\AuditRepositoryInterface;
use ICMS\Domain\Repositories\CaseRepositoryInterface;
use ICMS\Domain\ValueObjects\CaseStatus;

final class UpdateCaseStatusUseCase extends AbstractUseCase
{
    private CaseRepositoryInterface $caseRepository;
    private AuditRepositoryInterface $auditRepository;

    public function __construct(
        CaseRepositoryInterface $caseRepository,
        AuditRepositoryInterface $auditRepository
    ) {
        $this->caseRepository = $caseRepository;
        $this->auditRepository = $auditRepository;
    }

    public function execute(array $input): array
    {
        $caseId = isset($input['case_id']) ? trim((string) $input['case_id']) : '';
        $officerId = isset($input['officer_id']) ? (int) $input['officer_id'] : 0;
        $newStatusValue = isset($input['status']) ? trim((string) $input['status']) : '';
        $ipAddress = isset($input['ip_address']) ? (string) $input['ip_address'] : '';

        if ($caseId === '') {
            return ['ok' => false, 'error' => 'Case ID is required.'];
        }

        if ($officerId === 0) {
            return ['ok' => false, 'error' => 'Officer ID is required.'];
        }

        $caseRow = $this->caseRepository->findById($caseId);

        if ($caseRow === null) {
            return ['ok' => false, 'error' => 'Case not found.'];
        }

        // Row-level ownership: officer can only update their own cases
        if ((int) ($caseRow['assigned_officer_id'] ?? 0) !== $officerId) {
            return ['ok' => false, 'error' => 'Access denied. You are not assigned to this case.'];
        }

        try {
            $currentStatus = new CaseStatus((string) ($caseRow['status'] ?? CaseStatus::OPEN));
            $nextStatus = new CaseStatus($newStatusValue);
            $currentStatus->transitionTo($nextStatus);
        } catch (DomainException $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }

        $this->caseRepository->updateStatus($caseId, $newStatusValue);

        $this->auditRepository->append($caseId, $officerId, 'status_updated', [
            'previous_status' => $caseRow['status'] ?? '',
            'new_status'      => $newStatusValue,
        ], $ipAddress);

        return ['ok' => true, 'case_id' => $caseId, 'status' => $newStatusValue];
    }
}
