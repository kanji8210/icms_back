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

    public function save(array $payload): void
    {
        $table = $this->db->prefix . 'icms_cases';
        $this->db->insert($table, $payload);
    }
}
