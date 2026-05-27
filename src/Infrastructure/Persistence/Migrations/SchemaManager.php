<?php

declare(strict_types=1);

namespace ICMS\Infrastructure\Persistence\Migrations;

final class SchemaManager
{
    private const OPTION_SCHEMA_VERSION = 'icms_back_schema_version';
    private const SCHEMA_VERSION = '0.1.0';

    public static function activate(): void
    {
        self::migrate();
        update_option(self::OPTION_SCHEMA_VERSION, self::SCHEMA_VERSION);
    }

    public static function maybeMigrate(): void
    {
        $installedVersion = (string) get_option(self::OPTION_SCHEMA_VERSION, '');

        if ($installedVersion === self::SCHEMA_VERSION) {
            return;
        }

        self::migrate();
        update_option(self::OPTION_SCHEMA_VERSION, self::SCHEMA_VERSION);
    }

    private static function migrate(): void
    {
        global $wpdb;

        if (!($wpdb instanceof \wpdb)) {
            return;
        }

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $table = $wpdb->prefix . 'icms_cases';
        $charsetCollate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            id varchar(64) NOT NULL,
            assigned_officer_id bigint(20) unsigned DEFAULT NULL,
            status varchar(32) NOT NULL DEFAULT 'open',
            payload longtext DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY idx_assigned_officer_id (assigned_officer_id),
            KEY idx_status (status)
        ) {$charsetCollate};";

        dbDelta($sql);
    }
}
