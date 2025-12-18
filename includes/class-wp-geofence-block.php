<?php
trait WP_Geofence_Block_Trait {

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

        $deps = array();
        if (file_exists($block_min)) {
            wp_register_script('wpgeofence-block-editor', $this->plugin_url.'assets/js/wp-geofence-block.min.js', $deps, null, true);
        } elseif (file_exists($block_src)) {
            wp_register_script('wpgeofence-block-editor', $this->plugin_url.'assets/js/wp-geofence-block.js', $deps, null, true);
        } else {
            wp_register_script('wpgeofence-block-editor', '', $deps, null, true);
        }

        if (file_exists($block_css_min)) {
            wp_register_style('wpgeofence-block-editor-css',$this->plugin_url.'assets/css/block-editor.min.css', array(), null);
        } elseif (file_exists($block_css_src)) {
            wp_register_style('wpgeofence-block-editor-css',$this->plugin_url.'assets/css/block-editor.css', array(), null);
        } else {
            wp_register_style('wpgeofence-block-editor-css', false);
        }

        register_block_type('wp-geofence/block', array(
            'editor_script' => 'wpgeofence-block-editor',
            'editor_style' => 'wpgeofence-block-editor-css',
            'render_callback' => array($this,'render_block_callback'),
        ));
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
}