<?php
/**
 * Classe Core com correção de caminhos de diretório e sintaxe
 */
if ( ! class_exists( 'WP_Geofence_Plugin' ) ) {

    class WP_Geofence_Plugin {

        use WP_Geofence_Assets_Trait;
        use WP_Geofence_Block_Trait;
        use WP_Geofence_Settings_Trait;
        use WP_Geofence_Shortcode_Trait;

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
            // CORREÇÃO: Usando dirname para subir um nível, já que este arquivo está em /includes
            $this->plugin_dir = plugin_dir_path(dirname(__FILE__));
            $this->plugin_url = plugin_dir_url(dirname(__FILE__));

            $this->defaults = array(
                'areas'          => 'lat:-23.561455617058257; lng:-46.65596816474887; radius:50',
                'msg_out'        => __('Access denied: you are outside the authorized area.', 'wp-geofence'),
                'msg_error'      => __('Error obtaining your location.', 'wp-geofence'),
                'msg_limit'      => __('Access denied: the content is only available during a specific period.', 'wp-geofence'), 
                'redirect'       => '',
                'accuracy'       => '0',
                'strict'         => 'yes',
                'lazyload'       => 'no',
                'cache'          => 'no',
                'cache_ttl'      => '60',
                'cache_accuracy' => '50', // <--- A VÍRGULA ESTAVA FALTANDO AQUI
                'check_location' => 'yes'
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

        public function admin_menu() {
            // Alterado para aparecer em Configurações > WP Geofence
            add_options_page(__('WP Geofence','wp-geofence'), __('WP Geofence','wp-geofence'), 'manage_options', 'wp-geofence', array($this,'settings_page'));
        }
    }
}