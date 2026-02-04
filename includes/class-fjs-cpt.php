<?php
if (!defined('ABSPATH')) exit;

final class FJS_CPT {
    public static function init() : void {
        add_action('init', [__CLASS__, 'register']);
        add_filter('single_template', [__CLASS__, 'maybe_use_plugin_template']);
        add_action('wp_head', [__CLASS__, 'robots_for_expired']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_assets'], 20);
    }

    public static function register() : void {
        $labels = [
            'name'          => __('Job Listings', 'findajob-jobs-searcher'),
            'singular_name' => __('Job Listing', 'findajob-jobs-searcher'),
            'menu_name'     => __('Job Listings', 'findajob-jobs-searcher'),
        ];

        register_post_type(FJS_CPT, [
            'labels' => $labels,
            'public' => true,
            'has_archive' => true,
            'rewrite' => ['slug' => 'jobs'],
            'supports' => ['title', 'editor', 'excerpt'],
            'show_in_rest' => false,
            'menu_icon' => 'dashicons-portfolio',
        ]);
    }

    public static function enqueue_assets() : void {
        if (is_singular(FJS_CPT) || is_post_type_archive(FJS_CPT)) {
            // Registered in FJS_Shortcode::register_assets() (priority 5)
            wp_enqueue_style('fjs-css');
        }
    }

    public static function maybe_use_plugin_template($single_template) {
        if (is_singular(FJS_CPT)) {
            $tpl = FJS_PLUGIN_DIR . 'templates/single-job_listing.php';
            if (file_exists($tpl)) return $tpl;
        }
        return $single_template;
    }

    public static function robots_for_expired() : void {
        if (!is_singular(FJS_CPT)) return;
        $pid = get_the_ID();
        $expired = get_post_meta($pid, '_fjs_expired', true);
        if ($expired === '1') {
            echo "<meta name=\"robots\" content=\"noindex, nofollow\" />\n";
        }
    }
}
