<?php
trait WP_Geofence_Shortcode_Trait {

    /**
     * Converte a string de áreas em array.
     */
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
        foreach ($parts as $p) {
            $t = trim($p);
            if ($t === '') continue;
            $pbits = array_map('trim', explode(',', $t));
            if (count($pbits) >= 3) {
                $areas[] = array('lat'=>floatval($pbits[0]),'lng'=>floatval($pbits[1]),'radius'=>floatval($pbits[2]));
            }
        }
        return $areas;
    }

    private function iframe_replace_callback($m) {
        return '<iframe' . $m[1] . ' data-src="' . esc_attr($m[3]) . '" src="about:blank"' . $m[4] . '>';
    }

    /**
     * Shortcode [geofence] com validação de tempo robusta
     */
    public function shortcode($atts, $content = null) {
        wp_enqueue_script('wpgeofence-frontend');
        $settings = get_option('wp_geofence_settings', $this->defaults);

        $pairs = array(
            'areas'          => $settings['areas'],
            'msg_out'        => $settings['msg_out'],
            'msg_error'      => $settings['msg_error'],
            'msg_limit'      => $settings['msg_limit'],
            'redirect'       => $settings['redirect'],
            'accuracy'       => $settings['accuracy'],
            'strict'         => $settings['strict'],
            'lazyload'       => $settings['lazyload'],
            'cache'          => $settings['cache'],
            'cache_ttl'      => $settings['cache_ttl'],
            'cache_accuracy' => $settings['cache_accuracy'],
            'date_start'     => '', 
            'date_end'       => '',
            'time_start'     => '',
            'time_end'       => '',
            'check_location' => 'yes'
        );

        $merged = shortcode_atts($pairs, $atts, 'geofence');

        // --- VALIDAÇÃO DE TEMPO NO SERVIDOR (PROTEÇÃO CONTRA BURLAS) ---
        $server_now = new DateTime('now', wp_timezone());
        $is_expired_by_server = false;

        // Lógica de Start (00h00 se omitido)
        if (!empty($merged['date_start'])) {
            $start_h = !empty($merged['time_start']) ? $merged['time_start'] : '00:00';
            $start_dt = DateTime::createFromFormat('Y-m-d H:i', $merged['date_start'] . ' ' . $start_h, wp_timezone());
            if ($start_dt && $server_now < $start_dt) $is_expired_by_server = true;
        } elseif (!empty($merged['time_start'])) {
            // Apenas hora: compara com o dia de hoje
            if ($server_now->format('H:i') < $merged['time_start']) $is_expired_by_server = true;
        }

        // Lógica de End (23h59 se omitido)
        if (!$is_expired_by_server) {
            if (!empty($merged['date_end'])) {
                $end_h = !empty($merged['time_end']) ? $merged['time_end'] : '23:59';
                $end_dt = DateTime::createFromFormat('Y-m-d H:i', $merged['date_end'] . ' ' . $end_h, wp_timezone());
                if ($end_dt && $server_now > $end_dt) $is_expired_by_server = true;
            } elseif (!empty($merged['time_end'])) {
                // Apenas hora: compara com o dia de hoje
                if ($server_now->format('H:i') > $merged['time_end']) $is_expired_by_server = true;
            }
        }

        // Se o servidor já sabe que expirou, nem processa o resto (Segurança Máxima)
        if ($is_expired_by_server) {
            return !empty($merged['msg_limit']) ? '<div class="wp-geofence-time-limit">'.esc_html($merged['msg_limit']).'</div>' : '';
        }

        // --- PREPARAÇÃO PARA O FRONTEND (CASO O CACHE ESTEJA ATIVO) ---
        $id = 'geo_' . wp_rand(10000, 99999);
        remove_shortcode('geofence');
        $content_full = is_null($content) ? '' : do_shortcode($content);
        add_shortcode('geofence', array($this, 'shortcode'));

        $content_iframe_safe = preg_replace_callback(
            '/<iframe\b([^>]*)\bsrc=(["\'])(.*?)\2([^>]*)>/is',
            array($this, 'iframe_replace_callback'),
            $content_full
        );

        // Preparamos o output com Anti-Flash
        $has_geo = ($merged['check_location'] === 'yes');
        $output = '<style>#geo_wrap_'.$id.'{display:none!important;visibility:hidden!important;opacity:0!important;}</style>';
        $output .= '<div class="wp-geofence-container" id="container_'.$id.'">';
        $output .= '<div id="geo_msg_'.$id.'" class="wp-geofence-message"></div>';
        $output .= '<div id="geo_wrap_'.$id.'" class="wp-geofence-wrapper">';
        $output .= ($merged['lazyload'] === 'yes' ? '' : $content_full);
        $output .= '</div></div>';

        // Passamos a data do servidor para o JS não confiar no relógio do usuário
        $js_data = wp_json_encode(array(
            'id'             => $id,
            'serverTime'     => $server_now->format('Y-m-d H:i:s'),
            'dateStart'      => $merged['date_start'],
            'dateEnd'        => $merged['date_end'],
            'timeStart'      => $merged['time_start'],
            'timeEnd'        => $merged['time_end'],
            'areas'          => $this->parse_areas_string($merged['areas']),
            'msgOut'         => $merged['msg_out'],
            'msgError'       => $merged['msg_error'],
            'msgLimit'       => $merged['msg_limit'],
            'checkLocation'  => $merged['check_location'],
            'lazyload'       => $merged['lazyload'],
            'contentFull'    => $content_full,
            'contentSafe'    => $content_iframe_safe ?: $content_full
        ));

        $output .= '<script type="text/javascript">window.wpGeofenceData=window.wpGeofenceData||{};window.wpGeofenceData["'.$id.'"]='.$js_data.';</script>';

        return $output;
    }
}