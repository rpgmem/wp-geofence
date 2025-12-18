<?php
if ( ! function_exists( 'wp_geofence_run' ) ) {
    function wp_geofence_run() {
        return WP_Geofence_Plugin::instance();
    }
}