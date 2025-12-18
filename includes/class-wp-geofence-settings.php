<?php
/**
 * Trait handling settings sanitization and rendering.
 * * Part of the WP Geofence plugin.
 */

trait WP_Geofence_Settings_Trait {

    /**
     * Sanitize settings fields with strict validation.
     */
    public function sanitize_settings($input) {
        $clean = array();
        $raw = is_array($input) ? $input : array();
        
        foreach ($this->defaults as $key => $default) {
            if (!isset($raw[$key])) {
                $clean[$key] = $default;
                continue;
            }

            $val = $raw[$key];

            // 1. Cache TTL: Garantir que seja um número dentro do range permitido
            if ($key === 'cache_ttl') {
                $v = intval($val);
                $clean[$key] = (string)(($v < 30 || $v > 3600) ? $default : $v);
                continue;
            }
            
            // 2. Cache Accuracy: Validar contra valores permitidos
            if ($key === 'cache_accuracy') {
                $allowed = array('10','25','50','100','200');
                $clean[$key] = in_array($val, $allowed) ? $val : $default;
                continue;
            }
            
            // 3. URLs: Sanitização profunda para URLs de redirecionamento
            if ($key === 'redirect') {
                $sanitized_url = esc_url_raw(trim($val));
                $clean[$key] = ($sanitized_url === '' && trim($val) !== '') ? $default : $sanitized_url;
                continue;
            }
            
            // 4. Booleans/Selects: Validar estritamente yes/no
            if (in_array($key, array('cache', 'lazyload', 'strict', 'check_location'))) {
                $clean[$key] = ($val === 'no') ? 'no' : 'yes';
                continue;
            }

            // 5. Sanitização de Texto Geral
            $clean[$key] = sanitize_text_field(trim($val));
            
            // Se o campo obrigatório ficar vazio após o trim, volta ao padrão
            if ($clean[$key] === '' && in_array($key, array('areas', 'msg_out', 'msg_error', 'msg_limit'))) { 
                $clean[$key] = $default;
            }
            
            // 6. Precisão (Accuracy): Apenas números inteiros positivos
            if ($key === 'accuracy') {
                $v = absint($clean[$key]);
                $clean[$key] = (string)($v === 0 ? $default : $v);
            }
        }
        return $clean;
    }

    /**
     * Render settings page (Admin Interface).
     */
    public function settings_page() {
        if (! current_user_can('manage_options')) return;
        
        $settings = get_option('wp_geofence_settings', $this->defaults);
        if (!is_array($settings)) $settings = $this->defaults;
        $settings = array_merge($this->defaults, $settings);

        $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'overview';
        
        if (isset($_GET['settings-updated']) && $_GET['settings-updated'] === 'restored') {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Settings restored to default values.', 'wp-geofence') . '</p></div>';
        }
        ?>

        <div class="wrap">
            <h1 style="display: flex; align-items: center; gap: 10px;">
                <span class="dashicons dashicons-location-alt" style="font-size: 30px; width: 30px; height: 30px;"></span>
                <?php _e('WP Geofence','wp-geofence'); ?>
            </h1>

            <nav class="nav-tab-wrapper" style="margin-bottom:20px;">
                <a href="?page=wp-geofence&tab=overview" class="nav-tab <?php echo $active_tab=='overview' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Overview', 'wp-geofence'); ?>
                </a>
                <a href="?page=wp-geofence&tab=settings" class="nav-tab <?php echo $active_tab=='settings' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Settings', 'wp-geofence'); ?>
                </a>
                <a href="?page=wp-geofence&tab=restore" class="nav-tab <?php echo $active_tab=='restore' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Restore Defaults', 'wp-geofence'); ?>
                </a>
            </nav>

            <?php if ($active_tab == 'overview'): ?>

                <div style="background:#fff; padding:30px; border:1px solid #ddd; border-radius:8px; max-width: 1000px; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
                    <h2 style="margin-top:0; color:#23282d; border-bottom:1px solid #eee; padding-bottom:10px;">
                        <?php _e('Usage Guide & Documentation', 'wp-geofence'); ?>
                    </h2>
                    
                    <p style="font-size:15px; color:#555;"><?php _e('WP Geofence allows you to protect content based on geographic location and time intervals. Below are the details for the [geofence] shortcode attributes.', 'wp-geofence'); ?></p>

                    <h3 style="color:#0073aa; margin-top:30px;"><?php _e('1. Core Attributes', 'wp-geofence'); ?></h3>
                    <table class="widefat fixed striped" style="margin-top:10px;">
                        <thead>
                            <tr>
                                <th style="width:25%"><strong><?php _e('Attribute', 'wp-geofence'); ?></strong></th>
                                <th><strong><?php _e('Description', 'wp-geofence'); ?></strong></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><code>areas</code></td>
                                <td><?php _e('Allowed coordinates. Format: lat:-23.5;lng:-46.6;radius:100. Multiple areas via pipe | or newline.', 'wp-geofence'); ?></td>
                            </tr>
                            <tr>
                                <td><code>check_location</code></td>
                                <td><?php _e('Enables ("yes") or disables ("no") GPS request. Use "no" for time-only restrictions.', 'wp-geofence'); ?></td>
                            </tr>
                            <tr>
                                <td><code>date_start / date_end</code></td>
                                <td><?php _e('Date range (YYYY-MM-DD). If omitted, restrictions are time-only based on today.', 'wp-geofence'); ?></td>
                            </tr>
                            <tr>
                                <td><code>time_start / time_end</code></td>
                                <td><?php _e('Daily time window (HH:MM). Uses server time to prevent local clock tampering.', 'wp-geofence'); ?></td>
                            </tr>
                        </tbody>
                    </table>

                    <h3 style="color:#0073aa; margin-top:30px;"><?php _e('2. Security & Performance', 'wp-geofence'); ?></h3>
                    <table class="widefat fixed striped" style="margin-top:10px;">
                        <thead>
                            <tr>
                                <th style="width:25%"><strong><?php _e('Attribute', 'wp-geofence'); ?></strong></th>
                                <th><strong><?php _e('Details', 'wp-geofence'); ?></strong></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><code>strict</code></td>
                                <td><?php _e('If "yes", content is removed from server HTML until validated. Most secure.', 'wp-geofence'); ?></td>
                            </tr>
                            <tr>
                                <td><code>lazyload</code></td>
                                <td><?php _e('If "yes", content is only loaded/injected after location is confirmed.', 'wp-geofence'); ?></td>
                            </tr>
                            <tr>
                                <td><code>cache</code></td>
                                <td><?php _e('Saves valid position in browser for a specific time to avoid multiple GPS popups.', 'wp-geofence'); ?></td>
                            </tr>
                        </tbody>
                    </table>

                    <h3 style="color:#0073aa; margin-top:30px;"><?php _e('3. Live Example', 'wp-geofence'); ?></h3>
                    <div style="background:#f7f7f7; padding:20px; border-radius:4px; font-family:monospace; border:1px solid #ddd; line-height: 1.6;">
                        [geofence areas="lat:-23.56;lng:-46.65;radius:100" <br>
                        &nbsp;&nbsp;time_start="08:00" time_end="18:00" <br>
                        &nbsp;&nbsp;msg_limit="Closed now! Open daily from 8am to 6pm server time."]<br>
                        &nbsp;&nbsp;&nbsp;&nbsp;... Protected Content ...<br>
                        [/geofence]
                    </div>
                </div>

            <?php elseif ($active_tab == 'settings'): ?>

                <form method="post" action="options.php">
                    <?php settings_fields('wp_geofence_settings_group'); ?>

                    <h2 style="margin-bottom: 20px;"><?php _e('Default Shortcode Behaviors','wp-geofence'); ?></h2>

                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php _e('Global Default Areas', 'wp-geofence'); ?></th>
                            <td>
                                <textarea name="wp_geofence_settings[areas]" rows="3" style="width:100%; font-family:monospace;"><?php echo esc_textarea($settings['areas']); ?></textarea>
                                <p class="description"><?php _e('Example: lat:-23.56; lng:-46.65; radius:50', 'wp-geofence'); ?></p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row"><?php _e('Global GPS Restriction', 'wp-geofence'); ?></th>
                            <td>
                                <select name="wp_geofence_settings[check_location]">
                                    <option value="yes" <?php selected($settings['check_location'],'yes'); ?>><?php _e('Enabled (Verify GPS location)','wp-geofence'); ?></option>
                                    <option value="no" <?php selected($settings['check_location'],'no'); ?>><?php _e('Disabled (Date/Time restriction only)','wp-geofence'); ?></option>
                                </select>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row"><?php _e('Message when outside area', 'wp-geofence'); ?></th>
                            <td><input type="text" name="wp_geofence_settings[msg_out]" value="<?php echo esc_attr($settings['msg_out']); ?>" class="regular-text" /></td>
                        </tr>
                        
                        <tr>
                            <th scope="row"><?php _e('GPS Error message','wp-geofence'); ?></th>
                            <td><input type="text" name="wp_geofence_settings[msg_error]" value="<?php echo esc_attr($settings['msg_error']); ?>" class="regular-text" /></td>
                        </tr>

                        <tr>
                            <th scope="row"><?php _e('Date/Time limit message','wp-geofence'); ?></th>
                            <td>
                                <input type="text" name="wp_geofence_settings[msg_limit]" value="<?php echo esc_attr($settings['msg_limit']); ?>" class="regular-text" />
                                <p class="description"><?php _e('Message shown when content is restricted by date or time limits.','wp-geofence'); ?></p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row"><?php _e('Default Redirect (Optional)','wp-geofence'); ?></th>
                            <td><input type="text" name="wp_geofence_settings[redirect]" value="<?php echo esc_attr($settings['redirect']); ?>" class="regular-text" placeholder="https://..." /></td>
                        </tr>

                        <tr>
                            <th scope="row"><?php _e('GPS Min Accuracy (meters)','wp-geofence'); ?></th>
                            <td><input type="number" name="wp_geofence_settings[accuracy]" value="<?php echo esc_attr($settings['accuracy']); ?>" class="small-text" /></td>
                        </tr>

                        <tr>
                            <th scope="row"><?php _e('Security Level (Strict)','wp-geofence'); ?></th>
                            <td>
                                <select name="wp_geofence_settings[strict]">
                                    <option value="yes" <?php selected($settings['strict'],'yes'); ?>><?php _e('High (Strip content from HTML)','wp-geofence'); ?></option>
                                    <option value="no" <?php selected($settings['strict'],'no'); ?>><?php _e('Low (Hide via CSS only)','wp-geofence'); ?></option>
                                </select>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row"><?php _e('Lazy Load Content', 'wp-geofence'); ?></th>
                            <td>
                                <select name="wp_geofence_settings[lazyload]">
                                    <option value="no" <?php selected($settings['lazyload'],'no'); ?>><?php _e('No (Standard injection)','wp-geofence'); ?></option>
                                    <option value="yes" <?php selected($settings['lazyload'],'yes'); ?>><?php _e('Yes (Optimized loading)','wp-geofence'); ?></option>
                                </select>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row"><?php _e('Browser Persistence (Cache)','wp-geofence'); ?></th>
                            <td>
                                <select name="wp_geofence_settings[cache]">
                                    <option value="no" <?php selected($settings['cache'],'no'); ?>><?php _e('No Cache','wp-geofence'); ?></option>
                                    <option value="yes" <?php selected($settings['cache'],'yes'); ?>><?php _e('Enable Local Cache','wp-geofence'); ?></option>
                                </select>
                                <p class="description"><?php _e('Improves UX by remembering user location.','wp-geofence'); ?></p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row"><?php _e('Cache Expiration (TTL)','wp-geofence'); ?></th>
                            <td>
                                <select name="wp_geofence_settings[cache_ttl]">
                                    <?php $opts = array(30, 60, 120, 300, 600, 1800, 3600); 
                                    foreach($opts as $o) echo '<option value="'.esc_attr($o).'" '.selected($settings['cache_ttl'], $o, false).'>'.esc_html($o).'s</option>'; ?>
                                </select>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row"><?php _e('Cache Accuracy Tolerance','wp-geofence'); ?></th>
                            <td>
                                <select name="wp_geofence_settings[cache_accuracy]">
                                    <?php $acc = array('10','25','50','100','200'); 
                                    foreach($acc as $a) echo '<option value="'.esc_attr($a).'" '.selected($settings['cache_accuracy'], $a, false).'>'.esc_html($a).'m</option>'; ?>
                                </select>
                            </td>
                        </tr>
                    </table>
                    <?php submit_button(); ?>
                </form>

            <?php elseif ($active_tab == 'restore'): ?>
                <div style="background:#fff; padding:30px; border:1px solid #ddd; border-radius:8px; max-width: 600px;">
                    <h2 style="color: #cc0000; margin-top: 0;"><?php _e('Restore Factory Defaults', 'wp-geofence'); ?></h2>
                    <p style="margin-bottom: 20px;">
                        <?php _e('Are you sure you want to reset all settings to their original state? This will delete your default coordinates and custom messages.', 'wp-geofence'); ?>
                    </p>
                    <form method="post">
                        <?php wp_nonce_field('wp_geofence_restore_defaults_nonce'); ?>
                        <input type="hidden" name="wp_geofence_restore" value="1">
                        <p>
                            <label style="display: block; margin-bottom: 20px;">
                                <input type="checkbox" name="wp_geofence_confirm_restore" value="1" required> 
                                <strong><?php _e('I understand this action is irreversible.', 'wp-geofence'); ?></strong>
                            </label>
                        </p>
                        <button type="submit" class="button button-primary" style="background: #cc0000; border-color: #990000; text-shadow: none; box-shadow: none; height: 40px; padding: 0 30px;">
                            <?php _e('Wipe & Restore Now', 'wp-geofence'); ?>
                        </button>
                    </form>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
}