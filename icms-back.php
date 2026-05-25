<?php
/**
 * Plugin Name: ICMS Backend
 * Description: Immigration Case Management System backend plugin.
 * Version: 0.1.0
 * Author: Kipdev Tech Solutions
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

define('ICMS_BACK_VERSION', '0.1.0');
define('ICMS_BACK_PLUGIN_FILE', __FILE__);
define('ICMS_BACK_PLUGIN_DIR', plugin_dir_path(__FILE__));

require_once ICMS_BACK_PLUGIN_DIR . 'includes/bootstrap.php';
