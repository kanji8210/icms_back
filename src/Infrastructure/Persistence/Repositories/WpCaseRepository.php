<?php

declare(strict_types=1);

namespace ICMS\Infrastructure\Persistence\Repositories;

use ICMS\Domain\Repositories\CaseRepositoryInterface;

final class WpCaseRepository implements CaseRepositoryInterface
{
    /** @var \wpdb */
    private \wpdb $db;

    /** @param \wpdb $db */
    public function __construct(\wpdb $db)
    {
        $this->db = $db;
    }

    public function findById(string $id): ?array
    {
        $table = $this->db->prefix . 'icms_cases';
        $sql = $this->db->prepare("SELECT * FROM {$table} WHERE id = %s LIMIT 1", $id);
        $row = $this->db->get_row($sql, ARRAY_A);

        return is_array($row) ? $row : null;
    }

    public function findByOfficerId(int $officerId, int $limit = 20, int $offset = 0): array
    {
        $table = $this->db->prefix . 'icms_cases';
        $sql = $this->db->prepare(
            "SELECT * FROM {$table} WHERE assigned_officer_id = %d ORDER BY created_at DESC LIMIT %d OFFSET %d",
            $officerId,
            $limit,
            $offset
        );
        $rows = $this->db->get_results($sql, ARRAY_A);

        return is_array($rows) ? $rows : [];
    }

    public function countByOfficerId(int $officerId): int
    {
        $table = $this->db->prefix . 'icms_cases';
        $sql = $this->db->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE assigned_officer_id = %d",
            $officerId
        );

        return (int) $this->db->get_var($sql);
    }

    public function create(array $payload): void
    {
        $table = $this->db->prefix . 'icms_cases';
        $this->db->insert($table, $payload);
    }

    public function updateStatus(string $id, string $status): void
    {
        $table = $this->db->prefix . 'icms_cases';
        $this->db->update(
            $table,
            ['status' => $status, 'updated_at' => current_time('mysql')],
            ['id' => $id],
            ['%s', '%s'],
            ['%s']
        );
    }

    public function save(array $payload): void
    {
        $table = $this->db->prefix . 'icms_cases';
        $this->db->insert($table, $payload);
    }
}
