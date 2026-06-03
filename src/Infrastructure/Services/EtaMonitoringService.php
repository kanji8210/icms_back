<?php

declare(strict_types=1);

namespace ICMS\Infrastructure\Services;

final class EtaMonitoringService
{
    private \wpdb $db;

    public function __construct(\wpdb $db)
    {
        $this->db = $db;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function ingestRawEta(array $payload): array
    {
        $passport = $this->pickString($payload, ['passportNumber', 'passport_number', 'passport']);
        $expiryRaw = $this->pickString($payload, ['expiryDate', 'expiry_date', 'expiresAt', 'expires_at']);

        if ($passport === '' || $expiryRaw === '') {
            $this->logImport('webhook', 'invalid_payload', 1, 0, [
                'reason' => 'passport and expiry are required',
            ]);

            return [
                'ok' => false,
                'error' => 'passport and expiry are required',
            ];
        }

        $expiry = $this->parseDateToMysql($expiryRaw);
        if ($expiry === null) {
            $this->logImport('webhook', 'invalid_payload', 1, 0, [
                'reason' => 'invalid expiry date format',
                'value' => $expiryRaw,
            ]);

            return [
                'ok' => false,
                'error' => 'invalid expiry date format',
            ];
        }

        $entryDate = $this->parseDateToMysql($this->pickString($payload, ['entryDate', 'entry_date', 'entryAt', 'entry_at']));
        $issuedAt = $this->parseDateToMysql($this->pickString($payload, ['issuedAt', 'issued_at']));
        $etaRef = $this->pickString($payload, ['etaRefNumber', 'eta_ref_number', 'etaReference', 'eta_ref', 'etaNumber']);
        $nationality = strtoupper($this->pickString($payload, ['nationality', 'countryCode', 'country_code']));
        $reasonForTravel = $this->pickString($payload, ['reasonForTravel', 'reason_for_travel', 'travelReason']);

        $table = $this->db->prefix . 'icms_eta_records';
        $now = current_time('mysql');

        $existing = null;
        if ($etaRef !== '') {
            $sql = $this->db->prepare(
                "SELECT id FROM {$table} WHERE eta_ref_number = %s LIMIT 1",
                $etaRef
            );
            $existing = $this->db->get_row($sql, ARRAY_A);
        }

        if (!is_array($existing)) {
            $sql = $this->db->prepare(
                "SELECT id FROM {$table} WHERE passport_number = %s AND expiry_date = %s ORDER BY id DESC LIMIT 1",
                $passport,
                $expiry
            );
            $existing = $this->db->get_row($sql, ARRAY_A);
        }

        $rowPayload = [
            'passport_number' => $passport,
            'nationality' => $nationality !== '' ? $nationality : null,
            'reason_for_travel' => $reasonForTravel !== '' ? $reasonForTravel : null,
            'entry_date' => $entryDate,
            'expiry_date' => $expiry,
            'issued_at' => $issuedAt,
            'eta_ref_number' => $etaRef !== '' ? $etaRef : null,
            'source' => 'webhook',
            'raw_payload' => wp_json_encode($payload),
            'updated_at' => $now,
        ];

        if (is_array($existing)) {
            $this->db->update(
                $table,
                $rowPayload,
                ['id' => (int) $existing['id']],
                ['%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s'],
                ['%d']
            );
            $recordId = (int) $existing['id'];
            $action = 'updated';
        } else {
            $insertPayload = $rowPayload;
            $insertPayload['created_at'] = $now;

            $this->db->insert(
                $table,
                $insertPayload,
                ['%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s']
            );
            $recordId = (int) $this->db->insert_id;
            $action = 'created';
        }

        $this->logImport('webhook', 'success', 1, 1, [
            'action' => $action,
            'eta_record_id' => $recordId,
            'passport_number' => $passport,
        ]);

        return [
            'ok' => true,
            'action' => $action,
            'eta_record_id' => $recordId,
            'passport_number' => $passport,
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function recordMovement(array $input): array
    {
        $passport = $this->normalizePassport($this->pickString($input, ['passportNumber', 'passport_number', 'passport']));
        $movementType = strtoupper($this->pickString($input, ['movementType', 'movement_type']));
        $movementAtRaw = $this->pickString($input, ['movementAt', 'movement_at']);
        $borderPointCode = strtoupper($this->pickString($input, ['borderPointCode', 'border_point_code']));
        $officerId = (int) ($input['officerId'] ?? $input['officer_id'] ?? 0);

        if ($passport === '' || $movementType === '' || $movementAtRaw === '' || $borderPointCode === '' || $officerId < 1) {
            return [
                'ok' => false,
                'error' => 'passportNumber, movementType, movementAt, borderPointCode and officerId are required',
            ];
        }

        if (!in_array($movementType, ['ENTRY', 'EXIT'], true)) {
            return [
                'ok' => false,
                'error' => 'movementType must be ENTRY or EXIT',
            ];
        }

        $movementAt = $this->parseDateToMysql($movementAtRaw);
        if ($movementAt === null) {
            return [
                'ok' => false,
                'error' => 'invalid movementAt date format',
            ];
        }

        $table = $this->db->prefix . 'icms_eta_movement_events';
        $this->db->insert(
            $table,
            [
                'passport_number' => $passport,
                'movement_type' => $movementType,
                'movement_at' => $movementAt,
                'border_point_code' => $borderPointCode,
                'officer_id' => $officerId,
                'source' => 'direct',
                'created_at' => current_time('mysql'),
            ],
            ['%s', '%s', '%s', '%s', '%d', '%s', '%s']
        );

        return [
            'ok' => true,
            'movement_id' => (int) $this->db->insert_id,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function runMonitor(): array
    {
        $timezone = new \DateTimeZone('Africa/Nairobi');
        $today = new \DateTimeImmutable('now', $timezone);
        $today = $today->setTime(0, 0, 0);

        $etaTable = $this->db->prefix . 'icms_eta_records';
        $sql = "SELECT * FROM {$etaTable} ORDER BY expiry_date ASC";
        $records = $this->db->get_results($sql, ARRAY_A);

        $inspected = 0;
        $suppressedByExit = 0;
        $createdCases = 0;
        $createdAlerts = [];

        if (!is_array($records)) {
            return [
                'ok' => true,
                'inspected' => 0,
                'suppressedByExit' => 0,
                'casesCreated' => 0,
                'alerts' => [],
                'timezone' => 'Africa/Nairobi',
            ];
        }

        foreach ($records as $record) {
            if (!is_array($record)) {
                continue;
            }

            $inspected++;
            $passport = isset($record['passport_number']) ? (string) $record['passport_number'] : '';
            if ($passport === '') {
                continue;
            }

            if ($this->isSuppressedByLatestExit($passport)) {
                $suppressedByExit++;
                continue;
            }

            $expiryDate = $this->parseMysqlDate((string) ($record['expiry_date'] ?? ''), $timezone);
            if ($expiryDate === null) {
                continue;
            }

            $daysToExpiry = (int) $today->diff($expiryDate->setTime(0, 0, 0))->format('%r%a');
            $windowDays = $this->resolveWindowDays($daysToExpiry);
            if ($windowDays === null) {
                continue;
            }

            $etaRecordId = (int) ($record['id'] ?? 0);
            if ($etaRecordId < 1) {
                continue;
            }

            if ($this->alertExists($etaRecordId, $windowDays)) {
                continue;
            }

            $caseId = $this->createExpiryCase($record, $windowDays, $daysToExpiry);
            $this->createAlert($etaRecordId, $windowDays, $caseId);

            $createdCases++;
            $createdAlerts[] = [
                'etaRecordId' => $etaRecordId,
                'passportNumber' => $passport,
                'windowDays' => $windowDays,
                'daysToExpiry' => $daysToExpiry,
                'caseId' => $caseId,
            ];
        }

        return [
            'ok' => true,
            'inspected' => $inspected,
            'suppressedByExit' => $suppressedByExit,
            'casesCreated' => $createdCases,
            'alerts' => $createdAlerts,
            'timezone' => 'Africa/Nairobi',
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function registerOfficer(array $input): array
    {
        $officerCode = substr($this->pickString($input, ['officerId', 'officer_id', 'officerCode', 'officer_code']), 0, 64);
        $officerName = substr($this->pickString($input, ['name', 'officerName', 'officer_name']), 0, 191);
        $station = substr($this->pickString($input, ['station', 'stationName', 'station_name', 'borderPointCode', 'border_point_code']), 0, 191);

        if ($officerCode === '' || $officerName === '' || $station === '') {
            return [
                'ok' => false,
                'error' => 'officerId, name and station are required',
            ];
        }

        $table = $this->db->prefix . 'icms_eta_officers';
        $now = current_time('mysql');
        $metadata = [
            'source' => $this->pickString($input, ['source']),
        ];

        $existingSql = $this->db->prepare(
            "SELECT id FROM {$table} WHERE officer_code = %s LIMIT 1",
            $officerCode
        );
        $existing = $this->db->get_row($existingSql, ARRAY_A);

        if (is_array($existing)) {
            $officerRowId = (int) ($existing['id'] ?? 0);
            $updated = $this->db->update(
                $table,
                [
                    'officer_name' => $officerName,
                    'station' => $station,
                    'status' => 'active',
                    'metadata' => wp_json_encode($metadata),
                    'updated_at' => $now,
                ],
                ['id' => $officerRowId],
                ['%s', '%s', '%s', '%s', '%s'],
                ['%d']
            );

            if ($updated === false) {
                return [
                    'ok' => false,
                    'error' => 'failed to update officer record',
                ];
            }

            return [
                'ok' => true,
                'action' => 'updated',
                'officer_row_id' => $officerRowId,
                'officer_id' => $officerCode,
                'name' => $officerName,
                'station' => $station,
            ];
        }

        $inserted = $this->db->insert(
            $table,
            [
                'officer_code' => $officerCode,
                'officer_name' => $officerName,
                'station' => $station,
                'status' => 'active',
                'metadata' => wp_json_encode($metadata),
                'created_at' => $now,
                'updated_at' => $now,
            ],
            ['%s', '%s', '%s', '%s', '%s', '%s', '%s']
        );

        if ($inserted === false) {
            return [
                'ok' => false,
                'error' => 'failed to create officer record',
            ];
        }

        return [
            'ok' => true,
            'action' => 'created',
            'officer_row_id' => (int) $this->db->insert_id,
            'officer_id' => $officerCode,
            'name' => $officerName,
            'station' => $station,
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function loadSampleData(array $input = []): array
    {
        $timezone = new \DateTimeZone('Africa/Nairobi');
        $now = new \DateTimeImmutable('now', $timezone);

        $runMonitor = $this->asBool($input['runMonitor'] ?? $input['run_monitor'] ?? true, true);

        $officers = [
            ['officerId' => '90001', 'name' => 'Sample Officer One', 'station' => 'JKIA'],
            ['officerId' => '90002', 'name' => 'Sample Officer Two', 'station' => 'NAMANGA'],
            ['officerId' => '90003', 'name' => 'Sample Officer Three', 'station' => 'MOMBASA PORT'],
        ];

        $officerCount = 0;
        foreach ($officers as $officer) {
            $result = $this->registerOfficer([
                'officerId' => $officer['officerId'],
                'name' => $officer['name'],
                'station' => $officer['station'],
                'source' => 'eta-sample-seed',
            ]);

            if (($result['ok'] ?? false) === true) {
                $officerCount++;
            }
        }

        $sampleRecords = [
            [
                'passportNumber' => 'SMPA10001',
                'expiryDays' => 30,
                'entryDaysAgo' => 2,
                'issuedDaysAgo' => 10,
                'etaRefNumber' => 'ETA-SAMPLE-001',
                'nationality' => 'UG',
                'reasonForTravel' => 'Business',
            ],
            [
                'passportNumber' => 'SMPA10002',
                'expiryDays' => 7,
                'entryDaysAgo' => 5,
                'issuedDaysAgo' => 20,
                'etaRefNumber' => 'ETA-SAMPLE-002',
                'nationality' => 'TZ',
                'reasonForTravel' => 'Tourism',
            ],
            [
                'passportNumber' => 'SMPA10003',
                'expiryDays' => 1,
                'entryDaysAgo' => 1,
                'issuedDaysAgo' => 14,
                'etaRefNumber' => 'ETA-SAMPLE-003',
                'nationality' => 'RW',
                'reasonForTravel' => 'Conference',
            ],
            [
                'passportNumber' => 'SMPA10004',
                'expiryDays' => -2,
                'entryDaysAgo' => 10,
                'issuedDaysAgo' => 40,
                'etaRefNumber' => 'ETA-SAMPLE-004',
                'nationality' => 'ET',
                'reasonForTravel' => 'Family Visit',
            ],
            [
                'passportNumber' => 'SMPA10005',
                'expiryDays' => 7,
                'entryDaysAgo' => 7,
                'issuedDaysAgo' => 25,
                'etaRefNumber' => 'ETA-SAMPLE-005',
                'nationality' => 'IN',
                'reasonForTravel' => 'Tourism',
            ],
        ];

        $recordResults = [
            'created' => 0,
            'updated' => 0,
            'failed' => 0,
        ];

        foreach ($sampleRecords as $sample) {
            $expiryAt = $now->modify(($sample['expiryDays'] >= 0 ? '+' : '') . (string) $sample['expiryDays'] . ' days')->setTime(10, 0, 0);
            $entryAt = $now->modify('-' . (string) $sample['entryDaysAgo'] . ' days')->setTime(9, 0, 0);
            $issuedAt = $now->modify('-' . (string) $sample['issuedDaysAgo'] . ' days')->setTime(8, 0, 0);

            $result = $this->ingestRawEta([
                'passportNumber' => $sample['passportNumber'],
                'expiryDate' => $expiryAt->format('Y-m-d H:i:s'),
                'entryDate' => $entryAt->format('Y-m-d H:i:s'),
                'issuedAt' => $issuedAt->format('Y-m-d H:i:s'),
                'etaRefNumber' => $sample['etaRefNumber'],
                'nationality' => $sample['nationality'],
                'reasonForTravel' => $sample['reasonForTravel'],
            ]);

            if (($result['ok'] ?? false) !== true) {
                $recordResults['failed']++;
                continue;
            }

            if (($result['action'] ?? '') === 'created') {
                $recordResults['created']++;
            } else {
                $recordResults['updated']++;
            }
        }

        $movementSamples = [
            [
                'passportNumber' => 'SMPA10001',
                'movementType' => 'ENTRY',
                'movementAt' => $now->modify('-2 days')->setTime(9, 30, 0)->format('Y-m-d H:i:s'),
                'borderPointCode' => 'JKIA',
                'officerId' => 90001,
            ],
            [
                'passportNumber' => 'SMPA10002',
                'movementType' => 'ENTRY',
                'movementAt' => $now->modify('-5 days')->setTime(8, 45, 0)->format('Y-m-d H:i:s'),
                'borderPointCode' => 'NAMANGA',
                'officerId' => 90002,
            ],
            [
                'passportNumber' => 'SMPA10003',
                'movementType' => 'ENTRY',
                'movementAt' => $now->modify('-1 day')->setTime(11, 15, 0)->format('Y-m-d H:i:s'),
                'borderPointCode' => 'JKIA',
                'officerId' => 90001,
            ],
            [
                'passportNumber' => 'SMPA10004',
                'movementType' => 'ENTRY',
                'movementAt' => $now->modify('-10 days')->setTime(14, 0, 0)->format('Y-m-d H:i:s'),
                'borderPointCode' => 'MALABA',
                'officerId' => 90003,
            ],
            [
                'passportNumber' => 'SMPA10005',
                'movementType' => 'ENTRY',
                'movementAt' => $now->modify('-7 days')->setTime(10, 20, 0)->format('Y-m-d H:i:s'),
                'borderPointCode' => 'MOMBASA_PORT',
                'officerId' => 90003,
            ],
            [
                'passportNumber' => 'SMPA10005',
                'movementType' => 'EXIT',
                'movementAt' => $now->modify('-1 day')->setTime(17, 10, 0)->format('Y-m-d H:i:s'),
                'borderPointCode' => 'MOMBASA_PORT',
                'officerId' => 90003,
            ],
        ];

        $movementCount = 0;
        foreach ($movementSamples as $movement) {
            if (!$this->movementExists($movement)) {
                $movementResult = $this->recordMovement($movement);
                if (($movementResult['ok'] ?? false) === true) {
                    $movementCount++;
                }
            }
        }

        $monitorResult = null;
        if ($runMonitor) {
            $monitorResult = $this->runMonitor();
        }

        return [
            'ok' => true,
            'officers_synced' => $officerCount,
            'records' => $recordResults,
            'movement_events_inserted' => $movementCount,
            'monitor_executed' => $runMonitor,
            'monitor_result' => $monitorResult,
            'timezone' => 'Africa/Nairobi',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function clearSampleData(): array
    {
        $etaRecordsTable = $this->db->prefix . 'icms_eta_records';
        $movementTable = $this->db->prefix . 'icms_eta_movement_events';
        $alertsTable = $this->db->prefix . 'icms_eta_monitor_alerts';
        $officersTable = $this->db->prefix . 'icms_eta_officers';
        $casesTable = $this->db->prefix . 'icms_cases';
        $auditTable = $this->db->prefix . 'icms_audit_log';

        $sampleRecordIds = $this->db->get_col(
            "SELECT id FROM {$etaRecordsTable} WHERE eta_ref_number LIKE 'ETA-SAMPLE-%' OR passport_number LIKE 'SMPA%'"
        );
        $recordIds = array_map('intval', is_array($sampleRecordIds) ? $sampleRecordIds : []);

        $deletedAlerts = 0;
        if (!empty($recordIds)) {
            $in = implode(',', $recordIds);
            $deletedAlerts = (int) $this->db->query("DELETE FROM {$alertsTable} WHERE eta_record_id IN ({$in})");
        }

        $deletedMovements = (int) $this->db->query(
            "DELETE FROM {$movementTable} WHERE passport_number LIKE 'SMPA%'"
        );

        $deletedRecords = (int) $this->db->query(
            "DELETE FROM {$etaRecordsTable} WHERE eta_ref_number LIKE 'ETA-SAMPLE-%' OR passport_number LIKE 'SMPA%'"
        );

        $sampleCaseIdsRaw = $this->db->get_col(
            "SELECT id FROM {$casesTable} WHERE payload LIKE '%ETA-SAMPLE-%' OR payload LIKE '%SMPA%'"
        );
        $sampleCaseIds = array_values(array_filter(array_map(static function ($value): string {
            return is_scalar($value) ? (string) $value : '';
        }, is_array($sampleCaseIdsRaw) ? $sampleCaseIdsRaw : []), static function (string $value): bool {
            return $value !== '';
        }));

        $deletedAudit = 0;
        $deletedCases = 0;
        if (!empty($sampleCaseIds)) {
            $quotedCaseIds = array_map(static fn(string $id): string => "'" . esc_sql($id) . "'", $sampleCaseIds);
            $inCases = implode(',', $quotedCaseIds);
            $deletedAudit = (int) $this->db->query("DELETE FROM {$auditTable} WHERE case_id IN ({$inCases})");
            $deletedCases = (int) $this->db->query("DELETE FROM {$casesTable} WHERE id IN ({$inCases})");
        }

        $deletedOfficers = (int) $this->db->query(
            "DELETE FROM {$officersTable} WHERE officer_code LIKE '9000%' OR metadata LIKE '%eta-sample-seed%'"
        );

        return [
            'ok' => true,
            'deleted' => [
                'eta_records' => $deletedRecords,
                'movement_events' => $deletedMovements,
                'monitor_alerts' => $deletedAlerts,
                'cases' => $deletedCases,
                'audit_log' => $deletedAudit,
                'eta_officers' => $deletedOfficers,
            ],
            'timezone' => 'Africa/Nairobi',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getSampleDataStats(): array
    {
        $etaRecordsTable = $this->db->prefix . 'icms_eta_records';
        $movementTable = $this->db->prefix . 'icms_eta_movement_events';
        $alertsTable = $this->db->prefix . 'icms_eta_monitor_alerts';
        $officersTable = $this->db->prefix . 'icms_eta_officers';
        $casesTable = $this->db->prefix . 'icms_cases';

        $sampleEtaRecords = (int) $this->db->get_var(
            "SELECT COUNT(*) FROM {$etaRecordsTable} WHERE eta_ref_number LIKE 'ETA-SAMPLE-%' OR passport_number LIKE 'SMPA%'"
        );
        $sampleMovementEvents = (int) $this->db->get_var(
            "SELECT COUNT(*) FROM {$movementTable} WHERE passport_number LIKE 'SMPA%'"
        );
        $sampleAlerts = (int) $this->db->get_var(
            "SELECT COUNT(*) FROM {$alertsTable} WHERE eta_record_id IN (SELECT id FROM {$etaRecordsTable} WHERE eta_ref_number LIKE 'ETA-SAMPLE-%' OR passport_number LIKE 'SMPA%')"
        );
        $sampleOfficers = (int) $this->db->get_var(
            "SELECT COUNT(*) FROM {$officersTable} WHERE officer_code LIKE '9000%' OR metadata LIKE '%eta-sample-seed%'"
        );
        $sampleCases = (int) $this->db->get_var(
            "SELECT COUNT(*) FROM {$casesTable} WHERE payload LIKE '%ETA-SAMPLE-%' OR payload LIKE '%SMPA%'"
        );

        return [
            'ok' => true,
            'stats' => [
                'eta_records' => $sampleEtaRecords,
                'movement_events' => $sampleMovementEvents,
                'monitor_alerts' => $sampleAlerts,
                'eta_officers' => $sampleOfficers,
                'cases' => $sampleCases,
            ],
            'timezone' => 'Africa/Nairobi',
        ];
    }

    private function resolveWindowDays(int $daysToExpiry): ?int
    {
        if (in_array($daysToExpiry, [30, 7, 1], true)) {
            return $daysToExpiry;
        }

        if ($daysToExpiry < 0) {
            return 0;
        }

        return null;
    }

    private function isSuppressedByLatestExit(string $passport): bool
    {
        $table = $this->db->prefix . 'icms_eta_movement_events';
        $sql = $this->db->prepare(
            "SELECT movement_type FROM {$table} WHERE passport_number = %s ORDER BY movement_at DESC, id DESC LIMIT 1",
            $passport
        );
        $row = $this->db->get_row($sql, ARRAY_A);

        if (!is_array($row)) {
            return false;
        }

        $movementType = strtoupper((string) ($row['movement_type'] ?? ''));

        return $movementType === 'EXIT';
    }

    private function alertExists(int $etaRecordId, int $windowDays): bool
    {
        $table = $this->db->prefix . 'icms_eta_monitor_alerts';
        $sql = $this->db->prepare(
            "SELECT id FROM {$table} WHERE eta_record_id = %d AND alert_window_days = %d LIMIT 1",
            $etaRecordId,
            $windowDays
        );

        return (int) $this->db->get_var($sql) > 0;
    }

    private function createAlert(int $etaRecordId, int $windowDays, string $caseId): void
    {
        $table = $this->db->prefix . 'icms_eta_monitor_alerts';

        $this->db->insert(
            $table,
            [
                'eta_record_id' => $etaRecordId,
                'alert_window_days' => $windowDays,
                'case_id' => $caseId,
                'status' => 'created',
                'created_at' => current_time('mysql'),
            ],
            ['%d', '%d', '%s', '%s', '%s']
        );
    }

    /**
     * @param array<string, mixed> $etaRecord
     */
    private function createExpiryCase(array $etaRecord, int $windowDays, int $daysToExpiry): string
    {
        $caseId = $this->generateCaseId();
        $now = current_time('mysql');

        $payload = [
            'referral_source' => 'eta_expiry_monitor',
            'eta_record_id' => (int) ($etaRecord['id'] ?? 0),
            'passport_number' => (string) ($etaRecord['passport_number'] ?? ''),
            'nationality' => (string) ($etaRecord['nationality'] ?? ''),
            'reason_for_travel' => (string) ($etaRecord['reason_for_travel'] ?? ''),
            'entry_date' => (string) ($etaRecord['entry_date'] ?? ''),
            'expiry_date' => (string) ($etaRecord['expiry_date'] ?? ''),
            'issued_at' => (string) ($etaRecord['issued_at'] ?? ''),
            'eta_ref_number' => (string) ($etaRecord['eta_ref_number'] ?? ''),
            'alert_window_days' => $windowDays,
            'days_to_expiry' => $daysToExpiry,
            'timezone' => 'Africa/Nairobi',
        ];

        $casesTable = $this->db->prefix . 'icms_cases';
        $this->db->insert(
            $casesTable,
            [
                'id' => $caseId,
                'assigned_officer_id' => 0,
                'status' => 'open',
                'payload' => wp_json_encode($payload),
                'created_at' => $now,
                'updated_at' => $now,
            ],
            ['%s', '%d', '%s', '%s', '%s', '%s']
        );

        $auditTable = $this->db->prefix . 'icms_audit_log';
        $this->db->insert(
            $auditTable,
            [
                'case_id' => $caseId,
                'officer_id' => 0,
                'action' => 'eta_expiry_case_created',
                'details' => wp_json_encode([
                    'eta_record_id' => (int) ($etaRecord['id'] ?? 0),
                    'alert_window_days' => $windowDays,
                    'days_to_expiry' => $daysToExpiry,
                    'timezone' => 'Africa/Nairobi',
                ]),
                'ip_address' => '',
                'created_at' => $now,
            ],
            ['%s', '%d', '%s', '%s', '%s', '%s']
        );

        return $caseId;
    }

    private function generateCaseId(): string
    {
        return 'ICMS-' . strtoupper(substr(bin2hex(random_bytes(8)), 0, 12));
    }

    /**
     * @param array<string, mixed> $details
     */
    private function logImport(string $source, string $status, int $recordsReceived, int $recordsProcessed, array $details): void
    {
        $table = $this->db->prefix . 'icms_eta_import_log';
        $this->db->insert(
            $table,
            [
                'source' => $source,
                'status' => $status,
                'records_received' => $recordsReceived,
                'records_processed' => $recordsProcessed,
                'details' => wp_json_encode($details),
                'created_at' => current_time('mysql'),
            ],
            ['%s', '%s', '%d', '%d', '%s', '%s']
        );
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<int, string> $keys
     */
    private function pickString(array $payload, array $keys): string
    {
        foreach ($keys as $key) {
            if (!array_key_exists($key, $payload)) {
                continue;
            }

            $value = $payload[$key];
            if ($value === null) {
                continue;
            }

            $asString = is_scalar($value) ? trim((string) $value) : '';
            if ($asString !== '') {
                return $asString;
            }
        }

        return '';
    }

    private function normalizePassport(string $value): string
    {
        $normalized = strtoupper(trim($value));
        $normalized = preg_replace('/\s+/', '', $normalized);

        return is_string($normalized) ? $normalized : '';
    }

    private function parseDateToMysql(string $value): ?string
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }

        try {
            $date = new \DateTimeImmutable($trimmed, new \DateTimeZone('Africa/Nairobi'));
        } catch (\Throwable $e) {
            return null;
        }

        return $date->setTimezone(new \DateTimeZone('Africa/Nairobi'))->format('Y-m-d H:i:s');
    }

    private function parseMysqlDate(string $value, \DateTimeZone $timezone): ?\DateTimeImmutable
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $trimmed, $timezone);
        if ($date instanceof \DateTimeImmutable) {
            return $date;
        }

        try {
            return new \DateTimeImmutable($trimmed, $timezone);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * @param array<string, mixed> $movement
     */
    private function movementExists(array $movement): bool
    {
        $table = $this->db->prefix . 'icms_eta_movement_events';
        $sql = $this->db->prepare(
            "SELECT id FROM {$table} WHERE passport_number = %s AND movement_type = %s AND movement_at = %s AND border_point_code = %s LIMIT 1",
            (string) $movement['passportNumber'],
            (string) $movement['movementType'],
            (string) $movement['movementAt'],
            (string) $movement['borderPointCode']
        );

        return (int) $this->db->get_var($sql) > 0;
    }

    /**
     * @param mixed $value
     */
    private function asBool($value, bool $default): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            $normalized = strtolower(trim($value));
            if ($normalized === '1' || $normalized === 'true' || $normalized === 'yes') {
                return true;
            }

            if ($normalized === '0' || $normalized === 'false' || $normalized === 'no') {
                return false;
            }
        }

        if (is_int($value) || is_float($value)) {
            return ((int) $value) === 1;
        }

        return $default;
    }
}
