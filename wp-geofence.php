<?php
/*
Plugin Name: WP Geofence
Description: Shows or hides content based on visitor geolocation. Supports shortcode and Gutenberg block with map editor, lazy-load, per-shortcode intelligent cache and translations.
Version: 1.0.9
Author: Alex Meusburger
Text Domain: wp-geofence
Domain Path: /languages
*/

if ( ! defined( 'ABSPATH' ) ) {
    exit();
}

if ( ! class_exists( 'WP_Geofence_Plugin' ) ) {

    class WP_Geofence_Plugin {

        private static $instance = null;
        private $defaults = array();
        private $plugin_url;
        private $plugin_dir;

        public static function instance() {
            if ( self::$instance === null ) {
                self::$instance = new self();
            }
            return self::$instance;
        }

        private function __construct() {
            $this->plugin_dir = plugin_dir_path(__FILE__);
            $this->plugin_url = plugin_dir_url(__FILE__);

            $this->defaults = array(
                'areas' => 'lat:-23.561455617058257; lng:-46.65596816474887; radius:50',
                'msg_out' => 'Access denied: you are outside the authorized area.',
                'msg_error' => 'Error obtaining your location.',
                'redirect' => '',
                'accuracy' => '0',
                'strict' => 'yes',
                'lazyload' => 'no',
                'cache' => 'no',
                'cache_ttl' => '60',
                'cache_accuracy' => '50'
            );

            add_action('plugins_loaded', array($this, 'load_textdomain'));
            add_action('admin_menu', array($this, 'admin_menu'));
            add_action('admin_init', array($this, 'register_settings'));
            add_action('admin_init', array($this, 'handle_restore_defaults'));
            add_action('admin_enqueue_scripts', array($this, 'admin_assets'));
            add_action('wp_enqueue_scripts', array($this, 'frontend_assets'));
            add_action('init', array($this, 'register_block'));
            add_shortcode('geofence', array($this, 'shortcode'));
        }

        public function load_textdomain() {
            load_plugin_textdomain('wp-geofence', false, dirname(plugin_basename(__FILE__)) . '/languages/');
        }

        public function handle_restore_defaults() {
            if (
                isset($_POST['wp_geofence_restore']) &&
                isset($_POST['wp_geofence_confirm_restore']) &&
                check_admin_referer('wp_geofence_restore_defaults_nonce')
            ) {
                delete_option('wp_geofence_settings');

                $redirect_url = add_query_arg(
                    array(
                        'page' => 'wp-geofence', 
                        'tab' => 'restore', 
                        'settings-updated' => 'restored' 
                    ), 
                    admin_url('options-general.php')
                );

                wp_safe_redirect($redirect_url);
                exit();
            }
        }

        public function register_settings() {
            register_setting('wp_geofence_settings_group','wp_geofence_settings', array($this,'sanitize_settings'));
        }

        public function sanitize_settings($input) {
            $clean = array();
            $raw = is_array($input) ? $input : array();
            
            foreach ($this->defaults as $key => $default) {
                
                if (!isset($raw[$key])) {
                    $clean[$key] = $default;
                    continue;
                }

                $val = $raw[$key];


                if ($key === 'cache_ttl') {
                    $v = intval($val);
                    if ($v < 30 || $v > 240) {
                        $v = intval($default);
                    }
                    $clean[$key] = (string)$v;
                    continue;
                }
                
                if ($key === 'cache_accuracy') {
                    $allowed = array('10','25','50','100','200');
                    $clean[$key] = in_array($val, $allowed) ? $val : $default;
                    continue;
                }
                
                if ($key === 'redirect') {
                    $sanitized_url = esc_url_raw($val);
                    
                    if ($sanitized_url === '' && $val !== '') {
                        $clean[$key] = $default; 
                    } else {
                        $clean[$key] = $sanitized_url; 
                    }
                    continue;
                }
                
                if (in_array($key, array('cache','lazyload','strict'))) {
                    $clean[$key] = ($val === 'yes') ? 'yes' : 'no';
                    continue;
                }

                $clean[$key] = sanitize_text_field($val);
                
                if ($clean[$key] === '') {
                    if (in_array($key, array('areas', 'msg_out', 'msg_error'))) {
                        $clean[$key] = $default;
                    }
                }
                
                if ($key === 'accuracy') {
                    $clean[$key] = (string)intval($clean[$key]);
                }
            }
            return $clean;
        }

        public function admin_menu() {
            add_options_page(__('WP Geofence','wp-geofence'), __('WP Geofence','wp-geofence'), 'manage_options', 'wp-geofence', array($this,'settings_page'));
        }

        public function admin_assets($hook) {
            if ($hook!=='settings_page_wp-geofence') return;
            if (file_exists($this->plugin_dir.'assets/css/admin.min.css')) wp_enqueue_style('wpgeofence-admin',$this->plugin_url.'assets/css/admin.min.css', array(), null);
            if (file_exists($this->plugin_dir.'assets/js/admin.min.js')) wp_enqueue_script('wpgeofence-admin',$this->plugin_url.'assets/js/admin.min.js', array('jquery'), null, true);
        }

        public function frontend_assets() {
            $min = $this->plugin_dir.'assets/js/wp-geofence-frontend.min.js';
            $src = $this->plugin_dir.'assets/js/wp-geofence-frontend.js';
            
            if (file_exists($min)) { 
                wp_register_script('wpgeofence-frontend', $this->plugin_url.'assets/js/wp-geofence-frontend.min.js', array(), null, true); 
            } elseif (file_exists($src)) { 
                wp_register_script('wpgeofence-frontend', $this->plugin_url.'assets/js/wp-geofence-frontend.js', array(), null, true); 
            } else {
                return;
            }

            $strings = array(
                'no_support' => __('Your browser does not support geolocation.','wp-geofence'),
                'precision_insufficient' => __('Precision insufficient:','wp-geofence'),
                'cache_invalidated' => __('Position updated — access revoked.','wp-geofence')
            );
            wp_localize_script('wpgeofence-frontend','wpGeofenceL10n',$strings);
        }

        public function register_block() {
            if (! function_exists('register_block_type')) return;

            $leaflet_js = $this->plugin_dir.'assets/leaflet/leaflet.js';
            $leaflet_css = $this->plugin_dir.'assets/leaflet/leaflet.css';
            if (file_exists($leaflet_js)) wp_register_script('leaflet',$this->plugin_url.'assets/leaflet/leaflet.js', array(), null, true);
            if (file_exists($leaflet_css)) wp_register_style('leaflet-css',$this->plugin_url.'assets/leaflet/leaflet.css', array(), null);

            $block_min = $this->plugin_dir.'assets/js/wp-geofence-block.min.js';
            $block_src = $this->plugin_dir.'assets/js/wp-geofence-block.js';
            $block_css_min = $this->plugin_dir.'assets/css/block-editor.min.css';
            $block_css_src = $this->plugin_dir.'assets/css/block-editor.css';

            $deps = array('wp-blocks','wp-element','wp-i18n');
            if (file_exists($block_min) || file_exists($block_src)) {
                $url = file_exists($block_min) ? $this->plugin_url.'assets/js/wp-geofence-block.min.js' : $this->plugin_url.'assets/js/wp-geofence-block.js';
                wp_register_script('wpgeofence-block-editor',$url,$deps,null,true);

                if (file_exists($block_css_min)) wp_register_style('wpgeofence-block-editor-css',$this->plugin_url.'assets/css/block-editor.min.css', array(), null);
                elseif (file_exists($block_css_src)) wp_register_style('wpgeofence-block-editor-css',$this->plugin_url.'assets/css/block-editor.css', array(), null);
                else wp_register_style('wpgeofence-block-editor-css', false);

                register_block_type('wp-geofence/block', array(
                    'editor_script' => 'wpgeofence-block-editor',
                    'editor_style' => 'wpgeofence-block-editor-css',
                    'render_callback' => array($this,'render_block_callback'),
                ));
            }
        }

        private function parse_areas_string($str) {
            $areas = array();
            $s = trim((string)$str);
            if ($s === '') return $areas;

            $pattern = '/lat\s*:\s*([\-0-9.]+)\s*;\s*lng\s*:\s*([\-0-9.]+)\s*;\s*radius\s*:\s*([0-9.]+)\s*/i';
            if (preg_match_all($pattern, $s, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $m) {
                    $lat = floatval($m[1]);
                    $lng = floatval($m[2]);
                    $r = floatval($m[3]);
                    if ($r > 0) $areas[] = array('lat'=>$lat,'lng'=>$lng,'radius'=>$r);
                }
                return $areas;
            }

            $parts = preg_split('/\s*\|\s*|\r?\n/', $s);
            $clean_parts = array();
            foreach ($parts as $p) {
                $t = trim($p);
                if ($t === '') continue;
                if (substr_count($t,',') >= 2) {
                    $pbits = array_map('trim', explode(',', $t));
                    if (count($pbits) >= 3) {
                        $lat = floatval($pbits[0]);
                        $lng = floatval($pbits[1]);
                        $r = floatval($pbits[2]);
                        if ($r>0) $areas[] = array('lat'=>$lat,'lng'=>$lng,'radius'=>$r);
                        continue;
                    }
                }
                $clean_parts[] = $t;
            }
            if (!empty($areas)) return $areas;

            if (!empty($clean_parts)) {
                $joined = implode(' ; ', $clean_parts);
            } else {
                $joined = $s;
            }

            preg_match_all('/[-+]?\d*\.?\d+/', $joined, $num_matches);
            $nums = $num_matches[0];
            if (count($nums) % 3 === 0) {
                for ($i=0;$i < count($nums); $i+=3) {
                    $lat = floatval($nums[$i]);
                    $lng = floatval($nums[$i+1]);
                    $r = floatval($nums[$i+2]);
                    if ($r>0) $areas[] = array('lat'=>$lat,'lng'=>$lng,'radius'=>$r);
                }
                return $areas;
            }

            return $areas;
        }

        public function render_block_callback($attributes,$content) {
            wp_enqueue_script('wpgeofence-frontend');
            
            $atts = array();
            if (is_array($attributes)) {
                if (isset($attributes['areas'])) $atts[] = 'areas="'.esc_attr((string)$attributes['areas']).'"';
                foreach ($attributes as $k=>$v) {
                    if ($k !== 'areas' && !is_array($v)) $atts[] = $k.'="'.esc_attr((string)$v).'"';
                }
            }
            $short = '[geofence '.implode(' ',$atts).']'.(is_null($content)?'':$content).'[/geofence]';
            return do_shortcode($short);
        }

        public function settings_page() {
            if (! current_user_can('manage_options')) return;
            $settings = get_option('wp_geofence_settings', $this->defaults);

            $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'overview';
            
            if (isset($_GET['settings-updated']) && $_GET['settings-updated'] === 'restored') {
                ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php _e('Settings restored to default values.', 'wp-geofence'); ?></p>
                </div>
                <?php
            }
            ?>

            <div class="wrap">
                <h1><?php _e('WP Geofence','wp-geofence'); ?></h1>

                <h2 class="nav-tab-wrapper" style="margin-bottom:20px;">
                    <a href="?page=wp-geofence&tab=overview" class="nav-tab <?php echo $active_tab=='overview' ? 'nav-tab-active' : ''; ?>">
                        <?php _e('Overview', 'wp-geofence'); ?>
                    </a>
                    <a href="?page=wp-geofence&tab=settings" class="nav-tab <?php echo $active_tab=='settings' ? 'nav-tab-active' : ''; ?>">
                        <?php _e('Settings', 'wp-geofence'); ?>
                    </a>
                    <a href="?page=wp-geofence&tab=restore" class="nav-tab <?php echo $active_tab=='restore' ? 'nav-tab-active' : ''; ?>">
                        <?php _e('Restore', 'wp-geofence'); ?>
                    </a>
                </h2>

                <?php if ($active_tab == 'overview'): ?>

                    <div style="background:#fff; padding:20px; border:1px solid #ddd; border-radius:6px;">
                        <h2><?php _e('About WP Geofence', 'wp-geofence'); ?></h2>
                        <p style="font-size:14px; line-height:1.6;"><?php echo esc_html__('WP Geofence allows you to show or hide content based on the geographic location of the visitor. The plugin uses the browser\'s geolocation API to validate the user\'s position in real time.', 'wp-geofence'); ?></p>
                        <h3><?php _e('How it works', 'wp-geofence'); ?></h3>
                        <ul style="list-style:disc; padding-left:20px;">
                            <li><?php _e('You define geographic areas with latitude, longitude, and radius.', 'wp-geofence'); ?></li>
                            <li><?php _e('The visitor allows location access in the browser.', 'wp-geofence'); ?></li>
                            <li><?php _e('If the visitor is inside the configured area, the protected content becomes visible.', 'wp-geofence'); ?></li>
                            <li><?php _e('If the visitor is outside the permitted area, the plugin displays a message or performs a redirect.', 'wp-geofence'); ?></li>
                        </ul>
                        <h3><?php _e('Supported formats', 'wp-geofence'); ?></h3>
                        <p><strong><?php _e('Recommended new format:', 'wp-geofence'); ?></strong></p>
                        <pre style="background:#f7f7f7; padding:10px; border:1px solid #ddd; border-radius:4px;">
lat:-23.561455617058257; lng:-46.65596816474887; radius:50
                        </pre>
                        <p><strong><?php _e('Multiple areas:', 'wp-geofence'); ?></strong></p>
                        <pre style="background:#f7f7f7; padding:10px; border:1px solid #ddd; border-radius:4px;">
lat:-23.561455617058257; lng:-46.65596816474887; radius:50 ;
lat:-23.5452257409943; lng:-46.63864849355688; radius:30
                        </pre>
                        <h3><?php _e('How to use', 'wp-geofence'); ?></h3>
                        <p><strong><?php _e('Using the shortcode:', 'wp-geofence'); ?></strong></p>
                        <?php echo esc_html__('Make sure you have configured all the necessary variables in this plugins options. They will be used globally, and you wont need to type anything other than the tags, as shown below.', 'wp-geofence'); ?><br />
                        <pre style="background:#f7f7f7; padding:10px; border:1px solid #ddd; border-radius:4px;">
[geofence]
<?php _e('Protected content...', 'wp-geofence'); ?>
[/geofence]
                        </pre>
                        <h3><?php _e('Expanded use', 'wp-geofence'); ?></h3>
                        <?php echo esc_html__('If you need to use a specific configuration, different from what was established as the default in the plugin settings, you can configure it directly using tags, as shown below.', 'wp-geofence'); ?><br />
                        <p><strong><?php _e('Using the shortcode:', 'wp-geofence'); ?></strong></p>
                        <pre style="background:#f7f7f7; padding:10px; border:1px solid #ddd; border-radius:4px;">
[geofence areas="lat:-23.561455617058257; lng:-46.65596816474887; radius:50"; strict="yes"; lazyload="yes"]
<?php _e('Protected content...', 'wp-geofence'); ?>
[/geofence]
                        </pre>
                        <p>
                            <strong><?php _e('Using the Gutenberg block:', 'wp-geofence'); ?></strong>
                            <?php echo ' ' . esc_html__('The block works as a shortcode generator. All geolocation validation happens only on the frontend, not in the block editor.', 'wp-geofence'); ?>
                        </p>
                        <h3><?php _e('Other resources:', 'wp-geofence'); ?></h3>
                        <p>
                            <?php _e('Be sure of what you want to do when using the tags below, as they modify the default behavior set in the plugin settings and may cause unexpected effects.', 'wp-geofence'); ?><br />
                            <?php _e('Use "msg_out" tag to configure text (different from default) for users outside configured area.', 'wp-geofence'); ?><br />
                            <?php _e('Use "msg_error" tag to configure text (different from default) for errors when obtaining GPS coordinates from user.', 'wp-geofence'); ?><br />
                            <?php _e('Use "redirect" tag to configure a redirect that will occur when there is an error obtaining users GPS coordinates.', 'wp-geofence'); ?><br />
                            <?php _e('Use "accuracy" tag to configure a GPS coordinate accuracy (different from default).', 'wp-geofence'); ?><br />
                            <?php _e('Use "strict" tag to configure whether content will be strict or not (different from default). For this case, use "yes" or "no".', 'wp-geofence'); ?><br />
                            <?php _e('Use "lazyload" tag to configure whether or not content should wait for GPS validation before being displayed (different from default). For this case, use "yes" or "no".', 'wp-geofence'); ?><br />
                            <?php _e('Use "cache" tag to configure validity of GPS coordinate cache (different from default).', 'wp-geofence'); ?><br />
                            <?php _e('Use "cache_ttl" tag to configure validity of GPS coordinate cache (different from default).', 'wp-geofence'); ?><br />
                            <?php _e('Use "cache_accuracy" tag to configure the accuracy of the GPS coordinate cache (different from the default).', 'wp-geofence'); ?><br />
                        </p>
                        <h3><?php _e('Privacy', 'wp-geofence'); ?></h3>
                        <p>
                            <?php echo esc_html__('The user\'s location is never stored on the server. All geolocation processing occurs exclusively in the visitor\'s browser.', 'wp-geofence'); ?>
                        </p>
                    </div>

                <?php elseif ($active_tab == 'settings'): ?>

                    <form method="post" action="options.php">
                        <?php settings_fields('wp_geofence_settings_group'); ?>

                        <h2><?php _e('General settings','wp-geofence'); ?></h2>

                        <table class="form-table">
                            <tr><th><?php _e('Default areas', 'wp-geofence'); ?></th>
                            <td><textarea name="wp_geofence_settings[areas]" rows="3" style="width:100%;"><?php echo esc_textarea($settings['areas']); ?></textarea>
                            <p class="description"><?php _e('New format: lat:-23.561455617058257; lng:-46.65596816474887; radius:50 ; lat:-23.5452257409943; lng:-46.63864849355688; radius:30', 'wp-geofence'); ?></p>
                            <p class="description"><?php _e('Legacy formats are still accepted: -23.561455617058257,-46.65596816474887,50 | -23.551,-46.632,20 or one area per line.', 'wp-geofence'); ?></p></td></tr>
                            
                            <tr><th><?php _e('Message when outside','wp-geofence'); ?></th><td><input type="text" name="wp_geofence_settings[msg_out]" value="<?php echo esc_attr($settings['msg_out']); ?>" class="regular-text" /></td></tr>
                            <tr><th><?php _e('GPS error message','wp-geofence'); ?></th><td><input type="text" name="wp_geofence_settings[msg_error]" value="<?php echo esc_attr($settings['msg_error']); ?>" class="regular-text" /></td></tr>
                            
                            <tr><th><?php _e('Default redirect URL','wp-geofence'); ?></th><td><input type="text" name="wp_geofence_settings[redirect]" value="<?php echo esc_attr($settings['redirect']); ?>" class="regular-text" /><p class="description"><?php _e('Leave empty to disable redirect.','wp-geofence'); ?></p></td></tr>
                            <tr><th><?php _e('Minimum accuracy (m)','wp-geofence'); ?></th><td><input type="number" name="wp_geofence_settings[accuracy]" value="<?php echo esc_attr($settings['accuracy']); ?>" class="small-text" /><p class="description"><?php _e('0 = do not require minimum accuracy.', 'wp-geofence'); ?></p></td></tr>
                            <tr><th><?php _e('Strict mode','wp-geofence'); ?></th><td><select name="wp_geofence_settings[strict]"><option value="yes" <?php selected($settings['strict'],'yes'); ?>><?php _e('Yes (hide content until validated)','wp-geofence'); ?></option><option value="no" <?php selected($settings['strict'],'no'); ?>><?php _e('No (show content before validation)','wp-geofence'); ?></option></select></td></tr>
                            <tr><th><?php _e('Lazy load', 'wp-geofence'); ?></th><td><select name="wp_geofence_settings[lazyload]"><option value="no" <?php selected($settings['lazyload'],'no'); ?>><?php _e('Disabled','wp-geofence'); ?></option><option value="yes" <?php selected($settings['lazyload'],'yes'); ?>><?php _e('Enabled','wp-geofence'); ?></option></select><p class="description"><?php _e('Load content only after approval. Useful for embeds that break before validation.','wp-geofence'); ?></p></td></tr>
                            <tr><th><?php _e('Intelligent cache (only if needed)','wp-geofence'); ?></th><td><select name="wp_geofence_settings[cache]"><option value="no" <?php selected($settings['cache'],'no'); ?>><?php _e('Disabled','wp-geofence'); ?></option><option value="yes" <?php selected($settings['cache'],'yes'); ?>><?php _e('Enabled','wp-geofence'); ?></option></select><p class="description"><?php _e('Stores last position locally to speed up immediate validation. Can cause errors if the user moves. Default disabled.','wp-geofence'); ?></p></td></tr>
                            <tr><th><?php _e('Cache TTL (seconds)','wp-geofence'); ?></th><td><select name="wp_geofence_settings[cache_ttl]"><?php $opts=array(30,45,60,90,120,180,240); foreach($opts as $o) echo '<option value="'.esc_attr($o).'" '.selected($settings['cache_ttl'],$o,false).'>'.esc_html($o).'s</option>'; ?></select><p class="description"><?php _e('Max age of cached position (30-240 seconds).','wp-geofence'); ?></p></td></tr>
                            <tr><th><?php _e('Cache accuracy threshold (m)','wp-geofence'); ?></th><td><select name="wp_geofence_settings[cache_accuracy]"><?php $acc=array('10','25','50','100','200'); foreach($acc as $a) echo '<option value="'.esc_attr($a).'" '.selected($settings['cache_accuracy'],$a,false).'>'.esc_html($a).'m</option>'; ?></select><p class="description"><?php _e('Cache is used only if stored position has accuracy <= this value.','wp-geofence'); ?></p></td></tr>
                        </table>
                        <?php submit_button(); ?>
                    </form>

                <?php elseif ($active_tab == 'restore'): ?>
                    
                    <div style="background:#fff; padding:20px; border:1px solid #ddd; border-radius:6px;">
                        <h2><?php _e('Danger Zone: Restore Settings', 'wp-geofence'); ?></h2>
                        <p class="description" style="font-size: 1.1em; color: #cc0000; font-weight: bold;">
                            <?php _e('This action is irreversible.', 'wp-geofence'); ?>
                            <?php _e('Clicking the button below will permanently delete all saved plugin settings from the database and restore them to the original default values defined in the code.', 'wp-geofence'); ?>
                        </p>
                        
                        <form method="post">
                            <?php wp_nonce_field('wp_geofence_restore_defaults_nonce'); ?>
                            <input type="hidden" name="wp_geofence_restore" value="1">
                            
                            <p style="margin-top: 20px;">
                                <label>
                                    <input type="checkbox" name="wp_geofence_confirm_restore" value="1" required>
                                    <?php _e('I confirm that I understand this action is irreversible and I agree to restore all settings to their original defaults.', 'wp-geofence'); ?>
                                </label>
                            </p>
                            
                            <button type="submit" class="button button-secondary" style="color: white; background-color: #cc0000; border-color: #aa0000; margin-top: 15px;">
                                <?php _e('Restore Original Settings', 'wp-geofence'); ?>
                            </button>
                        </form>
                    </div>

                <?php endif; ?>
            </div>
            <?php
        }

        private function iframe_replace_callback($m) {
            $attrs_before = $m[1];
            $src = $m[3];
            $attrs_after = $m[4];
            return '<iframe' . $attrs_before . ' data-src="' . esc_attr($src) . '" src="about:blank"' . $attrs_after . '>';
        }

        public function shortcode($atts,$content=null) {
            wp_enqueue_script('wpgeofence-frontend');
            
            $settings = get_option('wp_geofence_settings',$this->defaults);
            $atts = shortcode_atts($settings,$atts,'geofence');

            $areas = $this->parse_areas_string($atts['areas']);

            $id = 'geo_'.wp_rand(10000,99999);
            $global_cache_enabled = (isset($settings['cache']) && $settings['cache']==='yes')?'yes':'no';
            $global_cache_ttl = isset($settings['cache_ttl'])?intval($settings['cache_ttl']):60;
            $global_cache_accuracy = isset($settings['cache_accuracy'])?intval($settings['cache_accuracy']):50;

            $content_full = is_null($content)?'':do_shortcode($content);

            $content_iframe_safe = '';
            if ($content_full !== '') {
                $content_iframe_safe = preg_replace_callback(
                    '/<iframe\b([^>]*)\bsrc=(["\'])(.*?)\2([^>]*)>/is',
                    array($this, 'iframe_replace_callback'),
                    $content_full
                );

                if ($content_iframe_safe === null) $content_iframe_safe = $content_full;
            }

            $output = '';

            $strict_display = ($atts['strict']==='yes') ? 'none' : 'block';
            $msg_validating = ($atts['strict']==='yes') ? esc_html__('Validating location...','wp-geofence') : '';
            $content_to_show = ($atts['lazyload']==='yes') ? '' : $content_full;

            $output .= '<div id="' . esc_attr('geo_msg_'.$id) . '">' . $msg_validating . '</div>';
            $output .= '<div id="' . esc_attr('geo_wrap_'.$id) . '" style="display:' . $strict_display . ';">' . $content_to_show . '</div>';
            
            $js_data = wp_json_encode(array(
                'id'=>$id,
                'areas'=>$areas,
                'msgOut'=>$atts['msg_out'],
                'msgError'=>$atts['msg_error'],
                'redirectURL'=>$atts['redirect'],
                'minAccuracy'=>intval($atts['accuracy']),
                'lazyload'=>$atts['lazyload'],
                'cacheEnabledShortcode'=> (isset($atts['cache'])?$atts['cache']:$settings['cache']),
                'cacheGlobalEnabled'=>$global_cache_enabled,
                'cacheTTL'=>$global_cache_ttl,
                'cacheAccuracy'=>$global_cache_accuracy,
                'contentSerialized'=>$content_full,
                'contentIframeSafe'=>$content_iframe_safe
            ), JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT);

            $output .= '<script type="text/javascript">(function(){ if(typeof window.wpGeofenceData===\'undefined\') window.wpGeofenceData={}; window.wpGeofenceData[\'' . esc_js($id) . '\'] = ' . $js_data . '; })();</script>';
            
            return $output;
        }
    }

    if ( ! function_exists( 'wp_geofence_run' ) ) {
        function wp_geofence_run() {
            return WP_Geofence_Plugin::instance();
        }
    }

    add_action('plugins_loaded', 'wp_geofence_run', 20);

}