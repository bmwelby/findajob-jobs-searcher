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

        // Parse attributes
        $a = shortcode_atts([
            'cat' => '',       // Default category ID
            'fields' => '',    // Comma-separated visible fields (empty = all)
            'url' => '',       // Target URL
            'action' => '',    // Alias for url
        ], $atts);

        $target_url = $a['url'] ?: $a['action'];

        // Determine visible fields
        $all_fields = ['q', 'w', 'd', 'cat', 'cti', 'cty', 'sf'];
        if ($a['fields']) {
            $visible = array_map('trim', explode(',', $a['fields']));
            $show = array_fill_keys($visible, true);
        } else {
            $show = array_fill_keys($all_fields, true);
        }

        // Get Current Values
        $q_val = isset($_GET['q']) ? sanitize_text_field((string)$_GET['q']) : '';
        $w_val = isset($_GET['w']) ? sanitize_text_field((string)$_GET['w']) : '';
        $d_val = isset($_GET['d']) ? sanitize_text_field((string)$_GET['d']) : '5';
        $cti_val = isset($_GET['cti']) ? sanitize_text_field((string)$_GET['cti']) : '';
        $cty_val = isset($_GET['cty']) ? sanitize_text_field((string)$_GET['cty']) : '';
        $sf_val = isset($_GET['sf']) ? (int)$_GET['sf'] : 0;
        $p_val = isset($_GET['p']) ? max(1, (int)$_GET['p']) : 1;

        // Category Logic
        $cat_attr = (int)$a['cat'];
        $cat_get = isset($_GET['cat']) ? (int)$_GET['cat'] : 0;

        if (empty($show['cat'])) {
            // If hidden, attribute strictly overrides GET (unless attr is empty, then keep GET?)
            // Usually if hidden, we want to force the context.
            $cat_val = $cat_attr > 0 ? $cat_attr : ($cat_get > 0 ? $cat_get : 0);
        } else {
            // If visible, GET overrides attribute
            $cat_val = $cat_get > 0 ? $cat_get : ($cat_attr > 0 ? $cat_attr : 0);
        }

        // Check if we should run a search
        // We search if:
        // 1. We are NOT submitting to a remote URL (local search)
        // 2. We have at least one search parameter OR we just want to list the category
        $is_remote = !empty($target_url);
        $has_params = ($w_val !== '' || $q_val !== '' || $cat_val > 0);

        $server_result = null;

        if (!$is_remote && $has_params) {
            $params = [
                'q' => $q_val,
                'w' => $w_val,
                'd' => $d_val,
                'cti' => $cti_val,
                'cty' => $cty_val,
                'sf' => $sf_val > 0 ? $sf_val : null,
                'cat' => $cat_val > 0 ? $cat_val : null,
                'p' => $p_val,
            ];

            $params = array_filter($params, function ($v) {
                return $v !== '' && $v !== null;
            });

            $server_result = FJS_API::search($params);
        }

        // Categories list for dropdown
        $categories = FJS_API::get_categories();

        ob_start();
        ?>
        <div class="fjs">
            <form class="fjs__form" method="get" action="<?php echo esc_url($target_url); ?>">

                <?php if (!empty($show['w'])): ?>
                <div class="fjs__field">
                    <label for="fjs-w"><?php echo esc_html__('Location', 'findajob-jobs-searcher'); ?></label>
                    <input id="fjs-w" name="w" type="text" required value="<?php echo esc_attr($w_val); ?>" placeholder="<?php echo esc_attr__('e.g. London, Scotland, SW1A, E1W', 'findajob-jobs-searcher'); ?>" />
                </div>
                <?php elseif ($w_val): ?>
                    <input type="hidden" name="w" value="<?php echo esc_attr($w_val); ?>" />
                <?php endif; ?>

                <?php if (!empty($show['d'])): ?>
                <div class="fjs__field">
                    <label for="fjs-d"><?php echo esc_html__('Radius (miles)', 'findajob-jobs-searcher'); ?></label>
                    <input id="fjs-d" name="d" type="number" min="0.5" step="0.5" value="<?php echo esc_attr($d_val); ?>" />
                </div>
                <?php elseif ($d_val): ?>
                    <input type="hidden" name="d" value="<?php echo esc_attr($d_val); ?>" />
                <?php endif; ?>

                <?php if (!empty($show['cat'])): ?>
                <div class="fjs__field">
                    <label for="fjs-cat"><?php echo esc_html__('Category', 'findajob-jobs-searcher'); ?></label>
                    <select id="fjs-cat" name="cat">
                        <option value=""><?php echo esc_html__('Any', 'findajob-jobs-searcher'); ?></option>
                        <?php foreach ($categories as $id => $name): ?>
                            <option value="<?php echo esc_attr((string)$id); ?>" <?php selected($id, $cat_val); ?>><?php echo esc_html($name); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php elseif ($cat_val > 0): ?>
                    <input type="hidden" name="cat" value="<?php echo esc_attr((string)$cat_val); ?>" />
                <?php endif; ?>

                <?php if (!empty($show['cti'])): ?>
                <div class="fjs__field">
                    <label for="fjs-cti"><?php echo esc_html__('Required hours', 'findajob-jobs-searcher'); ?></label>
                    <select id="fjs-cti" name="cti">
                        <option value="" <?php selected('', $cti_val); ?>><?php echo esc_html__('Any', 'findajob-jobs-searcher'); ?></option>
                        <option value="full_time" <?php selected('full_time', $cti_val); ?>><?php echo esc_html__('Full-time', 'findajob-jobs-searcher'); ?></option>
                        <option value="part_time" <?php selected('part_time', $cti_val); ?>><?php echo esc_html__('Part-time', 'findajob-jobs-searcher'); ?></option>
                    </select>
                </div>
                <?php elseif ($cti_val): ?>
                    <input type="hidden" name="cti" value="<?php echo esc_attr($cti_val); ?>" />
                <?php endif; ?>

                <?php if (!empty($show['cty'])): ?>
                <div class="fjs__field">
                    <label for="fjs-cty"><?php echo esc_html__('Contract type', 'findajob-jobs-searcher'); ?></label>
                    <select id="fjs-cty" name="cty">
                        <option value="" <?php selected('', $cty_val); ?>><?php echo esc_html__('Any', 'findajob-jobs-searcher'); ?></option>
                        <option value="permanent" <?php selected('permanent', $cty_val); ?>><?php echo esc_html__('Permanent', 'findajob-jobs-searcher'); ?></option>
                        <option value="contract" <?php selected('contract', $cty_val); ?>><?php echo esc_html__('Contract', 'findajob-jobs-searcher'); ?></option>
                        <option value="temporary" <?php selected('temporary', $cty_val); ?>><?php echo esc_html__('Temporary', 'findajob-jobs-searcher'); ?></option>
                        <option value="apprenticeship" <?php selected('apprenticeship', $cty_val); ?>><?php echo esc_html__('Apprenticeship', 'findajob-jobs-searcher'); ?></option>
                    </select>
                </div>
                <?php elseif ($cty_val): ?>
                    <input type="hidden" name="cty" value="<?php echo esc_attr($cty_val); ?>" />
                <?php endif; ?>

                <?php if (!empty($show['q'])): ?>
                <div class="fjs__field fjs__field--wide">
                    <label for="fjs-q"><?php echo esc_html__('Keywords', 'findajob-jobs-searcher'); ?></label>
                    <input id="fjs-q" name="q" type="text" value="<?php echo esc_attr($q_val); ?>" placeholder="<?php echo esc_attr__('e.g. manager, school teacher, C++', 'findajob-jobs-searcher'); ?>" />
                </div>
                <?php elseif ($q_val): ?>
                    <input type="hidden" name="q" value="<?php echo esc_attr($q_val); ?>" />
                <?php endif; ?>

                <?php if (!empty($show['sf'])): ?>
                <div class="fjs__field">
                    <label for="fjs-sf"><?php echo esc_html__('Minimum salary', 'findajob-jobs-searcher'); ?></label>
                    <input id="fjs-sf" name="sf" type="number" min="0" step="1000" value="<?php echo $sf_val > 0 ? esc_attr((string)$sf_val) : ''; ?>" placeholder="<?php echo esc_attr__('e.g. 30000', 'findajob-jobs-searcher'); ?>" />
                </div>
                <?php elseif ($sf_val > 0): ?>
                    <input type="hidden" name="sf" value="<?php echo esc_attr((string)$sf_val); ?>" />
                <?php endif; ?>

                <div class="fjs__actions">
                    <button type="submit" class="fjs__submit"><?php echo esc_html__('Search', 'findajob-jobs-searcher'); ?></button>
                    <div class="fjs__status" role="status" aria-live="polite"></div>
                </div>
            </form>

            <div class="fjs__results" aria-live="polite" aria-busy="false">
                <?php
                if (!$is_remote && $has_params) {
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
        echo '<p class="fjs__summary">' . esc_html(sprintf(__('%d jobs found.', 'findajob-jobs-searcher'), $total)) . '</p>';

        if (empty($jobs)) {
            echo '<p>' . esc_html__('No results found.', 'findajob-jobs-searcher') . '</p>';
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
            echo '<p class="fjs__links"><a href="' . $permalink . '">' . esc_html__('View details', 'findajob-jobs-searcher') . '</a> · <a href="' . $apply . '" target="_blank" rel="nofollow noopener">' . esc_html__('Apply', 'findajob-jobs-searcher') . '</a></p>';
            echo '</article>';
        }
        echo '</div>';

        return ob_get_clean();
    }
}
