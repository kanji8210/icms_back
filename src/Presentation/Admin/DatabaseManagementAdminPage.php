<?php

declare(strict_types=1);

namespace ICMS\Presentation\Admin;

use ICMS\Application\UseCases\GetDatabaseOverviewUseCase;
use ICMS\Application\UseCases\RepairDatabaseSchemaUseCase;

final class DatabaseManagementAdminPage
{
    private const PAGE_SLUG = 'icms-back-database';
    private const NONCE_ACTION = 'icms_back_repair_schema';

    private GetDatabaseOverviewUseCase $getDatabaseOverviewUseCase;

    private RepairDatabaseSchemaUseCase $repairDatabaseSchemaUseCase;

    public function __construct(
        GetDatabaseOverviewUseCase $getDatabaseOverviewUseCase,
        RepairDatabaseSchemaUseCase $repairDatabaseSchemaUseCase
    ) {
        $this->getDatabaseOverviewUseCase = $getDatabaseOverviewUseCase;
        $this->repairDatabaseSchemaUseCase = $repairDatabaseSchemaUseCase;
    }

    public function register(): void
    {
        add_action('admin_menu', [$this, 'registerMenu']);
        add_action('admin_post_icms_back_repair_schema', [$this, 'handleRepairRequest']);
    }

    public function registerMenu(): void
    {
        if (current_user_can('manage_options')) {
            add_management_page(
                'ICMS Database Manager',
                'ICMS Database',
                'manage_options',
                self::PAGE_SLUG,
                [$this, 'renderPage']
            );

            return;
        }

        if (current_user_can('icms_admin')) {
            add_management_page(
                'ICMS Database Manager',
                'ICMS Database',
                'icms_admin',
                self::PAGE_SLUG,
                [$this, 'renderPage']
            );
        }
    }

    public function renderPage(): void
    {
        if (!$this->canManageDatabase()) {
            wp_die(esc_html__('You are not allowed to manage ICMS database tools.', 'icms-back'));
        }

        $overview = $this->getDatabaseOverviewUseCase->execute();
        $repairStatus = isset($_GET['repair']) ? sanitize_text_field(wp_unslash((string) $_GET['repair'])) : '';

        echo '<div class="wrap">';
        echo '<h1>ICMS Database Manager</h1>';
        echo '<p>Use this page to inspect schema state and run automatic schema repair.</p>';

        if ($repairStatus === 'success') {
            echo '<div class="notice notice-success is-dismissible"><p>Schema repair executed successfully.</p></div>';
        } elseif ($repairStatus === 'noop') {
            echo '<div class="notice notice-info is-dismissible"><p>Schema repair ran with no structural changes needed.</p></div>';
        } elseif ($repairStatus === 'error') {
            echo '<div class="notice notice-error is-dismissible"><p>Schema repair failed. Please check logs.</p></div>';
        }

        echo '<table class="widefat striped" style="max-width: 960px; margin-top: 12px;">';
        echo '<tbody>';
        echo '<tr><th style="width: 260px;">Database Name</th><td>' . esc_html((string) ($overview['database_name'] ?? '')) . '</td></tr>';
        echo '<tr><th>Table Prefix</th><td>' . esc_html((string) ($overview['table_prefix'] ?? '')) . '</td></tr>';
        echo '<tr><th>Target Schema Version</th><td>' . esc_html((string) ($overview['schema_version'] ?? '')) . '</td></tr>';
        echo '<tr><th>Installed Schema Version</th><td>' . esc_html((string) ($overview['installed_schema_version'] ?? '')) . '</td></tr>';
        echo '<tr><th>Auto Update and Repair</th><td>' . ($overview['auto_update_repair_enabled'] ? 'Enabled' : 'Disabled') . '</td></tr>';
        echo '</tbody>';
        echo '</table>';

        $this->renderTableSection('Created Tables', (array) ($overview['created_tables'] ?? []));
        $this->renderTableSection('Missing Tables', (array) ($overview['missing_tables'] ?? []));

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="margin-top: 16px;">';
        echo '<input type="hidden" name="action" value="icms_back_repair_schema" />';
        wp_nonce_field(self::NONCE_ACTION, '_icms_nonce');
        submit_button('Run Schema Repair', 'primary', 'submit', false);
        echo '</form>';
        echo '</div>';
    }

    public function handleRepairRequest(): void
    {
        if (!$this->canManageDatabase()) {
            wp_die(esc_html__('You are not allowed to repair ICMS schema.', 'icms-back'));
        }

        check_admin_referer(self::NONCE_ACTION, '_icms_nonce');

        $result = $this->repairDatabaseSchemaUseCase->execute();
        $status = 'noop';

        if ((bool) ($result['repaired'] ?? false)) {
            $status = 'success';
        }

        $after = (array) ($result['after'] ?? []);
        $afterMissing = (array) ($after['missing_tables'] ?? []);
        if (count($afterMissing) > 0) {
            $status = 'error';
        }

        wp_safe_redirect(admin_url('tools.php?page=' . self::PAGE_SLUG . '&repair=' . $status));
        exit;
    }

    private function canManageDatabase(): bool
    {
        return is_user_logged_in() && (current_user_can('icms_admin') || current_user_can('manage_options'));
    }

    /**
     * @param array<int, string> $tables
     */
    private function renderTableSection(string $title, array $tables): void
    {
        echo '<h2 style="margin-top: 20px;">' . esc_html($title) . '</h2>';

        if (count($tables) === 0) {
            echo '<p><em>None</em></p>';
            return;
        }

        echo '<ul style="margin-left: 18px;">';
        foreach ($tables as $tableName) {
            echo '<li>' . esc_html($tableName) . '</li>';
        }
        echo '</ul>';
    }
}