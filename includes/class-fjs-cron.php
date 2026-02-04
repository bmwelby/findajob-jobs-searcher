<?php
if (!defined('ABSPATH')) exit;

final class FJS_Cron {
    const HOOK = 'fjs_daily_expiry';

    public static function init() : void {
        add_action(self::HOOK, [__CLASS__, 'run']);
    }

    public static function schedule() : void {
        if (!wp_next_scheduled(self::HOOK)) {
            wp_schedule_event(time() + 60, 'daily', self::HOOK);
        }
    }

    public static function unschedule() : void {
        $ts = wp_next_scheduled(self::HOOK);
        if ($ts) wp_unschedule_event($ts, self::HOOK);
    }

    public static function run() : void {
        $o = FJS_Settings::get();
        $expire_to_draft = ((int)$o['expire_to_draft'] === 1);
        $purge_after_days = (int)$o['purge_after_days'];

        if (!$expire_to_draft && $purge_after_days <= 0) return;

        $now = time();

        // A) Draft expired published jobs
        if ($expire_to_draft) {
            $q = new WP_Query([
                'post_type' => FJS_CPT,
                'post_status' => 'publish',
                'posts_per_page' => 500,
                'meta_query' => [
                    [
                        'key' => '_fjs_closing_ts',
                        'value' => $now,
                        'compare' => '<',
                        'type' => 'NUMERIC',
                    ],
                ],
                'fields' => 'ids',
                'no_found_rows' => true,
            ]);

            foreach ($q->posts as $pid) {
                wp_update_post(['ID' => (int)$pid, 'post_status' => 'draft']);
                update_post_meta((int)$pid, '_fjs_expired', '1');
            }
        }

        // B) Trash expired jobs older than purge window (based on closing_ts)
        if ($purge_after_days > 0) {
            $cutoff = $now - ($purge_after_days * DAY_IN_SECONDS);

            $q2 = new WP_Query([
                'post_type' => FJS_CPT,
                'post_status' => ['draft', 'publish'],
                'posts_per_page' => 500,
                'meta_query' => [
                    [
                        'key' => '_fjs_closing_ts',
                        'value' => $cutoff,
                        'compare' => '<',
                        'type' => 'NUMERIC',
                    ],
                ],
                'fields' => 'ids',
                'no_found_rows' => true,
            ]);

            foreach ($q2->posts as $pid) {
                wp_trash_post((int)$pid);
            }
        }
    }
}
