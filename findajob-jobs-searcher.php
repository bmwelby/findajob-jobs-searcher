<?php
/**
 * Plugin Name: Find a Job – Jobs Searcher
 * Description: Search Find a job API, show results, and persist each job as a standalone page with an “Apply” link.
 * Version: 1.0.0
 * Author: Ben Welby + ChatGPT + Google Jules
 * License: GPL-2.0+
 */

if (!defined('ABSPATH')) exit;

define('FJS_VERSION', '1.0.0');
define('FJS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('FJS_PLUGIN_URL', plugin_dir_url(__FILE__));
define('FJS_CPT', 'job_listing');

require_once FJS_PLUGIN_DIR . 'includes/class-fjs-settings.php';
require_once FJS_PLUGIN_DIR . 'includes/class-fjs-cpt.php';
require_once FJS_PLUGIN_DIR . 'includes/class-fjs-api.php';
require_once FJS_PLUGIN_DIR . 'includes/class-fjs-rest.php';
require_once FJS_PLUGIN_DIR . 'includes/class-fjs-cron.php';
require_once FJS_PLUGIN_DIR . 'includes/class-fjs-shortcode.php';

final class FJS_Plugin {
    public static function init() : void {
        FJS_Settings::init();
        FJS_CPT::init();
        FJS_REST::init();
        FJS_Cron::init();
        FJS_Shortcode::init();
    }

    public static function activate() : void {
        FJS_CPT::register();
        flush_rewrite_rules();
        FJS_Cron::schedule();
    }

    public static function deactivate() : void {
        FJS_Cron::unschedule();
        flush_rewrite_rules();
    }
}

FJS_Plugin::init();

register_activation_hook(__FILE__, ['FJS_Plugin', 'activate']);
register_deactivation_hook(__FILE__, ['FJS_Plugin', 'deactivate']);

