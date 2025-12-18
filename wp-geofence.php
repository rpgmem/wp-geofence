<?php
/**
 * Plugin Name: WP Geofence
 * Plugin URI: https://github.com/rpgmem/wp-geofence
 * Description: Restringe conteúdo baseado na localização geográfica do usuário e limites de data/hora.
 * Version: 1.2.0
 * Author: Alex Meusburger
 * Text Domain: wp-geofence
 * License: CC BY-NC-SA 4.0
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) exit;

// 1. Carregar os Traits Primeiro (Essencial para evitar Fatal Error)
require_once plugin_dir_path(__FILE__) . 'includes/class-wp-geofence-assets.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-wp-geofence-block.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-wp-geofence-settings.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-wp-geofence-shortcode.php';

// 2. Carregar a Classe Principal e Helpers
require_once plugin_dir_path(__FILE__) . 'includes/class-wp-geofence-plugin.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-wp-geofence-helpers.php';

/**
 * 3. Iniciar o Plugin
 * Esta função chama a instância única (Singleton) da classe principal.
 */
function wp_geofence_run() {
    if ( class_exists( 'WP_Geofence_Plugin' ) ) {
        WP_Geofence_Plugin::instance();
    }
}

add_action('plugins_loaded', 'wp_geofence_run');