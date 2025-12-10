<?php
/**
 * Plugin Name: RSSSL .htaccess Validator
 * Description: Validates if Really Simple SSL settings correspond with .htaccess rules.
 * Version: 1.1
 * Author: Really Simple Security
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

add_action( 'admin_notices', 'rsssl_hv_display_validation_results' );

function rsssl_hv_get_htaccess_path() {
    $htaccess_file = ABSPATH . '.htaccess';
    if ( function_exists('RSSSL') && method_exists(RSSSL()->admin, 'htaccess_file') ) {
        $htaccess_file = RSSSL()->admin->htaccess_file();
    } else {
        if ( strpos( strtolower( (string) filter_input( INPUT_SERVER, 'SERVER_SOFTWARE' ) ), 'bitnami' ) !== false ) {
            $bitnami_conf_path = dirname( ABSPATH ) . '/conf/htaccess.conf';
            if ( file_exists( $bitnami_conf_path ) ) {
                $htaccess_file = $bitnami_conf_path;
            }
        }
    }
    return $htaccess_file;
}

function rsssl_hv_get_htaccess_content() {
    $htaccess_path = rsssl_hv_get_htaccess_path();
    if ( file_exists( $htaccess_path ) && is_readable( $htaccess_path ) ) {
        return file_get_contents( $htaccess_path );
    }
    return false;
}

function rsssl_hv_display_validation_results() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    if ( ! function_exists( 'rsssl_get_option' ) || ! function_exists('RSSSL') || (function_exists('RSSSL') && (!isset(RSSSL()->server) || !method_exists(RSSSL()->server, 'auto_prepend_config')))) {
        echo '<div class="notice notice-warning is-dismissible"><p>Really Simple SSL plugin, or its Server component, does not seem to be fully active/available. Cannot perform all validation checks.</p></div>';
        if (!function_exists('RSSSL')) return;
    }

    $htaccess_content = rsssl_hv_get_htaccess_content();
    $messages = [];
    $errors = 0;
    $warnings = 0;

    $wp_content_dir = defined('WP_CONTENT_DIR') ? WP_CONTENT_DIR : ABSPATH . 'wp-content';
    $advanced_headers_file_path = $wp_content_dir . '/advanced-headers.php';
    $firewall_rules_file_path = $wp_content_dir . '/firewall.php'; // This is the Pro firewall rules file in wp-content

    $adv_headers_exists = file_exists($advanced_headers_file_path);
    $firewall_php_exists = file_exists($firewall_rules_file_path);

    if ( $htaccess_content === false && is_writable(ABSPATH) && !file_exists(rsssl_hv_get_htaccess_path()) ) {
        $messages[] = ['type' => 'info', 'text' => 'The <code>.htaccess</code> file is missing. If you have just set up WordPress or changed permalink settings, this might be temporary. Really Simple SSL may require it for some features.'];
    } else if ($htaccess_content === false ) {
        echo '<div class="notice notice-error is-dismissible"><p>Could not read the .htaccess file at ' . esc_html( rsssl_hv_get_htaccess_path() ) . '. Please check file existence and permissions.</p></div>';
        return;
    }

    $icon_success = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="vertical-align:middle;"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z" fill="#2271b1"/></svg>';
    $icon_error = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="vertical-align:middle;"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z" fill="#d63638"/></svg>';
    $icon_warning = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="vertical-align:middle;"><path d="M1 21h22L12 2 1 21zm12-3h-2v-2h2v2zm0-4h-2v-4h2v4z" fill="#dea500"/></svg>';
    $icon_info = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="vertical-align:middle;"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-6h2v6zm0-8h-2V7h2v2z" fill="#50575e"/></svg>';

    // General File System Checks
    if (is_writable($wp_content_dir)) {
        $messages[] = ['type' => 'info', 'text' => '<strong>WP_CONTENT_DIR Writable:</strong> The directory <code>' . esc_html(basename($wp_content_dir)) . '</code> appears to be writable by the web server.'];
    } else {
        $messages[] = ['type' => 'error', 'text' => '<strong>WP_CONTENT_DIR NOT Writable:</strong> The directory <code>' . esc_html(basename($wp_content_dir)) . '</code> is NOT writable. This will prevent RSSSL from creating necessary files.'];
        $errors++;
    }

    $rsssl_firewall_error_option = get_site_option( 'rsssl_firewall_error' );
    if ($rsssl_firewall_error_option) {
        $messages[] = ['type' => 'warning', 'text' => '<strong>RSSSL Firewall Error Option:</strong> Detected <code>rsssl_firewall_error</code> option value: <code>' . esc_html( var_export($rsssl_firewall_error_option, true) ) . '</code>.'];
        $warnings++; 
    } else {
        $messages[] = ['type' => 'info', 'text' => '<strong>RSSSL Firewall Error Option:</strong> No <code>rsssl_firewall_error</code> option detected or option is false.'];
    }

    // 1. HTTPS Redirect Rules
    $ssl_enabled = rsssl_get_option( 'ssl_enabled' );
    $redirect_method = rsssl_get_option( 'redirect' );
    $has_specific_redirect_block = $htaccess_content ? (preg_match( '/#BEGIN Really Simple SSL Redirect.*?#END Really Simple SSL Redirect/is', $htaccess_content ) || preg_match( '/#BEGIN Really Simple Security Redirect.*?#END Really Simple Security Redirect/is', $htaccess_content )) : false;
    $has_generic_rsssl_block = $htaccess_content ? preg_match( '/#Begin Really Simple Security.*?#End Really Simple Security/is', $htaccess_content ) : false;
    $redirect_rules_present_in_generic_block = false;
    if ($htaccess_content && $has_generic_rsssl_block && !$has_specific_redirect_block) {
        if (preg_match( '/#Begin Really Simple Security.*?RewriteCond\s+%\{HTTPS\}\s+!=on.*?RewriteRule\s+\^\(\.\*\)\$\s+https:\/\/%\{HTTP_HOST\}\/\$1\s+\[R=301,L\].*?#End Really Simple Security/is', $htaccess_content)) {
            $redirect_rules_present_in_generic_block = true;
        }
    }
    if ( $ssl_enabled && $redirect_method === 'htaccess' ) {
        if ( !$htaccess_content ) {
            $messages[] = ['type' => 'error', 'text' => '<strong>HTTPS Redirect:</strong> Setting is "htaccess redirect", but the .htaccess file is missing or unreadable.'];
            $errors++;
        } elseif ( ! $has_specific_redirect_block && ! $redirect_rules_present_in_generic_block ) {
            $messages[] = ['type' => 'error', 'text' => '<strong>HTTPS Redirect:</strong> Setting is "htaccess redirect", but no definitive Really Simple SSL redirect rules were found within a dedicated or generic RSSSL block in .htaccess.'];
            $errors++;
        } elseif ($redirect_rules_present_in_generic_block && !$has_specific_redirect_block) {
            $messages[] = ['type' => 'success', 'text' => '<strong>HTTPS Redirect:</strong> Setting is "htaccess redirect" and redirect rules appear to be present within a generic "#Begin/End Really Simple Security" block.'];
        } else {
            $messages[] = ['type' => 'success', 'text' => '<strong>HTTPS Redirect:</strong> Setting is "htaccess redirect" and a specific redirect block is present.'];
        }
    } elseif ( $htaccess_content && ($has_specific_redirect_block || $redirect_rules_present_in_generic_block) && ( ! $ssl_enabled || $redirect_method !== 'htaccess' ) ) {
        $messages[] = ['type' => 'warning', 'text' => '<strong>HTTPS Redirect:</strong> Really Simple SSL redirect rules found in .htaccess, but SSL is not enabled for htaccess redirection in settings.'];
        $warnings++;
    } else {
         $messages[] = ['type' => 'info', 'text' => '<strong>HTTPS Redirect:</strong> No .htaccess redirect rules expected or found based on current settings.'];
    }

    // 2. Firewall / Advanced Headers
    $firewall_setting_enabled = rsssl_get_option( 'enable_firewall' );
    $messages[] = ['type' => 'info', 'text' => '<strong>Firewall Setting:</strong> <code>enable_firewall</code> is currently <strong>' . ($firewall_setting_enabled ? 'ON' : 'OFF') . '</strong>.'];

    if ($adv_headers_exists) {
        $messages[] = ['type' => 'success', 'text' => '<strong>Firewall File Check:</strong> <code>advanced-headers.php</code> FOUND in <code>' . esc_html(basename($wp_content_dir)) . '</code>.'];
    } else {
        $messages[] = ['type' => ($firewall_setting_enabled ? 'error' : 'info'), 'text' => '<strong>Firewall File Check:</strong> <code>advanced-headers.php</code> is MISSING from <code>' . esc_html(basename($wp_content_dir)) . '</code>.' . ($firewall_setting_enabled ? ' This is critical for firewall operation.' : '')];
        if ($firewall_setting_enabled) $errors++;
    }

    if ($firewall_php_exists) {
        $messages[] = ['type' => 'success', 'text' => '<strong>Firewall File Check:</strong> <code>firewall.php</code> (ruleset) FOUND in <code>' . esc_html(basename($wp_content_dir)) . '</code>.'];
    } else {
        $messages[] = ['type' => ($firewall_setting_enabled ? 'warning' : 'info'), 'text' => '<strong>Firewall File Check:</strong> <code>firewall.php</code> (ruleset) is MISSING from <code>' . esc_html(basename($wp_content_dir)) . '</code>.' . ($firewall_setting_enabled ? ' This file contains core firewall rules and is needed if advanced-headers.php loads it.' : '')];
        if ($firewall_setting_enabled) $warnings++; // Warning because advanced-headers.php is the primary loader
    }
    
    $auto_prepend_config_option = get_option( 'rsssl_auto_prepend_config');
    $messages[] = ['type' => 'info', 'text' => '<strong>Firewall Config (Option):</strong> Detected <code>rsssl_auto_prepend_config</code> option value: <code>' . esc_html( var_export($auto_prepend_config_option, true) ) . '</code>'];
    
    $live_server_prepend_config = 'N/A';
    if (function_exists('RSSSL') && isset(RSSSL()->server) && method_exists(RSSSL()->server, 'auto_prepend_config')) {
        $live_server_prepend_config = RSSSL()->server->auto_prepend_config();
    }
    $messages[] = ['type' => 'info', 'text' => '<strong>Firewall Config (Live Detection):</strong> <code>RSSSL()->server->auto_prepend_config()</code> returns: <code>' . esc_html( var_export($live_server_prepend_config, true) ) . '</code>'];

    $effective_prepend_config = ($live_server_prepend_config !== 'N/A') ? $live_server_prepend_config : $auto_prepend_config_option;
    $is_effective_prepend_config_active = !empty($effective_prepend_config) && $effective_prepend_config !== 'disabled' && $effective_prepend_config !== false;
    $has_auto_prepend_block = $htaccess_content ? preg_match( '/#Begin Really Simple Auto Prepend File.*?#End Really Simple Auto Prepend File/is', $htaccess_content ) : false;

    if ( $firewall_setting_enabled ) {
        if ( $adv_headers_exists ) { // Only check .htaccess prepend if advanced-headers.php exists
            if ( $is_effective_prepend_config_active && $effective_prepend_config !== 'nginx' && $effective_prepend_config !== 'iis' ) { // Nginx/IIS don't use .htaccess for this
                if ( !$htaccess_content ) {
                    $messages[] = ['type' => 'error', 'text' => '<strong>Firewall/Headers (Auto Prepend):</strong> Firewall is configured for auto_prepend (Effective: <code>' . esc_html($effective_prepend_config) . '</code>), but .htaccess is missing/unreadable.'];
                    $errors++;
                } elseif ( !$has_auto_prepend_block ) {
                    $messages[] = ['type' => 'error', 'text' => '<strong>Firewall/Headers (Auto Prepend):</strong> Firewall is configured for auto_prepend (Effective: <code>' . esc_html($effective_prepend_config) . '</code>), but the "Really Simple Auto Prepend File" block is MISSING in .htaccess.'];
                    $errors++;
                } else {
                    $auto_prepend_block_content = '';
                    if ($htaccess_content && preg_match('/(#Begin Really Simple Auto Prepend File.*?#End Really Simple Auto Prepend File)/is', $htaccess_content, $block_matches)){
                        $auto_prepend_block_content = $block_matches[1];
                    }

                    $expected_directive_pattern = '/php_value\s+auto_prepend_file\s+' . preg_quote($advanced_headers_file_path, '/') . '/i';

                    if ( preg_match($expected_directive_pattern, $auto_prepend_block_content) ) {
                        $messages[] = ['type' => 'success', 'text' => '<strong>Firewall/Headers (Auto Prepend):</strong> Firewall is configured for auto_prepend (Effective: <code>' . esc_html($effective_prepend_config) . '</code>), and the .htaccess block correctly references <code>' . esc_html($advanced_headers_file_path) . '</code>.'];
                        
                        // Check for .user.ini protection rules
                        $user_ini_filename = ini_get('user_ini.filename');
                        if ( !empty($user_ini_filename) && $user_ini_filename !== false ) {
                            // As per firewall-manager.php: sprintf( '<Files "%s">', addcslashes( $userIni, '"' ) )
                            // So, if $user_ini_filename contains a double quote, it will be backslash-escaped.
                            $user_ini_filename_in_directive = addcslashes($user_ini_filename, '"');
                            $regex_escaped_user_ini_filename = preg_quote($user_ini_filename_in_directive, '/');
                            
                            $files_block_pattern = '/<Files\s+"' . $regex_escaped_user_ini_filename . '">\s*<IfModule mod_authz_core\.c>\s*Require all denied\s*<\/IfModule>\s*<IfModule !mod_authz_core\.c>\s*Order deny,allow\s*Deny from all\s*<\/IfModule>\s*<\/Files>/is';

                            if (preg_match($files_block_pattern, $auto_prepend_block_content)) {
                                $messages[] = ['type' => 'success', 'text' => '<strong>Firewall/Headers (.user.ini Protection):</strong> Found expected protection rules for <code>' . esc_html($user_ini_filename) . '</code> within the auto prepend block.'];
                            } else {
                                $messages[] = ['type' => 'error', 'text' => '<strong>Firewall/Headers (.user.ini Protection):</strong> Expected protection rules for <code>' . esc_html($user_ini_filename) . '</code> are MISSING within the auto prepend block.'];
                                $errors++;
                            }
                        } else {
                            // Check if .user.ini rules are absent when not expected
                            // A bit generic to catch if *any* such block is there when not expected.
                            $any_files_block_pattern = '/<Files\s+"[^"]+">\s*<IfModule mod_authz_core\.c>\s*Require all denied\s*<\/IfModule>\s*<IfModule !mod_authz_core\.c>\s*Order deny,allow\s*Deny from all\s*<\/IfModule>\s*<\/Files>/is';
                            if (preg_match($any_files_block_pattern, $auto_prepend_block_content)) {
                                $file_match_output = '';
                                if (preg_match('/<Files\s+"([^"]+)">/is', $auto_prepend_block_content, $file_name_match)) {
                                    $file_match_output = ' (found for <code>' . esc_html(stripslashes($file_name_match[1])) . '</code>)';
                                }
                                $messages[] = ['type' => 'warning', 'text' => '<strong>Firewall/Headers (.user.ini Protection):</strong> No <code>user_ini.filename</code> is active on this server, but .user.ini protection-like rules were unexpectedly found in the auto prepend block' . $file_match_output . '.'];
                                $warnings++;
                            } else {
                                $messages[] = ['type' => 'info', 'text' => '<strong>Firewall/Headers (.user.ini Protection):</strong> No <code>user_ini.filename</code> is active on this server, and no unexpected .user.ini protection rules found in the auto prepend block.'];
                            }
                        }

                    } else {
                        $messages[] = ['type' => 'error', 'text' => '<strong>Firewall/Headers (Auto Prepend):</strong> Firewall is configured for auto_prepend (Effective: <code>' . esc_html($effective_prepend_config) . '</code>), the .htaccess block exists, but it does NOT correctly reference <code>' . esc_html($advanced_headers_file_path) . '</code> (looking for full path).'];
                        $errors++;
                    }
                }
            } elseif ($is_effective_prepend_config_active && ($effective_prepend_config === 'nginx' || $effective_prepend_config === 'iis')) {
                 $messages[] = ['type' => 'info', 'text' => '<strong>Firewall/Headers (Auto Prepend):</strong> Server detected as <code>' . esc_html($effective_prepend_config) . '</code>. RSSSL relies on manual server configuration for auto_prepend_file, not .htaccess, for this server type.'];
            } elseif ($firewall_setting_enabled) { // if firewall on, but effective config is not active for prepend
                $messages[] = ['type' => 'warning', 'text' => '<strong>Firewall/Headers:</strong> Firewall is ON and <code>advanced-headers.php</code> exists, but RSSSL is not effectively configured to activate <code>auto_prepend_file</code> (Effective config: <code>' . esc_html(var_export($effective_prepend_config, true)) . '</code>). Firewall rules may not be loading via this method.'];
                $warnings++;
            }
        } // end if $adv_headers_exists (for .htaccess checks)
    } else { // Firewall setting is OFF
        if ($htaccess_content && $has_auto_prepend_block) {
            $messages[] = ['type' => 'warning', 'text' => '<strong>Firewall/Headers (Auto Prepend):</strong> Firewall setting is OFF, but an "Auto Prepend File" block was found in .htaccess. Consider removing it.'];
            $warnings++;
        }
        // No need to warn about leftover advanced-headers.php or firewall.php if main setting is off, already covered by initial file checks if they are missing when ON.
        $messages[] = ['type' => 'info', 'text' => '<strong>Firewall/Headers:</strong> Firewall setting is OFF. Related .htaccess block status checked.'];
    }

    // 3. Let's Encrypt ACME Challenge
    $lets_encrypt_allowed = false;
    if (function_exists('rsssl_letsencrypt_generation_allowed')) {
        $lets_encrypt_allowed = rsssl_letsencrypt_generation_allowed(true);
    }
    $has_le_block = $htaccess_content ? preg_match( '/#BEGIN Really Simple Security LETS ENCRYPT.*?#END Really Simple Security LETS ENCRYPT/is', $htaccess_content ) : false;
    if ($lets_encrypt_allowed) {
        if (!$htaccess_content) {
            $messages[] = ['type' => 'warning', 'text' => '<strong>Let\'s Encrypt:</strong> Let\'s Encrypt is configured, but .htaccess file is missing/unreadable. ACME challenge rules cannot be verified.'];
            $warnings++;
        } elseif (!$has_le_block) {
            $messages[] = ['type' => 'warning', 'text' => '<strong>Let\'s Encrypt:</strong> Let\'s Encrypt seems to be configured, but the ACME challenge rule block was not found in .htaccess. This might be okay if validation is handled differently or not currently needed.'];
            $warnings++;
        } else {
            $messages[] = ['type' => 'success', 'text' => '<strong>Let\'s Encrypt:</strong> Let\'s Encrypt is configured and the ACME challenge rule block is present.'];
        }
    } elseif ($htaccess_content && $has_le_block && !$lets_encrypt_allowed) {
        $messages[] = ['type' => 'warning', 'text' => '<strong>Let\'s Encrypt:</strong> ACME challenge rule block found in .htaccess, but Let\'s Encrypt does not seem to be active or require it.'];
        $warnings++;
    } else {
        $messages[] = ['type' => 'info', 'text' => '<strong>Let\'s Encrypt:</strong> No Let\'s Encrypt ACME challenge rules expected or found based on current settings.'];
    }

    // 4. Standard WordPress Rules
    $has_wp_begin_marker = $htaccess_content ? preg_match( '/^# BEGIN WordPress/im', $htaccess_content ) : false;
    $has_wp_end_marker = $htaccess_content ? preg_match( '/^# END WordPress/im', $htaccess_content ) : false;
    $wp_block_content = '';
    $found_wp_rules = [
        'RewriteRule ^index\\.php$ - [L]' => false,
        'RewriteCond %{REQUEST_FILENAME} !-f' => false,
        'RewriteCond %{REQUEST_FILENAME} !-d' => false,
        'RewriteRule \\s*.*?index\\.php [L]' => false,
    ];
    $rule_keys = array_keys($found_wp_rules);

    if ( $has_wp_begin_marker && $has_wp_end_marker ) {
        if ( $htaccess_content && preg_match( '/(^# BEGIN WordPress.*?^# END WordPress)/ims', $htaccess_content, $matches ) ) {
            $wp_block_content = $matches[1];
            foreach ( $rule_keys as $rule_pattern_key ) {
                $pattern_to_match = str_replace('\\.', '.', $rule_pattern_key);
                if ( preg_match( '/' . $pattern_to_match . '/i', $wp_block_content ) ) {
                    $found_wp_rules[$rule_pattern_key] = true;
                }
            }
        }
        $all_core_rules_found = !in_array(false, $found_wp_rules, true);
        if ($all_core_rules_found) {
            $messages[] = ['type' => 'success', 'text' => '<strong>WordPress Core Rules:</strong> Standard WordPress .htaccess block found with essential permalink rules.'];
        } else {
            $missing_rules_text = 'Missing or modified: ';
            $missing_count = 0;
            foreach($found_wp_rules as $rule => $is_found) {
                if (!$is_found) {
                    $missing_rules_text .= ($missing_count > 0 ? ', ' : '') . '<code>' . esc_html($rule) . '</code>';
                    $missing_count++;
                }
            }
            $messages[] = ['type' => 'warning', 'text' => '<strong>WordPress Core Rules:</strong> Standard WordPress .htaccess block found, but some essential permalink rules appear to be missing or modified. ' . $missing_rules_text . '. This could affect permalinks.'];
            $warnings++;
        }
    } elseif ($htaccess_content && ($has_wp_begin_marker && ! $has_wp_end_marker) ) {
        $messages[] = ['type' => 'error', 'text' => '<strong>WordPress Core Rules:</strong> Found <code># BEGIN WordPress</code> marker but no matching <code># END WordPress</code>. .htaccess file may be corrupted.'];
        $errors++;
    } elseif ($htaccess_content && (!$has_wp_begin_marker && $has_wp_end_marker) ) {
        $messages[] = ['type' => 'error', 'text' => '<strong>WordPress Core Rules:</strong> Found <code># END WordPress</code> marker but no matching <code># BEGIN WordPress</code>. .htaccess file may be corrupted.'];
        $errors++;
    } elseif ($htaccess_content) { 
        $messages[] = ['type' => 'error', 'text' => '<strong>WordPress Core Rules:</strong> Standard WordPress .htaccess block (<code># BEGIN WordPress</code> ... <code># END WordPress</code>) is missing. Permalinks may not function correctly.'];
        $errors++;
    } else if (!$htaccess_content && ($has_wp_begin_marker || $has_wp_end_marker) ) { 
        $messages[] = ['type' => 'error', 'text' => '<strong>WordPress Core Rules:</strong> .htaccess is unreadable/missing, but WordPress markers might be expected. Permalinks likely not working.'];
        $errors++;
    }

    // 5. Check for Duplicate RSSSL Blocks
    if ($htaccess_content) {
        $rsssl_htaccess_markers = [
            'auto_prepend' => ["#Begin Really Simple Auto Prepend File", "#End Really Simple Auto Prepend File"],
            'lets_encrypt' => ["#BEGIN Really Simple Security LETS ENCRYPT", "#END Really Simple Security LETS ENCRYPT"],
            'redirect'     => ["#Begin Really Simple Redirect", "#End Really Simple Redirect"],
            'old_redirect' => ["#Begin Really Simple SSL", "#End Really Simple SSL"], 
            'security'     => ["#Begin Really Simple Security", "#End Really Simple Security"], 
        ];

        $found_duplicate_blocks = false;
        $duplicate_block_details = [];

        foreach ($rsssl_htaccess_markers as $block_type => $markers) {
            $start_marker = preg_quote($markers[0], '/');
            $end_marker = preg_quote($markers[1], '/');
            $pattern = '/' . $start_marker . '.*?' . $end_marker . '/is';
            $count = preg_match_all($pattern, $htaccess_content, $matches);

            if ($count > 1) {
                $found_duplicate_blocks = true;
                $duplicate_block_details[] = 'Type "<code>' . esc_html($block_type) . '</code>" (markers: <code>' . esc_html($markers[0]) . '</code> ... <code>' . esc_html($markers[1]) . '</code>) found ' . $count . ' times.';
            }
        }

        if ($found_duplicate_blocks) {
            $messages[] = ['type' => 'error', 'text' => '<strong>Duplicate .htaccess Blocks:</strong> Multiple instances of RSSSL configuration blocks were found:<ul><li>' . implode("</li><li>", $duplicate_block_details) . '</li></ul>This can cause conflicts. Ensure only one of each RSSSL block type exists.'];
            $errors++;
        } else {
            $messages[] = ['type' => 'success', 'text' => '<strong>Duplicate .htaccess Blocks:</strong> No duplicate RSSSL configuration blocks found.'];
        }
    }

    // Display Messages
    if ( !empty($messages) ) {
        echo '<div class="notice rsssl-hv-notice" style="border-left-color: #0073aa;">';
        echo '<h4>Really Simple SSL .htaccess Validation (v1.1):</h4>';
        echo '<ul>';
        foreach ( $messages as $message ) {
            $style = 'padding: 5px 0; border-bottom: 1px solid #eee;';
            $current_icon = $icon_info;
            $color = '#50575e';

            switch ($message['type']) {
                case 'error': $current_icon = $icon_error; $color = '#d63638'; break;
                case 'warning': $current_icon = $icon_warning; $color = '#dea500'; break;
                case 'success': $current_icon = $icon_success; $color = '#2271b1'; break;
            }
            echo '<li style="' . esc_attr($style . ' color: ' . $color) . '"><span style="margin-right: 8px; display: inline-block; vertical-align: top;">' . $current_icon . '</span><span style="display: inline-block; vertical-align: top;">' . wp_kses_post( $message['text' ] ) . '</span></li>';
        }
        echo '</ul>';
        if ($errors > 0) {
            echo '<p style="color: #d63638;"><strong>Found ' . esc_html($errors) . ' critical ' . _n('discrepancy', 'discrepancies', $errors, 'rsssl-hv') . '.</strong></p>';
        }
        if ($warnings > 0) {
            echo '<p style="color: #dea500;"><strong>Found ' . esc_html($warnings) . ' potential ' . _n('issue', 'issues', $warnings, 'rsssl-hv') . ' or inactive rules.</strong></p>';
        }
        if ($errors === 0 && $warnings === 0 && !empty($htaccess_content)) {
            echo '<p style="color: #2271b1;"><strong>All checked .htaccess rules and file components appear consistent with settings and WordPress standards.</strong></p>';
        } elseif (empty($htaccess_content) && $errors === 0 && $warnings === 0) {
             echo '<p style="color: #50575e;"><strong>.htaccess file is currently missing or unreadable. Some features may not be active.</strong></p>';
        }
        echo '<p><small>Note: This validator checks common rules and file presence. For complex setups or specific header rules, manual verification might still be needed. This MU plugin does not make any changes.</small></p>';
        echo '</div>';
        ?>
        <style>
            .rsssl-hv-notice ul { list-style: none; padding-left: 0; }
            .rsssl-hv-notice li:last-child { border-bottom: none; }
        </style>
        <?php
    }
}

?> 