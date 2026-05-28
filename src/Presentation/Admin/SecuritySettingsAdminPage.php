<?php

declare(strict_types=1);

namespace ICMS\Presentation\Admin;

final class SecuritySettingsAdminPage
{
    private const PAGE_SLUG = 'admin-icms-security';
    private const NONCE_ACTION = 'icms_back_save_security_settings';
    private const NONCE_ACTION_GENERATE = 'icms_back_generate_security_secret';
    private const OPTION_JWT_SECRET = 'icms_back_jwt_secret';

    public function register(): void
    {
        add_action('admin_menu', [$this, 'registerMenu']);
        add_action('admin_post_icms_back_save_security_settings', [$this, 'handleSave']);
        add_action('admin_post_icms_back_generate_security_secret', [$this, 'handleGenerateSecret']);
    }

    public function registerMenu(): void
    {
        if (!$this->canManageSecurity()) {
            return;
        }

        add_options_page(
            'ICMS Security',
            'ICMS Security',
            current_user_can('manage_options') ? 'manage_options' : 'icms_admin',
            self::PAGE_SLUG,
            [$this, 'renderPage']
        );
    }

    public function renderPage(): void
    {
        if (!$this->canManageSecurity()) {
            wp_die(esc_html__('You are not allowed to manage ICMS security settings.', 'icms-back'));
        }

        $status = isset($_GET['status']) ? sanitize_text_field(wp_unslash((string) $_GET['status'])) : '';
        $storedSecret = (string) get_option(self::OPTION_JWT_SECRET, '');
        $constantOverride = defined('ICMS_JWT_SECRET') && ((string) constant('ICMS_JWT_SECRET') !== '');

        echo '<div class="wrap">';
        echo '<h1>ICMS Security Settings</h1>';
        echo '<p>Manage JWT secret used to secure API access from frontend apps.</p>';

        if ($status === 'saved') {
            echo '<div class="notice notice-success is-dismissible"><p>Security settings saved.</p></div>';
        } elseif ($status === 'invalid') {
            echo '<div class="notice notice-error is-dismissible"><p>JWT secret must be at least 32 characters.</p></div>';
        } elseif ($status === 'generated') {
            echo '<div class="notice notice-success is-dismissible"><p>A new strong JWT secret was generated and saved.</p></div>';
        }

        if ($constantOverride) {
            echo '<div class="notice notice-warning"><p>ICMS_JWT_SECRET is defined in wp-config.php. It overrides this page value.</p></div>';
        }

        echo '<table class="widefat striped" style="max-width: 960px; margin-top: 12px;">';
        echo '<tbody>';
        echo '<tr><th style="width: 260px;">Current Stored Secret</th><td>' . esc_html($this->maskSecret($storedSecret)) . '</td></tr>';
        echo '<tr><th>Active Secret Source</th><td>' . esc_html($constantOverride ? 'wp-config.php constant' : 'ICMS Security admin setting') . '</td></tr>';
        echo '<tr><th>Minimum Secret Length</th><td>32 characters</td></tr>';
        echo '</tbody>';
        echo '</table>';

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="margin-top: 16px; max-width: 960px;">';
        echo '<input type="hidden" name="action" value="icms_back_save_security_settings" />';
        wp_nonce_field(self::NONCE_ACTION, '_icms_nonce');

        echo '<table class="form-table" role="presentation">';
        echo '<tr>';
        echo '<th scope="row"><label for="icms_jwt_secret">New JWT Secret</label></th>';
        echo '<td>';
        echo '<input name="icms_jwt_secret" id="icms_jwt_secret" type="password" class="regular-text" autocomplete="new-password" />';
        echo '<p class="description">Leave empty to keep the existing secret. Enter at least 32 characters when changing.</p>';
        echo '</td>';
        echo '</tr>';
        echo '</table>';

        submit_button('Save Security Settings');
        echo '</form>';

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="margin-top: 10px; max-width: 960px;">';
        echo '<input type="hidden" name="action" value="icms_back_generate_security_secret" />';
        wp_nonce_field(self::NONCE_ACTION_GENERATE, '_icms_nonce_generate');
        submit_button('Generate Strong Secret', 'secondary', 'submit', false);
        echo '<p class="description">Creates and stores a new random key (64 characters). Existing tokens will stop working immediately.</p>';
        echo '</form>';
        echo '</div>';
    }

    public function handleSave(): void
    {
        if (!$this->canManageSecurity()) {
            wp_die(esc_html__('You are not allowed to update ICMS security settings.', 'icms-back'));
        }

        check_admin_referer(self::NONCE_ACTION, '_icms_nonce');

        $secret = isset($_POST['icms_jwt_secret']) ? trim((string) wp_unslash($_POST['icms_jwt_secret'])) : '';

        if ($secret !== '') {
            if (strlen($secret) < 32) {
                wp_safe_redirect(admin_url('options-general.php?page=' . self::PAGE_SLUG . '&status=invalid'));
                exit;
            }

            update_option(self::OPTION_JWT_SECRET, $secret, false);
        }

        wp_safe_redirect(admin_url('options-general.php?page=' . self::PAGE_SLUG . '&status=saved'));
        exit;
    }

    public function handleGenerateSecret(): void
    {
        if (!$this->canManageSecurity()) {
            wp_die(esc_html__('You are not allowed to update ICMS security settings.', 'icms-back'));
        }

        check_admin_referer(self::NONCE_ACTION_GENERATE, '_icms_nonce_generate');

        update_option(self::OPTION_JWT_SECRET, $this->generateStrongSecret(), false);

        wp_safe_redirect(admin_url('options-general.php?page=' . self::PAGE_SLUG . '&status=generated'));
        exit;
    }

    private function canManageSecurity(): bool
    {
        return is_user_logged_in() && (current_user_can('icms_admin') || current_user_can('manage_options'));
    }

    private function maskSecret(string $value): string
    {
        if ($value === '') {
            return 'Not set';
        }

        $length = strlen($value);
        if ($length <= 8) {
            return str_repeat('*', $length);
        }

        return substr($value, 0, 4) . str_repeat('*', max(4, $length - 8)) . substr($value, -4);
    }

    private function generateStrongSecret(): string
    {
        // 48 random bytes -> 64 chars after base64url encoding (without padding)
        return rtrim(strtr(base64_encode(random_bytes(48)), '+/', '-_'), '=');
    }
}
