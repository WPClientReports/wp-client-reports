<?php
/*
Plugin Name: WP Client Reports
Plugin URI: https://wpclientreports.com/
Description: Display update statistics directly in the WordPress admin or send reports via email.
Version: 1.0.24
Author: WP Client Reports
Author URI: https://wpclientreports.com/
Text Domain: wp-client-reports
Domain Path: /languages/
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WP_CLIENT_REPORTS_VERSION', '1.0.24' );

add_action( 'admin_enqueue_scripts', 'wp_client_reports_scripts' );
/**
 * Add scripts and styles into the admin as needed
 */
function wp_client_reports_scripts() {
	wp_enqueue_style( 'wp-client-reports-css', plugin_dir_url( __FILE__ ) . '/css/wp-client-reports.css', array(), WP_CLIENT_REPORTS_VERSION );

	$screen = get_current_screen();

	if ( $screen && ( $screen->id == 'dashboard_page_wp_client_reports' || $screen->id == 'settings_page_wp_client_reports' ) ) {

		// Check if WP is loading Moment
		if ( ! wp_script_is( 'moment' ) ) {
			wp_enqueue_script( 'moment', plugin_dir_url( __FILE__ ) . 'js/moment.min.js', array(), '2.30.1', true );
		}

		wp_enqueue_script( 'thickbox' );
		wp_enqueue_style( 'thickbox' );
		wp_enqueue_script( 'jquery-ui-datepicker' );
		wp_register_script( 'wp-client-reports-js', plugin_dir_url( __FILE__ ) . 'js/wp-client-reports.js', array(
			'jquery',
			'jquery-ui-datepicker',
			'moment'
		), WP_CLIENT_REPORTS_VERSION, true );

		$date_format = get_option( 'date_format' );
		$utc_offset  = get_option( 'gmt_offset' );
		$js_data     = array(
			'moment_date_format' => wp_client_reports_convert_date_format( $date_format ),
			'site_utc_offset'    => $utc_offset,
			'nowpupdates'        => __( 'No WordPress Core Updates', 'wp-client-reports' ),
			'nopluginupdates'    => __( 'No Plugin Updates', 'wp-client-reports' ),
			'nothemeupdates'     => __( 'No Theme Updates', 'wp-client-reports' )
		);

		wp_localize_script( 'wp-client-reports-js', 'wp_client_reports_data', $js_data );
		wp_enqueue_script( 'wp-client-reports-js' );
	}
}

add_filter( 'plugin_action_links', 'wp_client_reports_plugin_page_links1', 10, 2 );
/**
 * Add Reports and Settings links into plugin page information
 */
function wp_client_reports_plugin_page_links1( $links_array, $plugin_file_name ) {
	if ( strpos( $plugin_file_name, basename( __FILE__ ) ) ) {
		array_unshift( $links_array, '<a href="' . admin_url( 'index.php?page=wp_client_reports' ) . '">' . __( 'Reports', 'wp-client-reports' ) . '</a>' );
		array_unshift( $links_array, '<a href="' . admin_url( 'options-general.php?page=wp_client_reports' ) . '">' . __( 'Settings', 'wp-client-reports' ) . '</a>' );
	}

	return $links_array;
}

add_filter( 'plugin_row_meta', 'wp_client_reports_plugin_page_links2', 10, 4 );
/**
 * Add Docs links into plugin page information
 */
function wp_client_reports_plugin_page_links2( $links_array, $plugin_file_name, $plugin_data, $status ) {
	if ( strpos( $plugin_file_name, basename( __FILE__ ) ) ) {
		$links_array[] = '<a href="https://wpclientreports.com/docs/?utm_source=wordpress&utm_medium=pluginscreen&utm_campaign=wpclientreports" target="_blank">' . __( 'Docs', 'wp-client-reports' ) . '</a>';
	}

	return $links_array;
}

register_activation_hook( __FILE__, 'wp_client_reports_data_install' );
/**
 * On plugin activation create the database tables needed to store updates
 */
function wp_client_reports_data_install() {
	global $wpdb;
	global $wp_client_reports_version;

	$wp_client_reports_table_name = $wpdb->prefix . 'update_tracking';

	$charset_collate = $wpdb->get_charset_collate();

	$wp_client_reports_sql = "CREATE TABLE $wp_client_reports_table_name (
		id mediumint(9) NOT NULL AUTO_INCREMENT,
		date date DEFAULT '0000-00-00' NOT NULL,
        type varchar(191),
        name varchar(191),
        slug varchar(191),
        version_before varchar(191),
        version_after varchar(191),
        active tinyint(1),
		UNIQUE KEY id (id)
	) $charset_collate;";

	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
	dbDelta( $wp_client_reports_sql );

	add_option( 'wp_client_reports_version', WP_CLIENT_REPORTS_VERSION );
	add_option( 'wp_client_reports_enable_updates', 'on' );
	add_option( 'wp_client_reports_enable_content_stats', 'on' );

	wp_client_reports_check_for_updates();
}

add_action( 'init', 'wp_client_reports_load_actions', 985 );
/**
 * Load actions if options are enabled
 */
function wp_client_reports_load_actions() {
	if ( is_admin() || wp_doing_cron() ) {

		$updates_enabled = get_option( 'wp_client_reports_enable_updates' );
		if ( $updates_enabled == 'on' ) {
			add_action( 'wp_client_reports_stats', 'wp_client_reports_stats_page_updates', 10 );
			add_action( 'wp_client_reports_stats_email', 'wp_client_reports_stats_email_updates', 10, 2 );
			add_action( 'wp_ajax_wp_client_reports_updates_data', 'wp_client_reports_updates_data' );
		}

		$content_stats_enabled = get_option( 'wp_client_reports_enable_content_stats' );
		if ( $content_stats_enabled == 'on' ) {
			add_action( 'wp_client_reports_stats', 'wp_client_reports_stats_page_content', 30 );
			add_action( 'wp_client_reports_stats_email', 'wp_client_reports_stats_email_content', 30, 2 );
			add_action( 'wp_ajax_wp_client_reports_content_stats_data', 'wp_client_reports_content_stats_data' );
		}
	}
}

register_activation_hook( __FILE__, 'wp_client_reports_check_for_updates_daily_schedule' );
/**
 * On plugin activation schedule our daily check for updates
 */
function wp_client_reports_check_for_updates_daily_schedule() {
	//Use wp_next_scheduled to check if the event is already scheduled
	$timestamp = wp_next_scheduled( 'wp_client_reports_check_for_updates_daily' );
	//If $timestamp == false schedule daily backups since it hasn't been done previously
	if ( $timestamp == false ) {
		$timezone = wp_timezone();
		$midnight = new DateTime( "00:00:00", $timezone );
		//Schedule the event for right now, then to repeat daily
		wp_schedule_event( $midnight->format( 'U' ), 'daily', 'wp_client_reports_check_for_updates_daily' );
	}
}

register_deactivation_hook( __FILE__, 'wp_client_reports_check_for_updates_daily_schedule_clear' );
/**
 * On plugin deactivation remove the scheduled events
 */
function wp_client_reports_check_for_updates_daily_schedule_clear() {
	wp_clear_scheduled_hook( 'wp_client_reports_check_for_updates_daily' );
}

add_action( 'upgrader_process_complete', 'wp_client_reports_after_update', 10, 2 );
/**
 * After an update has run, check and log in database
 */
function wp_client_reports_after_update( $upgrader_object, $options ) {
	if ( $options['action'] == 'update' ) {
		wp_client_reports_check_for_updates();
	}
}

add_action( 'wp_client_reports_check_for_updates_daily', 'wp_client_reports_check_for_updates' );
/**
 * Loop through each type of update and determine if there is now a newer version
 */
function wp_client_reports_check_for_updates() {
	global $wpdb;
	$wp_client_reports_table_name = $wpdb->prefix . 'update_tracking';

	if ( ! function_exists( 'get_plugins' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}

	$timezone  = wp_timezone();
	$now       = new DateTime( "now", $timezone );
	$mysqldate = $now->format( 'Y-m-d' );

	$wordpress_version = get_bloginfo( 'version' );

	$last_wp_update = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $wp_client_reports_table_name WHERE `type` = 'wp' AND `slug` = %s ORDER BY `date` DESC", array( 'wp' ) ) );

	$today_wp_update = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $wp_client_reports_table_name WHERE `type` = 'wp' AND slug = %s AND date = %s", array(
		'wp',
		$mysqldate
	) ) );

	if ( ! $last_wp_update || version_compare( $wordpress_version, $last_wp_update->version_after, '>' ) ) {

		$last_version = null;
		if ( $last_wp_update ) {
			$last_version = $last_wp_update->version_after;
		}

		$update_id = null;
		if ( $today_wp_update ) {
			$update_id = $today_wp_update->id;
		}

		$wp_update = array(
			'id'             => $update_id,
			'date'           => $mysqldate,
			'type'           => 'wp',
			'name'           => 'WordPress',
			'slug'           => 'wp',
			'version_before' => $last_version,
			'version_after'  => $wordpress_version,
			'active'         => null,
		);

		wp_client_reports_track_update( $wp_update );
	}

	$themes = wp_get_themes();

	foreach ( $themes as $theme_slug => $theme ) {

		$theme_active = false;
		$active_theme = get_option( 'stylesheet' );

		if ( $theme_slug == $active_theme ) {
			$theme_active = true;
		}

		$last_theme_update = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $wp_client_reports_table_name WHERE `type` = 'theme' AND `slug` = %s ORDER BY `date` DESC", array( $theme_slug ) ) );

		$today_theme_update = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $wp_client_reports_table_name WHERE `type` = 'theme' AND slug = %s AND date = %s", array(
			$theme_slug,
			$mysqldate
		) ) );

		if ( ! $last_theme_update || version_compare( $theme->get( 'Version' ), $last_theme_update->version_after, '>' ) ) {

			$last_version = null;
			if ( $last_theme_update ) {
				$last_version = $last_theme_update->version_after;
			}

			$update_id = null;
			if ( $today_theme_update ) {
				$update_id = $today_theme_update->id;
			}

			$theme_update = array(
				'id'             => $update_id,
				'date'           => $mysqldate,
				'type'           => 'theme',
				'name'           => $theme['Name'],
				'slug'           => $theme_slug,
				'version_before' => $last_version,
				'version_after'  => $theme['Version'],
				'active'         => $theme_active,
			);

			wp_client_reports_track_update( $theme_update );
		}

	}

	$plugins = get_plugins();

	foreach ( $plugins as $plugin_slug => $plugin ) {

		$plugin_active = false;
		if ( is_plugin_active( $plugin_slug ) ) {
			$plugin_active = true;
		}

		$last_plugin_update = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $wp_client_reports_table_name WHERE `type` = 'plugin' AND `slug` = %s ORDER BY `date` DESC", array( $plugin_slug ) ) );

		$today_plugin_update = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $wp_client_reports_table_name WHERE `type` = 'plugin' AND `slug` = %s AND `date` = %s", array(
			$plugin_slug,
			$mysqldate
		) ) );

		if ( ! $last_plugin_update || version_compare( $plugin['Version'], $last_plugin_update->version_after, '>' ) ) {

			$last_version = null;
			if ( $last_plugin_update ) {
				$last_version = $last_plugin_update->version_after;
			}

			$update_id = null;
			if ( $today_plugin_update ) {
				$update_id = $today_plugin_update->id;
			}

			$plugin_update = array(
				'id'             => $update_id,
				'date'           => $mysqldate,
				'type'           => 'plugin',
				'name'           => $plugin['Name'],
				'slug'           => $plugin_slug,
				'version_before' => $last_version,
				'version_after'  => $plugin['Version'],
				'active'         => $plugin_active,
			);

			wp_client_reports_track_update( $plugin_update );
		}
	}

	do_action( 'wp_client_reports_check' );
}

/**
 * Track a single update and add it to the database
 */
function wp_client_reports_track_update( $thing_to_track ) {

	global $wpdb;
	$wp_client_reports_table_name = $wpdb->prefix . 'update_tracking';

	$new_entry = $wpdb->replace(
		$wp_client_reports_table_name,
		$thing_to_track,
		array(
			'%d',
			'%s',
			'%s',
			'%s',
			'%s',
			'%s',
			'%s',
			'%d',
		)
	);

	return $new_entry;
}

add_action( 'wp_dashboard_setup', 'wp_client_reports_add_dashboard_widget' );
/**
 * Add a widget to the dashboard.
 */
function wp_client_reports_add_dashboard_widget() {
	if ( current_user_can( 'manage_options' ) ) {
		wp_add_dashboard_widget(
			'wp_client_reports_last30_widget',         // Widget slug.
			__( 'Updates Run - Last 30 Days', 'wp-client-reports' ),         // Title.
			'wp_client_reports_last30_widget_function' // Display function.
		);
	}
}

/**
 * Create the function to output the contents of our Dashboard Widget.
 */
function wp_client_reports_last30_widget_function() {

	$timezone          = wp_timezone();
	$start_date_object = new DateTime( '-30 days', $timezone );
	$start_date        = $start_date_object->format( 'Y-m-d' );
	$end_date_object   = new DateTime( "now", $timezone );
	$end_date          = $end_date_object->format( 'Y-m-d' );

	$updates_data = wp_client_reports_get_updates_data( $start_date, $end_date );
	?>
    <div class="wp-client-reports-big-numbers wp-client-reports-postbox wp-client-reports-last30-widget">
        <div class="wp-client-reports-big-number">
            <h2 id="wp-client-reports-wp-update-count"><?php echo esc_html( $updates_data->wp_updated ); ?></h2>
            <h3>
				<?php
				/* translators: %s: <br> tag. */
				printf( __( 'WordPress %s Core Updates', 'wp-client-reports' ), '<br>' );
				?>
            </h3>
        </div><!-- .wp-client-reports-big-number -->
        <div class="wp-client-reports-big-number">
            <h2 id="wp-client-reports-theme-update-count"><?php echo esc_html( $updates_data->total_themes_updated ); ?></h2>
            <h3>
				<?php
				/* translators: %s: <br> tag. */
				printf( __( 'Theme %s Updates', 'wp-client-reports' ), '<br>' );
				?>
            </h3>
        </div><!-- .wp-client-reports-big-number -->
        <div class="wp-client-reports-big-number">
            <h2 id="wp-client-reports-plugin-update-count"><?php echo esc_html( $updates_data->total_plugins_updated ); ?></h2>
            <h3>
				<?php
				/* translators: %s: <br> tag. */
				printf( __( 'Plugin %s Updates', 'wp-client-reports' ), '<br>' );
				?>
            </h3>
        </div><!-- .wp-client-reports-big-number -->
    </div><!-- .wp-client-reports-last30-widget -->
	<?php
}

add_action( 'admin_menu', 'wp_client_reports_add_admin_menu' );
/**
 * Register options pages for the menu
 */
function wp_client_reports_add_admin_menu() {
	add_options_page( __( 'WP Client Reports Settings', 'wp-client-reports' ), __( 'WP Client Reports', 'wp-client-reports' ), 'manage_options', 'wp_client_reports', 'wp_client_reports_options_page' );
	add_submenu_page( 'index.php', __( 'Reports', 'wp-client-reports' ), __( 'Reports', 'wp-client-reports' ), 'manage_options', 'wp_client_reports', 'wp_client_reports_stats_page' );
}

/**
 * Main WP Client Reports page
 */
function wp_client_reports_stats_page() {
	$default_title = get_option( 'wp_client_reports_default_title' );
	if ( ! $default_title ) {
		$default_title = get_bloginfo( 'name' ) . ' ' . __( 'Site Report', 'wp-client-reports' );
	}
	$default_email = get_option( 'wp_client_reports_default_email' );
	if ( ! $default_email ) {
		$default_email = get_bloginfo( 'admin_email' );
	}
	$default_intro = get_option( 'wp_client_reports_default_intro' );
	?>
    <div class="wp-client-reports-stats-screen">
        <div class="wp-client-reports-header">
            <h1><?php _e( 'WP Client Reports', 'wp-client-reports' ); ?></h1>
            <div class="wp-client-reports-date-chooser-area">
                <a href="#TB_inline?width=600&height=550&inlineId=wp-client-reports-which-email-modal"
                   id="wp-client-reports-email-report"
                   class="thickbox button wp-client-reports-email-report-button"><?php _e( 'Email Report', 'wp-client-reports' ); ?>
                    <span class="dashicons dashicons-email"></span></a>
                <a href="<?php echo admin_url( 'options-general.php?page=wp_client_reports' ); ?>"
                   class="button"><?php _e( 'Settings', 'wp-client-reports' ); ?> <span
                            class="dashicons dashicons-admin-settings"></span></a>
                <button id="wp-client-reports-force-refresh"
                        class="button wp-client-reports-force-refresh-button"><?php _e( 'Refresh', 'wp-client-reports' ); ?>
                    <span
                            class="dashicons dashicons-update-alt"></span></button>
                <button id="wp-client-reports-date-chooser-button"
                        class="button button-primary wp-client-reports-date-chooser-button"><span
                            id="wp-client-reports-button-label"><?php _e( 'Last 30 Days', 'wp-client-reports' ); ?></span>
                    <span class="dashicons dashicons-arrow-down"></span>
                </button><!-- #wp-client-reports-date-chooser-menu -->
                <div id="wp-client-reports-date-chooser" style="display:none;">
                    <div class="date-chooser-presets">
                        <ul>
                            <li><a href="#"
                                   id="wp-client-reports-quick-today"><?php _e( 'Today', 'wp-client-reports' ); ?></a>
                            </li>
                            <li><a href="#"
                                   id="wp-client-reports-quick-yesterday"><?php _e( 'Yesterday', 'wp-client-reports' ); ?></a>
                            </li>
                            <li><a href="#"
                                   id="wp-client-reports-quick-last7"><?php _e( 'Last 7 Days', 'wp-client-reports' ); ?></a>
                            </li>
                            <li><a href="#"
                                   id="wp-client-reports-quick-last14"><?php _e( 'Last 14 Days', 'wp-client-reports' ); ?></a>
                            </li>
                            <li><a href="#"
                                   id="wp-client-reports-quick-last30"><?php _e( 'Last 30 Days', 'wp-client-reports' ); ?></a>
                            </li>
                            <li><a href="#"
                                   id="wp-client-reports-quick-lastmonth"><?php _e( 'Last Month', 'wp-client-reports' ); ?></a>
                            </li>
                            <li><a href="#"
                                   id="wp-client-reports-quick-thismonth"><?php _e( 'This Month', 'wp-client-reports' ); ?></a>
                            </li>
                            <li><a href="#"
                                   id="wp-client-reports-quick-last90"><?php _e( 'Last 90 Days', 'wp-client-reports' ); ?></a>
                            </li>
                        </ul>
                    </div>
                    <div id="date-range"></div>
                    <div class="date-chooser-footer">
                        <span class="wp-client-reports-dates"><span id="wp-client-reports-start-date"></span> - <span
                                    id="wp-client-reports-end-date"></span></span>
                        <button class="button"
                                id="wp-client-reports-cancel"><?php _e( 'Cancel', 'wp-client-reports' ); ?></button>
                        <button class="button button-primary"
                                id="wp-client-reports-apply"><?php _e( 'Apply', 'wp-client-reports' ); ?></button>
                    </div><!-- .date-chooser-footer -->
                    <input type="hidden" id="from_value" class="from_value" name="from_value"/><input type="hidden"
                                                                                                      id="to_value"
                                                                                                      class="to_value"
                                                                                                      name="to_value"/>
                </div><!-- #wp-client-reports-date-chooser -->
            </div><!-- .wp-client-reports-date-chooser-area -->
        </div><!-- .wp-client-reports-header -->

		<?php do_action( 'wp_client_reports_stats' ); ?>

		<?php if ( ! is_plugin_active( 'wp-client-reports-pro/wp_client_reports_pro.php' ) ) : ?>
            <p style="margin: 20px 0;text-align:center;">
				<?php
				$link_url   = esc_url( 'https://wpclientreports.com/?utm_source=wordpress&utm_medium=reports&utm_campaign=wpclientreports' );
				$link_open  = "<a href='{$link_url}' target='_blank' rel='noopener noreferrer'>";
				$link_close = '</a>';

				printf(
				/* translators: 1: Opening link tag, 2: Closing link tag */
					__( 'Report created with %1$sWP Client Reports%2$s.', 'wp-client-reports' ),
					$link_open,
					$link_close
				);
				?>
            </p>
		<?php endif; ?>

        <div id="wp-client-reports-which-email-modal" class="wp-client-reports-which-email-modal" style="display:none;">
            <form method="POST" action="#" id="wp-client-reports-send-email-report">
				<?php wp_nonce_field( 'wpcr_send_report_nonce', 'wpcr_send_report_nonce' ); ?>
                <table class="form-table" role="presentation">
                    <tbody>
                    <tr>
                        <th scope="row"><label
                                    for="report-title"><?php _e( 'Report Title', 'wp-client-reports' ); ?></label></th>
                        <td><input name="report_title" type="text" id="report-title"
                                   value="<?php echo esc_attr( $default_title ); ?>" required class="regular-text"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label
                                    for="report-email"><?php _e( 'Send Report Email To', 'wp-client-reports' ); ?></label>
                        </th>
                        <td><input name="report_email" type="text" id="report-email"
                                   value="<?php echo esc_attr( $default_email ); ?>" required class="regular-text">
                            <p class="description"><?php _e( 'You can comma separate multiple addresses' ); ?></p></td>
                    </tr>
                    <tr>
                        <th scope="row"><label
                                    for="report-intro"><?php _e( 'Report Email Introduction (optional)', 'wp-client-reports' ); ?></label>
                        </th>
                        <td><textarea name="report_intro" id="report-intro"
                                      class="large-text"><?php echo esc_textarea( $default_intro ); ?></textarea></td>
                    </tr>
                    </tbody>
                </table>
                <input type="hidden" name="action" value="wp_client_reports_send_email_report">
                <input type="hidden" name="start" class="from_value" id="start_date_email">
                <input type="hidden" name="end" class="to_value" id="end_date_email">
                <p class="submit"><input type="submit" name="submit" id="submit" class="button button-primary"
                                         value="<?php _e( 'Send Now', 'wp-client-reports' ); ?>"><img
                            src="<?php echo admin_url(); ?>images/spinner-2x.gif" id="send-report-spinner"
                            style="display:none;"></p>
            </form>
            <div class="notice wp-client-reports-success" id="wp-client-reports-report-status"
                 style="display:none;margin-top:26px;">
                <p></p>
            </div>
        </div><!-- #wp-client-reports-which-email-modal -->

    </div><!-- .wp-client-reports-stats-screen -->
	<?php
}

/**
 * Software Updates section
 */
function wp_client_reports_stats_page_updates() {
	?>
    <div class="metabox-holder">
        <div class="postbox wp-client-reports-postbox loading" id="wp-client-reports-updates">
            <div class="postbox-header">
                <h2 class="hndle"><?php _e( 'Software Updates', 'wp-client-reports' ); ?></h2>
            </div>
            <div class="inside">
                <div class="main">
                    <div class="wp-client-reports-big-numbers">
						<?php
						wp_client_reports_render_big_number(
						/* translators: %s: <br> tag. */
							sprintf( __( 'Total %s Updates', 'wp-client-reports' ), '<br>' ),
							'wp-client-reports-total-update-count'
						);
						wp_client_reports_render_big_number(
						/* translators: %s: <br> tag. */
							sprintf( __( 'WordPress %s Core Updates', 'wp-client-reports' ), '<br>' ),
							'wp-client-reports-wp-update-count'
						);
						wp_client_reports_render_big_number(
						/* translators: %s: <br> tag. */
							sprintf( __( 'Plugin %s Updates', 'wp-client-reports' ), '<br>' ),
							'wp-client-reports-plugin-update-count'
						);
						wp_client_reports_render_big_number(
						/* translators: %s: <br> tag. */
							sprintf( __( 'Theme %s Updates', 'wp-client-reports' ), '<br>' ),
							'wp-client-reports-theme-update-count'
						);
						?>
                    </div><!-- .wp-client-reports-big-numbers -->

					<?php
					$include_update_details = apply_filters( 'wp_client_reports_include_update_details', true );
					if ( $include_update_details === true ) :
						?>

                        <div class="wp-client-report-section wp-client-report-border-top">

                            <h3><?php _e( 'WordPress Core Updates', 'wp-client-reports' ); ?></h3>
                            <ul id="wp-client-reports-wp-updates-list" class="wp-client-reports-list"></ul>

                        </div>
                        <div class="wp-client-report-section wp-client-report-border-top">

                            <h3><?php _e( 'Plugin Updates', 'wp-client-reports' ); ?></h3>
                            <ul id="wp-client-reports-plugin-updates-list" class="wp-client-reports-list"></ul>

                        </div>
                        <div class="wp-client-report-section wp-client-report-border-top">

                            <h3><?php _e( 'Theme Updates', 'wp-client-reports' ); ?></h3>
                            <ul id="wp-client-reports-theme-updates-list" class="wp-client-reports-list"></ul>

                        </div><!-- .wp-client-report-section -->

					<?php endif; //$include_update_details ?>

                </div><!-- .inside -->
            </div><!-- .main -->
        </div><!-- .postbox -->

    </div><!-- .metabox-holder -->
	<?php
}

/**
 * Ajax call for software updates stats data
 */
function wp_client_reports_updates_data() {

	if ( ! current_user_can( 'manage_options' ) ) {
		echo json_encode( [
			'status'  => 'error',
			'message' => __( 'You do not have administrator privileges.', 'wp-client-reports' )
		] );
		wp_die();
	}

	$start = null;
	$end   = null;
	if ( isset( $_GET['start'] ) ) {
		$start = sanitize_text_field( $_GET['start'] );
	}
	if ( isset( $_GET['end'] ) ) {
		$end = sanitize_text_field( $_GET['end'] );
	}

	$dates = wp_client_reports_validate_dates( $start, $end );

	$data = wp_client_reports_get_updates_data( $dates->start_date, $dates->end_date );

	print json_encode( $data );
	wp_die();

}

/**
 * Validate dates anytime you get a request for data
 */
function wp_client_reports_validate_dates( $start, $end ) {
	$dates    = new \stdClass;
	$timezone = wp_timezone();
	if ( isset( $start ) && isset( $end ) ) {
		$start_date_object = DateTime::createFromFormat( 'Y-m-d', $start, $timezone );
		$dates->start_date = $start_date_object->format( 'Y-m-d' );
		$end_date_object   = DateTime::createFromFormat( 'Y-m-d', $end, $timezone );
		$dates->end_date   = $end_date_object->format( 'Y-m-d' );
	} else {
		$dates->start_date = date( 'Y-m-d', strtotime( '-30 days' ) );
		$dates->end_date   = date( 'Y-m-d' );
	}

	return $dates;
}

/**
 * Get the software updates data from the database
 */
function wp_client_reports_get_updates_data( $start_date, $end_date ) {

	global $wpdb;
	$wp_client_reports_table_name = $wpdb->prefix . 'update_tracking';

	$data = new \stdClass;

	$update_results = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $wp_client_reports_table_name WHERE `version_before` IS NOT NULL AND `date` >= %s AND `date` <= %s ORDER BY `date` ASC", array(
		$start_date,
		$end_date
	) ) );

	$data                        = new \stdClass;
	$data->total_updates         = 0;
	$data->total_themes_updated  = 0;
	$data->total_plugins_updated = 0;
	$data->wp_updated            = 0;
	$data->updates               = [];

	if ( isset( $update_results ) && is_array( $update_results ) ) {
		$data->updates = $update_results;
		foreach ( $data->updates as $update ) {
			$data->total_updates ++;
			if ( $update->type == 'theme' ) {
				$data->total_themes_updated ++;
			}
			if ( $update->type == 'plugin' ) {
				$data->total_plugins_updated ++;
			}
			if ( $update->type == 'wp' ) {
				$data->wp_updated ++;
			}
		}
	}

	$data = apply_filters( 'wp_client_reports_updates_data', $data, $start_date, $end_date );

	return $data;
}

add_filter( 'wp_client_reports_email_data', 'wp_client_reports_email_updates_data', 11, 3 );
/**
 * Filter the data when a report email is being put together and add software updates
 */
function wp_client_reports_email_updates_data( $data, $start_date, $end_date ) {
	$updates       = new \stdClass;
	$updates       = wp_client_reports_get_updates_data( $start_date, $end_date );
	$data->updates = $updates;

	return $data;
}

add_action( 'wp_ajax_wp_client_reports_force_refresh', 'wp_client_reports_force_refresh' );
/**
 * Force an update to the software update statistics
 */
function wp_client_reports_force_refresh() {
	if ( ! current_user_can( 'manage_options' ) ) {
		echo json_encode( [
			'status'  => 'error',
			'message' => __( 'You do not have administrator privileges.', 'wp-client-reports' )
		] );
		wp_die();
	}

	wp_client_reports_check_for_updates();

	do_action( 'wp_client_reports_force_update' );

	print json_encode( [ 'status' => 'success' ] );
	wp_die();
}

/**
 * Ajax call for content stats data
 */
function wp_client_reports_content_stats_data() {
	if ( ! current_user_can( 'manage_options' ) ) {
		echo json_encode( [
			'status'  => 'error',
			'message' => __( 'You do not have administrator privileges.', 'wp-client-reports' )
		] );
		wp_die();
	}

	$start = null;
	$end   = null;
	if ( isset( $_GET['start'] ) ) {
		$start = sanitize_text_field( $_GET['start'] );
	}
	if ( isset( $_GET['end'] ) ) {
		$end = sanitize_text_field( $_GET['end'] );
	}

	$dates = wp_client_reports_validate_dates( $start, $end );

	$data = wp_client_reports_get_content_stats_data( $dates->start_date, $dates->end_date );

	print json_encode( $data );
	wp_die();
}


/**
 * Get the content stats data from the database
 */
function wp_client_reports_get_content_stats_data( $start_date, $end_date ) {
	global $wpdb;
	$posts_table_name    = $wpdb->prefix . 'posts';
	$comments_table_name = $wpdb->prefix . 'comments';

	$data = new \stdClass;

	$posts_count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $posts_table_name WHERE `post_status` = 'publish' AND `post_type` = 'post' AND `post_date_gmt` >= %s AND `post_date_gmt` <= %s", array(
		$start_date . ' 00:00:00',
		$end_date . ' 23:59:59'
	) ) );

	$pages_count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $posts_table_name WHERE `post_status` = 'publish' AND `post_type` = 'page' AND `post_date_gmt` >= %s AND `post_date_gmt` <= %s", array(
		$start_date . ' 00:00:00',
		$end_date . ' 23:59:59'
	) ) );

	$comments_count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $comments_table_name WHERE `comment_approved` = 1 AND `comment_type` = '' AND `comment_date_gmt` >= %s AND `comment_date_gmt` <= %s", array(
		$start_date . ' 00:00:00',
		$end_date . ' 23:59:59'
	) ) );

	$data                 = new \stdClass;
	$data->posts_count    = intval( $posts_count );
	$data->pages_count    = intval( $pages_count );
	$data->comments_count = intval( $comments_count );

	$data = apply_filters( 'wp_client_reports_content_stats_data', $data, $start_date, $end_date );

	return $data;
}

add_filter( 'wp_client_reports_email_data', 'wp_client_reports_email_content_stats_data', 11, 3 );
/**
 * Filter the data when a report email is being put together and add content stats
 */
function wp_client_reports_email_content_stats_data( $data, $start_date, $end_date ) {
	$updates       = new \stdClass;
	$updates       = wp_client_reports_get_content_stats_data( $start_date, $end_date );
	$data->updates = $updates;

	return $data;
}

/**
 * Stats page for content stats
 */
function wp_client_reports_stats_page_content() {
	?>
    <div class="metabox-holder">
        <div class="postbox wp-client-reports-postbox loading" id="wp-client-reports-content-stats">
            <div class="postbox-header">
                <h2 class="hndle"><?php _e( 'Site Content', 'wp-client-reports' ); ?></h2>
            </div>
            <div class="inside">
                <div class="main">
                    <div class="wp-client-reports-big-numbers">
						<?php
						wp_client_reports_render_big_number(
						/* translators: %s: <br> tag. */
							sprintf( __( 'Posts %s Added', 'wp-client-reports' ), '<br>' ),
							'wp-client-reports-new-posts-count'
						);
						wp_client_reports_render_big_number(
						/* translators: %s: <br> tag. */
							sprintf( __( 'Pages %s Added', 'wp-client-reports' ), '<br>' ),
							'wp-client-reports-new-pages-count'
						);
						wp_client_reports_render_big_number(
						/* translators: %s: <br> tag. */
							sprintf( __( 'Comments %s Added', 'wp-client-reports' ), '<br>' ),
							'wp-client-reports-new-comments-count'
						);
						?>
                    </div><!-- .wp-client-reports-big-numbers -->

                </div><!-- .inside -->
            </div><!-- .main -->
        </div><!-- .postbox -->

    </div><!-- .metabox-holder -->
	<?php
}

add_action( 'wp_ajax_wp_client_reports_send_email_report', 'wp_client_reports_send_email_report_from_ajax' );
/**
 * Handles the AJAX request to send an email report.
 *
 * This function processes an AJAX request to send a custom report via email. It performs several checks before proceeding:
 * - Checks if the current user has administrative privileges to perform the action.
 * - Verifies the nonce to ensure the request is secure and originates from a valid source.
 * - Validates and sanitizes input data from the request.
 *
 * Required POST fields are 'report_title', 'start', and 'end'. The 'report_intro' field is optional.
 * The function also processes a comma-separated list of email addresses from 'report_email',
 * sanitizes them, and validates each email address. If all checks pass, it attempts to send the report.
 *
 * Responses are returned as JSON objects indicating the success or failure of the operation,
 * along with a relevant message.
 *
 * @return void The function directly outputs the JSON response and exits.
 * @uses current_user_can() To check if the current user has administrative privileges.
 * @uses sanitize_text_field() To sanitize the 'report_title', 'start', and 'end' fields.
 * @uses wp_kses_post() To sanitize the 'report_intro' field if present.
 * @uses sanitize_email() To sanitize email addresses.
 * @uses is_email() To validate email addresses.
 * @uses wp_send_json_success() To send a JSON success response.
 * @uses wp_send_json_error() To send a JSON error response.
 *
 * @uses wp_verify_nonce() To verify the nonce for security.
 */
function wp_client_reports_send_email_report_from_ajax() {
	// Check for admin
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( [ 'message' => __( 'You do not have administrative privileges.', 'wp-client-reports' ) ] );
	}

	// Verify nonce
	if ( ! isset( $_POST['wpcr_send_report_nonce'] ) || ! wp_verify_nonce( $_POST['wpcr_send_report_nonce'], 'wpcr_send_report_nonce' ) ) {
		wp_send_json_error( [ 'message' => __( 'Nonce verification failed.', 'wp-client-reports' ) ] );
	}

	// Validate and sanitize input data
	$report_title = sanitize_text_field( $_POST['report_title'] );
	$start        = sanitize_text_field( $_POST['start'] );
	$end          = sanitize_text_field( $_POST['end'] );

	// The report_intro field is optional, sanitize if present
	$report_intro = isset( $_POST['report_intro'] ) ? wp_kses_post( $_POST['report_intro'] ) : '';

	// Required fields check
	if ( empty( $report_title ) || empty( $start ) || empty( $end ) ) {
		wp_send_json_error( [ 'message' => __( 'Missing required field.', 'wp-client-reports' ) ] );
	}

	// Process email addresses
	$report_email_raw   = $_POST['report_email'];
	$report_email_array = explode( ",", $report_email_raw ); // Split by comma
	$report_email       = array_map( 'trim', $report_email_array ); // Trim spaces from each email

	// Sanitize and validate each email address
	$report_email = array_filter( array_map( function ( $email ) {
		$clean_email = sanitize_email( $email );

		return is_email( $clean_email ) ? $clean_email : null;
	}, $report_email ) );

	if ( empty( $report_email ) ) {
		wp_send_json_error( [ 'message' => __( 'Invalid email address.', 'wp-client-reports' ) ] );
	}

	// Attempt to send the report
	$sent = wp_client_reports_send_email_report( $start, $end, $report_title, $report_intro, $report_email );

	// Send appropriate response
	if ( $sent ) {
		wp_send_json_success( [ 'message' => __( 'Report has been sent!', 'wp-client-reports' ) ] );
	} else {
		wp_send_json_error( [ 'message' => __( 'There was an error sending the email.', 'wp-client-reports' ) ] );
	}
}

/**
 * Send an emailed report
 */
function wp_client_reports_send_email_report( $start, $end, $report_title = null, $report_intro = null, $report_email = null ) {
	if ( ! $report_title ) {
		$report_title = sanitize_text_field( get_option( 'wp_client_reports_default_title', null ) );
		if ( ! $report_title ) {
			$report_title = get_bloginfo( 'name' ) . ' ' . __( 'Site Report', 'wp-client-reports' );
		}
	}

	if ( $report_title ) {
		$report_title = stripslashes( $report_title );
	}

	$allowed_html = [
		'p'      => [],
		'br'     => [],
		'strong' => [],
		'em'     => [],
		'b'      => [],
		'i'      => [],
		'a'      => [ 'href' => [] ]
	];

	if ( ! $report_intro ) {
		$report_intro = get_option( 'wp_client_reports_default_intro', null );
	}

	if ( $report_intro ) {
		$report_intro = wpautop( $report_intro );
		$report_intro = stripslashes( wp_kses( $report_intro, $allowed_html ) );
	}

	if ( ! $report_email ) {
		$report_email = get_option( 'wp_client_reports_default_email', null );
		if ( ! $report_email ) {
			$report_email = sanitize_email( get_bloginfo( 'admin_email' ) );
		}
	}

	$dates = wp_client_reports_validate_dates( $start, $end );

	$date_format = get_option( 'date_format' );

	$timezone = wp_timezone();

	$start_date_object = DateTime::createFromFormat( 'Y-m-d', $dates->start_date, $timezone );
	$end_date_object   = DateTime::createFromFormat( 'Y-m-d', $dates->end_date, $timezone );
	$now               = new DateTime( "now", $timezone );

	if ( $report_title ) {
		$report_title = str_replace( "[MONTH]", $end_date_object->format( 'F' ), $report_title );
		$report_title = str_replace( "[YEAR]", $end_date_object->format( 'Y' ), $report_title );
		$report_title = str_replace( "[DATE]", $end_date_object->format( $date_format ), $report_title );
	}

	if ( $report_intro ) {
		$report_intro = str_replace( "[MONTH]", $end_date_object->format( 'F' ), $report_intro );
		$report_intro = str_replace( "[YEAR]", $end_date_object->format( 'Y' ), $report_intro );
		$report_intro = str_replace( "[DATE]", $end_date_object->format( $date_format ), $report_intro );
	}

	$start_day      = $start_date_object->format( 'j' );
	$start_month    = $start_date_object->format( 'n' );
	$end_day        = $end_date_object->format( 'j' );
	$end_month      = $end_date_object->format( 'n' );
	$lastdayofmonth = $now->format( 't' );

	if ( $start_month == $end_month && $start_day == 1 && $end_day == $lastdayofmonth ) {
		$date_formatted = $start_date_object->format( 'F Y' );
	} else {
		$start_date_formatted = $start_date_object->format( $date_format );
		$end_date_formatted   = $end_date_object->format( $date_format );
		/* translators: 1: Start date. 2: End date. */
		$date_formatted = sprintf( __( 'From %1$s - %2$s', 'wp-client-reports' ), esc_html( $start_date_formatted ), esc_html( $end_date_formatted ) );
	}

	$brand_color = wp_client_reports_get_brand_color();

	ob_start();

	include( "email/report-email-header.php" );

	do_action( 'wp_client_reports_stats_email_before' );

	?>
    <tr>
        <td bgcolor="#ffffff" align="left"
            style="padding: 40px 40px 20px 40px; font-family: -apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Oxygen-Sans,Ubuntu,Cantarell,'Helvetica Neue',sans-serif; font-size: 16px; line-height: 24px;">
            <h1 style="margin: 0 0 12px; font-size: 30px; font-weight: bold; line-height: 42px; color: <?php echo $brand_color; ?>; "><?php echo esc_html( $report_title ); ?></h1>
            <h5 style="font-weight:bold; font-size: 16px; line-height:18px; margin: 0px 0px 4px;"><?php echo $date_formatted; ?></h5>
        </td>
    </tr>
	<?php if ( $report_intro ) : ?>
        <tr>
            <td bgcolor="#ffffff" align="left"
                style="padding: 0px 40px 20px 40px; font-family: -apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Oxygen-Sans,Ubuntu,Cantarell,'Helvetica Neue',sans-serif; font-size: 16px; line-height: 24px;">
                <p style="margin: 0; color:#212529;"><?php echo $report_intro; ?></p>
            </td>
        </tr>
	<?php endif; ?>
	<?php do_action( 'wp_client_reports_stats_email', $dates->start_date, $dates->end_date ); ?>
    <tr>
        <td align="left" bgcolor="#ffffff">
            <table border="0" cellpadding="0" cellspacing="0" width="100%">
                <tr>
                    <td align="center" bgcolor="#ffffff" style="padding: 0px 40px 40px 40px;">
                        <table border="0" cellpadding="0" cellspacing="0">
                            <tr>
                                <td align="center" bgcolor="<?php echo $brand_color; ?>">
                                    <a href="<?php echo site_url(); ?>" target="_blank" rel="noopener noreferrer"
                                       style="display: inline-block; padding: 8px 20px; font-family: -apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Oxygen-Sans,Ubuntu,Cantarell,'Helvetica Neue',sans-serif; font-size: 13px; color: #ffffff; text-decoration: none;"><?php _e( 'Visit Website', 'wp-client-reports' ); ?></a>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
	<?php
	do_action( 'wp_client_reports_stats_email_after' );
	include( "email/report-email-footer.php" );

	$body = ob_get_clean();

	$email_from = get_option( 'wp_client_reports_email_from' );
	if ( ! $email_from ) {
		$email_from = get_bloginfo( 'admin_email' );
	}
	$name_from = get_option( 'wp_client_reports_name_from' );
	if ( ! $name_from ) {
		$name_from = get_bloginfo( 'name' );
	}

	$email_reply = get_option( 'wp_client_reports_email_reply' );

	$subject   = $report_title;
	$headers[] = 'Content-Type: text/html; charset=UTF-8';
	$headers[] = 'From: ' . $name_from . ' <' . $email_from . '>';
	if ( $email_reply && ! empty( $email_reply ) ) {
		$headers[] = 'Reply-To: ' . $name_from . ' <' . $email_reply . '>';
	}

	$sent = wp_mail( $report_email, $subject, $body, $headers );

	return $sent;
}

/**
 * Render a big number in the HTML report page
 */
function wp_client_reports_render_big_number( $title, $id ) {
	$allowed_html = [ 'br' => [] ];
	?>
    <div class="wp-client-reports-big-number">
        <h2 id="<?php echo esc_attr( $id ); ?>">0</h2>
        <h3><?php echo wp_kses( $title, $allowed_html ); ?></h3>
    </div><!-- .wp-client-reports-big-number -->
	<?php
}

/**
 * Render an email header
 */
function wp_client_reports_render_email_header( $title ) {
	?>
    <tr>
        <td align="left" bgcolor="#ffffff"
            style="padding: 0px 40px 0px 40px; font-family: -apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Oxygen-Sans,Ubuntu,Cantarell,'Helvetica Neue',sans-serif; font-size: 16px; line-height: 24px;">
            <h5 style="font-weight:bold; font-size: 16px; line-height:18px; padding-bottom:10px; margin: 15px 0px 10px;border-bottom:solid 1px #ddd;"><?php echo esc_html( $title ); ?></h5>
        </td>
    </tr>
	<?php
}

/**
 * Render an email row
 */
function wp_client_reports_render_email_row( $stat1, $label1, $stat2, $label2 ) {
	?>
    <tr>
        <td align="left" bgcolor="#ffffff" style="padding: 0px 40px 0px 40px;">
            <table border="0" cellpadding="0" cellspacing="0" width="100%">
                <td align="center" width="250"
                    style="padding: 20px; font-family: -apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Oxygen-Sans,Ubuntu,Cantarell,'Helvetica Neue',sans-serif; font-size: 16px; line-height: 24px;">
					<?php wp_client_reports_render_email_big_number( $stat1, $label1 ); ?>
                </td>
                <td bgcolor="#ffffff" align="center" width="20">&nbsp;</td>
                <td align="center" width="250"
                    style="padding: 20px; font-family: -apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Oxygen-Sans,Ubuntu,Cantarell,'Helvetica Neue',sans-serif; font-size: 16px; line-height: 24px;">
					<?php wp_client_reports_render_email_big_number( $stat2, $label2 ); ?>
                </td>
            </table>
        </td>
    </tr>
	<?php
}

/**
 * Render a big number in the emailed report
 */
function wp_client_reports_render_email_big_number( $stat, $label ) {
	if ( isset( $stat ) && isset( $label ) ) {
		$brand_color  = wp_client_reports_get_brand_color();
		$allowed_html = [ 'br' => [] ];
		?>
        <h1 style="font-weight: bold; color: <?php echo esc_attr( $brand_color ); ?>; margin: 0px; font-size: 66px; line-height: 1em;"><?php echo esc_html( $stat ); ?></h1>
        <h5 style="text-transform: uppercase; color: #888888; font-size: 16px; line-height:18px; font-weight: 300; margin: 0px;"><?php echo wp_kses( $label, $allowed_html ); ?></h5>
		<?php
	}
}

/**
 * Email section for software updates
 */
function wp_client_reports_stats_email_updates( $start_date, $end_date ) {
	$updates_data = wp_client_reports_get_updates_data( $start_date, $end_date );
	$date_format  = get_option( 'date_format' );

	wp_client_reports_render_email_header( __( 'Software Updates', 'wp-client-reports' ) );

	wp_client_reports_render_email_row(
		$updates_data->total_updates,
		/* translators: %s: <br> tag. */
		sprintf( __( 'Total %s Updates', 'wp-client-reports' ), '<br>' ),
		$updates_data->wp_updated,
		/* translators: %s: <br> tag. */
		sprintf( __( 'WordPress %s Core Updates', 'wp-client-reports' ), '<br>' )
	);

	wp_client_reports_render_email_row(
		$updates_data->total_plugins_updated,
		/* translators: %s: <br> tag. */
		sprintf( __( 'Plugin %s Updates', 'wp-client-reports' ), '<br>' ),
		$updates_data->total_themes_updated,
		/* translators: %s: <br> tag. */
		sprintf( __( 'Theme %s Updates', 'wp-client-reports' ), '<br>' )
	);

	$include_update_details = apply_filters( 'wp_client_reports_include_update_details', true );
	if ( $include_update_details === true ) :
		?>

        <tr>
            <td bgcolor="#ffffff" align="left"
                style="padding: 20px 40px 40px 40px; font-family: -apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Oxygen-Sans,Ubuntu,Cantarell,'Helvetica Neue',sans-serif; font-size: 14px; line-height: 20px;">
                <h3 style="font-size:14px;margin:0px 0px 4px 0px;"><?php _e( 'WordPress Core Updates', 'wp-client-reports' ); ?></h3>
                <table border="0" cellpadding="0" cellspacing="0" width="100%"
                       style="border-top:solid 1px #dddddd;margin-bottom:30px;">
					<?php
					if ( $updates_data->wp_updated > 0 ) :
						foreach ( $updates_data->updates as $update ) :
							if ( $update->type == 'wp' ) :
								echo '<tr><td style="width:40%;padding:8px 8px 8px 0px;border-bottom:solid 1px #dddddd;">' . esc_html( $update->name ) . '</td><td style="text-align:center;width:30%;padding:8px;border-bottom:solid 1px #dddddd;"">' . esc_html( $update->version_before ) . ' -> ' . esc_html( $update->version_after ) . '</td><td style="text-align:right;width:30%;padding:8px 0px 8px 8px;border-bottom:solid 1px #dddddd;"">' . esc_html( date( $date_format, strtotime( $update->date ) ) ) . '</td>';
							endif;
						endforeach;
					else:
						echo '<tr><td style="padding:8px 0px 8px 0px;border-bottom:solid 1px #dddddd;">' . __( 'No WordPress Core Updates', 'wp-client-reports' ) . '</td>';
					endif;
					?>
                </table>

                <h3 style="font-size:14px;margin:0px 0px 4px 0px;"><?php _e( 'Plugin Updates', 'wp-client-reports' ); ?></h3>
                <table border="0" cellpadding="0" cellspacing="0" width="100%"
                       style="border-top:solid 1px #dddddd;margin-bottom:30px;">
					<?php
					if ( $updates_data->total_plugins_updated > 0 ) :
						foreach ( $updates_data->updates as $update ) :
							if ( $update->type == 'plugin' ) :
								echo '<tr><td style="width:40%;padding:8px 8px 8px 0px;border-bottom:solid 1px #dddddd;">' . esc_html( $update->name ) . '</td><td style="text-align:center;width:30%;padding:8px;border-bottom:solid 1px #dddddd;"">' . esc_html( $update->version_before ) . ' -> ' . esc_html( $update->version_after ) . '</td><td style="text-align:right;width:30%;padding:8px 0px 8px 8px;border-bottom:solid 1px #dddddd;"">' . esc_html( date( $date_format, strtotime( $update->date ) ) ) . '</td>';
							endif;
						endforeach;
					else:
						echo '<tr><td style="padding:8px 0px 8px 0px;border-bottom:solid 1px #dddddd;">' . __( 'No Plugin Updates', 'wp-client-reports' ) . '</td>';
					endif;
					?>
                </table>

                <h3 style="font-size:14px;margin:0px 0px 4px 0px;"><?php _e( 'Theme Updates', 'wp-client-reports' ); ?></h3>
                <table border="0" cellpadding="0" cellspacing="0" width="100%"
                       style="border-top:solid 1px #dddddd;margin-bottom:20px;">
					<?php
					if ( $updates_data->total_themes_updated > 0 ) :
						foreach ( $updates_data->updates as $update ) :
							if ( $update->type == 'theme' ) :
								echo '<tr><td style="width:40%;padding:8px 8px 8px 0px;border-bottom:solid 1px #dddddd;">' . esc_html( $update->name ) . '</td><td style="text-align:center;width:30%;padding:8px;border-bottom:solid 1px #dddddd;"">' . esc_html( $update->version_before ) . ' -> ' . esc_html( $update->version_after ) . '</td><td style="text-align:right;width:30%;padding:8px 0px 8px 8px;border-bottom:solid 1px #dddddd;"">' . esc_html( date( $date_format, strtotime( $update->date ) ) ) . '</td>';
							endif;
						endforeach;
					else:
						echo '<tr><td style="padding:8px 0px 8px 0px;border-bottom:solid 1px #dddddd;">' . __( 'No Theme Updates', 'wp-client-reports' ) . '</td>';
					endif;
					?>
                </table>
            </td>
        </tr>
	<?php
	endif; //$include_update_details
}

/**
 * Email section for content stats
 */
function wp_client_reports_stats_email_content( $start_date, $end_date ) {
	$content_stats_data = wp_client_reports_get_content_stats_data( $start_date, $end_date );
	$date_format        = get_option( 'date_format' );

	wp_client_reports_render_email_header( __( 'Site Content', 'wp-client-reports' ) );

	wp_client_reports_render_email_row(
		$content_stats_data->posts_count,
		/* translators: %s: <br> tag. */
		sprintf( __( 'Posts %s Added', 'wp-client-reports' ), '<br>' ),
		$content_stats_data->pages_count,
		/* translators: %s: <br> tag. */
		sprintf( __( 'Pages %s Added', 'wp-client-reports' ), '<br>' )
	);

	wp_client_reports_render_email_row(
		$content_stats_data->comments_count,
		/* translators: %s: <br> tag. */
		sprintf( __( 'Comments %s Added', 'wp-client-reports' ), '<br>' ),
		null,
		null
	);
}

add_action( 'admin_init', 'wp_client_reports_options_init', 10 );
/**
 * Register the WP Client Report settings
 */
function wp_client_reports_options_init() {

	register_setting( 'wp_client_reports_options_page', 'wp_client_reports_default_title' );
	register_setting( 'wp_client_reports_options_page', 'wp_client_reports_default_email' );
	register_setting( 'wp_client_reports_options_page', 'wp_client_reports_email_from' );
	register_setting( 'wp_client_reports_options_page', 'wp_client_reports_email_reply' );
	register_setting( 'wp_client_reports_options_page', 'wp_client_reports_name_from' );
	register_setting( 'wp_client_reports_options_page', 'wp_client_reports_default_intro' );
	register_setting( 'wp_client_reports_options_page', 'wp_client_reports_email_footer' );
	register_setting( 'wp_client_reports_options_page', 'wp_client_reports_enable_updates' );
	register_setting( 'wp_client_reports_options_page', 'wp_client_reports_enable_content_stats' );

	add_settings_section(
		'wp_client_reports_email_section',
		__( 'Email Settings', 'wp-client-reports' ),
		'wp_client_reports_email_section_callback',
		'wp_client_reports_options_page'
	);

	add_settings_field(
		'wp_client_reports_default_title',
		__( 'Default Report Title', 'wp-client-reports' ),
		'wp_client_reports_default_title_render',
		'wp_client_reports_options_page',
		'wp_client_reports_email_section'
	);

	add_settings_field(
		'wp_client_reports_default_email',
		__( 'Default Email Address(es) to Send To', 'wp-client-reports' ),
		'wp_client_reports_default_email_render',
		'wp_client_reports_options_page',
		'wp_client_reports_email_section'
	);

	add_settings_field(
		'wp_client_reports_email_from',
		__( 'Email Address to Send From', 'wp-client-reports' ),
		'wp_client_reports_email_from_render',
		'wp_client_reports_options_page',
		'wp_client_reports_email_section'
	);

	add_settings_field(
		'wp_client_reports_email_reply',
		__( 'Reply To Email Address', 'wp-client-reports' ),
		'wp_client_reports_email_reply_render',
		'wp_client_reports_options_page',
		'wp_client_reports_email_section'
	);

	add_settings_field(
		'wp_client_reports_name_from',
		__( 'Name to Send From', 'wp-client-reports' ),
		'wp_client_reports_name_from_render',
		'wp_client_reports_options_page',
		'wp_client_reports_email_section'
	);

	add_settings_field(
		'wp_client_reports_default_intro',
		__( 'Default Email Introduction (optional)', 'wp-client-reports' ),
		'wp_client_reports_default_intro_render',
		'wp_client_reports_options_page',
		'wp_client_reports_email_section'
	);

	add_settings_field(
		'wp_client_reports_email_footer',
		__( 'Email Footer', 'wp-client-reports' ),
		'wp_client_reports_email_footer_render',
		'wp_client_reports_options_page',
		'wp_client_reports_email_section'
	);

	add_settings_section(
		'wp_client_reports_updates_section',
		__( 'Software Updates', 'wp-client-reports' ),
		'wp_client_reports_settings_section_callback',
		'wp_client_reports_options_page'
	);

	add_settings_field(
		'wp_client_reports_enable_updates',
		__( 'Enable Update Tracking', 'wp-client-reports' ),
		'wp_client_reports_enable_updates_render',
		'wp_client_reports_options_page',
		'wp_client_reports_updates_section'
	);

	add_settings_section(
		'wp_client_reports_content_stats_section',
		__( 'Site Content', 'wp-client-reports' ),
		'wp_client_reports_settings_section_callback',
		'wp_client_reports_options_page'
	);

	add_settings_field(
		'wp_client_reports_enable_content_stats',
		__( 'Enable Site Content Stats', 'wp-client-reports' ),
		'wp_client_reports_enable_content_stats_render',
		'wp_client_reports_options_page',
		'wp_client_reports_content_stats_section'
	);
}

/**
 * Add default title field to the options page
 */
function wp_client_reports_default_title_render() {
	$option = get_option( 'wp_client_reports_default_title' );
	if ( ! $option ) {
		$option = get_bloginfo( 'name' ) . ' ' . __( 'Site Report', 'wp-client-reports' );
	}
	?>
    <input type='text' name='wp_client_reports_default_title' value='<?php echo esc_attr( $option ); ?>'
           class="regular-text">
    <p class="description"><?php _e( 'You can use [YEAR], [MONTH], and [DATE] shortcodes for automatic replacement.', 'wp-client-reports' ); ?></p>
	<?php
}

/**
 * Add default email field to the options page
 */
function wp_client_reports_default_email_render() {
	$option = get_option( 'wp_client_reports_default_email' );
	if ( ! $option ) {
		$option = get_bloginfo( 'admin_email' );
	}
	?>
    <input type='text' name='wp_client_reports_default_email' value='<?php echo esc_attr( $option ); ?>'
           class="regular-text">
    <p class="description"><?php _e( 'You can comma separate multiple addresses', 'wp-client-reports' ); ?></p>
	<?php
}

/**
 * Add default email field to the options page
 */
function wp_client_reports_email_from_render() {
	$option = get_option( 'wp_client_reports_email_from' );
	if ( ! $option ) {
		$option = get_bloginfo( 'admin_email' );
	}
	?>
    <input type='text' name='wp_client_reports_email_from' value='<?php echo esc_attr( $option ); ?>'
           class="regular-text">
    <p class="description"><?php _e( 'Some SMTP and other email plugins will not obey this setting.', 'wp-client-reports' ); ?></p>
	<?php
}

/**
 * Add reply to email field to the options page
 */
function wp_client_reports_email_reply_render() {
	$option = get_option( 'wp_client_reports_email_reply' );
	?>
    <input type='text' name='wp_client_reports_email_reply' value='<?php echo esc_attr( $option ); ?>'
           class="regular-text">
    <p class="description"><?php _e( 'Optional. Only needed if different than the "from" address above.', 'wp-client-reports' ); ?></p>
	<?php
}

/**
 * Add default email field to the options page
 */
function wp_client_reports_name_from_render() {
	$option = get_option( 'wp_client_reports_name_from' );
	if ( ! $option ) {
		$option = get_bloginfo( 'name' );
	}
	?>
    <input type='text' name='wp_client_reports_name_from' value='<?php echo esc_attr( $option ); ?>'
           class="regular-text">
	<?php
}

/**
 * Add default intro field to the options page
 */
function wp_client_reports_default_intro_render() {
	$option = get_option( 'wp_client_reports_default_intro' );
	?>
    <textarea name='wp_client_reports_default_intro' class="large-text" rows="6"
              cols="50"><?php echo esc_textarea( $option ); ?></textarea>
    <p class="description"><?php _e( 'You can use [YEAR], [MONTH], and [DATE] shortcodes for automatic replacement.', 'wp-client-reports' ); ?></p>
	<?php
}

/**
 * Add email footer field to the options page
 */
function wp_client_reports_email_footer_render() {
	$option = get_option( 'wp_client_reports_email_footer' );
	if ( ! $option ) {
		$option = sprintf( __( 'This email was sent by an administrator at %s.', 'wp-client-reports' ), '<a href="' . site_url() . '">' . get_bloginfo( 'name' ) . '</a>' );
	}
	?>
    <textarea name='wp_client_reports_email_footer' class="large-text" rows="3"
              cols="50"><?php echo esc_textarea( $option ); ?></textarea>
	<?php
}

/**
 * Settings section help
 */
function wp_client_reports_email_section_callback() {
	//Print nothing
}

/**
 * Enable Software Updates Toggle Switch
 */
function wp_client_reports_enable_updates_render() {
	$option = get_option( 'wp_client_reports_enable_updates' );
	?>
    <label class="wp-client-reports-switch">
        <input type="checkbox" name="wp_client_reports_enable_updates" <?php if ( $option == 'on' ) {
			echo "checked";
		} ?>>
        <span class="wp-client-reports-slider"></span>
    </label>
	<?php
}

/**
 * Enable Content Stats Toggle Switch
 */
function wp_client_reports_enable_content_stats_render() {
	$option = get_option( 'wp_client_reports_enable_content_stats' );
	?>
    <label class="wp-client-reports-switch">
        <input type="checkbox" name="wp_client_reports_enable_content_stats" <?php if ( $option == 'on' ) {
			echo "checked";
		} ?>>
        <span class="wp-client-reports-slider"></span>
    </label>
	<?php
}

/**
 * Create the WP Client Reports Settings Page
 */
function wp_client_reports_options_page() {
	?>
    <div class="wrap" id="wp-client-reports-options">
        <h1 class="wp-heading-inline"><?php _e( 'WP Client Reports Settings', 'wp-client-reports' ); ?></h1>
        <a href="<?php echo admin_url( 'index.php?page=wp_client_reports' ); ?>"
           class="page-title-action"><?php _e( 'View Reports', 'wp-client-reports' ); ?></a>
        <form action='options.php' method='post' enctype="multipart/form-data">
            <div id="poststuff">
                <div id="post-body" class="metabox-holder columns-2">
                    <div id="postbox-container-1" class="postbox-container">
                        <div id="submitdiv" class="postbox">
                            <h2 class="hndle"><span><?php _e( 'Actions', 'wp-client-reports' ); ?></span></h2>
                            <div class="inside">
                                <div id="major-publishing-actions">
                                    <div id="publishing-action">
										<?php submit_button( __( 'Save Settings', 'wp-client-reports' ) ); ?>
                                    </div><!-- #publishing-action -->
                                    <div class="clear"></div>
                                </div><!-- #major-publishing-actions -->
                            </div><!-- .inside -->
                        </div><!-- #submitdiv -->
						<?php if ( ! is_plugin_active( 'wp-client-reports-pro/wp_client_reports_pro.php' ) ) : ?>
                            <div id="wp-client-reports-pro" class="postbox">
                                <div class="inside">
                                    <p>
										<?php _e( 'WP Client Reports Pro offers more branding options and additional reports such as:', 'wp-client-reports' ); ?>
                                    </p>
                                    <ul style="list-style: disc;padding-left: 18px;">
                                        <li><?php _e( 'Add Logo and Brand Color to Reports', 'wp-client-reports' ); ?></li>
                                        <li><?php _e( 'Google Analytics', 'wp-client-reports' ); ?></li>
                                        <li><?php _e( 'Pingdom & Uptime Robot', 'wp-client-reports' ); ?></li>
                                        <li><?php _e( 'WooCommerce', 'wp-client-reports' ); ?></li>
                                        <li><?php _e( 'Gravity Forms & Ninja Forms', 'wp-client-reports' ); ?></li>
                                    </ul>
                                    <div>
                                        <a href="https://wpclientreports.com/?utm_source=wordpress&utm_medium=plugin_settings&utm_campaign=wpclientreports"
                                           class="button"
                                           target='_blank'><?php _e( 'WP Client Reports Pro', 'wp-client-reports' ); ?></a>
                                    </div>
                                </div>
                            </div><!-- #wp-client-reports-pro -->
						<?php endif; ?>
                        <div id="bugs-features" class="postbox">
                            <div class="inside">
                                <p>
									<?php
									$link_url   = esc_url( 'https://wpclientreports.com/?utm_source=wordpress&utm_medium=plugin_settings&utm_campaign=wpclientreports' );
									$link_open  = "<a href='{$link_url}' target='_blank' rel='noopener noreferrer'>";
									$link_close = '</a>';

									printf(
									/* translators: 1: Opening link tag, 2: Closing link tag */
										__( '%1$sLearn more about WP Client Reports features and the PRO add-on.%2$s', 'wp-client-reports' ),
										$link_open,
										$link_close
									);
									?>
                                </p>
                                <p>
									<?php
									$github_url   = esc_url( 'https://github.com/WPClientReports/wp-client-reports' );
									$wp_url       = esc_url( 'https://wordpress.org/plugins/wp-client-reports/' );
									$link_open_wp = "<a href='{$wp_url}' target='_blank' rel='noopener noreferrer'>";
									$link_open_gh = "<a href='{$github_url}' target='_blank' rel='noopener noreferrer'>";

									printf(
									/* translators: 1: WordPress plugin link open tag, 2: GitHub link open tag, 3: Closing link tag */
										__( 'Found a bug or have a feature request? Let us know on the %1$sWP plugin directory%3$s, or send a pull request via %2$sGitHub%3$s.', 'wp-client-reports' ),
										$link_open_wp,
										$link_open_gh,
										$link_close
									);
									?>
                                </p>
                            </div>
                        </div><!-- #bugs-features -->
                    </div><!-- .postbox-container -->
                    <div id="postbox-container-2" class="postbox-container">

						<?php settings_fields( 'wp_client_reports_options_page' ); ?>

						<?php global $wp_settings_sections; ?>

						<?php foreach ( $wp_settings_sections['wp_client_reports_options_page'] as $section ) : ?>
                            <div class="postbox wp-client-reports-settings-postbox">
								<?php if ( $section['title'] ) : ?>
                                    <div class="postbox-header">
                                        <h2 class="hndle"><?php echo $section['title']; ?></h2>
                                    </div>
								<?php endif; ?>
                                <div class="inside">
                                    <table class="form-table" role="presentation">
										<?php do_settings_fields( 'wp_client_reports_options_page', $section['id'] ); ?>
                                    </table>
                                </div><!-- .inside -->
                            </div><!-- .postbox -->
						<?php endforeach; ?>

                    </div><!-- .postbox-container -->
                </div><!-- #post-body -->
                <br class="clear">
            </div><!-- #poststuff -->
        </form>
    </div><!-- .wrap -->
	<?php
}

/**
 * Get brand color
 */
function wp_client_reports_get_brand_color() {
	return apply_filters( 'wp_client_reports_brand_color', '#007cba' );
}

/**
 * Convert PHP date format to Moment.js date format
 */
function wp_client_reports_convert_date_format( $format ) {
	$replacements     = [
		'd' => 'DD',
		'D' => 'ddd',
		'j' => 'D',
		'l' => 'dddd',
		'N' => 'E',
		'S' => 'o',
		'w' => 'e',
		'z' => 'DDD',
		'W' => 'W',
		'F' => 'MMMM',
		'm' => 'MM',
		'M' => 'MMM',
		'n' => 'M',
		't' => '', // no equivalent
		'L' => '', // no equivalent
		'o' => 'YYYY',
		'Y' => 'YYYY',
		'y' => 'YY',
		'a' => 'a',
		'A' => 'A',
		'B' => '', // no equivalent
		'g' => 'h',
		'G' => 'H',
		'h' => 'hh',
		'H' => 'HH',
		'i' => 'mm',
		's' => 'ss',
		'u' => 'SSS',
		'e' => 'zz', // deprecated since version 1.6.0 of moment.js
		'I' => '', // no equivalent
		'O' => '', // no equivalent
		'P' => '', // no equivalent
		'T' => '', // no equivalent
		'Z' => '', // no equivalent
		'c' => '', // no equivalent
		'r' => '', // no equivalent
		'U' => 'X',
	];
	$moment_js_format = strtr( $format, $replacements );

	return $moment_js_format;
}

/**
 * Remove dashes from dates and other places you want them cleared
 */
function wp_client_reports_nodash( $text ) {
	return str_replace( '-', '_', $text );
}

/**
 * Delete all transients with a key prefix.
 *
 * @param string $prefix The key prefix.
 */
function wp_client_reports_delete_transients( $prefix ) {
	wp_client_reports_delete_transients_from_keys( wp_client_reports_search_database_for_transients_by_prefix( $prefix ) );
}

/**
 * Searches the database for transients stored there that match a specific prefix.
 *
 * @param string $prefix Prefix to search for.
 *
 * @return array|bool     Nested array response for wpdb->get_results or false on failure.
 */
function wp_client_reports_search_database_for_transients_by_prefix( $prefix ) {

	global $wpdb;

	// Add our prefix after concatenating our prefix with the _transient prefix
	$prefix = $wpdb->esc_like( '_transient_' . $prefix . '_' );

	// Build up our SQL query
	$sql = "SELECT `option_name` FROM $wpdb->options WHERE `option_name` LIKE '%s'";

	// Execute our query
	$transients = $wpdb->get_results( $wpdb->prepare( $sql, $prefix . '%' ), ARRAY_A );

	// If it looks good, pass it back
	if ( $transients && ! is_wp_error( $transients ) ) {
		return $transients;
	}

	// Otherwise return false
	return false;
}

/**
 * Expects a passed in multidimensional array of transient keys.
 *
 * array(
 *     array( 'option_name' => '_transient_blah_blah' ),
 *     array( 'option_name' => 'transient_another_one' ),
 *
 * Can also pass in an array of transient names.
 *
 * @param array|string $transients Nested array of transients, keyed by option_name,
 *                                   or array of names of transients.
 *
 * @return array|bool                Count of total vs deleted or false on failure.
 */
function wp_client_reports_delete_transients_from_keys( $transients ) {
	if ( ! isset( $transients ) ) {
		return false;
	}

	// If we get a string key passed in, might as well use it correctly
	if ( is_string( $transients ) ) {
		$transients = array( array( 'option_name' => $transients ) );
	}

	// If it's not an array, we can't do anything
	if ( ! is_array( $transients ) ) {
		return false;
	}

	$results = array();

	// Loop through our transients
	foreach ( $transients as $transient ) {

		if ( is_array( $transient ) ) {

			// If we have an array, grab the first element
			$transient = current( $transient );
		}

		// Remove that sucker
		$results[ $transient ] = delete_transient( str_replace( '_transient_', '', $transient ) );
	}

	// Return an array of total number, and number deleted
	return array(
		'total'   => count( $results ),
		'deleted' => array_sum( $results ),
	);
}