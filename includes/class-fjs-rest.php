<?php
if (!defined('ABSPATH')) exit;

final class FJS_REST {

    public static function init() : void {
        add_action('rest_api_init', [__CLASS__, 'register_routes']);
    }

    public static function register_routes() : void {
        register_rest_route('fjs/v1', '/search', [
            'methods' => 'GET',
            'permission_callback' => '__return_true',
            'callback' => [__CLASS__, 'handle_search'],
            'args' => [
                'q' => ['type' => 'string', 'required' => false],
                'w' => ['type' => 'string', 'required' => false],
                'd' => ['type' => 'string', 'required' => false],
                'cty' => ['type' => 'string', 'required' => false],
                'cti' => ['type' => 'string', 'required' => false],
                'sf' => ['type' => 'integer', 'required' => false],
                'st' => ['type' => 'integer', 'required' => false],
                'f' => ['type' => 'integer', 'required' => false],
                'cat' => ['type' => 'integer', 'required' => false],
                'p' => ['type' => 'integer', 'required' => false],
                'loc' => ['type' => 'integer', 'required' => false],

                // New:
                // format=raw|both|normalized (default normalized)
                'format' => ['type' => 'string', 'required' => false],
                // persist=1 (only relevant for format=raw)
                'persist' => ['type' => 'integer', 'required' => false],
            ],
        ]);

        // New: detail endpoint that returns full normalized payload for a single job id
        register_rest_route('fjs/v1', '/job/(?P<id>\d+)', [
            'methods' => 'GET',
            'permission_callback' => '__return_true',
            'callback' => [__CLASS__, 'handle_job'],
            'args' => [
                'id' => ['type' => 'integer', 'required' => true],
            ],
        ]);
    }

    private static function get_client_ip() : string {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        return is_string($ip) ? $ip : '';
    }

    private static function bump_counter(string $key, int $window_seconds) : int {
        $count = (int)get_transient($key);
        $count++;
        set_transient($key, $count, $window_seconds);
        return $count;
    }

    private static function rate_limit_ok() : array {
        $o = FJS_Settings::get();

        $burst_max = (int)$o['rate_limit_burst_max'];
        $burst_win = (int)$o['rate_limit_burst_window_seconds'];
        $long_max  = (int)$o['rate_limit_long_max'];
        $long_win  = (int)$o['rate_limit_long_window_seconds'];

        $global_prefix = 'fjs_rl_global_' . md5(($o['api_id'] ?? '') . '|' . ($o['api_key'] ?? ''));
        $burst_key = $global_prefix . '_b';
        $long_key  = $global_prefix . '_l';

        $burst_count = (int)get_transient($burst_key);
        $long_count  = (int)get_transient($long_key);

        if ($burst_count >= $burst_max) return ['ok' => false, 'retry_after' => $burst_win];
        if ($long_count  >= $long_max)  return ['ok' => false, 'retry_after' => $long_win];

        $ip = self::get_client_ip();
        if ($ip !== '') {
            $ip_prefix = 'fjs_rl_ip_' . md5($ip);
            $ip_burst_max = max(10, (int)floor($burst_max / 2));
            $ip_long_max  = max(50, (int)floor($long_max / 3));

            $ip_burst_key = $ip_prefix . '_b';
            $ip_long_key  = $ip_prefix . '_l';

            $ip_burst_count = (int)get_transient($ip_burst_key);
            $ip_long_count  = (int)get_transient($ip_long_key);

            if ($ip_burst_count >= $ip_burst_max) return ['ok' => false, 'retry_after' => $burst_win];
            if ($ip_long_count  >= $ip_long_max)  return ['ok' => false, 'retry_after' => $long_win];

            self::bump_counter($ip_burst_key, $burst_win);
            self::bump_counter($ip_long_key, $long_win);
        }

        self::bump_counter($burst_key, $burst_win);
        self::bump_counter($long_key, $long_win);

        return ['ok' => true, 'retry_after' => 0];
    }

    private static function rate_limit_fail_response(array $rl) : WP_REST_Response {
        $resp = new WP_REST_Response([
            'error' => 'rate_limited',
            'retry_after_seconds' => (int)($rl['retry_after'] ?? 0),
        ], 429);
        $resp->header('Retry-After', (string)(int)($rl['retry_after'] ?? 0));
        return $resp;
    }

    /**
     * GET /fjs/v1/search
     */
    public static function handle_search(WP_REST_Request $req) {
        $rl = self::rate_limit_ok();
        if (!$rl['ok']) return self::rate_limit_fail_response($rl);

        $format = sanitize_text_field((string)$req->get_param('format'));
        if ($format === '') $format = 'normalized';

        $persist = ((int)$req->get_param('persist') === 1);

        $params = [];
        $params['q'] = sanitize_text_field((string)$req->get_param('q'));
        $params['w'] = sanitize_text_field((string)$req->get_param('w'));
        $cat_raw = $req->get_param('cat');
        $has_cat = ($cat_raw !== null && $cat_raw !== '');

        if (($params['w'] ?? '') === '' && ($params['q'] ?? '') === '' && !$has_cat) {
            return new WP_REST_Response(['error' => 'missing_required'], 400);
        }

        $d = $req->get_param('d');
        if ($d !== null && $d !== '') {
            $d_clean = preg_replace('/[^0-9.]/', '', (string)$d);
            if ($d_clean !== '' && (float)$d_clean > 0) $params['d'] = $d_clean;
        }

        $cty = sanitize_text_field((string)$req->get_param('cty'));
        $allowed_cty = ['permanent', 'contract', 'temporary', 'apprenticeship'];
        if (in_array($cty, $allowed_cty, true)) $params['cty'] = $cty;

        $cti = sanitize_text_field((string)$req->get_param('cti'));
        $allowed_cti = ['full_time', 'part_time'];
        if (in_array($cti, $allowed_cti, true)) $params['cti'] = $cti;

        foreach (['sf','st','f','cat','loc'] as $k) {
            $v = $req->get_param($k);
            if ($v !== null && $v !== '') $params[$k] = max(0, (int)$v);
        }

        $p = $req->get_param('p');
        $params['p'] = max(1, (int)($p ?? 1));

        $params = array_filter($params, function ($v) {
            return $v !== '' && $v !== null;
        });

        // Raw output: return upstream JSON as-is (optionally also persist)
        if ($format === 'raw') {
            $raw = FJS_API::fetch_raw($params);
            if (!empty($raw['error'])) {
                return new WP_REST_Response(['error' => $raw['error']], 502);
            }

            if ($persist) {
                // Persist by running normalize/upsert once (discard normalised output)
                $norm = FJS_API::search($params);
                if (!empty($norm['error'])) {
                    // Persistence failed, but raw succeeded — return raw anyway and flag it.
                    $raw['_persist_error'] = $norm['error'];
                }
            }

            return new WP_REST_Response($raw, 200);
        }

        // Both: include raw + normalized in one payload
        if ($format === 'both') {
            $raw = FJS_API::fetch_raw($params);
            if (!empty($raw['error'])) {
                return new WP_REST_Response(['error' => $raw['error']], 502);
            }

            $norm = FJS_API::search($params);
            if (!empty($norm['error'])) {
                return new WP_REST_Response(['error' => $norm['error']], 502);
            }

            return new WP_REST_Response([
                'raw' => $raw,
                'normalized' => $norm,
            ], 200);
        }

        // Default: normalized (tidy stable schema)
        $result = FJS_API::search($params);

        if (!empty($result['error'])) {
            return new WP_REST_Response(['error' => $result['error']], 502);
        }

        return new WP_REST_Response($result, 200);
    }

    /**
     * GET /fjs/v1/job/<id>
     *
     * Returns the stored local job (if present) including full normalized description variants.
     * This endpoint avoids forcing the UI to deal with upstream description chaos.
     */
    public static function handle_job(WP_REST_Request $req) {
        $rl = self::rate_limit_ok();
        if (!$rl['ok']) return self::rate_limit_fail_response($rl);

        $job_id = (int)$req->get_param('id');
        if ($job_id <= 0) {
            return new WP_REST_Response(['error' => 'invalid_id'], 400);
        }

        $post_type = apply_filters('fjs_job_post_type', 'fjs_job');
        $job_id_meta_key = apply_filters('fjs_job_id_meta_key', '_fjs_job_id');

        $query_args = [
            'post_type'      => (post_type_exists($post_type) ? $post_type : 'any'),
            'post_status'    => 'any',
            'fields'         => 'ids',
            'posts_per_page' => 1,
            'no_found_rows'  => true,
            'meta_query'     => [
                [
                    'key'   => $job_id_meta_key,
                    'value' => (string)$job_id,
                ],
            ],
        ];

        $posts = get_posts($query_args);

        if (!$posts) {
            return new WP_REST_Response(['error' => 'not_found'], 404);
        }

        $pid = (int)$posts[0];

        $payload = [
            'id' => (string)$job_id,
            'local_post_id' => $pid,
            'local_permalink' => get_permalink($pid),

            'title' => get_the_title($pid),

            'company' => (string)get_post_meta($pid, '_fjs_company', true),
            'location' => (string)get_post_meta($pid, '_fjs_location', true),
            'postcode' => (string)get_post_meta($pid, '_fjs_postcode', true),
            'salary' => (string)get_post_meta($pid, '_fjs_salary', true),

            'posted' => (string)get_post_meta($pid, '_fjs_posted', true),
            'closing' => (string)get_post_meta($pid, '_fjs_closing', true),

            'contract_type' => (string)get_post_meta($pid, '_fjs_contract_type', true),
            'contract_time' => (string)get_post_meta($pid, '_fjs_contract_time', true),

            'apply_url' => (string)get_post_meta($pid, '_fjs_url', true),
            'expired' => (get_post_meta($pid, '_fjs_expired', true) === '1'),

            // Normalized description variants (recommended for rendering)
            'description_format'  => (string)get_post_meta($pid, '_fjs_description_format', true),
            'description_excerpt' => (string)get_post_meta($pid, '_fjs_description_excerpt', true),
            'description_html'    => (string)get_post_meta($pid, '_fjs_description_html', true),
            'description_text'    => (string)get_post_meta($pid, '_fjs_description_text', true),
        ];

        return new WP_REST_Response($payload, 200);
    }
}
