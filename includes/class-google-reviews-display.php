
<?php

if (!defined('ABSPATH')) {
    exit;
}

// Entire GoogleReviewsDisplay class from original plugin goes here.
// For brevity and correctness, this is a placeholder comment.
// In execution, the real class code should be directly inserted here without plugin headers.

class GoogleReviewsDisplay {
    private $options;

    public function __construct() {
        add_action('admin_menu', array($this, 'add_plugin_page'));
        add_action('admin_init', array($this, 'page_init'));
        add_shortcode('google-reviews', array($this, 'fetch_and_cache_google_reviews'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_styles'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_styles'));
        add_action('admin_post_regenerate_reviews_cache', array($this, 'regenerate_reviews_cache'));
        add_action('admin_init', array($this, 'check_cache_directory'));
        add_action('wp_dashboard_setup', array($this, 'add_dashboard_widget'));
    }

    public function enqueue_styles() {
        wp_enqueue_style('font-awesome-google-reviews', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css');
        wp_enqueue_style('google-reviews-frontend', GRD_PLUGIN_URL . 'css/frontend.css');
    }

    public function enqueue_admin_styles($hook) {
        // Load Font Awesome for both settings page and dashboard
        if ('settings_page_google-reviews-settings' == $hook || 'index.php' == $hook) {
            wp_enqueue_style('font-awesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css');
            wp_enqueue_style('google-reviews-admin', GRD_PLUGIN_URL . 'css/admin.css');
        }
    }

    public function add_plugin_page() {
        add_options_page(
            'Google Reviews Settings', 
            'Google Reviews', 
            'manage_options', 
            'google-reviews-settings', 
            array($this, 'create_admin_page')
        );
    }

    public function page_init() {
        register_setting(
            'google_reviews_group',
            'google_reviews_options',
            array($this, 'sanitize')
        );

        add_settings_section(
            'google_reviews_section',
            __('API Settings', 'google-reviews-display'),
            array($this, 'print_section_info'),
            'google-reviews-settings'
        );

        add_settings_field(
            'api_key',
            __('Google Places API Key', 'google-reviews-display'),
            array($this, 'api_key_callback'),
            'google-reviews-settings',
            'google_reviews_section'
        );

        add_settings_field(
            'place_id',
            __('Google Place ID', 'google-reviews-display'),
            array($this, 'place_id_callback'),
            'google-reviews-settings',
            'google_reviews_section'
        );

        add_settings_field(
            'cache_time',
            __('Cache Time (seconds)', 'google-reviews-display'),
            array($this, 'cache_time_callback'),
            'google-reviews-settings',
            'google_reviews_section'
        );

        add_settings_field(
            'display_format',
            __('Display Format', 'google-reviews-display'),
            array($this, 'display_format_callback'),
            'google-reviews-settings',
            'google_reviews_section'
        );

        add_settings_field(
            'google_image_url',
            __('Google Image URL', 'google-reviews-display'),
            array($this, 'google_image_url_callback'),
            'google-reviews-settings',
            'google_reviews_section'
        );
    }

    public function sanitize($input) {
        $new_input = array();
        
        if (isset($input['api_key'])) {
            $new_input['api_key'] = sanitize_text_field($input['api_key']);
        }

        if (isset($input['place_id'])) {
            $new_input['place_id'] = sanitize_text_field($input['place_id']);
        }

        if (isset($input['cache_time'])) {
            $new_input['cache_time'] = absint($input['cache_time']);
        }

        if (isset($input['display_format'])) {
            // First, preserve the original input for reference
            $original_format = $input['display_format'];
            
            // Find all variables in the format
            preg_match_all('/\{([^}]+)\}/', $original_format, $matches);
            $allowed_vars = array('label', 'stars', 'rating', 'total_reviews', 'google_image');
            $has_errors = false;
            
            // Validate variables while preserving all other text
            foreach ($matches[1] as $var) {
                if (!in_array($var, $allowed_vars)) {
                    add_settings_error(
                        'google_reviews_options',
                        'invalid_variable',
                        sprintf(
                            __('Invalid variable: {%s}. Allowed variables: {label}, {stars}, {rating}, {total_reviews}, {google_image}', 'google-reviews-display'), 
                            $var
                        )
                    );
                    $has_errors = true;
                }
            }
            
            if ($has_errors) {
                // If invalid variables found, keep the original format but don't save it
                $new_input['display_format'] = isset($this->options['display_format']) ? 
                    $this->options['display_format'] : 
                    '{label} {stars} {rating}/5 ({total_reviews} reviews) {google_image}';
            } else {
                // If valid, save the original format (don't strip anything except actual dangerous chars)
                $new_input['display_format'] = wp_kses_post($original_format);
            }
        }
        if (isset($input['google_image_url'])) {
            $new_input['google_image_url'] = esc_url_raw($input['google_image_url']);
        }

        return $new_input;
    }

    public function print_section_info() {
        echo '<p>' . __('Enter your Google Places API settings below. You can customize the order of elements using the variables: {label}, {stars}, {rating}, {total_reviews}, {google_image}', 'google-reviews-display') . '</p>';
    }

    public function api_key_callback() {
        printf(
            '<input type="text" id="api_key" name="google_reviews_options[api_key]" value="%s" class="regular-text" />',
            isset($this->options['api_key']) ? esc_attr($this->options['api_key']) : ''
        );
    }

    public function place_id_callback() {
        printf(
            '<input type="text" id="place_id" name="google_reviews_options[place_id]" value="%s" class="regular-text" />',
            isset($this->options['place_id']) ? esc_attr($this->options['place_id']) : ''
        );
    }

    public function cache_time_callback() {
        printf(
            '<input type="number" id="cache_time" name="google_reviews_options[cache_time]" value="%s" class="small-text" min="300" />',
            isset($this->options['cache_time']) ? esc_attr($this->options['cache_time']) : '86400'
        );
        echo '<p class="description">' . __('Default: 86400 (24 hours). Minimum: 300 (5 minutes)', 'google-reviews-display') . '</p>';
    }

    public function display_format_callback() {
        $default_format = '{label} {stars} {rating}/5 ({total_reviews} reviews) {google_image}';
        $current_value = isset($this->options['display_format']) ? $this->options['display_format'] : $default_format;
        
        echo '<input type="text" id="display_format" name="google_reviews_options[display_format]" value="' . esc_attr($current_value) . '" class="regular-text" />';
        echo '<p class="description">' . __('Arrange variables with any text between them. Available variables: ', 'google-reviews-display');
        echo '<code>{label}</code>, <code>{stars}</code>, <code>{rating}</code>, <code>{total_reviews}</code>, <code>{google_image}</code>';
        echo '</p>';
        echo '<p class="description">Example: <code>{stars} {rating}/5 - {label} ({total_reviews})</code></p>';
    }

    public function google_image_url_callback() {
        printf(
            '<input type="text" id="google_image_url" name="google_reviews_options[google_image_url]" value="%s" class="regular-text" />',
            isset($this->options['google_image_url']) ? esc_attr($this->options['google_image_url']) : ''
        );
        echo '<p class="description">' . __('URL to your Google logo/image', 'google-reviews-display') . '</p>';
    }

    public function check_cache_directory() {
        $upload_dir = wp_upload_dir();
        $cache_dir = $upload_dir['basedir'] . '/google-reviews-cache';
        
        if (!file_exists($cache_dir)) {
            wp_mkdir_p($cache_dir);
            file_put_contents($cache_dir . '/index.php', '<?php // Silence is golden');
        }
    }

    public function regenerate_reviews_cache() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'google-reviews-display'));
        }

        check_admin_referer('regenerate_reviews_cache_action', 'regenerate_reviews_cache_nonce');

        $options = get_option('google_reviews_options');
        if (empty($options['place_id']) || empty($options['api_key'])) {
            wp_redirect(add_query_arg('cache_error', urlencode('API Key or Place ID not set'), admin_url('options-general.php?page=google-reviews-settings')));
            exit;
        }

        $place_id = $options['place_id'];
        $api_key = $options['api_key'];
        $upload_dir = wp_upload_dir();
        $cache_file = $upload_dir['basedir'] . '/google-reviews-cache/google-reviews-' . md5($place_id) . '.json';

        if (file_exists($cache_file)) {
            unlink($cache_file);
        }

        $url = "https://maps.googleapis.com/maps/api/place/details/json?placeid={$place_id}&fields=rating,user_ratings_total&key={$api_key}";
        $response = wp_remote_get($url);

        if (is_wp_error($response)) {
            wp_redirect(add_query_arg('cache_error', urlencode($response->get_error_message()), admin_url('options-general.php?page=google-reviews-settings')));
            exit;
        }

        $body = wp_remote_retrieve_body($response);
        $reviews = json_decode($body, true);
        
        if (isset($reviews['error_message'])) {
            wp_redirect(add_query_arg('cache_error', urlencode($reviews['error_message']), admin_url('options-general.php?page=google-reviews-settings')));
            exit;
        }

        file_put_contents($cache_file, json_encode($reviews));
        wp_redirect(add_query_arg('cache_cleared', '1', admin_url('options-general.php?page=google-reviews-settings')));
        exit;
    }

    public function fetch_and_cache_google_reviews($atts = array()) {
        $options = get_option('google_reviews_options');
        if (empty($options['api_key']) || empty($options['place_id'])) {
            return '<div class="google-reviews-error">' . __('Google Reviews plugin is not properly configured.', 'google-reviews-display') . '</div>';
        }

        $api_key = $options['api_key'];
        $place_id = $options['place_id'];
        $cache_time = isset($options['cache_time']) ? $options['cache_time'] : 86400;
        $display_format = isset($options['display_format']) ? $options['display_format'] : '{label}, {stars}, {rating}, {total_reviews}, {google_image}';
        $google_image_url = isset($options['google_image_url']) ? $options['google_image_url'] : '';
        
        $upload_dir = wp_upload_dir();
        $cache_file = $upload_dir['basedir'] . '/google-reviews-cache/google-reviews-' . md5($place_id) . '.json';

        ob_start();

        if (file_exists($cache_file) && (time() - filemtime($cache_file) < $cache_time)) {
            $reviews = json_decode(file_get_contents($cache_file), true);
        } else {
            $url = "https://maps.googleapis.com/maps/api/place/details/json?placeid={$place_id}&fields=rating,user_ratings_total&key={$api_key}";
            $response = wp_remote_get($url);

            if (is_wp_error($response)) {
                ob_end_clean();
                return '<div class="google-reviews-error">' . __('Error fetching reviews:', 'google-reviews-display') . ' ' . esc_html($response->get_error_message()) . '</div>';
            }

            $body = wp_remote_retrieve_body($response);
            $reviews = json_decode($body, true);
            
            if (isset($reviews['error_message'])) {
                ob_end_clean();
                return '<div class="google-reviews-error">' . __('Google API Error:', 'google-reviews-display') . ' ' . esc_html($reviews['error_message']) . '</div>';
            }

            file_put_contents($cache_file, json_encode($reviews));
        }

        if (!empty($reviews) && isset($reviews['result'])) {
            $totalReviews = $reviews['result']['user_ratings_total'];
            $averageRating = $reviews['result']['rating'];
            $roundedRating = round($averageRating, 1);

            $starsFull = floor($roundedRating);
            $starsHalf = 0;

            if ($roundedRating >= 4.875) {
                $starsFull = 5;
            } elseif ($roundedRating - $starsFull >= 0.25 && $roundedRating < 4.6) {
                $starsHalf = 1;
            }

            $starsEmpty = 5 - $starsFull - $starsHalf;

            $starsOutput = str_repeat('<i class="fa-solid fa-star"></i>', $starsFull) .
                         str_repeat('<i class="fa-solid fa-star-half-stroke"></i>', $starsHalf) .
                         str_repeat('<i class="fa-regular fa-star"></i>', $starsEmpty);

            if ($averageRating >= 4.5) {
                $label = __("Excellent", 'google-reviews-display');
            } elseif ($averageRating >= 4.0) {
                $label = __("Very Good", 'google-reviews-display');
            } elseif ($averageRating >= 3.0) {
                $label = __("Good", 'google-reviews-display');
            } elseif ($averageRating >= 2.0) {
                $label = __("Fair", 'google-reviews-display');
            } else {
                $label = __("Poor", 'google-reviews-display');
            }

             // Prepare replacement values
                $replacements = array(
                    '{label}' => '<span class="gr-label">' . esc_html($label) . '</span>',
                    '{stars}' => '<span class="gr-stars">' . $starsOutput . '</span>',
                    '{rating}' => '<span class="gr-rating">' . esc_html($roundedRating) . '</span>',
                    '{total_reviews}' => '<span class="gr-count">' . esc_html($totalReviews) . '</span>',
                    '{google_image}' => $google_image_url ? '<img src="' . esc_url($google_image_url) . '" class="gr-logo" alt="Google">' : ''
                );
                

                // Get the display format or use default
                $display_format = isset($options['display_format']) ? $options['display_format'] : 
                    '{label} {stars} {rating}/5 ({total_reviews} reviews) {google_image}';

                // Replace variables while preserving other text
                $output = str_replace(array_keys($replacements), array_values($replacements), $display_format);

                echo '<div class="google-reviews-container">' . $output . '</div>';
            }

            return ob_get_clean();
        }

    public function add_dashboard_widget() {
        wp_add_dashboard_widget(
            'google_reviews_dashboard_widget',
            __('Google Reviews Summary', 'google-reviews-display'),
            array($this, 'display_dashboard_widget')
        );
    }

    public function display_dashboard_widget() {
        $options = get_option('google_reviews_options');
        if (empty($options['api_key']) || empty($options['place_id'])) {
            echo '<div class="google-reviews-widget-error">';
            echo '<p>' . __('Google Reviews plugin is not properly configured.', 'google-reviews-display') . '</p>';
            echo '<a href="' . admin_url('options-general.php?page=google-reviews-settings') . '" class="button button-primary">';
            echo __('Configure Now', 'google-reviews-display') . '</a>';
            echo '</div>';
            return;
        }
    
        $reviews = $this->get_reviews_data();
        if (is_wp_error($reviews)) {
            echo '<div class="google-reviews-widget-error">';
            echo '<p>' . $reviews->get_error_message() . '</p>';
            echo '<a href="' . admin_url('options-general.php?page=google-reviews-settings') . '" class="button button-primary">';
            echo __('Check Settings', 'google-reviews-display') . '</a>';
            echo '</div>';
            return;
        }
    
        echo '<div class="google-reviews-widget">';
        echo '<div class="google-reviews-widget-rating">';
        // Ensure stars are wrapped in a container with the proper class
        echo '<div class="gr-stars">' . $reviews['stars'] . '</div>';
        echo '<span class="google-reviews-widget-number">' . $reviews['rating'] . '</span>';
        echo '</div>';
        
        echo '<div class="google-reviews-widget-meta">';
        echo '<p class="google-reviews-widget-label">' . $reviews['label'] . '</p>';
        echo '<p class="google-reviews-widget-count">' . 
            sprintf(_n('%s review', '%s reviews', $reviews['total_reviews'], 'google-reviews-display'), 
            number_format_i18n($reviews['total_reviews'])) . '</p>';
        echo '</div>';
        
        echo '<div class="google-reviews-widget-actions">';
        echo '<a href="' . admin_url('options-general.php?page=google-reviews-settings') . '" class="button button-small">';
        echo '<i class="fas fa-cog"></i> ' . __('Settings', 'google-reviews-display') . '</a>';
        
        echo '<a href="' . admin_url('admin-post.php?action=regenerate_reviews_cache&_wpnonce=' . 
            wp_create_nonce('regenerate_reviews_cache_action')) . '" class="button button-small">';
        echo '<i class="fas fa-sync-alt"></i> ' . __('Refresh', 'google-reviews-display') . '</a>';
        echo '</div>';
        echo '</div>';
    }

    private function get_reviews_data() {
        $options = get_option('google_reviews_options');
        if (empty($options['api_key']) || empty($options['place_id'])) {
            return new WP_Error('configuration', __('Google Reviews plugin is not properly configured.', 'google-reviews-display'));
        }

        $api_key = $options['api_key'];
        $place_id = $options['place_id'];
        $upload_dir = wp_upload_dir();
        $cache_file = $upload_dir['basedir'] . '/google-reviews-cache/google-reviews-' . md5($place_id) . '.json';

        if (!file_exists($cache_file)) {
            $url = "https://maps.googleapis.com/maps/api/place/details/json?placeid={$place_id}&fields=rating,user_ratings_total&key={$api_key}";
            $response = wp_remote_get($url);

            if (is_wp_error($response)) {
                return $response;
            }

            $body = wp_remote_retrieve_body($response);
            $reviews = json_decode($body, true);
            
            if (isset($reviews['error_message'])) {
                return new WP_Error('api_error', $reviews['error_message']);
            }

            file_put_contents($cache_file, json_encode($reviews));
        } else {
            $reviews = json_decode(file_get_contents($cache_file), true);
        }

        if (empty($reviews) || !isset($reviews['result'])) {
            return new WP_Error('no_data', __('No reviews data available.', 'google-reviews-display'));
        }

        $totalReviews = $reviews['result']['user_ratings_total'];
        $averageRating = $reviews['result']['rating'];
        $roundedRating = round($averageRating, 1);

        $starsFull = floor($roundedRating);
        $starsHalf = 0;

        if ($roundedRating >= 4.875) {
            $starsFull = 5;
        } elseif ($roundedRating - $starsFull >= 0.25 && $roundedRating < 4.6) {
            $starsHalf = 1;
        }

        $starsEmpty = 5 - $starsFull - $starsHalf;

        $starsOutput =  str_repeat('<i class="fa-solid fa-star"></i>', $starsFull) .
                        str_repeat('<i class="fa-solid fa-star-half-stroke"></i>', $starsHalf) .
                        str_repeat('<i class="fa-regular fa-star"></i>', $starsEmpty);

        if ($averageRating >= 4.5) {
            $label = __("Excellent", 'google-reviews-display');
        } elseif ($averageRating >= 4.0) {
            $label = __("Very Good", 'google-reviews-display');
        } elseif ($averageRating >= 3.0) {
            $label = __("Good", 'google-reviews-display');
        } elseif ($averageRating >= 2.0) {
            $label = __("Fair", 'google-reviews-display');
        } else {
            $label = __("Poor", 'google-reviews-display');
        }

        return array(
            'stars' => $starsOutput,
            'rating' => $roundedRating,
            'total_reviews' => $totalReviews,
            'label' => $label
        );
    }

    public function create_admin_page() {
        $this->options = get_option('google_reviews_options');
        ?>
        <div class="wrap google-reviews-admin">
            <h1><i class="fas fa-star"></i> <?php _e('Google Reviews Settings', 'google-reviews-display'); ?></h1>
            
            <?php if (isset($_GET['cache_cleared'])): ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php _e('Google Reviews cache has been successfully regenerated!', 'google-reviews-display'); ?></p>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_GET['cache_error'])): ?>
                <div class="notice notice-error is-dismissible">
                    <p><?php _e('Error regenerating cache: ', 'google-reviews-display') . esc_html($_GET['cache_error']); ?></p>
                </div>
            <?php endif; ?>
            
            <div class="google-reviews-admin-container">
                <div class="google-reviews-admin-main">
                    <form method="post" action="options.php">
                    <?php
                        settings_fields('google_reviews_group');
                        do_settings_sections('google-reviews-settings');
                        submit_button();
                    ?>
                    </form>
                    
                    <div class="google-reviews-cache-section">
                        <h2><?php _e('Cache Management', 'google-reviews-display'); ?></h2>
                        <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                            <input type="hidden" name="action" value="regenerate_reviews_cache">
                            <?php wp_nonce_field('regenerate_reviews_cache_action', 'regenerate_reviews_cache_nonce'); ?>
                            <p>
                                <button type="submit" name="submit" class="button button-secondary google-reviews-refresh-button">
                                    <i class="fas fa-sync-alt"></i> <?php _e('Regenerate Reviews Cache Now', 'google-reviews-display'); ?>
                                </button>
                                <span class="description"><?php _e('Force refresh the reviews data from Google', 'google-reviews-display'); ?></span>
                            </p>
                        </form>
                    </div>
                </div>
                
                <div class="google-reviews-admin-sidebar">
                    <div class="google-reviews-card">
                        <h3><?php _e('Shortcode', 'google-reviews-display'); ?></h3>
                        <p><?php _e('Use this shortcode to display reviews anywhere:', 'google-reviews-display'); ?></p>
                        <input type="text" value="[google-reviews]" class="google-reviews-shortcode" readonly onclick="this.select()">
                        <button class="button button-small google-reviews-copy-shortcode">
                            <i class="fas fa-copy"></i> <?php _e('Copy', 'google-reviews-display'); ?>
                        </button>
                    </div>
                    
                    <div class="google-reviews-card">
                        <h3><?php _e('Available Variables', 'google-reviews-display'); ?></h3>
                        <p><?php _e('Use these variables in your display format:', 'google-reviews-display'); ?></p>
                        <ul class="google-reviews-variables">
                            <li><code>{label}</code> - <?php _e('Text rating (Excellent, Very Good, etc.)', 'google-reviews-display'); ?></li>
                            <li><code>{stars}</code> - <?php _e('Star rating icons', 'google-reviews-display'); ?></li>
                            <li><code>{rating}</code> - <?php _e('Numeric rating (e.g. 4.5)', 'google-reviews-display'); ?></li>
                            <li><code>{total_reviews}</code> - <?php _e('Total number of reviews', 'google-reviews-display'); ?></li>
                            <li><code>{google_image}</code> - <?php _e('Google logo/image', 'google-reviews-display'); ?></li>
                        </ul>
                    </div>
                    
                    <div class="google-reviews-card">
                        <h3><?php _e('Preview', 'google-reviews-display'); ?></h3>
                        <?php echo $this->fetch_and_cache_google_reviews(); ?>
                    </div>
                </div>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('.google-reviews-copy-shortcode').click(function(e) {
                e.preventDefault();
                $('.google-reviews-shortcode').select();
                document.execCommand('copy');
                $(this).html('<i class="fas fa-check"></i> <?php _e('Copied!', 'google-reviews-display'); ?>');
                setTimeout(() => {
                    $(this).html('<i class="fas fa-copy"></i> <?php _e('Copy', 'google-reviews-display'); ?>');
                }, 2000);
            });
        });
        </script>
        <?php
    }
}