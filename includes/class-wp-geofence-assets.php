<?php
trait WP_Geofence_Assets_Trait {

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
            wp_register_script('wpgeofence-frontend', '', array(), null, true);
        }

        $strings = array(
            'no_support' => __('Your browser does not support geolocation.','wp-geofence'),
            'precision_insufficient' => __('Precision insufficient:','wp-geofence'),
            'cache_invalidated' => __('Position updated — access revoked.','wp-geofence')
        );
        wp_localize_script('wpgeofence-frontend','wpGeofenceL10n',$strings);
    }
}