<?php
/**
 * Plugin Name: Really Simple SSL .htaccess Tracker
 * Description: Shows a history and diff of changes to the .htaccess file. Access it via ?show_htaccess_diff in the admin.
 * Version: 1.0
 * Author: Really Simple SSL
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define('RSSSL_RECORDS_HISTORY_VERSION', true);

add_action( 'admin_init', 'rsssl_show_htaccess_diff_page' );

function rsssl_show_htaccess_diff_page() {
    if ( isset( $_GET['show_htaccess_tracker'] ) && current_user_can( 'manage_options' ) ) {
        ?>
        <!DOCTYPE html>
        <html <?php language_attributes(); ?>>
        <head>
            <meta charset="<?php bloginfo( 'charset' ); ?>">
            <title>.htaccess Change History</title>
            <style>
                body {
                    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
                    margin: 20px;
                    background: #f0f0f1;
                    color: #1d2327;
                }
                h1 {
                    font-size: 23px;
                    font-weight: 600;
                    margin-bottom: 20px;
                    border-bottom: 1px solid #c8d7e1;
                    padding-bottom: 10px;
                }
                .entry {
                    border: 1px solid #c8d7e1;
                    padding: 15px;
                    margin-bottom: 20px;
                    background: #fff;
                    box-shadow: 0 1px 1px rgba(0,0,0,.04);
                }
                .entry h2 {
                    margin-top: 0;
                    font-size: 16px;
                    font-weight: 600;
                }
                .entry p {
                    margin: 0 0 1em;
                }
                table.diff {
                    width: 100%;
                    border-collapse: collapse;
                    border: 1px solid #c8d7e1;
                    margin-top: 1em;
                }
                table.diff th {
                    background: #f0f0f1;
                    padding: 8px 12px;
                    text-align: left;
                    font-weight: 600;
                }
                table.diff td {
                    padding: 8px 12px;
                    vertical-align: top;
                    font-family: "SFMono-Regular", Consolas, "Liberation Mono", Menlo, Courier, monospace;
                    white-space: pre-wrap;
                    line-height: 1.6;
                }
                /* Responsive diff table */
                .diff-scroll {
                    overflow-x: auto;
                }
                @media (max-width: 600px) {
                    table.diff th,
                    table.diff td {
                        padding: 6px 8px;
                        font-size: 12px;
                    }
                }
                .diff-deletedline {
                    background: #fdd;
                }
                .diff-addedline {
                    background: #dfd;
                }
                .diff-context {
                    color: #777;
                }
                #back-to-site {
                    margin-top: 20px;
                    display: inline-block;
                    margin-bottom: 20px;
                }
                .nav-tab-wrapper {
                    border-bottom: 1px solid #c8d7e1;
                    padding: 0;
                    margin-bottom: 20px;
                }
                .nav-tab {
                    display: inline-block;
                    padding: 10px 15px;
                    border: 1px solid transparent;
                    border-bottom: 0;
                    text-decoration: none;
                    font-size: 14px;
                    background: #f0f0f1;
                    color: #50575e;
                    border-top-left-radius: 3px;
                    border-top-right-radius: 3px;
                    margin-right: 5px;
                    cursor: pointer;
                }
                .nav-tab-active, .nav-tab:hover {
                    background: #fff;
                    border-color: #c8d7e1;
                    border-bottom-color: #fff;
                    color: #1d2327;
                }
                .tab-content {
                    display: none;
                }
                .tab-content.active {
                    display: block;
                }
                pre code {
                    display: block;
                    background: #f9f9f9;
                    padding: 15px;
                    border: 1px solid #ccc;
                    white-space: pre-wrap;
                    word-wrap: break-word;
                }
                .overview-error-row {
                    background: #ffe5e5 !important;
                }
            </style>
        </head>
        <body>
            <h1>.htaccess Change Viewer</h1>
            <a id="back-to-site" href="<?php echo esc_url(admin_url()); ?>">&larr; Back to Dashboard</a>

            <div class="nav-tab-wrapper">
                <a href="#history" class="nav-tab nav-tab-active">Change History</a>
                <a href="#current" class="nav-tab">Current .htaccess Files</a>
                <a href="#firewall" class="nav-tab">Firewall File</a>
                <a href="#overview" class="nav-tab">Overview</a>
            </div>

            <div id="history" class="tab-content active">
                <?php
                // We need the diff engine
                if ( ! function_exists( 'wp_text_diff' ) ) {
                    require_once ABSPATH . 'wp-admin/includes/diff.php';
                }

                $history = get_option( 'rsssl_htaccess_history', [] );

                if ( empty( $history ) ) {
                    echo '<div class="entry"><p>No changes have been recorded yet.</p></div>';
                } else {
                    $history = array_reverse( $history ); // Show most recent first

                    foreach ( $history as $item ) {
                        $user_info = 'Unknown User';
                        if ( ! empty( $item['user_id'] ) ) {
                            $user = get_user_by( 'id', $item['user_id'] );
                            if ( $user ) {
                                $user_info = sprintf(
                                    '<a href="%s">%s (%s)</a>',
                                    esc_url( get_edit_user_link( $user->ID ) ),
                                    esc_html( $user->display_name ),
                                    esc_html( $user->user_login )
                                );
                            }
                        }

                        $timestamp = wp_date( get_option('date_format') . ' ' . get_option('time_format'), $item['timestamp'] );
                        ?>
                        <div class="entry">
                            <h2><?php echo esc_html( $timestamp ); ?> by <?php echo $user_info; // Can contain HTML ?> for marker: <?php echo $item['marker']?></h2>
                            <p>
                                <strong>Action:</strong> <?php echo esc_html( $item['action'] ); ?><br>
                                <strong>Hook:</strong> <?php echo esc_html( $item['hook'] ); ?><br>
                                <strong>Type:</strong> <?php echo esc_html( $item['debug_test'] ); ?><br>
                            </p>
                            <p><strong>File:</strong> <?php echo esc_html( $item['file_path'] ); ?></p>
                            <?php
                            $diff = wp_text_diff( $item['old_content'], $item['new_content'], [
                                'title'      => 'Changes',
                                'title_left' => 'Before',
                                'title_right'=> 'After',
                            ] );

                            if ( $diff ) {
                                echo '<div class="diff-scroll">' . $diff . '</div>';
                            } else {
                                echo '<p>No functional changes detected.</p>';
                            }
                            ?>
                        </div>
                        <?php
                    }
                }
                ?>
            </div>

            <div id="current" class="tab-content">
                <?php
                $paths_to_check = [
                    'Root .htaccess' => get_home_path() . '.htaccess',
                ];
                $upload_dir = wp_get_upload_dir();
                $uploads_htaccess = $upload_dir['basedir'] . '/.htaccess';
                // Only add the uploads .htaccess if it exists, to not confuse users.
                if (file_exists($uploads_htaccess)) {
                    $paths_to_check['Uploads .htaccess'] = $uploads_htaccess;
                }

                foreach ($paths_to_check as $label => $path) {
                    ?>
                    <div class="entry">
                        <h2><?php echo esc_html($label); ?></h2>
                        <p><strong>File Path:</strong> <?php echo esc_html($path); ?></p>
                        <pre><code><?php
                            if (file_exists($path) && is_readable($path)) {
                                echo esc_html(file_get_contents($path));
                            } else {
                                echo 'File does not exist or is not readable.';
                            }
                        ?></code></pre>
                    </div>
                    <?php
                }
                ?>
            </div>

            <div id="firewall" class="tab-content">
	            <?php
	            $firewall_file = get_home_path() . 'wp-content/firewall.php';
                ?>
                    <div class="entry">
                        <h2><?php echo esc_html($label); ?></h2>
                        <p><strong>File Path:</strong> <?php echo esc_html($firewall_file); ?></p>
                        <pre><code><?php
					            if ( is_file($firewall_file) && is_readable($firewall_file)) {
						            echo esc_html(file_get_contents($firewall_file));
					            } else {
						            echo 'File does not exist or is not readable.';
					            }
					            ?></code></pre>
                    </div>
            </div>


            <div id="overview" class="tab-content">
                <?php
                // Overview logic
                $overview_results = [];
                $root_htaccess = get_home_path() . '.htaccess';
                $htaccess_content = (file_exists($root_htaccess) && is_readable($root_htaccess)) ? file_get_contents($root_htaccess) : '';

                // Markers to check
                $markers = [
                    'prepend' => 'Really Simple Auto Prepend File',
                    'redirect' => 'Really Simple Security Redirect',
                    'wordpress' => 'WordPress',
                ];

                // Helper for status icons
                function rsssl_status_icon($ok) {
                    return $ok
                        ? '<span style="color:green;font-weight:bold;">&#10003;</span>'
                        : '<span style="color:red;font-weight:bold;">&#10007;</span>';
                }

                // 1. Check for Prepend marker presence and position
                $prepend_pattern = '/#+\s*BEGIN\s+Really Simple Auto Prepend File.*?#+\s*END\s+Really Simple Auto Prepend File/s';
                preg_match_all($prepend_pattern, $htaccess_content, $prepend_matches, PREG_OFFSET_CAPTURE);
                $prepend_count = count($prepend_matches[0]);
                $prepend_present = $prepend_count > 0;
                $prepend_on_top = false;
                if ($prepend_present) {
                    $first_pos = $prepend_matches[0][0][1];
                    // Allow whitespace or BOM at the start
                    $prepend_on_top = ($first_pos <= 5);
                }
                $overview_results[] = [
                    'label' => 'Really Simple Auto Prepend File marker present',
                    'ok' => $prepend_present,
                    'msg' => $prepend_present ? 'Marker found.' : 'Marker NOT found!'
                ];
                $overview_results[] = [
                    'label' => 'Really Simple Auto Prepend File marker is at the top of the file',
                    'ok' => $prepend_on_top,
                    'msg' => $prepend_on_top ? 'Marker is at the top.' : 'Marker is NOT at the top!'
                ];
                $overview_results[] = [
                    'label' => 'Really Simple Auto Prepend File marker is not duplicated',
                    'ok' => $prepend_count <= 1,
                    'msg' => $prepend_count <= 1 ? 'No duplicates.' : 'Duplicate markers found!'
                ];

                // 2. Check for Redirect marker presence and duplication
                $redirect_pattern = '/#+\s*BEGIN\s+Really Simple Security Redirect.*?#+\s*END\s+Really Simple Security Redirect/s';
                preg_match_all($redirect_pattern, $htaccess_content, $redirect_matches);
                $redirect_count = count($redirect_matches[0]);
                $redirect_present = $redirect_count > 0;
                $overview_results[] = [
                    'label' => 'Really Simple Security Redirect marker present',
                    'ok' => $redirect_present,
                    'msg' => $redirect_present ? 'Marker found.' : 'Marker NOT found!'
                ];
                $overview_results[] = [
                    'label' => 'Really Simple Security Redirect marker is not duplicated',
                    'ok' => $redirect_count <= 1,
                    'msg' => $redirect_count <= 1 ? 'No duplicates.' : 'Duplicate markers found!'
                ];

                // 3. Check for WordPress marker
                $wp_marker_pattern = '/#+\s*BEGIN\s+WordPress/';
                $wp_marker_present = preg_match($wp_marker_pattern, $htaccess_content) === 1;
                $overview_results[] = [
                    'label' => 'WordPress marker present',
                    'ok' => $wp_marker_present,
                    'msg' => $wp_marker_present ? 'WordPress marker found.' : 'WordPress marker NOT found!'
                ];

                // 4. Check if WP Rocket is active
                $wp_rocket_active = false;
                if (function_exists('is_plugin_active')) {
                    $wp_rocket_active = is_plugin_active('wp-rocket/wp-rocket.php');
                } else {
                    // Fallback: check active_plugins option
                    $active_plugins = get_option('active_plugins', []);
                    $wp_rocket_active = in_array('wp-rocket/wp-rocket.php', $active_plugins);
                    // Multisite
                    if (!$wp_rocket_active && is_multisite()) {
                        $network_plugins = get_site_option('active_sitewide_plugins', []);
                        $wp_rocket_active = isset($network_plugins['wp-rocket/wp-rocket.php']);
                    }
                }
                $overview_results[] = [
                    'label' => 'WP Rocket active',
                    'ok' => $wp_rocket_active,
                    'msg' => $wp_rocket_active ? 'WP Rocket is active.' : 'WP Rocket is NOT active.'
                ];
                ?>
                <div class="entry">
                    <h2>Overview of .htaccess Markers and Plugins</h2>
                    <table class="diff">
                        <thead>
                            <tr><th>Status</th><th>Check</th><th>Details</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($overview_results as $result): ?>
                            <tr<?php if (!$result['ok']) echo ' class="overview-error-row"'; ?>>
                                <td><?php echo rsssl_status_icon($result['ok']); ?></td>
                                <td><?php echo esc_html($result['label']); ?></td>
                                <td><?php echo esc_html($result['msg']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    var tabs = document.querySelectorAll('.nav-tab');
                    var tabContents = document.querySelectorAll('.tab-content');

                    tabs.forEach(function(tab) {
                        tab.addEventListener('click', function(e) {
                            e.preventDefault();
                            
                            tabs.forEach(function(item) {
                                item.classList.remove('nav-tab-active');
                            });
                            tab.classList.add('nav-tab-active');

                            var target = tab.getAttribute('href');
                            tabContents.forEach(function(content) {
                                if ('#' + content.id === target) {
                                    content.classList.add('active');
                                } else {
                                    content.classList.remove('active');
                                }
                            });
                        });
                    });
                });
            </script>
        </body>
        </html>
        <?php
        exit;
    }
    if( isset ( $_GET['reset_htaccess_tracker'] ) && current_user_can( 'manage_options' ) ) {
        // reset the htaccess tracker by deleting the option
        delete_option( 'rsssl_htaccess_history' );
    }
} 