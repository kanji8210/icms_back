<?php

declare(strict_types=1);

namespace ICMS\Infrastructure\Persistence\Migrations;

final class DatabaseManagementService
{
    private \wpdb $wpdb;

    public function __construct(\wpdb $wpdb)
    {
        $this->wpdb = $wpdb;
    }

    /**
     * @return array<string, mixed>
     */
    public function getOverview(): array
    {
        $expectedTables = SchemaManager::expectedTables($this->wpdb);
        $existingTables = $this->getAllTables();

        $createdTables = [];
        $missingTables = [];

        foreach ($expectedTables as $tableName) {
            if (in_array($tableName, $existingTables, true)) {
                $createdTables[] = $tableName;
                continue;
            }

            $missingTables[] = $tableName;
        }

        return [
            'database_name' => $this->getDatabaseName(),
            'table_prefix' => $this->wpdb->prefix,
            'schema_version' => SchemaManager::getSchemaVersion(),
            'installed_schema_version' => SchemaManager::getInstalledSchemaVersion(),
            'expected_tables' => $expectedTables,
            'created_tables' => $createdTables,
            'missing_tables' => $missingTables,
            'auto_update_repair_enabled' => true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function repairSchema(): array
    {
        $before = $this->getOverview();

        SchemaManager::maybeMigrate();

        $after = $this->getOverview();

        $beforeMissing = count((array) ($before['missing_tables'] ?? []));
        $afterMissing = count((array) ($after['missing_tables'] ?? []));

        return [
            'repaired' => $afterMissing < $beforeMissing,
            'before' => $before,
            'after' => $after,
        ];
    }

    /**
     * @return array<int, string>
     */
    private function getAllTables(): array
    {
        /** @var array<int, string>|null $tables */
        $tables = $this->wpdb->get_col('SHOW TABLES');

        if (!is_array($tables)) {
            return [];
        }

        return array_values(array_map('strval', $tables));
    }

    private function getDatabaseName(): string
    {
        $configuredName = (string) ($this->wpdb->dbname ?? '');
        if ($configuredName !== '') {
            return $configuredName;
        }

        $databaseName = (string) $this->wpdb->get_var('SELECT DATABASE()');

        return $databaseName;
    }
}