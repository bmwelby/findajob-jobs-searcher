<?php
if (!defined('ABSPATH')) exit;

final class FJS_Settings {
    const OPT_KEY = 'fjs_options';

    public static function init() : void {
        add_action('admin_menu', [__CLASS__, 'add_settings_page']);
        add_action('admin_init', [__CLASS__, 'register_settings']);
        add_action('admin_post_fjs_create_results_page', [__CLASS__, 'create_results_page']);
    }

    public static function create_results_page() : void {
        if (!current_user_can('manage_options')) wp_die('Unauthorized');
        check_admin_referer('fjs_create_results_page');

        $page_id = wp_insert_post([
            'post_title'   => __('Find a Job Results', 'findajob-jobs-searcher'),
            'post_content' => '[findajob_search]',
            'post_status'  => 'publish',
            'post_type'    => 'page',
        ]);

        wp_redirect(add_query_arg('fjs_page_created', $page_id, menu_page_url('fjs-settings', false)));
        exit;
    }

    public static function defaults() : array {
        return [
            'api_base' => 'https://findajob.dwp.gov.uk',
            'api_id'   => '',
            'api_key'  => '',
            'per_page' => 10,
            'cache_ttl_seconds' => 600, // 10 minutes

            // Upstream quotas:
            // 45 requests per 15 seconds; 600 per 10 minutes
            'rate_limit_burst_max' => 45,
            'rate_limit_burst_window_seconds' => 15,
            'rate_limit_long_max' => 600,
            'rate_limit_long_window_seconds' => 600,

            // Expiry behaviour
            'expire_to_draft' => 1,
            'purge_after_days' => 30,  // 0 disables trashing
        ];
    }

    public static function get() : array {
        $opts = get_option(self::OPT_KEY, []);
        return array_merge(self::defaults(), is_array($opts) ? $opts : []);
    }

    public static function add_settings_page() : void {
        add_options_page(
            __('Find a Job – Jobs Searcher', 'findajob-jobs-searcher'),
            __('Jobs Searcher', 'findajob-jobs-searcher'),
            'manage_options',
            'fjs-settings',
            [__CLASS__, 'render_settings_page']
        );
    }

    public static function register_settings() : void {
        register_setting(self::OPT_KEY, self::OPT_KEY, [
            'type' => 'array',
            'sanitize_callback' => [__CLASS__, 'sanitize'],
            'default' => self::defaults(),
        ]);

        add_settings_section('fjs_api', __('API settings', 'findajob-jobs-searcher'), function () {
            echo '<p>' . esc_html__('Configure your Find a job API credentials and behaviour.', 'findajob-jobs-searcher') . '</p>';
        }, 'fjs-settings');

        self::add_field('api_base', 'API base URL', 'url', 'https://findajob.dwp.gov.uk');
        self::add_field('api_id', 'API ID', 'text', '');
        self::add_field('api_key', 'API key', 'text', '');

        add_settings_field('per_page', __('Results per page', 'findajob-jobs-searcher'), function () {
            $o = self::get();
            printf(
                '<input type="number" min="1" max="50" name="%1$s[per_page]" value="%2$d" class="small-text" />',
                esc_attr(self::OPT_KEY),
                (int)$o['per_page']
            );
        }, 'fjs-settings', 'fjs_api');

        add_settings_field('cache_ttl_seconds', __('Cache TTL (seconds)', 'findajob-jobs-searcher'), function () {
            $o = self::get();
            printf(
                '<input type="number" min="0" max="86400" name="%1$s[cache_ttl_seconds]" value="%2$d" class="small-text" /> <span class="description">%3$s</span>',
                esc_attr(self::OPT_KEY),
                (int)$o['cache_ttl_seconds'],
                esc_html__('0 disables caching. 600 is a good default.', 'findajob-jobs-searcher')
            );
        }, 'fjs-settings', 'fjs_api');

        add_settings_field('rate_limits', __('Rate limits (upstream quota)', 'findajob-jobs-searcher'), function () {
            $o = self::get();

            echo '<p class="description">' . esc_html__('Defaults match Find a job: 45 requests/15s and 600 requests/10m.', 'findajob-jobs-searcher') . '</p>';

            printf(
                '<p><label>%s <input type="number" min="1" max="5000" name="%s[rate_limit_burst_max]" value="%d" class="small-text" /></label> ',
                esc_html__('Burst max', 'findajob-jobs-searcher'),
                esc_attr(self::OPT_KEY),
                (int)$o['rate_limit_burst_max']
            );
            printf(
                '<label>%s <input type="number" min="1" max="600" name="%s[rate_limit_burst_window_seconds]" value="%d" class="small-text" /></label></p>',
                esc_html__('per (seconds)', 'findajob-jobs-searcher'),
                esc_attr(self::OPT_KEY),
                (int)$o['rate_limit_burst_window_seconds']
            );

            printf(
                '<p><label>%s <input type="number" min="1" max="50000" name="%s[rate_limit_long_max]" value="%d" class="small-text" /></label> ',
                esc_html__('Long max', 'findajob-jobs-searcher'),
                esc_attr(self::OPT_KEY),
                (int)$o['rate_limit_long_max']
            );
            printf(
                '<label>%s <input type="number" min="10" max="86400" name="%s[rate_limit_long_window_seconds]" value="%d" class="small-text" /></label></p>',
                esc_html__('per (seconds)', 'findajob-jobs-searcher'),
                esc_attr(self::OPT_KEY),
                (int)$o['rate_limit_long_window_seconds']
            );
        }, 'fjs-settings', 'fjs_api');

        add_settings_field('expiry', __('Expiry handling', 'findajob-jobs-searcher'), function () {
            $o = self::get();
            printf(
                '<label><input type="checkbox" name="%s[expire_to_draft]" value="1" %s /> %s</label><br/>',
                esc_attr(self::OPT_KEY),
                checked(1, (int)$o['expire_to_draft'], false),
                esc_html__('Move expired jobs to Draft daily (based on closing date).', 'findajob-jobs-searcher')
            );
            printf(
                '<label>%s <input type="number" min="0" max="3650" name="%s[purge_after_days]" value="%d" class="small-text" /></label> <span class="description">%s</span>',
                esc_html__('Trash expired jobs after (days):', 'findajob-jobs-searcher'),
                esc_attr(self::OPT_KEY),
                (int)$o['purge_after_days'],
                esc_html__('0 disables trashing.', 'findajob-jobs-searcher')
            );
        }, 'fjs-settings', 'fjs_api');
    }

    private static function add_field(string $key, string $label, string $type, string $placeholder) : void {
        add_settings_field($key, __($label, 'findajob-jobs-searcher'), function () use ($key, $type, $placeholder) {
            $o = self::get();
            printf(
                '<input type="%4$s" name="%1$s[%2$s]" value="%3$s" class="regular-text" placeholder="%5$s" autocomplete="off" />',
                esc_attr(self::OPT_KEY),
                esc_attr($key),
                esc_attr((string)($o[$key] ?? '')),
                esc_attr($type),
                esc_attr($placeholder)
            );
        }, 'fjs-settings', 'fjs_api');
    }

    public static function sanitize($input) : array {
        $d = self::defaults();
        $out = [];

        $out['api_base'] = isset($input['api_base']) ? esc_url_raw(trim((string)$input['api_base'])) : $d['api_base'];
        $out['api_id']   = isset($input['api_id']) ? sanitize_text_field((string)$input['api_id']) : '';
        $out['api_key']  = isset($input['api_key']) ? sanitize_text_field((string)$input['api_key']) : '';

        $out['per_page'] = isset($input['per_page']) ? max(1, min(50, (int)$input['per_page'])) : $d['per_page'];
        $out['cache_ttl_seconds'] = isset($input['cache_ttl_seconds']) ? max(0, (int)$input['cache_ttl_seconds']) : $d['cache_ttl_seconds'];

        $out['rate_limit_burst_max'] = isset($input['rate_limit_burst_max']) ? max(1, min(5000, (int)$input['rate_limit_burst_max'])) : $d['rate_limit_burst_max'];
        $out['rate_limit_burst_window_seconds'] = isset($input['rate_limit_burst_window_seconds']) ? max(1, min(600, (int)$input['rate_limit_burst_window_seconds'])) : $d['rate_limit_burst_window_seconds'];

        $out['rate_limit_long_max'] = isset($input['rate_limit_long_max']) ? max(1, min(50000, (int)$input['rate_limit_long_max'])) : $d['rate_limit_long_max'];
        $out['rate_limit_long_window_seconds'] = isset($input['rate_limit_long_window_seconds']) ? max(10, min(86400, (int)$input['rate_limit_long_window_seconds'])) : $d['rate_limit_long_window_seconds'];

        $out['expire_to_draft'] = !empty($input['expire_to_draft']) ? 1 : 0;
        $out['purge_after_days'] = isset($input['purge_after_days']) ? max(0, min(3650, (int)$input['purge_after_days'])) : $d['purge_after_days'];

        return $out;
    }

    public static function render_settings_page() : void {
        if (!current_user_can('manage_options')) return;

        if (isset($_GET['fjs_page_created'])) {
            $created_id = (int)$_GET['fjs_page_created'];
            $created_url = get_permalink($created_id);
            echo '<div class="notice notice-success is-dismissible"><p>' . sprintf(
                esc_html__('Results page created successfully! URL: %s', 'findajob-jobs-searcher'),
                '<a href="' . esc_url($created_url) . '" target="_blank">' . esc_html($created_url) . '</a>'
            ) . '</p></div>';
        }
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Find a Job – Jobs Searcher', 'findajob-jobs-searcher'); ?></h1>

            <div class="card" style="max-width: 800px; padding: 20px; margin-bottom: 20px;">
                <h3><?php echo esc_html__('Quick Setup', 'findajob-jobs-searcher'); ?></h3>
                <p><?php echo esc_html__('Need a page to display search results? Click the button below to automatically create a "Find a Job Results" page with the shortcode pre-installed.', 'findajob-jobs-searcher'); ?></p>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="fjs_create_results_page" />
                    <?php wp_nonce_field('fjs_create_results_page'); ?>
                    <?php submit_button(__('Create Results Page', 'findajob-jobs-searcher'), 'secondary', 'submit', false); ?>
                </form>
            </div>

            <form method="post" action="options.php">
                <?php
                settings_fields(self::OPT_KEY);
                do_settings_sections('fjs-settings');
                submit_button();
                ?>
            </form>
            <hr />

            <h2><?php echo esc_html__('Shortcode Generator', 'findajob-jobs-searcher'); ?></h2>
            <div class="card" style="max-width: 800px; padding: 20px;">
                <p class="description"><?php echo esc_html__('Use this tool to generate a custom shortcode for your pages.', 'findajob-jobs-searcher'); ?></p>

                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="fjs-gen-cat"><?php echo esc_html__('Default Category', 'findajob-jobs-searcher'); ?></label></th>
                        <td>
                            <select id="fjs-gen-cat" class="regular-text">
                                <option value=""><?php echo esc_html__('None (User Selectable)', 'findajob-jobs-searcher'); ?></option>
                                <?php foreach (FJS_API::get_categories() as $id => $name): ?>
                                    <option value="<?php echo esc_attr((string)$id); ?>"><?php echo esc_html($name); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description"><?php echo esc_html__('If selected, the search will default to this category.', 'findajob-jobs-searcher'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__('Visible Fields', 'findajob-jobs-searcher'); ?></th>
                        <td>
                            <fieldset id="fjs-gen-fields">
                                <?php
                                $fields = [
                                    'q' => 'Keywords',
                                    'w' => 'Location',
                                    'd' => 'Radius',
                                    'cat' => 'Category',
                                    'cti' => 'Hours',
                                    'cty' => 'Contract Type',
                                    'sf' => 'Min Salary'
                                ];
                                foreach ($fields as $k => $label) {
                                    printf(
                                        '<label style="margin-right: 15px;"><input type="checkbox" value="%s" checked /> %s</label>',
                                        esc_attr($k),
                                        esc_html($label)
                                    );
                                }
                                ?>
                            </fieldset>
                            <p class="description"><?php echo esc_html__('Uncheck fields to hide them (they will use default values or be empty).', 'findajob-jobs-searcher'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="fjs-gen-page"><?php echo esc_html__('Target Page', 'findajob-jobs-searcher'); ?></label></th>
                        <td>
                            <select id="fjs-gen-page" class="regular-text">
                                <option value=""><?php echo esc_html__('Select a page...', 'findajob-jobs-searcher'); ?></option>
                                <?php foreach (get_pages() as $page): ?>
                                    <option value="<?php echo esc_url(get_permalink($page->ID)); ?>"><?php echo esc_html($page->post_title); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description"><?php echo esc_html__('Select an existing page to automatically fill the Target URL.', 'findajob-jobs-searcher'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="fjs-gen-url"><?php echo esc_html__('Target URL', 'findajob-jobs-searcher'); ?></label></th>
                        <td>
                            <input type="text" id="fjs-gen-url" class="regular-text" placeholder="/jobs-results" />
                            <p class="description"><?php echo esc_html__('Leave empty to show results on the same page.', 'findajob-jobs-searcher'); ?></p>
                        </td>
                    </tr>
                </table>

                <h3><?php echo esc_html__('Your Shortcode', 'findajob-jobs-searcher'); ?></h3>
                <code id="fjs-gen-output" style="display: block; padding: 10px; background: #f0f0f1; font-size: 1.2em;">[findajob_search]</code>
            </div>

            <script>
            (function() {
                const cat = document.getElementById('fjs-gen-cat');
                const page = document.getElementById('fjs-gen-page');
                const url = document.getElementById('fjs-gen-url');
                const output = document.getElementById('fjs-gen-output');
                const fields = document.querySelectorAll('#fjs-gen-fields input');

                function update() {
                    let parts = ['findajob_search'];

                    if (cat.value) {
                        parts.push('cat="' + cat.value + '"');
                    }

                    if (url.value.trim()) {
                        parts.push('url="' + url.value.trim() + '"');
                    }

                    let visible = [];
                    let allChecked = true;
                    fields.forEach(f => {
                        if (f.checked) visible.push(f.value);
                        else allChecked = false;
                    });

                    if (!allChecked) {
                        parts.push('fields="' + visible.join(',') + '"');
                    }

                    output.innerText = '[' + parts.join(' ') + ']';
                }

                cat.addEventListener('change', update);
                url.addEventListener('input', update);

                page.addEventListener('change', function() {
                    if (this.value) {
                        url.value = this.value;
                        update();
                    }
                });

                fields.forEach(f => f.addEventListener('change', update));
            })();
            </script>
        </div>
        <?php
    }
}
