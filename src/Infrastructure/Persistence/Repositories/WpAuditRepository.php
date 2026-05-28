<?php

declare(strict_types=1);

namespace ICMS\Infrastructure\Persistence\Repositories;

use ICMS\Domain\Repositories\AuditRepositoryInterface;

/**
 * Append-only audit log repository. Only inserts are allowed; no updates or deletes.
 */
final class WpAuditRepository implements AuditRepositoryInterface
{
    private \wpdb $db;

    public function __construct(\wpdb $db)
    {
        $this->db = $db;
    }

    public function append(
        string $caseId,
        int $officerId,
        string $action,
        array $details,
        string $ipAddress
    ): void {
        $table = $this->db->prefix . 'icms_audit_log';

        $this->db->insert($table, [
            'case_id'    => $caseId,
            'officer_id' => $officerId,
            'action'     => $action,
            'details'    => wp_json_encode($details),
            'ip_address' => $ipAddress,
            'created_at' => current_time('mysql'),
        ]);
    }

    public function findByCaseId(string $caseId): array
    {
        $table = $this->db->prefix . 'icms_audit_log';
        $sql = $this->db->prepare(
            "SELECT * FROM {$table} WHERE case_id = %s ORDER BY created_at ASC",
            $caseId
        );
        $rows = $this->db->get_results($sql, ARRAY_A);

        return is_array($rows) ? $rows : [];
    }
}
