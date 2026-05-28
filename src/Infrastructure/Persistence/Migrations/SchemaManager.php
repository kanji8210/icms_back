<?php

declare(strict_types=1);

namespace ICMS\Infrastructure\Persistence\Migrations;

final class SchemaManager
{
    private const OPTION_SCHEMA_VERSION = 'icms_back_schema_version';
    private const SCHEMA_VERSION = '0.2.0';

    public static function getSchemaVersion(): string
    {
        return self::SCHEMA_VERSION;
    }

    public static function getInstalledSchemaVersion(): string
    {
        return (string) get_option(self::OPTION_SCHEMA_VERSION, '');
    }

    /**
     * @return array<int, string>
     */
    public static function expectedTables(\wpdb $wpdb): array
    {
        return [
            $wpdb->prefix . 'icms_cases',
            $wpdb->prefix . 'icms_audit_log',
            $wpdb->prefix . 'icms_ban_flags',
            $wpdb->prefix . 'icms_public_reports',
            $wpdb->prefix . 'icms_purge_log',
            $wpdb->prefix . 'icms_public_posts',
            $wpdb->prefix . 'icms_insights_cache',
        ];
    }

    public static function activate(): void
    {
        self::migrate();
        update_option(self::OPTION_SCHEMA_VERSION, self::SCHEMA_VERSION);
    }

    public static function maybeMigrate(): void
    {
        $installedVersion = self::getInstalledSchemaVersion();

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

        $charsetCollate = $wpdb->get_charset_collate();
        $tables = self::expectedTables($wpdb);

        $casesTable = $tables[0];
        $auditLogTable = $tables[1];
        $banFlagsTable = $tables[2];
        $publicReportsTable = $tables[3];
        $purgeLogTable = $tables[4];
        $publicPostsTable = $tables[5];
        $insightsCacheTable = $tables[6];

        $schemas = [
            "CREATE TABLE {$casesTable} (
                id varchar(64) NOT NULL,
                assigned_officer_id bigint(20) unsigned DEFAULT NULL,
                status varchar(32) NOT NULL DEFAULT 'open',
                payload longtext DEFAULT NULL,
                purge_at datetime DEFAULT NULL,
                created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY  (id),
                KEY idx_assigned_officer_id (assigned_officer_id),
                KEY idx_status (status),
                KEY idx_purge_at (purge_at)
            ) {$charsetCollate};",
            "CREATE TABLE {$auditLogTable} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                case_id varchar(64) NOT NULL,
                officer_id bigint(20) unsigned DEFAULT NULL,
                action varchar(64) NOT NULL,
                details longtext DEFAULT NULL,
                ip_address varchar(45) DEFAULT NULL,
                created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY  (id),
                KEY idx_case_id (case_id),
                KEY idx_officer_id (officer_id),
                KEY idx_action (action)
            ) {$charsetCollate};",
            "CREATE TABLE {$banFlagsTable} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                case_id varchar(64) DEFAULT NULL,
                passport_hash varchar(128) NOT NULL,
                ban_start datetime NOT NULL,
                ban_end datetime DEFAULT NULL,
                reason varchar(255) DEFAULT NULL,
                created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY  (id),
                UNIQUE KEY uniq_passport_hash (passport_hash),
                KEY idx_case_id (case_id)
            ) {$charsetCollate};",
            "CREATE TABLE {$publicReportsTable} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                reference_code varchar(64) NOT NULL,
                report_type varchar(64) NOT NULL,
                details longtext NOT NULL,
                status varchar(32) NOT NULL DEFAULT 'submitted',
                reporter_ip_hash varchar(128) DEFAULT NULL,
                created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY  (id),
                UNIQUE KEY uniq_reference_code (reference_code),
                KEY idx_status (status)
            ) {$charsetCollate};",
            "CREATE TABLE {$purgeLogTable} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                case_hash varchar(128) NOT NULL,
                reason varchar(128) NOT NULL,
                purged_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY  (id),
                KEY idx_case_hash (case_hash),
                KEY idx_purged_at (purged_at)
            ) {$charsetCollate};",
            "CREATE TABLE {$publicPostsTable} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                slug varchar(191) NOT NULL,
                title varchar(255) NOT NULL,
                body longtext NOT NULL,
                post_type varchar(64) NOT NULL,
                status varchar(32) NOT NULL DEFAULT 'published',
                published_at datetime DEFAULT NULL,
                created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY  (id),
                UNIQUE KEY uniq_slug (slug),
                KEY idx_post_type (post_type),
                KEY idx_status (status)
            ) {$charsetCollate};",
            "CREATE TABLE {$insightsCacheTable} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                metric_key varchar(191) NOT NULL,
                metric_value longtext NOT NULL,
                calculated_at datetime NOT NULL,
                expires_at datetime NOT NULL,
                created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY  (id),
                UNIQUE KEY uniq_metric_key (metric_key),
                KEY idx_expires_at (expires_at)
            ) {$charsetCollate};",
        ];

        foreach ($schemas as $schemaSql) {
            dbDelta($schemaSql);
        }
    }
}
