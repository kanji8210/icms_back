<?php

declare(strict_types=1);

namespace ICMS\Presentation\GraphQL\Mutations;

use ICMS\Application\UseCases\CreateCaseUseCase;
use ICMS\Application\UseCases\UpdateCaseStatusUseCase;

final class CaseMutation
{
    private CreateCaseUseCase $createCaseUseCase;
    private UpdateCaseStatusUseCase $updateCaseStatusUseCase;

    public function __construct(
        CreateCaseUseCase $createCaseUseCase,
        UpdateCaseStatusUseCase $updateCaseStatusUseCase
    ) {
        $this->createCaseUseCase = $createCaseUseCase;
        $this->updateCaseStatusUseCase = $updateCaseStatusUseCase;
    }

    /**
     * @param array<string, mixed> $args
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    public function createCase(mixed $root, array $args, array $context): array
    {
        $officerId = (int) ($context['officer_id'] ?? 0);
        $ipAddress = (string) ($context['ip_address'] ?? '');
        $input = (array) ($args['input'] ?? []);

        $result = $this->createCaseUseCase->execute([
            'officer_id'      => $officerId,
            'referral_source' => (string) ($input['referralSource'] ?? ''),
            'ip_address'      => $ipAddress,
        ]);

        return [
            'ok'     => (bool) ($result['ok'] ?? false),
            'caseId' => ($result['ok'] ?? false) ? (string) ($result['case_id'] ?? '') : null,
            'error'  => ($result['ok'] ?? false) ? null : (string) ($result['error'] ?? 'Unknown error'),
        ];
    }

    /**
     * @param array<string, mixed> $args
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    public function updateCaseStatus(mixed $root, array $args, array $context): array
    {
        $officerId = (int) ($context['officer_id'] ?? 0);
        $ipAddress = (string) ($context['ip_address'] ?? '');
        $input = (array) ($args['input'] ?? []);

        $result = $this->updateCaseStatusUseCase->execute([
            'case_id'    => (string) ($input['caseId'] ?? ''),
            'status'     => (string) ($input['status'] ?? ''),
            'officer_id' => $officerId,
            'ip_address' => $ipAddress,
        ]);

        return [
            'ok'     => (bool) ($result['ok'] ?? false),
            'caseId' => ($result['ok'] ?? false) ? (string) ($result['case_id'] ?? '') : null,
            'status' => ($result['ok'] ?? false) ? (string) ($result['status'] ?? '') : null,
            'error'  => ($result['ok'] ?? false) ? null : (string) ($result['error'] ?? 'Unknown error'),
        ];
    }
}
