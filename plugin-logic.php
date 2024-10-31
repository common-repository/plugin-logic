<?php
/***
 * Plugin Name: Plugin Logic
 * Plugin URI: http://wordpress.org/plugins/plugin-logic/
 * Description: Activate plugins on pages only if they are really needed.
 * Author: simon_h
 * Version: 1.1.0
 * Text Domain: plugin-logic
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package Plugin Logic
 */

// Security check.
if ( ! class_exists( 'WP' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	exit();
}

if ( ! class_exists( 'Plugin_Logic' ) ) {

	define( 'PLULO_DBTABLE', $GLOBALS['wpdb']->base_prefix . 'plugin_logic' );

	add_action( 'plugins_loaded', array( 'Plugin_Logic', 'init' ) );
	register_activation_hook( __FILE__, array( 'Plugin_Logic', 'on_activation' ) );
	register_deactivation_hook( __FILE__, array( 'Plugin_Logic', 'on_deactivation' ) );
	register_uninstall_hook( __FILE__, array( 'Plugin_Logic', 'on_uninstall' ) );

	/**
	 * The Plugin Logic main class.
	 *
	 * @package Plugin Logic
	 */
	class Plugin_Logic {

		/**
		 * The class instance.
		 *
		 * @var \Plugin_Logic $classobj
		 */
		protected static $classobj = null;

		/**
		 * Holds the screen option value from plulo_toggle_dash_col input.
		 *
		 * @var string $on_dash_columm
		 */
		private string $on_dash_columm;

		/**
		 * Holds plugin basename.
		 *
		 * @var string $plugin_base
		 */
		private string $plugin_base;

		/***
		 * Handler for the action 'init'. Instantiates this class.
		 *
		 * @since 1.0.0
		 */
		public static function init() {
			if ( null === self::$classobj ) {
				self::$classobj = new self();
			}
			return self::$classobj;
		}


		/***
		 * Init class properties, register hooks, add menu entries.
		 *
		 * @since 1.0.0
		 */
		public function __construct() {
			$this->plugin_base = plugin_basename( __FILE__ );

			add_filter( "plugin_action_links_{$this->plugin_base}", array( $this, 'plugin_add_settings_link' ) );
			add_action( 'admin_menu', array( $this, 'on_admin_menu' ) );
		}


		/***
		 * Add plugin settings link to plugins list table.
		 *
		 * @param array $links an array of setting links.
		 * @since 1.0.0
		 */
		public function plugin_add_settings_link( $links ) {
			$settings_link = '<a href="plugins.php?page=plugin-logic">' . __( 'Settings' ) . '</a>';
			array_push( $links, $settings_link );
				return $links;
		}


		/***
		 * Add the menu entry to the plugins-options-page and add access to the screen-options-wrap.
		 *
		 * @since 1.0.0
		 */
		public function on_admin_menu() {
			$this->pagehook = add_plugins_page( 'Plugin Logic', 'Plugin Logic', 'activate_plugins', 'plugin-logic', array( $this, 'plulo_option_page' ) );
			add_action( "load-{$this->pagehook}", array( $this, 'register_screen_options_wrap' ) );
		}


		/***
		 * Register a new screen-options-wrap and add custom HTML to the screen-options-wrap panel.
		 *
		 * @since 1.0.0
		 * @change 1.0.4
		 */
		public function register_screen_options_wrap() {
			$screen = get_current_screen();

			if ( ! is_object( $screen ) || $screen->id !== $this->pagehook ) {
				return;
			}

			$screen->add_option( 'my_option', '' );
			add_filter( 'screen_layout_columns', array( $this, 'screen_options_controls' ) );
		}


		/***
		 * Check if screen-options-wrap controls changed and output the html-code for the controls.
		 *
		 * @since 1.0.0
		 */
		public function screen_options_controls() {

			// Load textdomain for translation.
			load_plugin_textdomain( 'plugin-logic', false, dirname( $this->plugin_base ) . '/I18n/' );

			// Load view settings.
			$this->on_dash_columm = get_option( 'plulo_on_dash_col', '' );

			if ( isset( $_POST['plulo_toggle_dash_col'] ) && check_admin_referer( 'plulo_screen_options_action', 'plulo_screen_options_nonce_field' ) ) {
				if ( ! current_user_can( 'activate_plugins' ) ) {
					wp_die( esc_html( __( 'Cheatin&#8217; uh?' ) ) );
				}

				$this->on_dash_columm = sanitize_key( $_POST['plulo_toggle_dash_col'] );

				// Validate plulo_toggle_dash_col user input.
				if ( ! ( '' === $this->on_dash_columm xor 'checked' === $this->on_dash_columm ) ) {
					wp_die( esc_html( __( 'Wrong user Input!' ) ) );
				}

				// Update the Database with the new on dashboard behavior option.
				if ( get_option( 'plulo_on_dash_col' ) !== false ) {
					update_option( 'plulo_on_dash_col', $this->on_dash_columm );
				} else {
					add_option( 'plulo_on_dash_col', $this->on_dash_columm, '', 'no' );
				}
			}

			?>
			<div style="padding:15px 0 0 15px;">
				<form action="" method="post">
					<?php wp_nonce_field( 'plulo_screen_options_action', 'plulo_screen_options_nonce_field' ); ?>
					<p>
						<input name="plulo_toggle_dash_col" type="hidden" value=""/>
						<input name="plulo_toggle_dash_col" type="checkbox" value="checked" onChange="this.form.submit()" <?php echo esc_html( $this->on_dash_columm ); ?> />
							<?php esc_html_e( 'Show Options for Behavoir on Dashboard', 'plugin-logic' ); ?>
					</p>
				</form>
			</div>
			<?php
		}


		/***
		 * Display error message to user
		 *
		 * @param string $msg Error to display.
		 * @since 1.0.9
		 */
		protected static function admin_notice__error( $msg ) {
			$class = 'notice notice-error';
			printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), esc_html( $msg ) );
			add_action( 'admin_notices', 'admin_notice__error' );
		}


		/***
		 * Check if plugins database table exists
		 *
		 * @since 1.0.9
		 * @return boolean
		 */
		protected static function plulo_db_table_exists() {
			global $wpdb;

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery
			$result = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLE STATUS LIKE %s', PLULO_DBTABLE ) );
			return ( null !== $result ) ? true : false;
		}


		/***
		 * Get Plugin Logic database table content
		 *
		 * @param string $order_by field name to order database select results.
		 * @since 1.0.9
		 * @return array|object|null
		 */
		protected static function get_plulo_db_content( $order_by = 'name' ) {
			global $wpdb;

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery
			return $wpdb->get_results(
				$wpdb->prepare( 'SELECT * FROM %i ORDER BY %s ASC', array( PLULO_DBTABLE, $order_by ) )
			);
		}

		/***
		 * Check if plugin already has a database entry and write changes to database.
		 *
		 * @param string $plugin_name is equal to plugin path.
		 * @param string $on_dashboard holds the on dashboard visible status.
		 * @param string $logic holds the logic of the rules.
		 * @param array  $rules all rules for the plugin.
		 * @since 1.0.9
		 */
		protected function update_plulo_db( $plugin_name, $on_dashboard, $logic, $rules ) {
			global $wpdb;

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery
			$db_row_exists = $wpdb->get_var(
				$wpdb->prepare(
					'SELECT name FROM %i WHERE name = %s',
					PLULO_DBTABLE,
					$plugin_name
				)
			);

			if ( null !== $db_row_exists ) {
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery
					$wpdb->update(
						PLULO_DBTABLE,
						array(
							'on_dashboard' => $on_dashboard,
							'logic'        => $logic,
							'rules'        => wp_json_encode( $rules ),
						),
						array( 'name' => $plugin_name )
					);
			} else {
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery
					$wpdb->insert(
						PLULO_DBTABLE,
						array(
							'name'         => $plugin_name,
							'on_dashboard' => $on_dashboard,
							'logic'        => $logic,
							'rules'        => wp_json_encode( $rules ),
						)
					);
			}
		}


		/***
		 * Delete uninstalled plugins rules from database and the given content.
		 *
		 * @param array $plulo_db_content All rules from the plugin logic database table.
		 * @since 1.0.9
		 */
		protected function delete_uninstalled_from_plulo_db( &$plulo_db_content ) {
			global $wpdb;

			// Create list with all installed plugin names.
			$installed_plugins_paths = array_keys( get_plugins() );

			// Search for uninstalled plugins and delete them.
			$z = 0;
			foreach ( $plulo_db_content as $db_pl ) {
				if ( ! in_array( $db_pl->name, $installed_plugins_paths, true ) ) {
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery
					$wpdb->delete( PLULO_DBTABLE, array( 'name' => $db_pl->name ) );
					unset( $plulo_db_content[ $z ] );
				}
				++$z;
			}
		}


		/***
		 * Connect to WP_Filesystem
		 *
		 * @param string $url a wp_nonce_url.
		 * @param string $method Connection method.
		 * @param string $context Destination folder.
		 * @param array  $fields Fileds of $_POST array that should be preserved between screens.
		 * @since 1.0.9
		 * @return bool
		 */
		protected static function connect_to_wp_fs( $url = '', $method = '', $context = false, $fields = null ) {
			global $wp_filesystem;

			$credentials = request_filesystem_credentials( $url, $method, false, $context, $fields );
			if ( false === $credentials ) {
				return false;
			}

			// Check if credentials are correct or not.
			if ( ! WP_Filesystem( $credentials ) ) {
				request_filesystem_credentials( $url, $method, true, $context );
				return false;
			}

			return true;
		}


		/***
		 * Check the database version and complete the tasks necessary for an update
		 *
		 * @since 1.0.9
		 */
		protected function upgrade_db_table_version() {
			global $wpdb;

			if ( false === get_option( 'plulo_db_version' ) ) {
				add_option( 'plulo_db_version', 1, '', 'no' );
			}

			$version = intval( get_option( 'plulo_db_version' ) );
			if ( 1 === $version ) {

				// Add new database column rules.
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery
				$row = $wpdb->get_results(
					$wpdb->prepare( "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE table_name = %s AND column_name = 'rules'", PLULO_DBTABLE )
				);
				if ( empty( $row ) ) {
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery
					$wpdb->query(
						// phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange
						$wpdb->prepare( "ALTER TABLE %i ADD rules longtext NOT NULL DEFAULT ''", PLULO_DBTABLE )
					);
				}

				// Migrate urls and words columns content into rules column.
				$rules_from_database = self::get_plulo_db_content();
				foreach ( $rules_from_database as $r ) {
					$rules          = array();
					$rules['urls']  = unserialize( $r->urls );
					$rules['words'] = unserialize( $r->words );
					$rules['regex'] = array();

					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery
					$wpdb->update(
						PLULO_DBTABLE,
						array( 'rules' => wp_json_encode( $rules ) ),
						array( 'name' => $r->name )
					);
				}

				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery
				$wpdb->query(
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange
					$wpdb->prepare( 'ALTER TABLE %i DROP COLUMN urls, DROP COLUMN words', PLULO_DBTABLE )
				);

				update_option( 'plulo_db_version', 2 );
			}
		}

		/***
		 * Plugin Logic options page for the Dashboard
		 *
		 * @since 1.0.0
		 */
		public function plulo_option_page() {
			global $wpdb;
			$error = null;

			// Load textdomain for translation.
			load_plugin_textdomain( 'plugin-logic', false, dirname( $this->plugin_base ) . '/I18n/' );

			// Load view settings.
			$this->on_dash_columm = get_option( 'plulo_on_dash_col', '' );

			// Get active plugins.
			$active_plugin_list = get_option( 'active_plugins', array() );

			// Load data from db.
			if ( false === self::plulo_db_table_exists() ) {
				self::create_database_table();
			}

			// Check for database version update.
			$this->upgrade_db_table_version();

			$plulo_db_content = self::get_plulo_db_content();

			$this->delete_uninstalled_from_plulo_db( $plulo_db_content );

			// Filter inactive Plugins with rules and add it to the $all_plugin_list.
			$no_dashboard_plugs = array();
			$all_plugin_list    = $active_plugin_list;
			foreach ( $plulo_db_content as $db_pl ) {
				if ( ! in_array( $db_pl->name, $active_plugin_list, true ) ) {
					$no_dashboard_plugs[] = $db_pl->name;
				}
			}

			if ( count( $no_dashboard_plugs ) > 0 ) {
				$all_plugin_list = array_merge( $active_plugin_list, $no_dashboard_plugs );
				sort( $all_plugin_list );
			}

			// Action if Save-Button pressed.
			if ( isset( $_POST['plulo_submit'] ) && check_admin_referer( 'plulo_settings_page_action', 'plulo_settings_page_nonce_field' ) ) {
				if ( ! current_user_can( 'activate_plugins' ) ) {
					wp_die( esc_html( __( 'Cheatin&#8217; uh?' ) ) );
				}

				// Get user input.
				$check_array = array();
				if ( isset( $_POST['plulo_checklist'] ) ) {
					// Check-Button-List with dashboard bevavior options.
					$check_array = array_map( 'sanitize_key', $_POST['plulo_checklist'] );

					// Validate plulo_checklist user input.
					foreach ( $check_array as $check_val ) {
						if ( null === filter_var( $check_val, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE ) ) {
								wp_die( esc_html_e( 'Wrong user Input!', 'plugin-logic' ) );
						}
					}
				}

				$radio_array = array();
				if ( isset( $_POST['plulo_radiolist'] ) ) {
					// Radio-Button-List with logic options.
					$radio_array = array_map( 'sanitize_key', $_POST['plulo_radiolist'] );

					// Validate plulo_radiolist user input.
					foreach ( $radio_array as $radio_val ) {
						if ( null === filter_var( $radio_val, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE ) ) {
								wp_die( esc_html_e( 'Wrong user Input!', 'plugin-logic' ) );
						}
					}
				}

				$user_txt_input = array();
				if ( isset( $_POST['plulo_txt_list'] ) ) {
					// User rules as textinput.
					$user_txt_input = array_map( 'sanitize_textarea_field', wp_unslash( $_POST['plulo_txt_list'] ) );
				}

				// Save new values to the database and create activation/deactivation rules.
				$z            = 0;
				$plugin_rules = '';
				foreach ( $all_plugin_list as $path ) {
					if ( $path === $this->plugin_base ) {
						continue;
					}

					// Validate and filter users rules input.
					$error = $this->validate_rule_input( $user_txt_input[ $z ], $z + 1, $path );
					if ( is_wp_error( $error ) ) {
						break;
					}

					// Remove all whitespaces.
					$buffer = preg_replace( '/\s+/', '', $user_txt_input[ $z ] );

					$rules = array();
					if ( '' !== $buffer ) {
						$unsorted_rules = $this->explode_rules_string( $buffer );

						$rules['urls']  = array_values( array_filter( $unsorted_rules, array( $this, 'is_url' ) ) );
						$rules['words'] = array_values( array_filter( $unsorted_rules, array( $this, 'is_word' ) ) );
						$rules['regex'] = array_values( array_filter( $unsorted_rules, array( $this, 'is_regex' ) ) );
					}

					if ( ! empty( $rules['regex'] ) ) {
						$error = $this->validate_regex_rules( $rules['regex'], $z + 1, $path );
						if ( is_wp_error( $error ) ) {
							break;
						}
					}

					if ( ( ! empty( $rules['urls'] ) ) || ( ! empty( $rules['words'] ) ) || ( ! empty( $rules['regex'] ) ) ) { // Rules exists.

						if ( 'checked' === $this->on_dash_columm ) { // The on dashboard column is visible.
							$on_dashboard = $check_array[ $z ];
						} else {
							// Get the on dashboard behavior from database table.
							// phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery
							$on_dashboard = $wpdb->get_var(
								$wpdb->prepare(
									'SELECT on_dashboard FROM %i WHERE name = %s',
									PLULO_DBTABLE,
									$path
								)
							);
							if ( empty( $on_dashboard ) ) {
								$on_dashboard = '1';
							}
						}

						$logic = $radio_array[ $z ];

						$this->update_plulo_db( $path, $on_dashboard, $logic, $rules );

						// Prevent reactivation bug.
						if ( ( '1' === $on_dashboard ) && ! is_plugin_active( $path ) ) {
							activate_plugin( $path, '', false, true );
						}
					} else {
						// Delete database entry if user input is empty.
						// phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery
						$wpdb->delete( PLULO_DBTABLE, array( 'name' => $path ) );
					}

					++$z;
				}

				// Create the rules from the updated database.
				if ( ! is_wp_error( $error ) && true === self::plulo_db_table_exists() ) {
					$content = $this->create_rule_file_content( self::get_plulo_db_content() );
					$url     = wp_nonce_url( 'plugins.php?page=plugin-logic', 'plulo_settings_page_nonce_field' );
					$error   = $this->write_rule_file( $content, $url );
				}

				// Second site refresh to get the new Plugin status.
				if ( ! is_wp_error( $error ) ) {
					$this->reload_with_ajax();
				}
			}

			require_once 'plugin-logic-fields.php';
			$plulo_fields = new Plulo_Fields( $this->plugin_base );

			?>
			<!-- Plugin Logic setting page -->
			<div class="wrap">
				<h2>Plugin Logic</h2> <br>
				<form action="" method="post">
					<?php wp_nonce_field( 'plulo_settings_page_action', 'plulo_settings_page_nonce_field' ); ?>
					<?php

					$allowed_fields_html = array(
						'br'       => array(),
						'div'      => array(),
						'p'        => array(),
						'table'    => array(
							'class'  => array(),
							'border' => array(),
						),
						'tr'       => array(
							'id'    => array(),
							'style' => array(),
						),
						'td'       => array(
							'style' => array(),
						),
						'th'       => array(),
						'h4'       => array(),
						'input'    => array(
							'type'    => array(),
							'name'    => array(),
							'value'   => array(),
							'checked' => array(),
						),
						'textarea' => array(
							'name'  => array(),
							'style' => array(),
						),
						'style'    => array(),
					);

					if ( is_wp_error( $error ) ) {
						self::admin_notice__error( $error->get_error_message() );
					}
					echo wp_kses(
						$plulo_fields->create_the_fields( self::get_plulo_db_content() ),
						$allowed_fields_html,
					);
					?>
					<div id="tfoot" style="margin-top:10px">
						<input name="plulo_submit" type="submit" value="<?php esc_html_e( 'Save Changes' ); ?>" class="button-primary"/>
					</div>
				</form>
			</div>
			<?php
		}


		/***
		 * Checks if textarea input string contains valid signs to create rules.
		 *
		 * @param string  $text  An string of a textarea field.
		 * @param integer $index The index of the textarea field.
		 * @param string  $path  The name of the plugin file.
		 * @since 1.0.8
		 * @return null|WP_Error
		 */
		protected function validate_rule_input( $text, $index, $path ) {
			$regex = '/[^\\\\0-9a-zA-Z&$#@!?*;=:,~_+.%\{\}\|\^\"\[\]\/\-\(\)\s\n\r\t]/'; // Allowed signs for user input.
			if ( preg_match( $regex, $text ) ) {
				$plugin_infos = get_plugins();
				$plugin_name  = isset( $plugin_infos[ $path ] ) ? $plugin_infos[ $path ]['Name'] : $path;
				/* translators: 1: index of the textarea, 2: name of the plugin*/
				return new WP_Error( 'validation_error', sprintf( __( 'Cannot save the data, illegal character in Textarea %1$s for Plugin "%2$s".', 'plugin-logic' ), $index, $plugin_name ) );
			}
			return null;
		}


		/***
		 * Prepare regex rules for preg_match.
		 *
		 * @param array $regex_rules The rules to prepare.
		 * @since 1.1.0
		 * @return array
		 */
		protected static function prepare_regex_rules( $regex_rules ) {
			return array_map(
				function ( $val ) {
					return str_replace( '"', '/', $val );
				},
				$regex_rules
			);
		}


		/***
		 * Prevalidate a regex rule syntax from user input.
		 *
		 * @param array   $regex_rules The rules to validate as array.
		 * @param integer $index The index of the textarea field.
		 * @param string  $path  The name of the plugin file.
		 * @since 1.1.0
		 * @return null|WP_Error
		 */
		protected function validate_regex_rules( $regex_rules, $index, $path ) {
			$prep_regex_rules = self::prepare_regex_rules( $regex_rules );
			$z                = 0;
			foreach ( $prep_regex_rules as $r ) {
				if ( false === preg_match( $r, 'validate' ) ) {
					/* translators: 1: the wrong regex rule, 2: index of the textarea, 3: name of the plugin*/
					return new WP_Error( 'regex_error', sprintf( __( 'Cannot save the data, error in regex rule %1$s in textarea %2$s for Plugin "%3$s".', 'plugin-logic' ), $regex_rules[ $z ], $index, $path ) );
				}
				++$z;
			}
			return null;
		}


		/***
		 * Explode comma seperated rules from string and exclude comma in regex rules.
		 *
		 * @param string $rules_string The unseperated rules.
		 * @since 1.0.9
		 * @return array
		 */
		protected function explode_rules_string( $rules_string ) {
			preg_match_all( '/[^,]+\{[0-9]*,[1-9]*\}[^,]+|[^,]+/', $rules_string, $matches );
			return $matches[0] ?? array();
		}


		/***
		 * Filter callback function checks if array-element is an url.
		 *
		 * @param string $value The data from user input.
		 * @since 1.0.0
		 */
		protected function is_url( $value ) {
			$valid_url    = filter_var( $value, FILTER_VALIDATE_URL ) ? true : false;
			$valid_scheme = ( 'http://' === substr( $value, 0, 7 ) ) || ( 'https://' === substr( $value, 0, 8 ) );
			return( $valid_url && $valid_scheme );
		}


		/***
		 * Filter callback function checks if array-element is an regex pattern.
		 *
		 * @param string $value The data from user input.
		 * @since 1.0.9
		 */
		protected function is_regex( $value ) {
			return( preg_match( '/^\".+\"$/', $value ) );
		}


		/***
		 * Filter callback function checks if array-element is not an url.
		 *
		 * @param string $value The data from user input.
		 * @since 1.0.0
		 */
		protected function is_word( $value ) {
			return( ! empty( $value ) && ! $this->is_url( $value ) && ! $this->is_regex( $value ) );
		}


		/***
		 * Reload page with ajax and jQuery.
		 *
		 * @since 1.0.0
		 */
		protected function reload_with_ajax() {
			add_action(
				'admin_footer',
				function () {
					?>
					<script type="text/javascript">
						//<![CDATA[
						jQuery(document).ready( function($) {
							$.ajax({
								url: "",
								context: document.body,
								success: function(s,x){
									$(this).html(s);
								}
							});
						});
						//]]>
					</script>
					<?php
				}
			);
		}

		/***
		 * Indent php code string.
		 *
		 * @param string $code_str The Unindendet php code.
		 * @param string $type     The type of the sign used for indention.
		 * @param string $width    The indention width.
		 * @since 1.0.9
		 * @return string
		 */
		protected static function indent_php_code( $code_str, $type = "\t", $width = 1 ) {
			$result       = '';
			$indent_accu  = 0;
			$code_str_len = strlen( $code_str );
			for ( $i = 0; $i < $code_str_len; $i++ ) {
				if ( '{' === $code_str[ $i ] ) {
					++$indent_accu;
					$result .= $code_str[ $i ];
				} elseif ( '}' === $code_str[ $i ] ) {
					--$indent_accu;
					$result .= str_repeat( $type, $indent_accu * $width ) . $code_str[ $i ];
				} elseif ( substr( $result, -1 ) === "\n" ) {
					$result .= str_repeat( $type, $indent_accu * $width ) . $code_str[ $i ];
				} else {
					$result .= $code_str[ $i ];
				}
			}
			return $result;
		}


		/***
		 * Creates the plugin activation/deactivation rules for singlesite installations
		 *
		 * @param array $singlesite_rules An array of singlesite rules.
		 * @since 1.0.4
		 * @return string
		 */
		protected static function create_rule_file_content( $singlesite_rules = array() ) {
			if ( ! empty( $singlesite_rules ) ) {

				$all_on_dash = true;
				foreach ( $singlesite_rules as $r ) {
					if ( '0' === $r->on_dashboard ) {
						$all_on_dash = false;
						break;
					}
				}

				$t1 = ( $all_on_dash ) ? "\t" : '';

				// Structur from the beginning and the end of the rule file.
				$first_part  = "<?php\n";
				$first_part .= "/***\n";
				$first_part .= " * Plugin Name: Plugin Logic Rule File\n";
				$first_part .= " * Plugin URI: http://wordpress.org/plugins/plugin-logic/\n";
				$first_part .= " * Description: This file was created with Plugin Logic and contains the rules for the activation and deactivation of plugins.\n";
				$first_part .= " * Author: simon_h\n";
				$first_part .= " *\n";
				$first_part .= " * @package     Plugin Logic\n";
				$first_part .= " * @author      simon_h\n";
				$first_part .= " *\n";
				$first_part .= " * @since       1.0.0\n";
				$first_part .= " */\n\n";
				$first_part .= "if ( php_sapi_name() === 'cli' && ( ! isset( \$_SERVER['HTTP_HOST'] ) || ! isset( \$_SERVER['REQUEST_URI'] ) ) ) {\n";
				$first_part .= "trigger_error( 'Necessary \$_Server variables not set. If you use WP-CLI you can set it with the parameter --url.' );\n";
				$first_part .= "return;\n";
				$first_part .= "}\n\n";
				if ( $all_on_dash ) {
					$first_part .= "if ( ! is_admin() ) {\n\n";
				}
				$first_part .= "function search_words( \$words, \$url ) {\n";
				$first_part .= "foreach( \$words as \$word ) {\n";
				$first_part .= "if ( strpos( \$url, \$word ) !== false ) return true;\n";
				$first_part .= "}\n";
				$first_part .= "return false;\n";
				$first_part .= "}\n\n";
				$first_part .= "function search_patterns( \$patterns, \$url ) {\n";
				$first_part .= "foreach( \$patterns as \$pattern ) {\n";
				$first_part .= "if ( preg_match( \$pattern , \$url ) ) return true;\n";
				$first_part .= "}\n";
				$first_part .= "return false;\n";
				$first_part .= "}\n\n";
				$first_part .= "function plugin_logic_rules( \$plugins ){ \n";
				$first_part .= "\$current_url = 'http' . ( ( ! empty( \$_SERVER['HTTPS']) && \$_SERVER['HTTPS'] == 'on' ) ? 's://' : '://' ) . \$_SERVER['HTTP_HOST'] . \$_SERVER['REQUEST_URI'];\n";

				$last_part  = "\n";
				$last_part .= "// Rules for plugin-logic\n";
				if ( ! $all_on_dash ) {
					$last_part .= "if ( ! is_admin() ) {\n";
				}
				$last_part .= "\$key = array_search( '" . plugin_basename( __FILE__ ) . "' , \$plugins );\n";
				$last_part .= "if ( \$key !== false ) {\n";
				$last_part .= "unset( \$plugins[\$key] );\n";
				$last_part .= "}\n";
				if ( ! $all_on_dash ) {
					$last_part .= "}\n";
				}
				$last_part .= "\n";
				$last_part .= "return \$plugins;\n";
				$last_part .= "}\n";
				$last_part .= "add_filter( 'option_active_plugins', 'plugin_logic_rules' );\n";
				if ( $all_on_dash ) {
					$last_part .= "\n}\n";
				}

				$plugin_rules = '';
				foreach ( $singlesite_rules as $r ) {
					$plugin_rules .= self::create_plugin_rule( $r, $all_on_dash );
				}

				return self::indent_php_code( $first_part . $plugin_rules . $last_part );
			} else {
				return '';
			}
		}


		/***
		 * The create essential rule syntax for a plugin.
		 *
		 * @param array   $r           The rules for a plugin.
		 * @param boolean $all_on_dash True if all plugins visible on dashboard.
		 * @since 1.0.4
		 * @return string
		 */
		protected static function create_plugin_rule( $r = array(), $all_on_dash = true ) {
			$current_url_str = '$current_url';

			$rules       = json_decode( $r->rules, true );
			$url_rules   = $rules['urls'] ?? array();
			$word_rules  = $rules['words'] ?? array();
			$regex_rules = $rules['regex'] ?? array();
			$regex_rules = self::prepare_regex_rules( $regex_rules );

			// Prepare the syntax for the rule-file.
			$plugin_name = substr( strrchr( $r->name, '/' ), 1 );
			if ( empty( $plugin_name ) ) {
				$plugin_name = $r->name;
			}

			$logic_syn = ( '0' === $r->logic ) ? '! ' : '';
			$or_syn1   = ( ( count( $url_rules ) > 0 ) && ( ( count( $regex_rules ) > 0 ) || ( count( $word_rules ) > 0 ) ) ) ? ' ||' : '';
			$or_syn2   = ( ( count( $regex_rules ) > 0 ) && ( count( $word_rules ) > 0 ) ) ? ' ||' : '';

			$url_rules_syn = array( '', '' );
			if ( count( $url_rules ) > 0 ) {
				$url_rules_syn[0] = "\$url_rules = array(\n\t'" . implode( "',\n\t'", $url_rules ) . "'\n);\n";
				$url_rules_syn[1] = "in_array( $current_url_str, \$url_rules )";
			}

			$regex_rules_syn = array( '', '' );
			if ( count( $regex_rules ) > 0 ) {
				$regex_rules_syn[0] = "\$regex_rules = array(\n\t'" . implode( "',\n\t'", $regex_rules ) . "'\n);\n";
				$regex_rules_syn[1] = "search_patterns( \$regex_rules, $current_url_str )";
			}

			$word_rules_syn = array( '', '' );
			if ( count( $word_rules ) > 0 ) {
				$word_rules_syn[0] = "\$word_rules = array(\n\t'" . implode( "',\n\t'", $word_rules ) . "'\n);\n";
				$word_rules_syn[1] = "search_words( \$word_rules, $current_url_str )";
			}

			$essential_rule = "\n";

			// Rule head with plugin name.
			if ( ( '1' === $r->on_dashboard ) && ! $all_on_dash ) {
				$essential_rule .= '/' . str_repeat( '*', 40 ) . "\n";
				$essential_rule .= " * Rules for $plugin_name\n";
				$essential_rule .= ' ' . str_repeat( '*', 40 ) . "/\n";
				$essential_rule .= "if ( ! is_admin() ) {\n";
			} else {
				$essential_rule .= '/' . str_repeat( '*', 40 ) . "\n";
				$essential_rule .= " * Rules for $plugin_name\n";
				$essential_rule .= ' ' . str_repeat( '*', 40 ) . "/\n";
			}

			$essential_rule .= $url_rules_syn[0];
			$essential_rule .= $regex_rules_syn[0];
			$essential_rule .= $word_rules_syn[0];
			$essential_rule .= "if ( $logic_syn( $url_rules_syn[1]$or_syn1 $regex_rules_syn[1]$or_syn2 $word_rules_syn[1] ) ) {\n";
			$essential_rule .= "\$key = array_search( '{$r->name}' , \$plugins );\n";
			$essential_rule .= "if ( \$key !== false ) {\n";
			$essential_rule .= "unset( \$plugins[\$key] );\n";
			$essential_rule .= "}\n";
			$essential_rule .= "}\n";
			if ( ( '1' === $r->on_dashboard ) && ! $all_on_dash ) {
				$essential_rule .= "}\n";
			}

			return $essential_rule;
		}


		/***
		 * Try to write the rule content string into the file WPMU_PLUGIN_DIR/plugin-logic-rules.php
		 *
		 * @param string $rules an string with the rules to save to a file.
		 * @param string $wp_fs_nonce_url nonce url for the WP_Filesystem.
		 * @since 1.0.4
		 * @return boolean
		 */
		protected static function write_rule_file( $rules = '', $wp_fs_nonce_url = '' ) {
			global $wp_filesystem;

			if ( self::connect_to_wp_fs( $wp_fs_nonce_url ) ) {

				$rule_file = trailingslashit( WPMU_PLUGIN_DIR ) . 'plugin-logic-rules.php';
				if ( '' !== $rules ) {

					// Check directory permissions and write the WPMU_PLUGIN_DIR directory if not exists.
					if ( ! $wp_filesystem->exists( WPMU_PLUGIN_DIR ) ) {
						if ( $wp_filesystem->is_writable( WP_CONTENT_DIR ) ) {
							$wp_filesystem->mkdir( WPMU_PLUGIN_DIR, 0755 );
						} else {
							return new WP_Error( 'write_error', 'Your ' . substr( WP_CONTENT_DIR, strlen( ABSPATH ) ) . ' directory isn&#8217;t writable.' );
						}
					}

					// Check directory and file permissions and write the plugin-logic-rules.php file.
					if ( $wp_filesystem->exists( WPMU_PLUGIN_DIR ) ) {
						if ( $wp_filesystem->is_writable( WPMU_PLUGIN_DIR ) ) {
							if ( $wp_filesystem->exists( $rule_file ) ) {
								if ( false === $wp_filesystem->delete( $rule_file ) ) {
									return new WP_Error( 'write_error', 'Cannot delete the old rule file ' . substr( $rule_file, strlen( ABSPATH ) ) );
								}
							}
							if ( false === $wp_filesystem->put_contents( $rule_file, $rules ) ) {
								return new WP_Error( 'write_error', 'Cannot create the new rule file ' . substr( $rule_file, strlen( ABSPATH ) ) );
							}
						} else {
							return new WP_Error( 'write_error', 'Your ' . substr( WPMU_PLUGIN_DIR, strlen( ABSPATH ) ) . ' directory isn&#8217;t writable.' );
						}
					}
				} elseif ( $wp_filesystem->exists( $rule_file ) ) {

					if ( false === $wp_filesystem->delete( $rule_file ) ) {
						return new WP_Error( 'write_error', 'Cannot delete the old rule file ' . substr( $rule_file, strlen( ABSPATH ) ) );
					}
				}
			} else {
				return new WP_Error( 'write_error', 'Cannot initialize filesystem' );
			}

			return true;
		}


		/***
		 * If the database table for Plugin Logic rules doesn't exists, create it
		 *
		 * @since 1.0.4
		 */
		protected static function create_database_table() {
			global $wpdb;

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->query(
				$wpdb->prepare(
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange
					'CREATE TABLE IF NOT EXISTS %i (
						name VARCHAR(128) NOT NULL PRIMARY KEY,
						on_dashboard tinytext NOT NULL,
						logic tinytext NOT NULL,
						rules longtext NOT NULL
					)',
					PLULO_DBTABLE
				)
			);

			// Save database version to wp_options for future database table updates.
			if ( false === get_option( 'plulo_db_version' ) ) {
				add_option( 'plulo_db_version', 2, '', 'no' );
			} else {
				update_option( 'plulo_db_version', 2 );
			}
		}


		/***
		 * Actions if user activate Plugin Logic:
		 * If database table with rules exists, try create to create the rule file
		 *
		 * @since 1.0.0
		 */
		public static function on_activation() {
			if ( ! current_user_can( 'activate_plugins' ) ) {
				wp_die( esc_html( __( 'Cheatin&#8217; uh?' ) ) );
			}
			$plugin = isset( $_REQUEST['plugin'] ) ? sanitize_text_field( ( wp_unslash( $_REQUEST['plugin'] ) ) ) : '';
			check_admin_referer( "activate-plugin_{$plugin}" );

			if ( false === self::plulo_db_table_exists() ) {
				self::create_database_table();
			} else {

				// Get previous saved data from database.
				$rules_from_database = self::get_plulo_db_content();
				if ( count( $rules_from_database ) > 0 ) {
					$content     = self::create_rule_file_content( $rules_from_database );
					$url         = sprintf( admin_url( 'plugins.php?action=activate&plugin=%s&plugin_status=all&paged=1&s' ), $plugin );
					$write_error = self::write_rule_file( $content, $url );

					if ( is_wp_error( $write_error ) ) {
						wp_die( esc_html( $write_error->get_error_message() ) );
					}
				}

				// Activate plugins which are deactivated on admin page through the rule file.
				global $wpdb;

				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery
				$plugins_to_activate = $wpdb->get_results(
					$wpdb->prepare( 'SELECT name FROM %i WHERE on_dashboard = 0', PLULO_DBTABLE )
				);
				foreach ( $plugins_to_activate as $p ) {
					if ( false === is_plugin_active( $p->name ) ) {
						activate_plugin( $p->name, '', false, true );
					}
				}
			}
		}


		/***
		 * Actions if user deactivate Plugin Logic:
		 * Delete the rule file in the WPMU_PLUGIN_DIR and if the directory is empty also delete them.
		 *
		 * @since 1.0.0
		 */
		public static function on_deactivation() {
			if ( ! current_user_can( 'activate_plugins' ) ) {
				wp_die( esc_html( __( 'Cheatin&#8217; uh?' ) ) );
			}
			$plugin = isset( $_REQUEST['plugin'] ) ? sanitize_text_field( ( wp_unslash( $_REQUEST['plugin'] ) ) ) : '';
			check_admin_referer( "deactivate-plugin_{$plugin}" );

			global $wp_filesystem;

			$url = sprintf( admin_url( 'plugins.php?action=deactivate&plugin=%s&plugin_status=all&paged=1&s' ), $plugin );
			if ( false === self::connect_to_wp_fs( wp_nonce_url( $url, "deactivate-plugin_{$plugin}" ), '', false, null ) ) {
				wp_die( esc_html( __( 'Cannot initialize filesystem' ) ) );
			}

			$rule_file = trailingslashit( WPMU_PLUGIN_DIR ) . 'plugin-logic-rules.php';
			if ( $wp_filesystem->exists( $rule_file ) && $wp_filesystem->is_file( $rule_file ) ) {
				if ( ! $wp_filesystem->delete( $rule_file ) ) {
					if ( false === $wp_filesystem->delete( $rule_file ) ) {
						wp_die( esc_html( 'Cannot delete the old rule file: ' . substr( $rule_file, strlen( ABSPATH ) ) ) );
					}
				}
			}

			if ( false !== $wp_filesystem->dirlist( WPMU_PLUGIN_DIR )
				&& 0 === count( $wp_filesystem->dirlist( WPMU_PLUGIN_DIR ) ) ) {
				$wp_filesystem->delete( WPMU_PLUGIN_DIR );
			}
		}

		/***
		 * Actions if user uninstall Plugin Logic:
		 * Delete database entries.
		 *
		 * @since 1.0.0
		 */
		public static function on_uninstall() {
			if ( ! current_user_can( 'activate_plugins' ) ) {
				wp_die( esc_html( __( 'Cheatin&#8217; uh?' ) ) );
			}

			$GLOBALS['wpdb']->query( 'DROP TABLE IF EXISTS ' . PLULO_DBTABLE );
			delete_option( 'plulo_on_dash_col' );
			delete_option( 'plulo_db_version' );
		}
	} // end class

} // end if class exists
