<?php
if (!defined('ABSPATH')) exit;

final class FJS_API {

    public static function get_categories() : array {
        return [
            1 => __('Accounting & Finance Jobs', 'findajob-jobs-searcher'),
            16 => __('Logistics & Warehouse Jobs', 'findajob-jobs-searcher'),
            2 => __('Admin Jobs', 'findajob-jobs-searcher'),
            17 => __('Maintenance Jobs', 'findajob-jobs-searcher'),
            3 => __('Agriculture, Fishing & Forestry Jobs', 'findajob-jobs-searcher'),
            18 => __('Manufacturing Jobs', 'findajob-jobs-searcher'),
            4 => __('Consultancy Jobs', 'findajob-jobs-searcher'),
            19 => __('Other/General Jobs', 'findajob-jobs-searcher'),
            5 => __('Creative & Design Jobs', 'findajob-jobs-searcher'),
            20 => __('PR, Advertising & Marketing Jobs', 'findajob-jobs-searcher'),
            6 => __('Customer Services Jobs', 'findajob-jobs-searcher'),
            21 => __('Property Jobs', 'findajob-jobs-searcher'),
            7 => __('Domestic Help & Cleaning Jobs', 'findajob-jobs-searcher'),
            22 => __('Retail Jobs', 'findajob-jobs-searcher'),
            8 => __('Energy, Oil & Gas Jobs', 'findajob-jobs-searcher'),
            23 => __('Sales Jobs', 'findajob-jobs-searcher'),
            9 => __('Engineering Jobs', 'findajob-jobs-searcher'),
            24 => __('Scientific & QA Jobs', 'findajob-jobs-searcher'),
            10 => __('Graduate Jobs', 'findajob-jobs-searcher'),
            25 => __('Security & Protective Services Jobs', 'findajob-jobs-searcher'),
            11 => __('HR & Recruitment Jobs', 'findajob-jobs-searcher'),
            26 => __('Social Work Jobs', 'findajob-jobs-searcher'),
            12 => __('Healthcare & Nursing Jobs', 'findajob-jobs-searcher'),
            27 => __('Education & Childcare Jobs', 'findajob-jobs-searcher'),
            13 => __('Hospitality & Catering Jobs', 'findajob-jobs-searcher'),
            28 => __('Trade & Construction Jobs', 'findajob-jobs-searcher'),
            14 => __('IT Jobs', 'findajob-jobs-searcher'),
            29 => __('Travel Jobs', 'findajob-jobs-searcher'),
            15 => __('Legal Jobs', 'findajob-jobs-searcher'),
            177 => __('Social Care Jobs', 'findajob-jobs-searcher'),
        ];
    }

    public static function search(array $params) : array {
        $opts = FJS_Settings::get();

        if (empty($opts['api_id']) || empty($opts['api_key'])) {
            return ['error' => __('API credentials are not configured.', 'findajob-jobs-searcher')];
        }

        $api_base = rtrim((string)($opts['api_base'] ?? ''), '/');
        if (!$api_base) $api_base = 'https://findajob.dwp.gov.uk';

        // Find a job API params
        $q = [
            'api_id' => (string)$opts['api_id'],
            'api_key' => (string)$opts['api_key'],
            // If you want to reduce HTML variability, you can set this to 0
            // and rely on our normaliser below.
            'html_description' => 1,
        ];

        // allowed: q,w,loc,f,cat,cty,cti,d,sf,st,p
        foreach (['q','w','cty','cti','d'] as $k) {
            if (!empty($params[$k])) $q[$k] = (string)$params[$k];
        }
        foreach (['loc','f','cat','sf','st','p'] as $k) {
            if (isset($params[$k]) && $params[$k] !== '' && $params[$k] !== null) {
                $q[$k] = (int)$params[$k];
            }
        }

        $q['p'] = max(1, (int)($q['p'] ?? 1));

        $cache_ttl = (int)($opts['cache_ttl_seconds'] ?? 0);

        // Cache key should NOT include api_key, but should include api_id + query params
        $cache_fingerprint = $q;
        unset($cache_fingerprint['api_key']);
        $cache_key = 'fjs_search_' . md5(wp_json_encode($cache_fingerprint));

        if ($cache_ttl > 0) {
            $cached = get_transient($cache_key);
            if (is_array($cached)) {
                return self::normalize_and_upsert($cached);
            }
        }

        $url = $api_base . '/api/search?' . http_build_query($q, '', '&', PHP_QUERY_RFC3986);

        $res = wp_remote_get($url, [
            'timeout' => 12,
            'redirection' => 2,
            'headers' => ['Accept' => 'application/json'],
        ]);

        if (is_wp_error($res)) {
            return ['error' => $res->get_error_message()];
        }

        $code = (int)wp_remote_retrieve_response_code($res);
        $body = (string)wp_remote_retrieve_body($res);
        if ($code < 200 || $code >= 300) {
            return ['error' => sprintf(__('API error (HTTP %d).', 'findajob-jobs-searcher'), $code)];
        }

        $json = json_decode($body, true);
        if (!is_array($json)) {
            return ['error' => __('Invalid JSON returned by API.', 'findajob-jobs-searcher')];
        }

        if ($cache_ttl > 0) {
            set_transient($cache_key, $json, $cache_ttl);
        }

        return self::normalize_and_upsert($json);
    }

    private static function normalize_and_upsert(array $json) : array {
        $pager = $json['pager'] ?? ['total_entries' => 0, 'pages' => 0, 'current_page' => 1];
        $jobs  = $json['jobs'] ?? [];

        $out_jobs = [];
        if (is_array($jobs)) {
            foreach ($jobs as $job) {
                if (!is_array($job)) continue;
                $mapped = self::upsert_job($job);
                if ($mapped) $out_jobs[] = $mapped;
            }
        }

        return [
            'pager' => [
                'total_entries' => (int)($pager['total_entries'] ?? 0),
                'pages' => (int)($pager['pages'] ?? 0),
                'current_page' => (int)($pager['current_page'] ?? 1),
            ],
            'jobs' => $out_jobs,
        ];
    }

    public static function upsert_job(array $j) : ?array {
        if (!isset($j['id'])) return null;
        $job_id = (int)$j['id'];
        if ($job_id <= 0) return null;

        $existing = get_posts([
            'post_type' => FJS_CPT,
            'meta_key' => '_fjs_job_id',
            'meta_value' => $job_id,
            'fields' => 'ids',
            'posts_per_page' => 1,
            'no_found_rows' => true,
        ]);

        $post_id = $existing ? (int)$existing[0] : 0;

        $title = isset($j['title']) ? wp_strip_all_tags((string)$j['title']) : ('Job ' . $job_id);
        $slug = 'job-' . $job_id;

        $posted_iso  = isset($j['posted']) ? sanitize_text_field((string)$j['posted']) : '';
        $closing_iso = isset($j['closing']) ? sanitize_text_field((string)$j['closing']) : '';

        $posted_ts  = $posted_iso  ? (int)strtotime($posted_iso)  : 0;
        $closing_ts = $closing_iso ? (int)strtotime($closing_iso) : 0;

        $is_expired = ($closing_ts > 0 && $closing_ts < time());

        // Normalise description into stable formats
        $desc_raw = isset($j['description']) ? (string)$j['description'] : '';
        $desc = self::normalize_description($desc_raw);

        $postarr = [
            'post_type' => FJS_CPT,
            'post_title' => $title,
            'post_status' => $is_expired ? 'draft' : 'publish',
            'post_name' => $slug,
        ];

        if ($post_id) {
            $postarr['ID'] = $post_id;
            $updated = wp_update_post($postarr, true);
            if (is_wp_error($updated)) return null;
            $post_id = (int)$updated;
        } else {
            $inserted = wp_insert_post($postarr, true);
            if (is_wp_error($inserted)) return null;
            $post_id = (int)$inserted;
        }

        $meta = [
            '_fjs_job_id' => $job_id,

            // Store raw + normalised variants
            '_fjs_description_raw' => $desc['raw'],
            '_fjs_description_html' => $desc['html'],
            '_fjs_description_text' => $desc['text'],
            '_fjs_description_excerpt' => $desc['excerpt'],
            '_fjs_description_format' => $desc['format'],

            '_fjs_location' => isset($j['location']) ? sanitize_text_field((string)$j['location']) : '',
            '_fjs_postcode' => isset($j['postcode']) ? sanitize_text_field((string)$j['postcode']) : '',
            '_fjs_salary' => isset($j['salary']) ? sanitize_text_field((string)$j['salary']) : '',
            '_fjs_salary_base' => isset($j['salary_base']) ? sanitize_text_field((string)$j['salary_base']) : '',
            '_fjs_salary_info' => isset($j['salary_info']) ? sanitize_text_field((string)$j['salary_info']) : '',
            '_fjs_posted' => $posted_iso,
            '_fjs_posted_ts' => $posted_ts,
            '_fjs_closing' => $closing_iso,
            '_fjs_closing_ts' => $closing_ts,
            '_fjs_company' => isset($j['company']) ? sanitize_text_field((string)$j['company']) : '',
            '_fjs_contract_type' => isset($j['contract_type']) ? sanitize_text_field((string)$j['contract_type']) : '',
            '_fjs_contract_time' => isset($j['contract_time']) ? sanitize_text_field((string)$j['contract_time']) : '',
            '_fjs_url' => isset($j['url']) ? esc_url_raw((string)$j['url']) : '',
            '_fjs_isco_code' => isset($j['isco_code']) ? sanitize_text_field((string)$j['isco_code']) : '',
            '_fjs_disability_confident' => isset($j['disability_confident']) ? sanitize_text_field((string)$j['disability_confident']) : '',
            '_fjs_last_seen' => gmdate('c'),
            '_fjs_expired' => $is_expired ? '1' : '0',
        ];

        foreach ($meta as $k => $v) {
            if ($v === '' || $v === null) delete_post_meta($post_id, $k);
            else update_post_meta($post_id, $k, $v);
        }

        // Use sanitised HTML for post_content; use excerpt from text
        $content_html = (string)($meta['_fjs_description_html'] ?? '');
        $excerpt_text = (string)($meta['_fjs_description_excerpt'] ?? '');

        if ($content_html !== '') {
            wp_update_post([
                'ID' => $post_id,
                'post_content' => $content_html,
                'post_excerpt' => $excerpt_text,
            ]);
        }

        return [
            'id' => (string)$job_id,
            'title' => $title,
            'company' => (string)$meta['_fjs_company'],
            'location' => (string)$meta['_fjs_location'],
            'postcode' => (string)$meta['_fjs_postcode'],
            'salary' => (string)$meta['_fjs_salary'],
            'posted' => (string)$meta['_fjs_posted'],
            'closing' => (string)$meta['_fjs_closing'],
            'contract_type' => (string)$meta['_fjs_contract_type'],
            'contract_time' => (string)$meta['_fjs_contract_time'],
            'apply_url' => (string)$meta['_fjs_url'],
            'local_permalink' => get_permalink($post_id),
            'expired' => ((string)$meta['_fjs_expired'] === '1'),

            // Stable variants you can return in REST responses:
            'description_format' => $desc['format'],
            'description_excerpt' => $desc['excerpt'],
            // keep full HTML out of list responses unless you explicitly want it
        ];
    }

    /**
     * Normalise upstream job descriptions into:
     * - raw: best-effort decoded string (still untrusted)
     * - html: sanitised HTML safe for output
     * - text: plain text for excerpts/cards/search
     * - excerpt: trimmed text
     * - format: 'html' or 'text'
     */
    private static function normalize_description(string $raw) : array {
        $raw = trim((string)$raw);
        if ($raw === '') {
            return [
                'raw' => '',
                'html' => '',
                'text' => '',
                'excerpt' => '',
                'format' => 'text',
            ];
        }

        // Decode entities (often appears double-encoded)
        $decoded = self::decode_entities_loop($raw, 2);

        // Normalise line endings
        $decoded = str_replace(["\r\n", "\r"], "\n", $decoded);

        // Decide: does it contain HTML tags?
        $looks_html = (bool)preg_match('/<\/?[a-z][\s\S]*>/i', $decoded);

        if ($looks_html) {
            $format = 'html';
            $html = self::sanitize_description_html($decoded);

            // If it’s “HTML but basically line breaks”, wpautop helps.
            if ($html !== '' && !preg_match('/<(p|ul|ol|h[1-6]|blockquote)\b/i', $html)) {
                $html = wpautop($html);
            }

            $text = trim(wp_strip_all_tags($html, true));
        } else {
            $format = 'text';
            $text = self::cleanup_text_description($decoded);
            $html = self::text_to_structured_html($text);
        }

        $text = self::cleanup_text_description($text);
        $excerpt = $text ? wp_trim_words($text, 55) : '';

        return [
            'raw' => $decoded,
            'html' => $html,
            'text' => $text,
            'excerpt' => $excerpt,
            'format' => $format,
        ];
    }

    private static function decode_entities_loop(string $s, int $max_passes = 2) : string {
        $out = $s;
        for ($i = 0; $i < $max_passes; $i++) {
            $next = html_entity_decode($out, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if ($next === $out) break;
            $out = $next;
        }
        return $out;
    }

    private static function sanitize_description_html(string $html) : string {
        $html = trim($html);
        if ($html === '') return '';

        // Strip scripts/styles outright before kses (belt and braces)
        $html = preg_replace('#<(script|style)[^>]*>[\s\S]*?</\1>#i', '', $html);

        // Balance broken tags (common in feeds)
        if (function_exists('force_balance_tags')) {
            $html = force_balance_tags($html);
        }

        // Sanitise to a controlled allowlist
        $allowed = self::allowed_description_tags();
        $html = wp_kses($html, $allowed);

        // Reduce excessive <br> spam
        $html = preg_replace('#(<br\s*/?>\s*){3,}#i', "<br /><br />", $html);

        return trim($html);
    }

    private static function allowed_description_tags() : array {
        return [
            'p' => [],
            'br' => [],
            'strong' => [],
            'b' => [],
            'em' => [],
            'i' => [],
            'ul' => [],
            'ol' => [],
            'li' => [],
            'blockquote' => [],
            'h2' => [],
            'h3' => [],
            'h4' => [],
            'a' => [
                'href' => true,
                'rel' => true,
                'target' => true,
            ],
        ];
    }

    private static function cleanup_text_description(string $text) : string {
        $text = trim($text);
        if ($text === '') return '';

        $text = self::decode_entities_loop($text, 2);
        $text = str_replace(["\r\n", "\r"], "\n", $text);

        // Collapse repeated whitespace and blank lines
        $text = preg_replace("/[ \t]+/", " ", $text);
        $text = preg_replace("/\n{3,}/", "\n\n", $text);

        // Dedupe consecutive identical lines (common recruiter spam formatting)
        $lines = explode("\n", $text);
        $out = [];
        $prev = null;
        foreach ($lines as $ln) {
            $t = trim($ln);
            if ($t === '') {
                $out[] = '';
                $prev = '';
                continue;
            }
            if ($prev !== null && $t === $prev) {
                continue;
            }
            $out[] = $t;
            $prev = $t;
        }
        $text = trim(implode("\n", $out));
        $text = preg_replace("/\n{3,}/", "\n\n", $text);

        return $text;
    }

    /**
     * Convert plain text into nicer HTML:
     * - paragraphs via wpautop
     * - basic bullets (•, -, *) into <ul>
     * - short "Heading:" lines into <h3>
     */
    private static function text_to_structured_html(string $text) : string {
        $text = trim($text);
        if ($text === '') return '';

        $lines = explode("\n", $text);

        $html = '';
        $in_list = false;

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '') {
                if ($in_list) {
                    $html .= "</ul>\n";
                    $in_list = false;
                }
                $html .= "\n";
                continue;
            }

            // Headings like "Requirements:" / "Key Responsibilities:"
            if (preg_match('/^[A-Za-z0-9][A-Za-z0-9 \/\&\-\(\)]{2,60}:\s*$/', $line)) {
                if ($in_list) {
                    $html .= "</ul>\n";
                    $in_list = false;
                }
                $heading = rtrim($line, ':');
                $html .= '<h3>' . esc_html($heading) . "</h3>\n";
                continue;
            }

            // Bullets
            if (preg_match('/^(?:•|\-|\*)\s+(.*)$/u', $line, $m)) {
                if (!$in_list) {
                    $html .= "<ul>\n";
                    $in_list = true;
                }
                $html .= '<li>' . esc_html($m[1]) . "</li>\n";
                continue;
            }

            // Default: treat as paragraph text
            if ($in_list) {
                $html .= "</ul>\n";
                $in_list = false;
            }
            $html .= esc_html($line) . "\n";
        }

        if ($in_list) {
            $html .= "</ul>\n";
        }

        // Make URLs clickable (safe; then kses will allow <a>)
        $html = make_clickable($html);

        // Paragraphs
        $html = wpautop($html);

        // Final sanitisation pass
        $html = wp_kses($html, self::allowed_description_tags());

        return trim($html);
    }

    /**
     * Fetch "similar jobs" for a given local job post.
     * Quota-safe: cached per job for 6 hours by default.
     */
    public static function get_similar_jobs(int $post_id, int $limit = 5) : array {
        $job_id   = (int)get_post_meta($post_id, '_fjs_job_id', true);
        $title    = get_the_title($post_id);

        $isco     = (string)get_post_meta($post_id, '_fjs_isco_code', true);
        $postcode = (string)get_post_meta($post_id, '_fjs_postcode', true);
        $location = (string)get_post_meta($post_id, '_fjs_location', true);

        $cache_key = 'fjs_similar_' . md5($job_id . '|' . $limit);
        $ttl = 6 * HOUR_IN_SECONDS;

        $cached = get_transient($cache_key);
        if (is_array($cached)) return $cached;

        $q = ($isco !== '') ? $isco : self::simplify_title_for_query($title);
        $w = ($postcode !== '') ? $postcode : $location;

        if ($w === '' && $q === '') {
            set_transient($cache_key, [], $ttl);
            return [];
        }

        $params = [
            'q' => $q,
            'w' => $w,
            'd' => '10',
            'f' => 30,
            'p' => 1,
        ];

        // Filter empties (no arrow functions, for compatibility)
        $filtered = [];
        foreach ($params as $k => $v) {
            if ($v === '' || $v === null) continue;
            $filtered[$k] = $v;
        }

        $result = self::search($filtered);
        if (!empty($result['error']) || empty($result['jobs']) || !is_array($result['jobs'])) {
            set_transient($cache_key, [], $ttl);
            return [];
        }

        $jobs = [];
        foreach ($result['jobs'] as $j) {
            if (!isset($j['id'])) continue;
            if ((int)$j['id'] === $job_id) continue;
            $jobs[] = $j;
            if (count($jobs) >= $limit) break;
        }

        set_transient($cache_key, $jobs, $ttl);
        return $jobs;
    }

    private static function simplify_title_for_query(string $title) : string {
        $t = strtolower($title);
        $t = preg_replace('/[^a-z0-9\s\+\#]/i', ' ', $t); // keep C++, C#, etc roughly
        $t = preg_replace('/\s+/', ' ', $t);
        $words = array_filter(explode(' ', trim($t)));

        $stop = array_flip([
            'and','or','the','a','an','to','of','for','in','on','with','at','by',
            'assistant','junior','senior','lead','manager','officer','worker','role','job'
        ]);

        $keep = [];
        foreach ($words as $w) {
            if (strlen($w) < 2) continue;
            if (isset($stop[$w])) continue;
            $keep[] = $w;
            if (count($keep) >= 4) break;
        }

        if (empty($keep)) {
            $keep = array_slice($words, 0, 3);
        }

        return implode(' ', $keep);
    }
}
