<?php

declare(strict_types=1);

namespace ICMS\Infrastructure\Persistence\Migrations;

final class SchemaManager
{
    private const OPTION_SCHEMA_VERSION = 'icms_back_schema_version';
    private const SCHEMA_VERSION = '0.4.0';

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
            $wpdb->prefix . 'icms_eta_records',
            $wpdb->prefix . 'icms_eta_movement_events',
            $wpdb->prefix . 'icms_eta_monitor_alerts',
            $wpdb->prefix . 'icms_eta_import_log',
            $wpdb->prefix . 'icms_eta_officers',
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
        $etaRecordsTable = $tables[7];
        $etaMovementsTable = $tables[8];
        $etaAlertsTable = $tables[9];
        $etaImportLogTable = $tables[10];
        $etaOfficersTable = $tables[11];

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
            "CREATE TABLE {$etaRecordsTable} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                passport_number varchar(64) NOT NULL,
                nationality varchar(8) DEFAULT NULL,
                reason_for_travel varchar(191) DEFAULT NULL,
                entry_date datetime DEFAULT NULL,
                expiry_date datetime NOT NULL,
                issued_at datetime DEFAULT NULL,
                eta_ref_number varchar(128) DEFAULT NULL,
                source varchar(64) NOT NULL DEFAULT 'webhook',
                raw_payload longtext DEFAULT NULL,
                created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY  (id),
                UNIQUE KEY uniq_eta_ref_number (eta_ref_number),
                KEY idx_passport_number (passport_number),
                KEY idx_expiry_date (expiry_date)
            ) {$charsetCollate};",
            "CREATE TABLE {$etaMovementsTable} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                passport_number varchar(64) NOT NULL,
                movement_type varchar(16) NOT NULL,
                movement_at datetime NOT NULL,
                border_point_code varchar(64) NOT NULL,
                officer_id bigint(20) unsigned NOT NULL,
                source varchar(64) NOT NULL DEFAULT 'direct',
                created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY  (id),
                KEY idx_passport_movement_at (passport_number, movement_at),
                KEY idx_movement_type (movement_type)
            ) {$charsetCollate};",
            "CREATE TABLE {$etaAlertsTable} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                eta_record_id bigint(20) unsigned NOT NULL,
                alert_window_days int(11) NOT NULL,
                case_id varchar(64) NOT NULL,
                status varchar(32) NOT NULL DEFAULT 'created',
                created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY  (id),
                UNIQUE KEY uniq_eta_window_alert (eta_record_id, alert_window_days),
                KEY idx_case_id (case_id),
                KEY idx_status (status)
            ) {$charsetCollate};",
            "CREATE TABLE {$etaImportLogTable} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                source varchar(64) NOT NULL,
                status varchar(32) NOT NULL,
                records_received int(11) NOT NULL DEFAULT 0,
                records_processed int(11) NOT NULL DEFAULT 0,
                details longtext DEFAULT NULL,
                created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY  (id),
                KEY idx_source_status (source, status),
                KEY idx_created_at (created_at)
            ) {$charsetCollate};",
            "CREATE TABLE {$etaOfficersTable} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                officer_code varchar(64) NOT NULL,
                officer_name varchar(191) NOT NULL,
                station varchar(191) NOT NULL,
                status varchar(32) NOT NULL DEFAULT 'active',
                metadata longtext DEFAULT NULL,
                created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY  (id),
                UNIQUE KEY uniq_officer_code (officer_code),
                KEY idx_station (station),
                KEY idx_status (status)
            ) {$charsetCollate};",
        ];

        foreach ($schemas as $schemaSql) {
            dbDelta($schemaSql);
        }
    }
}
