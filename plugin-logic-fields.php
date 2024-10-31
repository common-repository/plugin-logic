<?php
/***
 * Create the settings page for the dashboard view
 *
 * @package     Plugin Logic
 * @author      simon_h
 *
 * @since       1.0.0
 */

// Security check.
if ( ! class_exists( 'WP' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	exit();
}

if ( ! class_exists( 'Plulo_Fields' ) ) {

	/**
	 * Plugin Logic class to generate fields for user input.
	 *
	 * @package Plugin Logic
	 */
	class Plulo_Fields {

		/**
		 * The class instance.
		 *
		 * @var \plugin_logic $classobj
		 */
		protected static $classobj = null;

		/**
		 * The name of the plugin base.
		 *
		 * @var string $plugin_base
		 */
		private $plugin_base = '';


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
		 * Install settings;
		 *
		 * @param string $plugin_basename Plugin name from the calling class.
		 * @since 1.0.4
		 */
		public function __construct( $plugin_basename = '' ) {
			$this->plugin_base = $plugin_basename;
		}


		/***
		 * Get colors from the dashboard style
		 *
		 * @since 1.0.0
		 */
		public function get_adminbar_colors() {
			?>
			<script type="text/javascript">

				function getStyle(el, cssprop){
					if (el.currentStyle) //IE
						return el.currentStyle[cssprop]
					else if (document.defaultView && document.defaultView.getComputedStyle) //Firefox
						return document.defaultView.getComputedStyle(el, "")[cssprop]
					else //try and get inline style
						return el.style[cssprop]
				}

				function rgbStringToHex(rgbStr){
					var a = rgbStr.split("(")[1].split(")")[0];
					a = a.split(",");
					var b = a.map(function(x){             
						x = parseInt(x).toString(16);      //Convert to a base16 string
						return (x.length==1) ? "0"+x : x;  //Add zero if we get only one character
					})
					return "#"+b.join("");
				}

				var wpadminbar = document.getElementById("wpadminbar");
				var table = document.getElementById("hrow");

				// Paint the table headline with admin colors 
				if ( wpadminbar ) {
					admin_background = rgbStringToHex( getStyle(wpadminbar, 'backgroundColor') );
					admin_color = rgbStringToHex( getStyle(wpadminbar, 'color') );

					table.style.background = admin_background;
					table.style.color = admin_color;

				} else {
					table.style.background = '#222';
					table.style.color = '#EEE';
				}

			</script> 
			<?php
		}


		/***
		 * Table CSS-Style
		 *
		 * @since 1.0.0
		 * @change 1.0.4
		 */
		public function create_style() {
			return '<!-- Table Style -->
					<style type="text/css"> 
					.tftable { border:1px solid #EFEFEF; border-collapse:collapse; width:100%; font-size:12px; }
					.tftable th { padding:8px; text-align:left;}
					.tftable tr { background-color:#fff; color:#000; }
					.tftable td { border:1px solid #EFEFEF; padding:8px; }
					#hrow { background-color:#222; color:#EEE; }
					</style>' . "\n";
		}


		/***
		 * Generate the html output to display the tabel with input fields
		 *
		 * @param array $db_pl_list Plugin Logic database table content.
		 * @since 1.0.0
		 * @return string html output.
		 */
		public function create_the_fields( $db_pl_list = array() ) {
			global $wpdb;
			$structur = '';
			( get_option( 'plulo_on_dash_col' ) !== false ) ? $on_dash_columm = get_option( 'plulo_on_dash_col' ) : $on_dash_columm = '';

			$plugin_infos = array();
			if ( is_admin() ) {
				$plugin_infos = get_plugins();
			}

			// Filter inactive plugins with rules and add it to the $active_plugin_list.
			$active_plugin_list  = get_option( 'active_plugins', array() );
			$inactive_rule_plugs = array();
			$plugins_for_output  = $active_plugin_list;
			foreach ( $db_pl_list as $db_pl ) {
				if ( ! in_array( $db_pl->name, $active_plugin_list, true ) ) {
					$inactive_rule_plugs[] = $db_pl->name;
				}
			}
			if ( count( $inactive_rule_plugs ) > 0 ) {
				$plugins_for_output = array_merge( $active_plugin_list, $inactive_rule_plugs );
				sort( $plugins_for_output );
			}

			// Check if relevant Plugins available.
			if ( 1 === count( $plugins_for_output ) ) {
				add_action(
					'admin_footer',
					function () {
						?>
					<script type="text/javascript">
						var tableFoot = document.getElementById("tfoot");
						tableFoot.style.display = 'none';
					</script> 
						<?php
					}
				);
				$structur .= "<div class=\"update-nag\">\n";
				$structur .= '	<h4>' . esc_html_e( 'There are no active Plugins or inactive Plugins with Rules.', 'plugin-logic' ) . "</h4>\n";
				$structur .= "</div>\n";
				return $structur;
			}

			// Create the html-table.
			if ( 'fresh' !== get_user_option( 'admin_color' ) ) {
				add_action( 'admin_footer', array( $this, 'get_adminbar_colors' ) );
			}
			$structur .= $this->create_style();
			$structur .= '<table class="tftable" border="1">' . "\n";
			$structur .= "<tr id=\"hrow\">\n";
			$structur .= '	<th>' . __( 'Activated Plugins', 'plugin-logic' ) . "</th>\n";
			if ( 'checked' === $on_dash_columm ) {
				$structur .= '	<th>' . __( 'Behavior on Dashbord', 'plugin-logic' ) . "</th>\n";
			}
			$structur .= '	<th>' . __( 'Active / Inactive', 'plugin-logic' ) . "</th>\n";
			$structur .= '	<th>' . __( 'Urls or occurring Words', 'plugin-logic' ) . "</th>\n";
			$structur .= "</tr>\n";

			$z = 0;
			foreach ( $plugins_for_output as $p ) {
				if ( $p === $this->plugin_base ) {
					continue;
				}
				$on_dashboard  = 'checked';
				$logic         = '0';
				$act_rules     = array();
				$act_rules_str = '';

				// Check if there are rules for the Plugin in the Database.
				foreach ( $db_pl_list as $db_pl ) {
					if ( $p === $db_pl->name ) {
						$act_rules     = json_decode( $db_pl->rules, true );
						$act_rules     = array_merge( $act_rules['urls'], $act_rules['words'], $act_rules['regex'] );
						$act_rules_str = implode( ",\n", $act_rules );
						$logic         = $db_pl->logic;
						$on_dashboard  = ( '1' === $db_pl->on_dashboard ) ? 'checked' : '';
						break;
					}
				}

				$select_in                      = '';
				$select_ex                      = '';
				( '0' === $logic ) ? $select_in = 'checked' : $select_ex = 'checked';

				$inactive = ( ! in_array( $p, $active_plugin_list, true ) ) ? ' style="background:#D3D1D1;"' : '';

				$txt_in_style = ( 'checked' === $on_dash_columm ) ? 'style="width:70%;"' : 'style="width:78%;"';

				$structur .= "<tr$inactive> \n";
				$structur .= '  <td style="min-width:98px;">' . $plugin_infos[ $p ]['Name'] . "</td>\n";
				if ( 'checked' === $on_dash_columm ) {
					$structur .= "  <td style=\"min-width:87px;\"> \n";
					$structur .= '	 	<input type="hidden" name="plulo_checklist[' . $z . ']" value="0">' . " \n";
					$structur .= '		<input type="checkbox" name="plulo_checklist[' . $z . ']" value="1" ' . $on_dashboard . '>' . __( 'Always on', 'plugin-logic' ) . "\n";
					$structur .= "  </td> \n";
				}
				$structur .= "  <td style=\"min-width:95px;\"> \n";
				$structur .= '	 	<input type="radio" name="plulo_radiolist[' . $z . ']" value="0" ' . $select_in . '>' . __( 'Active on:', 'plugin-logic' ) . "<br> \n";
				$structur .= '		<input type="radio" name="plulo_radiolist[' . $z . ']" value="1" ' . $select_ex . '>' . __( 'Inactive on:', 'plugin-logic' ) . "\n";
				$structur .= "  </td> \n";
				$structur .= '  <td ' . $txt_in_style . ' ><textarea name="plulo_txt_list[' . $z . ']" style="width:100%; min-height:74px">' . $act_rules_str . '</textarea></td>' . "\n";
				$structur .= "</tr> \n";

				++$z;
			}
			$structur .= "</table> \n";
			return $structur;
		}
	} // end class

} // end if class exists
