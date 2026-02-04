<?php
if (!defined('ABSPATH')) exit;

final class FJS_Shortcode {
    public static function init() : void {
        add_shortcode('findajob_search', [__CLASS__, 'render']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'register_assets'], 5);
    }

    public static function register_assets() : void {
        wp_register_style('fjs-css', FJS_PLUGIN_URL . 'assets/css/fjs.css', [], FJS_VERSION);
        wp_register_script('fjs-js', FJS_PLUGIN_URL . 'assets/js/fjs.js', [], FJS_VERSION, true);

        wp_localize_script('fjs-js', 'FJS', [
            'endpoint' => esc_url_raw(rest_url('fjs/v1/search')),
        ]);
    }

    public static function render($atts = []) : string {
        wp_enqueue_style('fjs-css');
        wp_enqueue_script('fjs-js');

        $q = isset($_GET['q']) ? sanitize_text_field((string)$_GET['q']) : '';
        $w = isset($_GET['w']) ? sanitize_text_field((string)$_GET['w']) : '';
        $d = isset($_GET['d']) ? sanitize_text_field((string)$_GET['d']) : '5';
        $cti = isset($_GET['cti']) ? sanitize_text_field((string)$_GET['cti']) : '';
        $cty = isset($_GET['cty']) ? sanitize_text_field((string)$_GET['cty']) : '';
        $sf = isset($_GET['sf']) ? (int)$_GET['sf'] : 0;
        $p = isset($_GET['p']) ? max(1, (int)$_GET['p']) : 1;

        $has_query = ($w !== '' || $q !== '');
        $server_result = null;

        if ($has_query) {
            $params = [
                'q' => $q,
                'w' => $w,
                'd' => $d,
                'cti' => $cti,
                'cty' => $cty,
                'sf' => $sf > 0 ? $sf : null,
                'p' => $p,
            ];

            $params = array_filter($params, function ($v) {
                return $v !== '' && $v !== null;
            });

            $server_result = FJS_API::search($params);
        }

        ob_start();
        ?>
        <div class="fjs">
            <form class="fjs__form" method="get" action="">
                <div class="fjs__field">
                    <label for="fjs-w">Location</label>
                    <input id="fjs-w" name="w" type="text" required value="<?php echo esc_attr($w); ?>" placeholder="e.g. London, Scotland, SW1A, E1W" />
                </div>

                <div class="fjs__field">
                    <label for="fjs-d">Radius (miles)</label>
                    <input id="fjs-d" name="d" type="number" min="0.5" step="0.5" value="<?php echo esc_attr($d); ?>" />
                </div>

                <div class="fjs__field">
                    <label for="fjs-cti">Required hours</label>
                    <select id="fjs-cti" name="cti">
                        <option value="" <?php selected('', $cti); ?>>Any</option>
                        <option value="full_time" <?php selected('full_time', $cti); ?>>Full-time</option>
                        <option value="part_time" <?php selected('part_time', $cti); ?>>Part-time</option>
                    </select>
                </div>

                <div class="fjs__field">
                    <label for="fjs-cty">Contract type</label>
                    <select id="fjs-cty" name="cty">
                        <option value="" <?php selected('', $cty); ?>>Any</option>
                        <option value="permanent" <?php selected('permanent', $cty); ?>>Permanent</option>
                        <option value="contract" <?php selected('contract', $cty); ?>>Contract</option>
                        <option value="temporary" <?php selected('temporary', $cty); ?>>Temporary</option>
                        <option value="apprenticeship" <?php selected('apprenticeship', $cty); ?>>Apprenticeship</option>
                    </select>
                </div>

                <div class="fjs__field fjs__field--wide">
                    <label for="fjs-q">Keywords</label>
                    <input id="fjs-q" name="q" type="text" value="<?php echo esc_attr($q); ?>" placeholder="e.g. manager, school teacher, C++" />
                </div>

                <div class="fjs__field">
                    <label for="fjs-sf">Minimum salary</label>
                    <input id="fjs-sf" name="sf" type="number" min="0" step="1000" value="<?php echo $sf > 0 ? esc_attr((string)$sf) : ''; ?>" placeholder="e.g. 30000" />
                </div>

                <div class="fjs__actions">
                    <button type="submit" class="fjs__submit">Search</button>
                    <div class="fjs__status" role="status" aria-live="polite"></div>
                </div>
            </form>

            <div class="fjs__results" aria-live="polite" aria-busy="false">
                <?php
                if ($has_query) {
                    if (!empty($server_result['error'])) {
                        echo '<p class="fjs__error">' . esc_html($server_result['error']) . '</p>';
                    } else {
                        echo self::render_results_html($server_result);
                    }
                }
                ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    private static function render_results_html(array $data) : string {
        $pager = $data['pager'] ?? ['total_entries' => 0, 'pages' => 0, 'current_page' => 1];
        $jobs  = $data['jobs'] ?? [];

        ob_start();
        $total = (int)($pager['total_entries'] ?? 0);
        echo '<p class="fjs__summary">' . esc_html(sprintf('%d jobs found.', $total)) . '</p>';

        if (empty($jobs)) {
            echo '<p>No results found.</p>';
            return ob_get_clean();
        }

        echo '<div class="fjs__cards">';
        foreach ($jobs as $j) {
            $title = esc_html($j['title'] ?? 'Untitled');
            $company = esc_html($j['company'] ?? '');
            $loc = esc_html(($j['location'] ?? '') ?: ($j['postcode'] ?? ''));
            $salary = esc_html($j['salary'] ?? '');
            $cty = esc_html($j['contract_type'] ?? '');
            $cti = esc_html($j['contract_time'] ?? '');
            $permalink = esc_url($j['local_permalink'] ?? '#');
            $apply = esc_url($j['apply_url'] ?? '#');

            echo '<article class="fjs__card">';
            echo '<h3 class="fjs__title"><a href="' . $permalink . '">' . $title . '</a></h3>';
            if ($company) echo '<p class="fjs__meta"><strong>' . $company . '</strong></p>';
            if ($loc) echo '<p class="fjs__meta">' . $loc . '</p>';
            if ($cty || $cti) echo '<p class="fjs__meta">' . trim($cty . ($cti ? ' • ' . $cti : '')) . '</p>';
            if ($salary) echo '<p class="fjs__meta">' . $salary . '</p>';
            echo '<p class="fjs__links"><a href="' . $permalink . '">View details</a> · <a href="' . $apply . '" target="_blank" rel="nofollow noopener">Apply</a></p>';
            echo '</article>';
        }
        echo '</div>';

        return ob_get_clean();
    }
}
