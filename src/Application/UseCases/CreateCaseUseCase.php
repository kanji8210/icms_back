<?php

declare(strict_types=1);

namespace ICMS\Application\UseCases;

use ICMS\Domain\Repositories\AuditRepositoryInterface;
use ICMS\Domain\Repositories\CaseRepositoryInterface;
use ICMS\Domain\ValueObjects\CaseStatus;

final class CreateCaseUseCase extends AbstractUseCase
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
        $officerId = isset($input['officer_id']) ? (int) $input['officer_id'] : 0;
        $referralSource = isset($input['referral_source']) ? trim((string) $input['referral_source']) : '';
        $payloadRaw = isset($input['payload']) && is_array($input['payload']) ? $input['payload'] : [];
        $ipAddress = isset($input['ip_address']) ? (string) $input['ip_address'] : '';

        if ($officerId === 0) {
            return ['ok' => false, 'error' => 'Officer ID is required.'];
        }

        if ($referralSource === '') {
            return ['ok' => false, 'error' => 'Referral source is required.'];
        }

        $caseId = $this->generateCaseId();
        $now = current_time('mysql');

        $this->caseRepository->create([
            'id'                  => $caseId,
            'assigned_officer_id' => $officerId,
            'status'              => CaseStatus::OPEN,
            'payload'             => wp_json_encode(array_merge($payloadRaw, [
                'referral_source' => $referralSource,
            ])),
            'created_at'          => $now,
            'updated_at'          => $now,
        ]);

        $this->auditRepository->append($caseId, $officerId, 'case_created', [
            'referral_source' => $referralSource,
        ], $ipAddress);

        return ['ok' => true, 'case_id' => $caseId];
    }

    private function generateCaseId(): string
    {
        return 'ICMS-' . strtoupper(substr(bin2hex(random_bytes(8)), 0, 12));
    }
}
