<?php

declare(strict_types=1);

namespace ICMS\Presentation\GraphQL\Resolvers;

use ICMS\Application\UseCases\GetCaseByIdUseCase;
use ICMS\Application\UseCases\ListOfficerCasesUseCase;

final class CaseResolver
{
    private GetCaseByIdUseCase $getCaseByIdUseCase;
    private ListOfficerCasesUseCase $listOfficerCasesUseCase;

    public function __construct(
        GetCaseByIdUseCase $getCaseByIdUseCase,
        ListOfficerCasesUseCase $listOfficerCasesUseCase
    ) {
        $this->getCaseByIdUseCase = $getCaseByIdUseCase;
        $this->listOfficerCasesUseCase = $listOfficerCasesUseCase;
    }

    /**
     * @param array<string, mixed> $args
     * @param array<string, mixed> $context
     * @return array<string, mixed>|null
     */
    public function resolveCase(mixed $root, array $args, array $context): ?array
    {
        $officerId = (int) ($context['officer_id'] ?? 0);
        $caseId = (string) ($args['id'] ?? '');

        $result = $this->getCaseByIdUseCase->execute(['id' => $caseId]);

        if (!($result['ok'] ?? false)) {
            return null;
        }

        $caseRow = (array) ($result['case'] ?? []);

        // Row-level access: officers can only retrieve their own cases
        if ((int) ($caseRow['assigned_officer_id'] ?? 0) !== $officerId) {
            return null;
        }

        return $this->mapCaseRow($caseRow);
    }

    /**
     * @param array<string, mixed> $args
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    public function resolveMyCases(mixed $root, array $args, array $context): array
    {
        $officerId = (int) ($context['officer_id'] ?? 0);

        $result = $this->listOfficerCasesUseCase->execute([
            'officer_id' => $officerId,
            'limit'      => isset($args['limit']) ? (int) $args['limit'] : 20,
            'offset'     => isset($args['offset']) ? (int) $args['offset'] : 0,
        ]);

        if (!($result['ok'] ?? false)) {
            return ['cases' => [], 'total' => 0, 'limit' => 20, 'offset' => 0];
        }

        return [
            'cases'  => array_map([$this, 'mapCaseRow'], (array) ($result['cases'] ?? [])),
            'total'  => (int) ($result['total'] ?? 0),
            'limit'  => (int) ($result['limit'] ?? 20),
            'offset' => (int) ($result['offset'] ?? 0),
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function mapCaseRow(array $row): array
    {
        $payload = [];
        if (isset($row['payload']) && is_string($row['payload'])) {
            $decoded = json_decode($row['payload'], true);
            $payload = is_array($decoded) ? $decoded : [];
        }

        return [
            'id'             => (string) ($row['id'] ?? ''),
            'status'         => (string) ($row['status'] ?? ''),
            'referralSource' => (string) ($payload['referral_source'] ?? ''),
            'createdAt'      => (string) ($row['created_at'] ?? ''),
            'updatedAt'      => (string) ($row['updated_at'] ?? ''),
        ];
    }
}
