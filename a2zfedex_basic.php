<?php
/**
 * Plugin Name:  FedEx Rates & Labels
 * Plugin URI: https://myshipi.com/
 * Description: Realtime Shipping Rates, shipping labels.
 * Version: 4.7.8
 * Author: Shipi
 * Author URI: https://myshipi.com/
 * Developer: Shipi
 * Developer URI: https://myshipi.com/
 * Text Domain: a2z_fedex
 * Domain Path: /i18n/languages/
 *
 * WC requires at least: 2.6
 * WC tested up to: 6.4
 *
 *
 * @package WooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// Define WC_PLUGIN_FILE.
if ( ! defined( 'HITSHIPPO_FEDEX_PLUGIN_FILE' ) ) {
	define( 'HITSHIPPO_FEDEX_PLUGIN_FILE', __FILE__ );
}
// set HPOS feature compatible by plugin
add_action(
    'before_woocommerce_init',
    function () {
        if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
        }
    }
);

// Include the main WooCommerce class.
if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {

	if( !class_exists('hitshippo_fedex_parent') ){
		Class hitshippo_fedex_parent
		{
			public $hpos_enabled = false;
			public $new_prod_editor_enabled = false;
			public function __construct() {
				add_action('rest_api_init', array($this, 'rest_api' ));

				if (get_option("woocommerce_custom_orders_table_enabled") === "yes") {
					$this->hpos_enabled = true;
				}
				if (get_option("woocommerce_feature_product_block_editor_enabled") === "yes") {
					$this->new_prod_editor_enabled = true;
				}
				add_action( 'woocommerce_shipping_init', array($this,'hitshippo_fedex_init') );
				add_action( 'init', array($this,'hitshippo_fedex_order_status_update') );
				add_filter( 'woocommerce_shipping_methods', array($this,'hitshippo_fedex_method') );
				add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'hitshippo_fedex_plugin_action_links' ) );
				add_action( 'add_meta_boxes', array($this, 'create_fedex_shipping_meta_box'), 10, 1);
				if ($this->hpos_enabled) {
					add_action( 'woocommerce_process_shop_order_meta', array($this, 'hitshippo_create_fedex_shipping'), 10, 1 );
					add_action( 'woocommerce_process_shop_order_meta', array($this, 'hitshippo_create_fedex_return_shipping'), 10, 1 );
				} else {
					add_action( 'save_post', array($this, 'hitshippo_create_fedex_shipping'), 10, 1 );
					add_action( 'save_post', array($this, 'hitshippo_create_fedex_return_shipping'), 10, 1 );
				}
				add_action( 'admin_menu', array($this, 'hit_fedex_menu_page' ));
				add_filter( 'woocommerce_product_data_tabs', array($this,'hit_product_data_tab') );
				add_action( 'woocommerce_process_product_meta', array($this,'hit_save_product_options' ));
				add_filter( 'woocommerce_product_data_panels', array($this,'hit_product_option_view') );
				add_action( 'woocommerce_order_status_processing', array( $this, 'hitshippo_fedex_wc_checkout_order_processed' ) );
				 // add_action( 'woocommerce_thankyou', array( $this, 'hitshippo_fedex_wc_checkout_order_processed' ) );

				$general_settings = get_option('hitshippo_fedex_main_settings');
				$general_settings = empty($general_settings) ? array() : $general_settings;

				if(isset($general_settings['hitshippo_fedex_v_enable']) && $general_settings['hitshippo_fedex_v_enable'] == 'yes' ){
					add_action( 'woocommerce_product_options_shipping', array($this,'hit_choose_vendor_address' ));
					add_action( 'woocommerce_process_product_meta', array($this,'hit_save_product_meta' ));

					// Edit User Hooks
					add_action( 'edit_user_profile', array($this,'hit_define_fedex_credentails') );
					add_action( 'edit_user_profile_update', array($this, 'save_user_fields' ));

				}
				add_action( 'woocommerce_view_order', array($this, 'add_tracking_info_to_order_view'), 10 );
			}

			function add_tracking_info_to_order_view( $order_id ) {
				$shipment_data = json_decode(get_option('hitshippo_fedex_values_'.$order_id), true); // using "true" to convert stdobject to array
				
				if ($shipment_data){
					foreach($shipment_data as $key => $shipment){
						if(isset($shipment["tracking_num"])){
	
							?>
							<script>
								// Listener in the parent window
								function receiveMessage(event) {
									// Optionally check the event origin for security
									if (event.data && event.data.type === 'setHeight') {
										var iframe = document.getElementById('shipi_fedexcontentFrame<?php echo $key;?>');
										iframe.style.height = event.data.height + 'px';
									}
								}
								window.addEventListener('message', receiveMessage, false);
							</script>
							<iframe src="https://track.myshipi.com/?no=<?php echo $shipment["tracking_num"]; ?>&track=1&embed=1" scrolling="no" id="shipi_fedexcontentFrame<?php echo $key;?>" style="width: 100%;border: 0px;background: transparent;"></iframe>
							<?php
						}
					}
				}
				
				
			}

			function rest_api($data) {
				register_rest_route('shipi/v1', '/connect/fedex/', array(
					'methods' => 'GET',
					'callback' => array($this, "shipi_connect"),
					'permission_callback' => '__return_true'
				));
			}
			function shipi_connect($data){
				$shipi_key = $data->get_param('shipi_key');
				$shipi_key = sanitize_text_field($shipi_key);
				$response = array();
				if($shipi_key == 'fetch'){
					$response = array(
						get_transient('hitshipo_fedex_express_nonce_temp')
					);
					return new WP_REST_Response($response, 200);
				}
				return new WP_REST_Response($response, 400);
			}
			function hit_fedex_menu_page() {
				$general_settings = get_option('hitshippo_fedex_main_settings');
				if (isset($general_settings['hitshippo_fedex_shippo_int_key']) && !empty($general_settings['hitshippo_fedex_shippo_int_key'])) {
					add_menu_page(__( 'Fedex Labels', 'hitshippo_fedex' ), 'Fedex Labels', 'manage_options', 'hit-fedex-labels', array($this,'my_label_page_contents'), '', 6);
				}

				add_submenu_page( 'options-general.php', 'Fedex Config', 'Fedex Config', 'manage_options', 'hit-fedex-configuration', array($this, 'my_admin_page_contents') );

			}
			function my_label_page_contents(){
				$general_settings = get_option('hitshippo_fedex_main_settings');
				$url = site_url();
				if (isset($general_settings['hitshippo_fedex_shippo_int_key']) && !empty($general_settings['hitshippo_fedex_shippo_int_key'])) {
					echo "<iframe style='width: 100%;height: 100vh;' src='https://app.myshipi.com/embed/label.php?shop=".$url."&key=".$general_settings['hitshippo_fedex_shippo_int_key']."&show=ship'></iframe>";
				}
            }
			function my_admin_page_contents(){
				include_once('controllors/views/hitshippo_fedex_settings_view.php');
			}
			
			public function hit_product_data_tab( $tabs) {

				$tabs['hits_fedex_product_options'] = array(
					'label'		=> __( 'Shipi - FedEx Options', 'hitshippo_fedex' ),
					'target'	=> 'hit_fedex_product_options',
					// 'class'		=> array( 'show_if_simple', 'show_if_variable' ),
				);
			
				return $tabs;
			
			}

			public function hit_choose_vendor_address(){
				global $woocommerce, $post;
				$hit_multi_vendor = get_option('hit_multi_vendor');
				$hit_multi_vendor = empty($hit_multi_vendor) ? array() : $hit_multi_vendor;
				if ($this->hpos_enabled) {
					$hpos_prod_data = wc_get_product($post->ID);
					$selected_addr = $hpos_prod_data->get_meta("fedex_address");
				} else {
					$selected_addr = get_post_meta( $post->ID, 'fedex_address', true);
				}

				$main_settings = get_option('hitshippo_fedex_main_settings');
				$main_settings = empty($main_settings) ? array() : $main_settings;
				if(!isset($main_settings['hitshippo_fedex_v_roles']) || empty($main_settings['hitshippo_fedex_v_roles'])){
					return;
				}
				$v_users = get_users( [ 'role__in' => $main_settings['hitshippo_fedex_v_roles'] ] );

				?>
				<div class="options_group">
				<p class="form-field fedex_shipment">
					<label for="fedex_shipment"><?php _e( 'Fedex Account', 'woocommerce' ); ?></label>
					<select id="fedex_shipment" style="width:240px;" name="fedex_shipment" class="wc-enhanced-select" data-placeholder="<?php _e( 'Search for a product&hellip;', 'woocommerce' ); ?>">
						<option value="default" >Default Account</option>
						<?php
							if ( $v_users ) {
								foreach ( $v_users as $value ) {
									echo '<option value="' .  $value->data->ID  . '" '.($selected_addr == $value->data->ID ? 'selected="true"' : '').'>' . $value->data->display_name . '</option>';
								}
							}
						?>
					</select>
					</p>
				</div>
				<?php
			}

			public function hit_save_product_meta( $post_id ){
				if(isset( $_POST['fedex_shipment'])){
					$fedex_shipment = $_POST['fedex_shipment'];
					if( !empty( $fedex_shipment ) )
					update_post_meta( $post_id, 'fedex_address', (string) esc_html( $fedex_shipment ) );
				}

			}
			public function hit_save_product_options( $post_id ){
				if( isset($_POST['hits_fedex_cc']) ){
					$cc = sanitize_text_field($_POST['hits_fedex_cc']);
					if ($this->hpos_enabled && $this->new_prod_editor_enabled) {
						$hpos_prod_data = wc_get_product($post_id);
						$fedex_account = $hpos_prod_data->update_meta_data("hits_fedex_cc", (string) esc_html( $cc ));
					} else {
						update_post_meta( $post_id, 'hits_fedex_cc', (string) esc_html( $cc ) );
					}
					
					// print_r($post_id);die();
				}
				
				if( isset($_POST['hits_fedex_label_dec']) ){
					$cc = sanitize_text_field($_POST['hits_fedex_label_dec']);
					if ($this->hpos_enabled && $this->new_prod_editor_enabled) {
						$hpos_prod_data = wc_get_product($post_id);
						$fedex_account = $hpos_prod_data->update_meta_data("hits_fedex_label_dec", (string) esc_html( $cc ));
					} else {
						update_post_meta( $post_id, 'hits_fedex_label_dec', (string) esc_html( $cc ) );
					}
					// print_r($post_id);die();
				}
				
			}

			public function hit_product_option_view(){
				global $woocommerce, $post;
				if ($this->hpos_enabled) {
					$hpos_prod_data = wc_get_product($post->ID);
					$hits_fedex_saved_cc = $hpos_prod_data->get_meta("hits_fedex_cc");
					$hits_fedex_saved_dec = $hpos_prod_data->get_meta("hits_fedex_label_dec");
				} else {
					$hits_fedex_saved_cc = get_post_meta( $post->ID, 'hits_fedex_cc', true);
					$hits_fedex_saved_dec = get_post_meta( $post->ID, 'hits_fedex_label_dec', true);
				}
				?>
				<div id='hit_fedex_product_options' class='panel woocommerce_options_panel'>
					<div class='options_group'>
						<p class="form-field">
							<label for="hits_fedex_cc"><?php _e( 'Enter Commodity code / HS code', 'hitshippo_fedex' ); ?></label>
							<span class='woocommerce-help-tip' data-tip="<?php _e('Enter commodity code for product (20 charcters max).','hitshippo_fedex') ?>"></span>
							<input type='text' id='hits_fedex_cc' name='hits_fedex_cc' <?php echo (!empty($hits_fedex_saved_cc) ? 'value="'.$hits_fedex_saved_cc.'"' : '');?> style="width: 90%;">
						</p>
					</div>	
					<div class='options_group'>
						<p class="form-field">
							<label for="hits_fedex_label_dec"><?php _e( 'Product Description', 'hitshippo_fedex' ); ?></label>
							<span class='woocommerce-help-tip' data-tip="<?php _e('label description.','hitshippo_fedex') ?>"></span>
							<input type='text' id='hits_fedex_label_dec' name='hits_fedex_label_dec' <?php echo (!empty($hits_fedex_saved_dec) ? 'value="'.$hits_fedex_saved_dec.'"' : '');?> style="width: 90%;">
						</p>
					</div>
					
				</div>
				<?php
			}

			public function hit_define_fedex_credentails( $user ){

				$main_settings = get_option('hitshippo_fedex_main_settings');
				$main_settings = empty($main_settings) ? array() : $main_settings;
				$allow = false;

				if(!isset($main_settings['hitshippo_fedex_v_roles'])){
					return;
				}else{
					foreach ($user->roles as $value) {
						if(in_array($value, $main_settings['hitshippo_fedex_v_roles'])){
							$allow = true;
						}
					}
				}

				if(!$allow){
					return;
				}

				$general_settings = get_post_meta($user->ID,'hitshippo_fedex_vendor_settings',true);
				$general_settings = empty($general_settings) ? array() : $general_settings;
				$countires =  array(
									'AF' => 'Afghanistan',
									'AL' => 'Albania',
									'DZ' => 'Algeria',
									'AS' => 'American Samoa',
									'AD' => 'Andorra',
									'AO' => 'Angola',
									'AI' => 'Anguilla',
									'AG' => 'Antigua and Barbuda',
									'AR' => 'Argentina',
									'AM' => 'Armenia',
									'AW' => 'Aruba',
									'AU' => 'Australia',
									'AT' => 'Austria',
									'AZ' => 'Azerbaijan',
									'BS' => 'Bahamas',
									'BH' => 'Bahrain',
									'BD' => 'Bangladesh',
									'BB' => 'Barbados',
									'BY' => 'Belarus',
									'BE' => 'Belgium',
									'BZ' => 'Belize',
									'BJ' => 'Benin',
									'BM' => 'Bermuda',
									'BT' => 'Bhutan',
									'BO' => 'Bolivia',
									'BA' => 'Bosnia and Herzegovina',
									'BW' => 'Botswana',
									'BR' => 'Brazil',
									'VG' => 'British Virgin Islands',
									'BN' => 'Brunei',
									'BG' => 'Bulgaria',
									'BF' => 'Burkina Faso',
									'BI' => 'Burundi',
									'KH' => 'Cambodia',
									'CM' => 'Cameroon',
									'CA' => 'Canada',
									'CV' => 'Cape Verde',
									'KY' => 'Cayman Islands',
									'CF' => 'Central African Republic',
									'TD' => 'Chad',
									'CL' => 'Chile',
									'CN' => 'China',
									'CO' => 'Colombia',
									'KM' => 'Comoros',
									'CK' => 'Cook Islands',
									'CR' => 'Costa Rica',
									'HR' => 'Croatia',
									'CU' => 'Cuba',
									'CY' => 'Cyprus',
									'CZ' => 'Czech Republic',
									'DK' => 'Denmark',
									'DJ' => 'Djibouti',
									'DM' => 'Dominica',
									'DO' => 'Dominican Republic',
									'TL' => 'East Timor',
									'EC' => 'Ecuador',
									'EG' => 'Egypt',
									'SV' => 'El Salvador',
									'GQ' => 'Equatorial Guinea',
									'ER' => 'Eritrea',
									'EE' => 'Estonia',
									'ET' => 'Ethiopia',
									'FK' => 'Falkland Islands',
									'FO' => 'Faroe Islands',
									'FJ' => 'Fiji',
									'FI' => 'Finland',
									'FR' => 'France',
									'GF' => 'French Guiana',
									'PF' => 'French Polynesia',
									'GA' => 'Gabon',
									'GM' => 'Gambia',
									'GE' => 'Georgia',
									'DE' => 'Germany',
									'GH' => 'Ghana',
									'GI' => 'Gibraltar',
									'GR' => 'Greece',
									'GL' => 'Greenland',
									'GD' => 'Grenada',
									'GP' => 'Guadeloupe',
									'GU' => 'Guam',
									'GT' => 'Guatemala',
									'GG' => 'Guernsey',
									'GN' => 'Guinea',
									'GW' => 'Guinea-Bissau',
									'GY' => 'Guyana',
									'HT' => 'Haiti',
									'HN' => 'Honduras',
									'HK' => 'Hong Kong',
									'HU' => 'Hungary',
									'IS' => 'Iceland',
									'IN' => 'India',
									'ID' => 'Indonesia',
									'IR' => 'Iran',
									'IQ' => 'Iraq',
									'IE' => 'Ireland',
									'IL' => 'Israel',
									'IT' => 'Italy',
									'CI' => 'Ivory Coast',
									'JM' => 'Jamaica',
									'JP' => 'Japan',
									'JE' => 'Jersey',
									'JO' => 'Jordan',
									'KZ' => 'Kazakhstan',
									'KE' => 'Kenya',
									'KI' => 'Kiribati',
									'KW' => 'Kuwait',
									'KG' => 'Kyrgyzstan',
									'LA' => 'Laos',
									'LV' => 'Latvia',
									'LB' => 'Lebanon',
									'LS' => 'Lesotho',
									'LR' => 'Liberia',
									'LY' => 'Libya',
									'LI' => 'Liechtenstein',
									'LT' => 'Lithuania',
									'LU' => 'Luxembourg',
									'MO' => 'Macao',
									'MK' => 'Macedonia',
									'MG' => 'Madagascar',
									'MW' => 'Malawi',
									'MY' => 'Malaysia',
									'MV' => 'Maldives',
									'ML' => 'Mali',
									'MT' => 'Malta',
									'MH' => 'Marshall Islands',
									'MQ' => 'Martinique',
									'MR' => 'Mauritania',
									'MU' => 'Mauritius',
									'YT' => 'Mayotte',
									'MX' => 'Mexico',
									'FM' => 'Micronesia',
									'MD' => 'Moldova',
									'MC' => 'Monaco',
									'MN' => 'Mongolia',
									'ME' => 'Montenegro',
									'MS' => 'Montserrat',
									'MA' => 'Morocco',
									'MZ' => 'Mozambique',
									'MM' => 'Myanmar',
									'NA' => 'Namibia',
									'NR' => 'Nauru',
									'NP' => 'Nepal',
									'NL' => 'Netherlands',
									'NC' => 'New Caledonia',
									'NZ' => 'New Zealand',
									'NI' => 'Nicaragua',
									'NE' => 'Niger',
									'NG' => 'Nigeria',
									'NU' => 'Niue',
									'KP' => 'North Korea',
									'MP' => 'Northern Mariana Islands',
									'NO' => 'Norway',
									'OM' => 'Oman',
									'PK' => 'Pakistan',
									'PW' => 'Palau',
									'PA' => 'Panama',
									'PG' => 'Papua New Guinea',
									'PY' => 'Paraguay',
									'PE' => 'Peru',
									'PH' => 'Philippines',
									'PL' => 'Poland',
									'PT' => 'Portugal',
									'PR' => 'Puerto Rico',
									'QA' => 'Qatar',
									'CG' => 'Republic of the Congo',
									'RE' => 'Reunion',
									'RO' => 'Romania',
									'RU' => 'Russia',
									'RW' => 'Rwanda',
									'SH' => 'Saint Helena',
									'KN' => 'Saint Kitts and Nevis',
									'LC' => 'Saint Lucia',
									'VC' => 'Saint Vincent and the Grenadines',
									'WS' => 'Samoa',
									'SM' => 'San Marino',
									'ST' => 'Sao Tome and Principe',
									'SA' => 'Saudi Arabia',
									'SN' => 'Senegal',
									'RS' => 'Serbia',
									'SC' => 'Seychelles',
									'SL' => 'Sierra Leone',
									'SG' => 'Singapore',
									'SK' => 'Slovakia',
									'SI' => 'Slovenia',
									'SB' => 'Solomon Islands',
									'SO' => 'Somalia',
									'ZA' => 'South Africa',
									'KR' => 'South Korea',
									'SS' => 'South Sudan',
									'ES' => 'Spain',
									'LK' => 'Sri Lanka',
									'SD' => 'Sudan',
									'SR' => 'Suriname',
									'SZ' => 'Swaziland',
									'SE' => 'Sweden',
									'CH' => 'Switzerland',
									'SY' => 'Syria',
									'TW' => 'Taiwan',
									'TJ' => 'Tajikistan',
									'TZ' => 'Tanzania',
									'TH' => 'Thailand',
									'TG' => 'Togo',
									'TO' => 'Tonga',
									'TT' => 'Trinidad and Tobago',
									'TN' => 'Tunisia',
									'TR' => 'Turkey',
									'TC' => 'Turks and Caicos Islands',
									'TV' => 'Tuvalu',
									'VI' => 'U.S. Virgin Islands',
									'UG' => 'Uganda',
									'UA' => 'Ukraine',
									'AE' => 'United Arab Emirates',
									'GB' => 'United Kingdom',
									'US' => 'United States',
									'UY' => 'Uruguay',
									'UZ' => 'Uzbekistan',
									'VU' => 'Vanuatu',
									'VE' => 'Venezuela',
									'VN' => 'Vietnam',
									'YE' => 'Yemen',
									'ZM' => 'Zambia',
									'ZW' => 'Zimbabwe',
								);
				 $_fedex_carriers = array(
							'FIRST_OVERNIGHT'                    => 'FedEx First Overnight',
							'PRIORITY_OVERNIGHT'                 => 'FedEx Priority Overnight',
							'STANDARD_OVERNIGHT'                 => 'FedEx Standard Overnight',
							'FEDEX_2_DAY_AM'                     => 'FedEx 2Day A.M',
							'FEDEX_2_DAY'                        => 'FedEx 2Day',
							'SAME_DAY'                        => 'FedEx Same Day',
							'SAME_DAY_CITY'                        => 'FedEx Same Day City',
							'SAME_DAY_METRO_AFTERNOON'                        => 'FedEx Same Day Metro Afternoon',
							'SAME_DAY_METRO_MORNING'                        => 'FedEx Same Day Metro Morning',
							'SAME_DAY_METRO_RUSH'                        => 'FedEx Same Day Metro Rush',
							'FEDEX_EXPRESS_SAVER'                => 'FedEx Express Saver',
							'GROUND_HOME_DELIVERY'               => 'FedEx Ground Home Delivery',
							'FEDEX_GROUND'                       => 'FedEx Ground',
							'INTERNATIONAL_ECONOMY'              => 'International Economy',
							'INTERNATIONAL_ECONOMY_DISTRIBUTION'              => 'International Economy Distribution',
							'INTERNATIONAL_FIRST'                => 'International First',
							'INTERNATIONAL_GROUND'                => 'International Ground',
							'INTERNATIONAL_PRIORITY'             => 'International Priority',
							'INTERNATIONAL_PRIORITY_DISTRIBUTION'             => 'FedEx International Priority Distribution',
							'EUROPE_FIRST_INTERNATIONAL_PRIORITY' => 'FedEx Europe First International Priority',
							'INTERNATIONAL_PRIORITY_EXPRESS' => 'FedEx International Priority Express',
							'FEDEX_INTERNATIONAL_PRIORITY_PLUS' => 'FedEx First International Priority Plus',
							'FEDEX_INTERNATIONAL_PRIORITY_EXPRESS'  => 'Fedex international priority express',
							'FEDEX_INTERNATIONAL_PRIORITY'          => 'Fedex international priority',
							'FEDEX_INTERNATIONAL_CONNECT_PLUS'      => 'Fedex international connect plus',
							'INTERNATIONAL_DISTRIBUTION_FREIGHT' => 'FedEx International Distribution Fright',
							'FEDEX_1_DAY_FREIGHT'                => 'FedEx 1 Day Freight',
							'FEDEX_2_DAY_FREIGHT'                => 'FedEx 2 Day Freight',
							'FEDEX_3_DAY_FREIGHT'                => 'FedEx 3 Day Freight',
							'INTERNATIONAL_ECONOMY_FREIGHT'      => 'FedEx Economy Freight',
							'INTERNATIONAL_PRIORITY_FREIGHT'     => 'FedEx Priority Freight',
							'SMART_POST'                         => 'FedEx Smart Post',
							'FEDEX_FIRST_FREIGHT'                => 'FedEx First Freight',
							'FEDEX_FREIGHT_ECONOMY'              => 'FedEx Freight Economy',
							'FEDEX_FREIGHT_PRIORITY'             => 'FedEx Freight Priority',
							'FEDEX_CARGO_AIRPORT_TO_AIRPORT'             => 'FedEx CARGO Airport to Airport',
							'FEDEX_CARGO_FREIGHT_FORWARDING'             => 'FedEx CARGO Freight FOrwarding',
							'FEDEX_CARGO_INTERNATIONAL_EXPRESS_FREIGHT'             => 'FedEx CARGO International Express Fright',
							'FEDEX_CARGO_INTERNATIONAL_PREMIUM'             => 'FedEx CARGO International Premium',
							'FEDEX_CARGO_MAIL'             => 'FedEx CARGO Mail',
							'FEDEX_CARGO_REGISTERED_MAIL'             => 'FedEx CARGO Registered Mail',
							'FEDEX_CARGO_SURFACE_MAIL'             => 'FedEx CARGO Surface Mail',
							'FEDEX_CUSTOM_CRITICAL_AIR_EXPEDITE_EXCLUSIVE_USE'             => 'FedEx Custom Critical Air Expedite Exclusive Use',
							'FEDEX_CUSTOM_CRITICAL_AIR_EXPEDITE_NETWORK'             => 'FedEx Custom Critical Air Expedite Network',
							'FEDEX_CUSTOM_CRITICAL_CHARTER_AIR'             => 'FedEx Custom Critical Charter Air',
							'FEDEX_CUSTOM_CRITICAL_POINT_TO_POINT'             => 'FedEx Custom Critical Point to Point',
							'FEDEX_CUSTOM_CRITICAL_SURFACE_EXPEDITE'             => 'FedEx Custom Critical Surface Expedite',
							'FEDEX_CUSTOM_CRITICAL_SURFACE_EXPEDITE_EXCLUSIVE_USE'             => 'FedEx Custom Critical Surface Expedite Exclusive Use',
							'FEDEX_CUSTOM_CRITICAL_TEMP_ASSURE_AIR'             => 'FedEx Custom Critical Temp Assure Air',
							'FEDEX_CUSTOM_CRITICAL_TEMP_ASSURE_VALIDATED_AIR'             => 'FedEx Custom Critical Temp Assure Validated Air',
							'FEDEX_CUSTOM_CRITICAL_WHITE_GLOVE_SERVICES'             => 'FedEx Custom Critical White Glove Services',
							'TRANSBORDER_DISTRIBUTION_CONSOLIDATION'             => 'Fedex Transborder Distribution Consolidation',
							'FEDEX_DISTANCE_DEFERRED'            => 'FedEx Distance Deferred',
							'FEDEX_NEXT_DAY_EARLY_MORNING'       => 'FedEx Next Day Early Morning',
							'FEDEX_NEXT_DAY_MID_MORNING'         => 'FedEx Next Day Mid Morning',
							'FEDEX_NEXT_DAY_AFTERNOON'           => 'FedEx Next Day Afternoon',
							'FEDEX_NEXT_DAY_END_OF_DAY'          => 'FedEx Next Day End of Day',
							'FEDEX_NEXT_DAY_FREIGHT'             => 'FedEx Next Day Freight',
							);

			$fedex_core = array();
			$fedex_core['AD'] = array('region' => 'EU', 'currency' =>'EUR', 'weight' => 'KG_CM');
			$fedex_core['AE'] = array('region' => 'AP', 'currency' =>array('AED', 'DHS'), 'weight' => 'KG_CM');
			$fedex_core['AF'] = array('region' => 'AP', 'currency' =>'AFN', 'weight' => 'KG_CM');
			$fedex_core['AG'] = array('region' => 'AM', 'currency' =>'XCD', 'weight' => 'LB_IN');
			$fedex_core['AI'] = array('region' => 'AM', 'currency' =>'XCD', 'weight' => 'LB_IN');
			$fedex_core['AL'] = array('region' => 'AP', 'currency' =>'EUR', 'weight' => 'KG_CM');
			$fedex_core['AM'] = array('region' => 'AP', 'currency' =>'AMD', 'weight' => 'KG_CM');
			$fedex_core['AN'] = array('region' => 'AM', 'currency' =>'ANG', 'weight' => 'KG_CM');
			$fedex_core['AO'] = array('region' => 'AP', 'currency' =>'AOA', 'weight' => 'KG_CM');
			$fedex_core['AR'] = array('region' => 'AM', 'currency' =>'ARS', 'weight' => 'KG_CM');
			$fedex_core['AS'] = array('region' => 'AM', 'currency' =>'USD', 'weight' => 'LB_IN');
			$fedex_core['AT'] = array('region' => 'EU', 'currency' =>'EUR', 'weight' => 'KG_CM');
			$fedex_core['AU'] = array('region' => 'AP', 'currency' =>'AUD', 'weight' => 'KG_CM');
			$fedex_core['AW'] = array('region' => 'AM', 'currency' =>'AWG', 'weight' => 'LB_IN');
			$fedex_core['AZ'] = array('region' => 'AM', 'currency' =>'AZN', 'weight' => 'KG_CM');
			$fedex_core['AZ'] = array('region' => 'AM', 'currency' =>'AZN', 'weight' => 'KG_CM');
			$fedex_core['GB'] = array('region' => 'EU', 'currency' =>'GBP', 'weight' => 'KG_CM');
			$fedex_core['BA'] = array('region' => 'AP', 'currency' =>'BAM', 'weight' => 'KG_CM');
			$fedex_core['BB'] = array('region' => 'AM', 'currency' =>'BBD', 'weight' => 'LB_IN');
			$fedex_core['BD'] = array('region' => 'AP', 'currency' =>'BDT', 'weight' => 'KG_CM');
			$fedex_core['BE'] = array('region' => 'EU', 'currency' =>'EUR', 'weight' => 'KG_CM');
			$fedex_core['BF'] = array('region' => 'AP', 'currency' =>'XOF', 'weight' => 'KG_CM');
			$fedex_core['BG'] = array('region' => 'EU', 'currency' =>'BGN', 'weight' => 'KG_CM');
			$fedex_core['BH'] = array('region' => 'AP', 'currency' =>'BHD', 'weight' => 'KG_CM');
			$fedex_core['BI'] = array('region' => 'AP', 'currency' =>'BIF', 'weight' => 'KG_CM');
			$fedex_core['BJ'] = array('region' => 'AP', 'currency' =>'XOF', 'weight' => 'KG_CM');
			$fedex_core['BM'] = array('region' => 'AM', 'currency' =>'BMD', 'weight' => 'LB_IN');
			$fedex_core['BN'] = array('region' => 'AP', 'currency' =>'BND', 'weight' => 'KG_CM');
			$fedex_core['BO'] = array('region' => 'AM', 'currency' =>'BOB', 'weight' => 'KG_CM');
			$fedex_core['BR'] = array('region' => 'AM', 'currency' =>'BRL', 'weight' => 'KG_CM');
			$fedex_core['BS'] = array('region' => 'AM', 'currency' =>'BSD', 'weight' => 'LB_IN');
			$fedex_core['BT'] = array('region' => 'AP', 'currency' =>'BTN', 'weight' => 'KG_CM');
			$fedex_core['BW'] = array('region' => 'AP', 'currency' =>'BWP', 'weight' => 'KG_CM');
			$fedex_core['BY'] = array('region' => 'AP', 'currency' =>'BYR', 'weight' => 'KG_CM');
			$fedex_core['BZ'] = array('region' => 'AM', 'currency' =>'BZD', 'weight' => 'KG_CM');
			$fedex_core['CA'] = array('region' => 'AM', 'currency' =>'CAD', 'weight' => 'LB_IN');
			$fedex_core['CF'] = array('region' => 'AP', 'currency' =>'XAF', 'weight' => 'KG_CM');
			$fedex_core['CG'] = array('region' => 'AP', 'currency' =>'XAF', 'weight' => 'KG_CM');
			$fedex_core['CH'] = array('region' => 'EU', 'currency' =>array('CHF', 'SFR'), 'weight' => 'KG_CM');
			$fedex_core['CI'] = array('region' => 'AP', 'currency' =>'XOF', 'weight' => 'KG_CM');
			$fedex_core['CK'] = array('region' => 'AP', 'currency' =>'NZD', 'weight' => 'KG_CM');
			$fedex_core['CL'] = array('region' => 'AM', 'currency' =>'CLP', 'weight' => 'KG_CM');
			$fedex_core['CM'] = array('region' => 'AP', 'currency' =>'XAF', 'weight' => 'KG_CM');
			$fedex_core['CN'] = array('region' => 'AP', 'currency' =>'CNY', 'weight' => 'KG_CM');
			$fedex_core['CO'] = array('region' => 'AM', 'currency' =>'COP', 'weight' => 'KG_CM');
			$fedex_core['CR'] = array('region' => 'AM', 'currency' =>'CRC', 'weight' => 'KG_CM');
			$fedex_core['CU'] = array('region' => 'AM', 'currency' =>'CUC', 'weight' => 'KG_CM');
			$fedex_core['CV'] = array('region' => 'AP', 'currency' =>'CVE', 'weight' => 'KG_CM');
			$fedex_core['CY'] = array('region' => 'AP', 'currency' =>'EUR', 'weight' => 'KG_CM');
			$fedex_core['CZ'] = array('region' => 'EU', 'currency' =>'CZK', 'weight' => 'KG_CM');
			$fedex_core['DE'] = array('region' => 'AP', 'currency' =>'EUR', 'weight' => 'KG_CM');
			$fedex_core['DJ'] = array('region' => 'EU', 'currency' =>'DJF', 'weight' => 'KG_CM');
			$fedex_core['DK'] = array('region' => 'AM', 'currency' =>'DKK', 'weight' => 'KG_CM');
			$fedex_core['DM'] = array('region' => 'AM', 'currency' =>'XCD', 'weight' => 'LB_IN');
			$fedex_core['DO'] = array('region' => 'AP', 'currency' =>'DOP', 'weight' => 'LB_IN');
			$fedex_core['DZ'] = array('region' => 'AM', 'currency' =>'DZD', 'weight' => 'KG_CM');
			$fedex_core['EC'] = array('region' => 'EU', 'currency' =>'USD', 'weight' => 'KG_CM');
			$fedex_core['EE'] = array('region' => 'AP', 'currency' =>'EUR', 'weight' => 'KG_CM');
			$fedex_core['EG'] = array('region' => 'AP', 'currency' =>'EGP', 'weight' => 'KG_CM');
			$fedex_core['ER'] = array('region' => 'EU', 'currency' =>'ERN', 'weight' => 'KG_CM');
			$fedex_core['ES'] = array('region' => 'AP', 'currency' =>'EUR', 'weight' => 'KG_CM');
			$fedex_core['ET'] = array('region' => 'AU', 'currency' =>'ETB', 'weight' => 'KG_CM');
			$fedex_core['FI'] = array('region' => 'AP', 'currency' =>'EUR', 'weight' => 'KG_CM');
			$fedex_core['FJ'] = array('region' => 'AP', 'currency' =>'FJD', 'weight' => 'KG_CM');
			$fedex_core['FK'] = array('region' => 'AM', 'currency' =>'GBP', 'weight' => 'KG_CM');
			$fedex_core['FM'] = array('region' => 'AM', 'currency' =>'USD', 'weight' => 'LB_IN');
			$fedex_core['FO'] = array('region' => 'AM', 'currency' =>'DKK', 'weight' => 'KG_CM');
			$fedex_core['FR'] = array('region' => 'EU', 'currency' =>'EUR', 'weight' => 'KG_CM');
			$fedex_core['GA'] = array('region' => 'AP', 'currency' =>'XAF', 'weight' => 'KG_CM');
			$fedex_core['GB'] = array('region' => 'EU', 'currency' =>'GBP', 'weight' => 'KG_CM');
			$fedex_core['GD'] = array('region' => 'AM', 'currency' =>'XCD', 'weight' => 'LB_IN');
			$fedex_core['GE'] = array('region' => 'AM', 'currency' =>'GEL', 'weight' => 'KG_CM');
			$fedex_core['GF'] = array('region' => 'AM', 'currency' =>'EUR', 'weight' => 'KG_CM');
			$fedex_core['GG'] = array('region' => 'AM', 'currency' =>'GBP', 'weight' => 'KG_CM');
			$fedex_core['GH'] = array('region' => 'AP', 'currency' =>'GBS', 'weight' => 'KG_CM');
			$fedex_core['GI'] = array('region' => 'AM', 'currency' =>'GBP', 'weight' => 'KG_CM');
			$fedex_core['GL'] = array('region' => 'AM', 'currency' =>'DKK', 'weight' => 'KG_CM');
			$fedex_core['GM'] = array('region' => 'AP', 'currency' =>'GMD', 'weight' => 'KG_CM');
			$fedex_core['GN'] = array('region' => 'AP', 'currency' =>'GNF', 'weight' => 'KG_CM');
			$fedex_core['GP'] = array('region' => 'AM', 'currency' =>'EUR', 'weight' => 'KG_CM');
			$fedex_core['GQ'] = array('region' => 'AP', 'currency' =>'XAF', 'weight' => 'KG_CM');
			$fedex_core['GR'] = array('region' => 'EU', 'currency' =>'EUR', 'weight' => 'KG_CM');
			$fedex_core['GT'] = array('region' => 'AM', 'currency' =>'GTQ', 'weight' => 'KG_CM');
			$fedex_core['GU'] = array('region' => 'AM', 'currency' =>'USD', 'weight' => 'LB_IN');
			$fedex_core['GW'] = array('region' => 'AP', 'currency' =>'XOF', 'weight' => 'KG_CM');
			$fedex_core['GY'] = array('region' => 'AP', 'currency' =>'GYD', 'weight' => 'LB_IN');
			$fedex_core['HK'] = array('region' => 'AM', 'currency' =>'HKD', 'weight' => 'KG_CM');
			$fedex_core['HN'] = array('region' => 'AM', 'currency' =>'HNL', 'weight' => 'KG_CM');
			$fedex_core['HR'] = array('region' => 'AP', 'currency' =>'HRK', 'weight' => 'KG_CM');
			$fedex_core['HT'] = array('region' => 'AM', 'currency' =>'HTG', 'weight' => 'LB_IN');
			$fedex_core['HU'] = array('region' => 'EU', 'currency' =>'HUF', 'weight' => 'KG_CM');
			$fedex_core['IC'] = array('region' => 'EU', 'currency' =>'EUR', 'weight' => 'KG_CM');
			$fedex_core['ID'] = array('region' => 'AP', 'currency' =>'IDR', 'weight' => 'KG_CM');
			$fedex_core['IE'] = array('region' => 'EU', 'currency' =>'EUR', 'weight' => 'KG_CM');
			$fedex_core['IL'] = array('region' => 'AP', 'currency' =>'ILS', 'weight' => 'KG_CM');
			$fedex_core['IN'] = array('region' => 'AP', 'currency' =>'INR', 'weight' => 'KG_CM');
			$fedex_core['IQ'] = array('region' => 'AP', 'currency' =>'IQD', 'weight' => 'KG_CM');
			$fedex_core['IR'] = array('region' => 'AP', 'currency' =>'IRR', 'weight' => 'KG_CM');
			$fedex_core['IS'] = array('region' => 'EU', 'currency' =>'ISK', 'weight' => 'KG_CM');
			$fedex_core['IT'] = array('region' => 'EU', 'currency' =>'EUR', 'weight' => 'KG_CM');
			$fedex_core['JE'] = array('region' => 'AM', 'currency' =>'GBP', 'weight' => 'KG_CM');
			$fedex_core['JM'] = array('region' => 'AM', 'currency' =>'JMD', 'weight' => 'KG_CM');
			$fedex_core['JO'] = array('region' => 'AP', 'currency' =>'JOD', 'weight' => 'KG_CM');
			$fedex_core['JP'] = array('region' => 'AP', 'currency' =>'JPY', 'weight' => 'KG_CM');
			$fedex_core['KE'] = array('region' => 'AP', 'currency' =>'KES', 'weight' => 'KG_CM');
			$fedex_core['KG'] = array('region' => 'AP', 'currency' =>'KGS', 'weight' => 'KG_CM');
			$fedex_core['KH'] = array('region' => 'AP', 'currency' =>'KHR', 'weight' => 'KG_CM');
			$fedex_core['KI'] = array('region' => 'AP', 'currency' =>'AUD', 'weight' => 'KG_CM');
			$fedex_core['KM'] = array('region' => 'AP', 'currency' =>'KMF', 'weight' => 'KG_CM');
			$fedex_core['KN'] = array('region' => 'AM', 'currency' =>'XCD', 'weight' => 'LB_IN');
			$fedex_core['KP'] = array('region' => 'AP', 'currency' =>'KPW', 'weight' => 'LB_IN');
			$fedex_core['KR'] = array('region' => 'AP', 'currency' =>'KRW', 'weight' => 'KG_CM');
			$fedex_core['KV'] = array('region' => 'AM', 'currency' =>'EUR', 'weight' => 'KG_CM');
			$fedex_core['KW'] = array('region' => 'AP', 'currency' =>'KWD', 'weight' => 'KG_CM');
			$fedex_core['KY'] = array('region' => 'AM', 'currency' =>'KYD', 'weight' => 'KG_CM');
			$fedex_core['KZ'] = array('region' => 'AP', 'currency' =>'KZF', 'weight' => 'LB_IN');
			$fedex_core['LA'] = array('region' => 'AP', 'currency' =>'LAK', 'weight' => 'KG_CM');
			$fedex_core['LB'] = array('region' => 'AP', 'currency' =>'USD', 'weight' => 'KG_CM');
			$fedex_core['LC'] = array('region' => 'AM', 'currency' =>'XCD', 'weight' => 'KG_CM');
			$fedex_core['LI'] = array('region' => 'AM', 'currency' =>'CHF', 'weight' => 'LB_IN');
			$fedex_core['LK'] = array('region' => 'AP', 'currency' =>'LKR', 'weight' => 'KG_CM');
			$fedex_core['LR'] = array('region' => 'AP', 'currency' =>'LRD', 'weight' => 'KG_CM');
			$fedex_core['LS'] = array('region' => 'AP', 'currency' =>'LSL', 'weight' => 'KG_CM');
			$fedex_core['LT'] = array('region' => 'EU', 'currency' =>'EUR', 'weight' => 'KG_CM');
			$fedex_core['LU'] = array('region' => 'EU', 'currency' =>'EUR', 'weight' => 'KG_CM');
			$fedex_core['LV'] = array('region' => 'EU', 'currency' =>'EUR', 'weight' => 'KG_CM');
			$fedex_core['LY'] = array('region' => 'AP', 'currency' =>'LYD', 'weight' => 'KG_CM');
			$fedex_core['MA'] = array('region' => 'AP', 'currency' =>'MAD', 'weight' => 'KG_CM');
			$fedex_core['MC'] = array('region' => 'AM', 'currency' =>'EUR', 'weight' => 'KG_CM');
			$fedex_core['MD'] = array('region' => 'AP', 'currency' =>'MDL', 'weight' => 'KG_CM');
			$fedex_core['ME'] = array('region' => 'AM', 'currency' =>'EUR', 'weight' => 'KG_CM');
			$fedex_core['MG'] = array('region' => 'AP', 'currency' =>'MGA', 'weight' => 'KG_CM');
			$fedex_core['MH'] = array('region' => 'AM', 'currency' =>'USD', 'weight' => 'LB_IN');
			$fedex_core['MK'] = array('region' => 'AP', 'currency' =>'MKD', 'weight' => 'KG_CM');
			$fedex_core['ML'] = array('region' => 'AP', 'currency' =>'COF', 'weight' => 'KG_CM');
			$fedex_core['MM'] = array('region' => 'AP', 'currency' =>'USD', 'weight' => 'KG_CM');
			$fedex_core['MN'] = array('region' => 'AP', 'currency' =>'MNT', 'weight' => 'KG_CM');
			$fedex_core['MO'] = array('region' => 'AP', 'currency' =>'MOP', 'weight' => 'KG_CM');
			$fedex_core['MP'] = array('region' => 'AM', 'currency' =>'USD', 'weight' => 'LB_IN');
			$fedex_core['MQ'] = array('region' => 'AM', 'currency' =>'EUR', 'weight' => 'KG_CM');
			$fedex_core['MR'] = array('region' => 'AP', 'currency' =>'MRO', 'weight' => 'KG_CM');
			$fedex_core['MS'] = array('region' => 'AM', 'currency' =>'XCD', 'weight' => 'LB_IN');
			$fedex_core['MT'] = array('region' => 'AP', 'currency' =>'EUR', 'weight' => 'KG_CM');
			$fedex_core['MU'] = array('region' => 'AP', 'currency' =>'MUR', 'weight' => 'KG_CM');
			$fedex_core['MV'] = array('region' => 'AP', 'currency' =>'MVR', 'weight' => 'KG_CM');
			$fedex_core['MW'] = array('region' => 'AP', 'currency' =>'MWK', 'weight' => 'KG_CM');
			$fedex_core['MX'] = array('region' => 'AM', 'currency' =>'MXN', 'weight' => 'KG_CM');
			$fedex_core['MY'] = array('region' => 'AP', 'currency' =>'MYR', 'weight' => 'KG_CM');
			$fedex_core['MZ'] = array('region' => 'AP', 'currency' =>'MZN', 'weight' => 'KG_CM');
			$fedex_core['NA'] = array('region' => 'AP', 'currency' =>'NAD', 'weight' => 'KG_CM');
			$fedex_core['NC'] = array('region' => 'AP', 'currency' =>'XPF', 'weight' => 'KG_CM');
			$fedex_core['NE'] = array('region' => 'AP', 'currency' =>'XOF', 'weight' => 'KG_CM');
			$fedex_core['NG'] = array('region' => 'AP', 'currency' =>'NGN', 'weight' => 'KG_CM');
			$fedex_core['NI'] = array('region' => 'AM', 'currency' =>'NIO', 'weight' => 'KG_CM');
			$fedex_core['NL'] = array('region' => 'EU', 'currency' =>'EUR', 'weight' => 'KG_CM');
			$fedex_core['NO'] = array('region' => 'EU', 'currency' =>'NOK', 'weight' => 'KG_CM');
			$fedex_core['NP'] = array('region' => 'AP', 'currency' =>'NPR', 'weight' => 'KG_CM');
			$fedex_core['NR'] = array('region' => 'AP', 'currency' =>'AUD', 'weight' => 'KG_CM');
			$fedex_core['NU'] = array('region' => 'AP', 'currency' =>'NZD', 'weight' => 'KG_CM');
			$fedex_core['NZ'] = array('region' => 'AP', 'currency' =>'NZD', 'weight' => 'KG_CM');
			$fedex_core['OM'] = array('region' => 'AP', 'currency' =>'OMR', 'weight' => 'KG_CM');
			$fedex_core['PA'] = array('region' => 'AM', 'currency' =>'USD', 'weight' => 'KG_CM');
			$fedex_core['PE'] = array('region' => 'AM', 'currency' =>'PEN', 'weight' => 'KG_CM');
			$fedex_core['PF'] = array('region' => 'AP', 'currency' =>'XPF', 'weight' => 'KG_CM');
			$fedex_core['PG'] = array('region' => 'AP', 'currency' =>'PGK', 'weight' => 'KG_CM');
			$fedex_core['PH'] = array('region' => 'AP', 'currency' =>'PHP', 'weight' => 'KG_CM');
			$fedex_core['PK'] = array('region' => 'AP', 'currency' =>'PKR', 'weight' => 'KG_CM');
			$fedex_core['PL'] = array('region' => 'EU', 'currency' =>'PLN', 'weight' => 'KG_CM');
			$fedex_core['PR'] = array('region' => 'AM', 'currency' =>'USD', 'weight' => 'LB_IN');
			$fedex_core['PT'] = array('region' => 'EU', 'currency' =>'EUR', 'weight' => 'KG_CM');
			$fedex_core['PW'] = array('region' => 'AM', 'currency' =>'USD', 'weight' => 'KG_CM');
			$fedex_core['PY'] = array('region' => 'AM', 'currency' =>'PYG', 'weight' => 'KG_CM');
			$fedex_core['QA'] = array('region' => 'AP', 'currency' =>'QAR', 'weight' => 'KG_CM');
			$fedex_core['RE'] = array('region' => 'AP', 'currency' =>'EUR', 'weight' => 'KG_CM');
			$fedex_core['RO'] = array('region' => 'EU', 'currency' =>'RON', 'weight' => 'KG_CM');
			$fedex_core['RS'] = array('region' => 'AP', 'currency' =>'RSD', 'weight' => 'KG_CM');
			$fedex_core['RU'] = array('region' => 'AP', 'currency' =>'RUB', 'weight' => 'KG_CM');
			$fedex_core['RW'] = array('region' => 'AP', 'currency' =>'RWF', 'weight' => 'KG_CM');
			$fedex_core['SA'] = array('region' => 'AP', 'currency' =>'SAR', 'weight' => 'KG_CM');
			$fedex_core['SB'] = array('region' => 'AP', 'currency' =>'SBD', 'weight' => 'KG_CM');
			$fedex_core['SC'] = array('region' => 'AP', 'currency' =>'SCR', 'weight' => 'KG_CM');
			$fedex_core['SD'] = array('region' => 'AP', 'currency' =>'SDG', 'weight' => 'KG_CM');
			$fedex_core['SE'] = array('region' => 'EU', 'currency' =>'SEK', 'weight' => 'KG_CM');
			$fedex_core['SG'] = array('region' => 'AP', 'currency' =>'SGD', 'weight' => 'KG_CM');
			$fedex_core['SH'] = array('region' => 'AP', 'currency' =>'SHP', 'weight' => 'KG_CM');
			$fedex_core['SI'] = array('region' => 'EU', 'currency' =>'EUR', 'weight' => 'KG_CM');
			$fedex_core['SK'] = array('region' => 'EU', 'currency' =>'EUR', 'weight' => 'KG_CM');
			$fedex_core['SL'] = array('region' => 'AP', 'currency' =>'SLL', 'weight' => 'KG_CM');
			$fedex_core['SM'] = array('region' => 'EU', 'currency' =>'EUR', 'weight' => 'KG_CM');
			$fedex_core['SN'] = array('region' => 'AP', 'currency' =>'XOF', 'weight' => 'KG_CM');
			$fedex_core['SO'] = array('region' => 'AM', 'currency' =>'SOS', 'weight' => 'KG_CM');
			$fedex_core['SR'] = array('region' => 'AM', 'currency' =>'SRD', 'weight' => 'KG_CM');
			$fedex_core['SS'] = array('region' => 'AP', 'currency' =>'SSP', 'weight' => 'KG_CM');
			$fedex_core['ST'] = array('region' => 'AP', 'currency' =>'STD', 'weight' => 'KG_CM');
			$fedex_core['SV'] = array('region' => 'AM', 'currency' =>'USD', 'weight' => 'KG_CM');
			$fedex_core['SY'] = array('region' => 'AP', 'currency' =>'SYP', 'weight' => 'KG_CM');
			$fedex_core['SZ'] = array('region' => 'AP', 'currency' =>'SZL', 'weight' => 'KG_CM');
			$fedex_core['TC'] = array('region' => 'AM', 'currency' =>'USD', 'weight' => 'LB_IN');
			$fedex_core['TD'] = array('region' => 'AP', 'currency' =>'XAF', 'weight' => 'KG_CM');
			$fedex_core['TG'] = array('region' => 'AP', 'currency' =>'XOF', 'weight' => 'KG_CM');
			$fedex_core['TH'] = array('region' => 'AP', 'currency' =>'THB', 'weight' => 'KG_CM');
			$fedex_core['TJ'] = array('region' => 'AP', 'currency' =>'TJS', 'weight' => 'KG_CM');
			$fedex_core['TL'] = array('region' => 'AP', 'currency' =>'USD', 'weight' => 'KG_CM');
			$fedex_core['TN'] = array('region' => 'AP', 'currency' =>'TND', 'weight' => 'KG_CM');
			$fedex_core['TO'] = array('region' => 'AP', 'currency' =>'TOP', 'weight' => 'KG_CM');
			$fedex_core['TR'] = array('region' => 'AP', 'currency' =>'TRY', 'weight' => 'KG_CM');
			$fedex_core['TT'] = array('region' => 'AM', 'currency' =>'TTD', 'weight' => 'LB_IN');
			$fedex_core['TV'] = array('region' => 'AP', 'currency' =>'AUD', 'weight' => 'KG_CM');
			$fedex_core['TW'] = array('region' => 'AP', 'currency' =>'TWD', 'weight' => 'KG_CM');
			$fedex_core['TZ'] = array('region' => 'AP', 'currency' =>'TZS', 'weight' => 'KG_CM');
			$fedex_core['UA'] = array('region' => 'AP', 'currency' =>'UAH', 'weight' => 'KG_CM');
			$fedex_core['UG'] = array('region' => 'AP', 'currency' =>'USD', 'weight' => 'KG_CM');
			$fedex_core['US'] = array('region' => 'AM', 'currency' =>'USD', 'weight' => 'LB_IN');
			$fedex_core['UY'] = array('region' => 'AM', 'currency' =>'UYU', 'weight' => 'KG_CM');
			$fedex_core['UZ'] = array('region' => 'AP', 'currency' =>'UZS', 'weight' => 'KG_CM');
			$fedex_core['VC'] = array('region' => 'AM', 'currency' =>'XCD', 'weight' => 'LB_IN');
			$fedex_core['VE'] = array('region' => 'AM', 'currency' =>'VEF', 'weight' => 'KG_CM');
			$fedex_core['VG'] = array('region' => 'AM', 'currency' =>'USD', 'weight' => 'LB_IN');
			$fedex_core['VI'] = array('region' => 'AM', 'currency' =>'USD', 'weight' => 'LB_IN');
			$fedex_core['VN'] = array('region' => 'AP', 'currency' =>'VND', 'weight' => 'KG_CM');
			$fedex_core['VU'] = array('region' => 'AP', 'currency' =>'VUV', 'weight' => 'KG_CM');
			$fedex_core['WS'] = array('region' => 'AP', 'currency' =>'WST', 'weight' => 'KG_CM');
			$fedex_core['XB'] = array('region' => 'AM', 'currency' =>'EUR', 'weight' => 'LB_IN');
			$fedex_core['XC'] = array('region' => 'AM', 'currency' =>'EUR', 'weight' => 'LB_IN');
			$fedex_core['XE'] = array('region' => 'AM', 'currency' =>'ANG', 'weight' => 'LB_IN');
			$fedex_core['XM'] = array('region' => 'AM', 'currency' =>'EUR', 'weight' => 'LB_IN');
			$fedex_core['XN'] = array('region' => 'AM', 'currency' =>'XCD', 'weight' => 'LB_IN');
			$fedex_core['XS'] = array('region' => 'AP', 'currency' =>'SIS', 'weight' => 'KG_CM');
			$fedex_core['XY'] = array('region' => 'AM', 'currency' =>'ANG', 'weight' => 'LB_IN');
			$fedex_core['YE'] = array('region' => 'AP', 'currency' =>'YER', 'weight' => 'KG_CM');
			$fedex_core['YT'] = array('region' => 'AP', 'currency' =>'EUR', 'weight' => 'KG_CM');
			$fedex_core['ZA'] = array('region' => 'AP', 'currency' =>'ZAR', 'weight' => 'KG_CM');
			$fedex_core['ZM'] = array('region' => 'AP', 'currency' =>'ZMW', 'weight' => 'KG_CM');
			$fedex_core['ZW'] = array('region' => 'AP', 'currency' =>'USD', 'weight' => 'KG_CM');

				 echo '<hr><h3 class="heading">Fedex - <a href="https://myshipi.com/" target="_blank">Shipi</a></h3>';
				    ?>

				    <table class="form-table">
				    	<tr>
				    		<td colspan="2" style="padding: 5px;">
				    			<h4>SOAP API Credentials :-</h4>
				    		</td>
				    	</tr>
						<tr>
						<td style=" width: 50%; padding: 5px; ">
							<h4> <span class="woocommerce-help-tip" data-tip="<?php _e('Fedex Integration Team will give this details to you.','hitshippo_fedex') ?>"></span>	<?php _e('Web Service Key','hitshippo_fedex') ?></h4>
							<p> <?php _e('Leave this field as empty to use default account.','hitshippo_fedex') ?> </p>
						</td>
						<td>
							<input type="text" name="hitshippo_fedex_site_id" value="<?php echo (isset($general_settings['hitshippo_fedex_site_id'])) ? $general_settings['hitshippo_fedex_site_id'] : ''; ?>">
						</td>

					</tr>
					<tr>
						<td style=" width: 50%; padding: 5px; ">
							<h4> <span class="woocommerce-help-tip" data-tip="<?php _e('Fedex Integration Team will give this details to you.','hitshippo_fedex') ?>"></span>	<?php _e('Web Service Password','hitshippo_fedex') ?></h4>
							<p> <?php _e('Leave this field as empty to use default account.','hitshippo_fedex') ?> </p>
						</td>
						<td>
							<input type="text" name="hitshippo_fedex_site_pwd" value="<?php echo (isset($general_settings['hitshippo_fedex_site_pwd'])) ? $general_settings['hitshippo_fedex_site_pwd'] : ''; ?>">
						</td>
					</tr>
					<tr>
						<td style=" width: 50%; padding: 5px; ">
							<h4> <span class="woocommerce-help-tip" data-tip="<?php _e('Fedex Integration Team will give this details to you.','hitshippo_fedex') ?>"></span>	<?php _e('Fedex Account Number','hitshippo_fedex') ?></h4>
							<p> <?php _e('Leave this field as empty to use default account.','hitshippo_fedex') ?> </p>
						</td>
						<td>

							<input type="text" name="hitshippo_fedex_acc_no" value="<?php echo (isset($general_settings['hitshippo_fedex_acc_no'])) ? $general_settings['hitshippo_fedex_acc_no'] : ''; ?>">
						</td>
					</tr>
					<tr>
						<td style=" width: 50%; padding: 5px; ">
							<h4> <span class="woocommerce-help-tip" data-tip="<?php _e('Fedex Integration Team will give this details to you.','hitshippo_fedex') ?>"></span>	<?php _e('Fedex Meter Number','hitshippo_fedex') ?></h4>
							<p> <?php _e('Leave this field as empty to use default account.','hitshippo_fedex') ?> </p>
						</td>
						<td>

							<input type="text" name="hitshippo_fedex_access_key" value="<?php echo (isset($general_settings['hitshippo_fedex_access_key'])) ? $general_settings['hitshippo_fedex_access_key'] : ''; ?>">
						</td>
					</tr>
					<tr>
				    	<td colspan="2" style="padding: 5px;">
				    		<h4>REST API Credentials :-</h4>
				    	</td>
				    </tr>
				    <tr>
						<td style=" width: 50%; padding: 5px; ">
							<h4> <span class="woocommerce-help-tip" data-tip="<?php _e('Fedex Integration Team will give this details to you.','hitshippo_fedex') ?>"></span>	<?php _e('API Grant type','hitshippo_fedex') ?></h4>
						</td>
						<td>
							<select name="hitshippo_fedex_rest_grant_type" class="wc-enhanced-select" style="width:30%;padding:5px;">
								<option value="client_credentials" <?php echo (isset($general_settings['hitshippo_fedex_rest_grant_type']) && $general_settings['hitshippo_fedex_rest_grant_type'] == 'client_credentials') ? 'Selected="true"' : ''; ?>> Customer </option>
								<!-- <option value="csp_credentials" <?php echo (isset($general_settings['hitshippo_fedex_rest_grant_type']) && $general_settings['hitshippo_fedex_rest_grant_type'] == 'csp_credentials') ? 'Selected="true"' : ''; ?>> Compatible Provider customer </option> -->
							</select>
						</td>
					</tr>
					<tr>
						<td style=" width: 50%; padding: 5px; ">
							<h4> <span class="woocommerce-help-tip" data-tip="<?php _e('Fedex Integration Team will give this details to you.','hitshippo_fedex') ?>"></span>	<?php _e('Account number','hitshippo_fedex') ?></h4>
							<p> <?php _e('Leave this field as empty to use default account.','hitshippo_fedex') ?> </p>
						</td>
						<td>
							<input type="text" name="hitshippo_fedex_rest_acc_no" value="<?php echo (isset($general_settings['hitshippo_fedex_rest_acc_no'])) ? $general_settings['hitshippo_fedex_rest_acc_no'] : ''; ?>">
						</td>
					</tr>
					<tr>
						<td style=" width: 50%; padding: 5px; ">
							<h4> <span class="woocommerce-help-tip" data-tip="<?php _e('Fedex Integration Team will give this details to you.','hitshippo_fedex') ?>"></span>	<?php _e('API key','hitshippo_fedex') ?></h4>
							<p> <?php _e('Leave this field as empty to use default account.','hitshippo_fedex') ?> </p>
						</td>
						<td>
							<input type="text" name="hitshippo_fedex_rest_api_key" value="<?php echo (isset($general_settings['hitshippo_fedex_rest_api_key'])) ? $general_settings['hitshippo_fedex_rest_api_key'] : ''; ?>">
						</td>
					</tr>
					<tr>
						<td style=" width: 50%; padding: 5px; ">
							<h4> <span class="woocommerce-help-tip" data-tip="<?php _e('Fedex Integration Team will give this details to you.','hitshippo_fedex') ?>"></span>	<?php _e('Secret key','hitshippo_fedex') ?></h4>
							<p> <?php _e('Leave this field as empty to use default account.','hitshippo_fedex') ?> </p>
						</td>
						<td>
							<input type="text" name="hitshippo_fedex_rest_secret_key" value="<?php echo (isset($general_settings['hitshippo_fedex_rest_secret_key'])) ? $general_settings['hitshippo_fedex_rest_secret_key'] : ''; ?>">
						</td>
					</tr>
					<tr>
						<td style=" width: 50%; padding: 5px; ">
							<h4> <span class="woocommerce-help-tip" data-tip="<?php _e('Shipping Person Name','hitshippo_fedex') ?>"></span>	<?php _e('Shipper Name','hitshippo_fedex') ?></h4>
						</td>
						<td>
							<input type="text" name="hitshippo_fedex_shipper_name" value="<?php echo (isset($general_settings['hitshippo_fedex_shipper_name'])) ? $general_settings['hitshippo_fedex_shipper_name'] : ''; ?>">
						</td>
					</tr>
					<tr>
						<td style=" width: 50%; padding: 5px; ">
							<h4> <span class="woocommerce-help-tip" data-tip="<?php _e('Shipper Company Name.','hitshippo_fedex') ?>"></span>	<?php _e('Company Name','hitshippo_fedex') ?></h4>
						</td>
						<td>
							<input type="text" name="hitshippo_fedex_company" value="<?php echo (isset($general_settings['hitshippo_fedex_company'])) ? $general_settings['hitshippo_fedex_company'] : ''; ?>">
						</td>
					</tr>
					<tr>
						<td style=" width: 50%; padding: 5px; ">
							<h4> <span class="woocommerce-help-tip" data-tip="<?php _e('Shipper Mobile / Contact Number.','hitshippo_fedex') ?>"></span>	<?php _e('Contact Number','hitshippo_fedex') ?></h4>
						</td>
						<td>
							<input type="text" name="hitshippo_fedex_mob_num" value="<?php echo (isset($general_settings['hitshippo_fedex_mob_num'])) ? $general_settings['hitshippo_fedex_mob_num'] : ''; ?>">
						</td>
					</tr>
					<tr>
						<td style=" width: 50%; padding: 5px; ">
							<h4> <span class="woocommerce-help-tip" data-tip="<?php _e('Email Address of the Shipper.','hitshippo_fedex') ?>"></span>	<?php _e('Email Address','hitshippo_fedex') ?></h4>
						</td>
						<td>
							<input type="text" name="hitshippo_fedex_email" value="<?php echo (isset($general_settings['hitshippo_fedex_email'])) ? $general_settings['hitshippo_fedex_email'] : ''; ?>">
						</td>
					</tr>
					<tr>
						<td style=" width: 50%; padding: 5px; ">
							<h4> <span class="woocommerce-help-tip" data-tip="<?php _e('Address Line 1 of the Shipper from Address.','hitshippo_fedex') ?>"></span>	<?php _e('Address Line 1','hitshippo_fedex') ?></h4>
						</td>
						<td>
							<input type="text" name="hitshippo_fedex_address1" value="<?php echo (isset($general_settings['hitshippo_fedex_address1'])) ? $general_settings['hitshippo_fedex_address1'] : ''; ?>">
						</td>
					</tr>
					<tr>
						<td style=" width: 50%; padding: 5px; ">
							<h4> <span class="woocommerce-help-tip" data-tip="<?php _e('Address Line 2 of the Shipper from Address.','hitshippo_fedex') ?>"></span>	<?php _e('Address Line 2','hitshippo_fedex') ?></h4>
						</td>
						<td>
							<input type="text" name="hitshippo_fedex_address2" value="<?php echo (isset($general_settings['hitshippo_fedex_address2'])) ? $general_settings['hitshippo_fedex_address2'] : ''; ?>">
						</td>
					</tr>
					<tr>
						<td style=" width: 50%;padding: 5px; ">
							<h4> <span class="woocommerce-help-tip" data-tip="<?php _e('City of the Shipper from address.','hitshippo_fedex') ?>"></span>	<?php _e('City','hitshippo_fedex') ?></h4>
						</td>
						<td>
							<input type="text" name="hitshippo_fedex_city" value="<?php echo (isset($general_settings['hitshippo_fedex_city'])) ? $general_settings['hitshippo_fedex_city'] : ''; ?>">
						</td>
					</tr>
					<tr>
						<td style=" width: 50%; padding: 5px; ">
							<h4> <span class="woocommerce-help-tip" data-tip="<?php _e('State of the Shipper from address.','hitshippo_fedex') ?>"></span>	<?php _e('State (Two Digit String)','hitshippo_fedex') ?></h4>
						</td>
						<td>
							<input type="text" name="hitshippo_fedex_state" value="<?php echo (isset($general_settings['hitshippo_fedex_state'])) ? $general_settings['hitshippo_fedex_state'] : ''; ?>">
						</td>
					</tr>
					<tr>
						<td style=" width: 50%; padding: 5px; ">
							<h4> <span class="woocommerce-help-tip" data-tip="<?php _e('Postal/Zip Code.','hitshippo_fedex') ?>"></span>	<?php _e('Postal/Zip Code','hitshippo_fedex') ?></h4>
						</td>
						<td>
							<input type="text" name="hitshippo_fedex_zip" value="<?php echo (isset($general_settings['hitshippo_fedex_zip'])) ? $general_settings['hitshippo_fedex_zip'] : ''; ?>">
						</td>
					</tr>
					<tr>
						<td style=" width: 50%; padding: 5px; ">
							<h4> <span class="woocommerce-help-tip" data-tip="<?php _e('Country of the Shipper from Address.','hitshippo_fedex') ?>"></span>	<?php _e('Country','hitshippo_fedex') ?></h4>
						</td>
						<td>
							<select name="hitshippo_fedex_country" class="wc-enhanced-select" style="width:210px;">
								<?php foreach($countires as $key => $value)
								{
									if (isset($fedex_core[$key]['currency']) && !is_array($fedex_core[$key]['currency'])) {
										if(isset($general_settings['hitshippo_fedex_country']) && ($general_settings['hitshippo_fedex_country'] == $key))
										{
											echo "<option value=".$key." selected='true'>".$value." [". $fedex_core[$key]['currency'] ."]</option>";
										}
										else
										{
											echo "<option value=".$key.">".$value." [". $fedex_core[$key]['currency'] ."]</option>";
										}
									} elseif (isset($fedex_core[$key]['currency']) && is_array($fedex_core[$key]['currency'])) {
										foreach ($fedex_core[$key]['currency'] as $f_c_k) {
											if(isset($general_settings['hitshippo_fedex_country']) && ($general_settings['hitshippo_fedex_country'] == $f_c_k))
											{
												echo "<option value=".$key." selected='true'>".$value." [". $f_c_k ."]</option>";
											}
											else
											{
												echo "<option value=".$key.">".$value." [". $f_c_k ."]</option>";
											}
										}
									}

								} ?>
							</select>
						</td>
					</tr>
					<tr>
						<td style=" width: 50%; padding: 5px; ">
							<h4> <span class="woocommerce-help-tip" data-tip="<?php _e('Conversion Rate from Site Currency to FedEx Currency.','hitshippo_fedex') ?>"></span>	<?php _e('Conversion Rate from Site Currency to Fedex Currency ( Ignore if auto conversion is Enabled )','hitshippo_fedex') ?></h4>
						</td>
						<td>
							<input type="text" name="hitshippo_fedex_con_rate" value="<?php echo (isset($general_settings['hitshippo_fedex_con_rate'])) ? $general_settings['hitshippo_fedex_con_rate'] : ''; ?>">
						</td>
					</tr>
					<tr>
						<td>
							<h4> <span class="woocommerce-help-tip" data-tip="<?php _e('Choose currency that return by fedex, currency will be converted from this currency to woocommerce currency while showing rates on frontoffice.','hitshippo_fedex') ?>"></span><?php _e('Fedex Currency Code','hitshippo_fedex') ?></h4>
						</td>
						<td>
							<select name="hitshippo_fedex_currency" style="width:153px;">
								<?php foreach($fedex_core as  $currency)
								{
									if (!is_array($currency['currency'])) {
										if(isset($general_settings['hitshippo_fedex_currency']) && ($general_settings['hitshippo_fedex_currency'] == $currency['currency']))
										{
											echo "<option value=".$currency['currency']." selected='true'>".$currency['currency']."</option>";
										}
										else
										{
											echo "<option value=".$currency['currency'].">".$currency['currency']."</option>";
										}
									} elseif (is_array($currency['currency'])) {
										foreach ($currency['currency'] as $fed_curr) {
											if(isset($general_settings['hitshippo_fedex_currency']) && ($general_settings['hitshippo_fedex_currency'] == $fed_curr))
											{
												echo "<option value=".$fed_curr." selected='true'>".$fed_curr."</option>";
											}
											else
											{
												echo "<option value=".$fed_curr.">".$fed_curr."</option>";
											}
										}
									}
								}

								if (!isset($general_settings['hitshippo_fedex_currency']) || ($general_settings['hitshippo_fedex_currency'] != "NMP")) {
										echo "<option value=NMP>NMP</option>";
								}elseif (isset($general_settings['hitshippo_fedex_currency']) && ($general_settings['hitshippo_fedex_currency'] == "NMP")) {
										echo "<option value=NMP selected='true'>NMP</option>";
								} ?>
							</select>
						</td>
					</tr>
					<tr>
						<td style=" width: 50%; padding: 5px; ">
							<h4> <span class="woocommerce-help-tip" data-tip="<?php _e('Default Domestic Shipping Service.','hitshippo_fedex') ?>"></span>	<?php _e('Default Domestic Service','hitshippo_fedex') ?></h4>
							<p><?php _e('This will be used while shipping label generation.','hitshippo_fedex') ?></p>
						</td>
						<td>
							<select name="hitshippo_fedex_def_dom" class="wc-enhanced-select" style="width:210px;">
								<?php foreach($_fedex_carriers as $key => $value)
								{
									if(isset($general_settings['hitshippo_fedex_def_dom']) && ($general_settings['hitshippo_fedex_def_dom'] == $key))
									{
										echo "<option value=".$key." selected='true'>[".$key."] ".$value."</option>";
									}
									else
									{
										echo "<option value=".$key.">[".$key."] ".$value."</option>";
									}
								} ?>
							</select>
						</td>
					</tr>
					<tr>
						<td style=" width: 50%; padding: 5px; ">
							<h4> <span class="woocommerce-help-tip" data-tip="<?php _e('Default International Shipping Service.','hitshippo_fedex') ?>"></span>	<?php _e('Default International Service','hitshippo_fedex') ?></h4>
							<p><?php _e('This will be used while shipping label generation.','hitshippo_fedex') ?></p>
						</td>
						<td>
							<select name="hitshippo_fedex_def_inter" class="wc-enhanced-select" style="width:210px;">
								<?php foreach($_fedex_carriers as $key => $value)
								{
									if(isset($general_settings['hitshippo_fedex_def_inter']) && ($general_settings['hitshippo_fedex_def_inter'] == $key))
									{
										echo "<option value=".$key." selected='true'>[".$key."] ".$value."</option>";
									}
									else
									{
										echo "<option value=".$key.">[".$key."] ".$value."</option>";
									}
								} ?>
							</select>
						</td>
					</tr>
				    </table>
				    <hr>
				    <?php
			}

			public function save_user_fields($user_id){
				if(isset($_POST['hitshippo_fedex_country'])){
					$general_settings['hitshippo_fedex_site_id'] = sanitize_text_field(isset($_POST['hitshippo_fedex_site_id']) ? $_POST['hitshippo_fedex_site_id'] : '');
					$general_settings['hitshippo_fedex_site_pwd'] = sanitize_text_field(isset($_POST['hitshippo_fedex_site_pwd']) ? $_POST['hitshippo_fedex_site_pwd'] : '');
					$general_settings['hitshippo_fedex_acc_no'] = sanitize_text_field(isset($_POST['hitshippo_fedex_acc_no']) ? $_POST['hitshippo_fedex_acc_no'] : '');
					$general_settings['hitshippo_fedex_access_key'] = sanitize_text_field(isset($_POST['hitshippo_fedex_access_key']) ? $_POST['hitshippo_fedex_access_key'] : '');
					$general_settings['hitshippo_fedex_rest_grant_type'] = sanitize_text_field(isset($_POST['hitshippo_fedex_rest_grant_type']) ? $_POST['hitshippo_fedex_rest_grant_type'] : '');
					$general_settings['hitshippo_fedex_rest_acc_no'] = sanitize_text_field(isset($_POST['hitshippo_fedex_rest_acc_no']) ? $_POST['hitshippo_fedex_rest_acc_no'] : '');
					$general_settings['hitshippo_fedex_rest_api_key'] = sanitize_text_field(isset($_POST['hitshippo_fedex_rest_api_key']) ? $_POST['hitshippo_fedex_rest_api_key'] : '');
					$general_settings['hitshippo_fedex_rest_secret_key'] = sanitize_text_field(isset($_POST['hitshippo_fedex_rest_secret_key']) ? $_POST['hitshippo_fedex_rest_secret_key'] : '');
					$general_settings['hitshippo_fedex_shipper_name'] = sanitize_text_field(isset($_POST['hitshippo_fedex_shipper_name']) ? $_POST['hitshippo_fedex_shipper_name'] : '');
					$general_settings['hitshippo_fedex_company'] = sanitize_text_field(isset($_POST['hitshippo_fedex_company']) ? $_POST['hitshippo_fedex_company'] : '');
					$general_settings['hitshippo_fedex_mob_num'] = sanitize_text_field(isset($_POST['hitshippo_fedex_mob_num']) ? $_POST['hitshippo_fedex_mob_num'] : '');
					$general_settings['hitshippo_fedex_email'] = sanitize_text_field(isset($_POST['hitshippo_fedex_email']) ? $_POST['hitshippo_fedex_email'] : '');
					$general_settings['hitshippo_fedex_address1'] = sanitize_text_field(isset($_POST['hitshippo_fedex_address1']) ? $_POST['hitshippo_fedex_address1'] : '');
					$general_settings['hitshippo_fedex_address2'] = sanitize_text_field(isset($_POST['hitshippo_fedex_address2']) ? $_POST['hitshippo_fedex_address2'] : '');
					$general_settings['hitshippo_fedex_city'] = sanitize_text_field(isset($_POST['hitshippo_fedex_city']) ? $_POST['hitshippo_fedex_city'] : '');
					$general_settings['hitshippo_fedex_state'] = sanitize_text_field(isset($_POST['hitshippo_fedex_state']) ? $_POST['hitshippo_fedex_state'] : '');
					$general_settings['hitshippo_fedex_zip'] = sanitize_text_field(isset($_POST['hitshippo_fedex_zip']) ? $_POST['hitshippo_fedex_zip'] : '');
					$general_settings['hitshippo_fedex_country'] = sanitize_text_field(isset($_POST['hitshippo_fedex_country']) ? $_POST['hitshippo_fedex_country'] : '');
					// $general_settings['hitshippo_fedex_gstin'] = sanitize_text_field(isset($_POST['hitshippo_fedex_gstin']) ? $_POST['hitshippo_fedex_gstin'] : '');
					$general_settings['hitshippo_fedex_con_rate'] = sanitize_text_field(isset($_POST['hitshippo_fedex_con_rate']) ? $_POST['hitshippo_fedex_con_rate'] : '');
					$general_settings['hitshippo_fedex_currency'] = sanitize_text_field(isset($_POST['hitshippo_fedex_currency']) ? $_POST['hitshippo_fedex_currency'] : '');
					$general_settings['hitshippo_fedex_def_dom'] = sanitize_text_field(isset($_POST['hitshippo_fedex_def_dom']) ? $_POST['hitshippo_fedex_def_dom'] : '');

					$general_settings['hitshippo_fedex_def_inter'] = sanitize_text_field(isset($_POST['hitshippo_fedex_def_inter']) ? $_POST['hitshippo_fedex_def_inter'] : '');

					update_post_meta($user_id,'hitshippo_fedex_vendor_settings',$general_settings);
				}

			}

			public function hitshippo_fedex_init()
			{
				include_once("controllors/hitshippo_fedex_init.php");
			}
			public function hitshippo_fedex_method( $methods )
			{
				$methods['hitshippo_fedex'] = 'hitshippo_fedex';
				return $methods;
			}
			public function hitshippo_fedex_plugin_action_links($links)
			{
				$setting_value = version_compare(WC()->version, '2.1', '>=') ? "wc-settings" : "woocommerce_settings";
				$plugin_links = array(
					'<a href="' . admin_url( 'options-general.php?page=hit-fedex-configuration' ) . '" style="color:green;">' . __( 'Configure', 'hitshippo_fedex' ) . '</a>',
					'<a href="https://app.myshipi.com/support" target="_blank" >' . __('Support', 'hitshippo_fedex') . '</a>'
					);
				return array_merge( $plugin_links, $links );
			}

			public function create_fedex_shipping_meta_box() {
				$meta_scrn = $this->hpos_enabled ? wc_get_page_screen_id( 'shop-order' ) : 'shop_order';
				add_meta_box( 'hitshippo_create_fedex_shipping', __('Automated Fedex Shipping Label','hitshippo_fedex'), array($this, 'create_fedex_shipping_label_genetation'), $meta_scrn, 'side', 'core' );
				add_meta_box( 'hitshippo_create_fedex_return_shipping', __('Automated FEDEX Return Label','hitshippo_fedex'), array($this, 'create_fedex_return_label_genetation'), $meta_scrn, 'side', 'core' );
				wp_enqueue_script('shipi_fedex_edit_order_js', plugin_dir_url(__FILE__).'/js/edit_order.js');

			}

		    public function hitshippo_fedex_order_status_update(){
		    	global $woocommerce;
				if(isset($_GET['shipi_key'])){
					$shipi_key = sanitize_text_field($_GET['shipi_key']);
					if($shipi_key == 'fetch' && get_transient('hitshipo_fedex_express_nonce_temp')){
						echo json_encode(array(get_transient('hitshipo_fedex_express_nonce_temp')));
						die();
					}
				}

				if(isset($_GET['hitshipo_integration_key']) && isset($_GET['hitshipo_action'])){
					$integration_key = sanitize_text_field($_GET['hitshipo_integration_key']);
					$hitshipo_action = sanitize_text_field($_GET['hitshipo_action']);
					$general_settings = get_option('hitshippo_fedex_main_settings');
					$general_settings = empty($general_settings) ? array() : $general_settings;
					if(isset($general_settings['hitshippo_fedex_shippo_int_key']) && $integration_key == $general_settings['hitshippo_fedex_shippo_int_key']){
						if($hitshipo_action == 'stop_working'){
							update_option('hitshipo_fedex_working_status', 'stop_working');
						}else if ($hitshipo_action = 'start_working'){
							update_option('hitshipo_fedex_working_status', 'start_working');
						}
					}

				}

		    	if (isset($_GET['carrier']) && $_GET['carrier'] == "fedex") {
				if(isset($_GET['h1t_updat3_0rd3r']) && isset($_GET['key']) && isset($_GET['action'])){
					$order_id = $_GET['h1t_updat3_0rd3r'];
					$key = $_GET['key'];
					$action = $_GET['action'];
					$order_ids = explode(",",$order_id);
					$general_settings = get_option('hitshippo_fedex_main_settings',array());

					if(isset($general_settings['hitshippo_fedex_shippo_int_key']) && $general_settings['hitshippo_fedex_shippo_int_key'] == $key){
						if($action == 'processing'){
							foreach ($order_ids as $order_id) {
								$order = wc_get_order( $order_id );
								$order->update_status( 'processing' );
							}
						}else if($action == 'completed'){
							foreach ($order_ids as $order_id) {
								  $order = wc_get_order( $order_id );
								  $order->update_status( 'completed' );

							}
						}
					}
					die();
				}

				if(isset($_GET['h1t_updat3_sh1pp1ng']) && isset($_GET['key']) && isset($_GET['user_id']) && isset($_GET['carrier']) && isset($_GET['track'])){

					$order_id = $_GET['h1t_updat3_sh1pp1ng'];
					$key = $_GET['key'];
					$general_settings = get_option('hitshippo_fedex_main_settings',array());
					$user_id = $_GET['user_id'];
					$carrier = $_GET['carrier'];
					$track = $_GET['track'];
					$output['status'] = 'success';
					$output['tracking_num'] = $track;
					// $output['label'] = "localhost/hitshipo/api/shipping_labels/".$user_id."/".$carrier."/order_".$order_id."_track_".$track."_label.pdf";
					// $output['invoice'] = "localhost/hitshipo/api/shipping_labels/".$user_id."/".$carrier."/order_".$order_id."_track_".$track."_invoice.pdf";
					$output['label'] = "https://app.myshipi.com/api/shipping_labels/".$user_id."/".$carrier."/order_".$order_id."_track_".$track."_label.pdf";
					$output['invoice'] = "https://app.myshipi.com/api/shipping_labels/".$user_id."/".$carrier."/order_".$order_id."_track_".$track."_invoice.pdf";
					$result_arr = array();

					if(isset($general_settings['hitshippo_fedex_shippo_int_key']) && $general_settings['hitshippo_fedex_shippo_int_key'] == $key){

						if(isset($_GET['label'])){
							$output['user_id'] = $_GET['label'];
							$result_arr = !empty(get_option('hitshippo_fedex_values_'.$order_id, array())) ? json_decode(get_option('hitshippo_fedex_values_'.$order_id, array())) : [];
							$result_arr[] = $output;

						}else{
							$result_arr[] = $output;
						}

						update_option('hitshippo_fedex_values_'.$order_id, json_encode($result_arr));
					}
					die();
				}
		    }
		}

		public function create_fedex_return_label_genetation($post){
			if(!$this->hpos_enabled && $post->post_type !='shop_order' ){
				return;
			}
			$order = (!$this->hpos_enabled) ? wc_get_order( $post->ID ) : $post;
			$order_id = $order->get_id();

			$_fedex_carriers = array(
				'FIRST_OVERNIGHT'                    => 'FedEx First Overnight',
				'PRIORITY_OVERNIGHT'                 => 'FedEx Priority Overnight',
				'STANDARD_OVERNIGHT'                 => 'FedEx Standard Overnight',
				'FEDEX_2_DAY_AM'                     => 'FedEx 2Day A.M',
				'FEDEX_2_DAY'                        => 'FedEx 2Day',
				'SAME_DAY'                        => 'FedEx Same Day',
				'SAME_DAY_CITY'                        => 'FedEx Same Day City',
				'SAME_DAY_METRO_AFTERNOON'                        => 'FedEx Same Day Metro Afternoon',
				'SAME_DAY_METRO_MORNING'                        => 'FedEx Same Day Metro Morning',
				'SAME_DAY_METRO_RUSH'                        => 'FedEx Same Day Metro Rush',
				'FEDEX_EXPRESS_SAVER'                => 'FedEx Express Saver',
				'GROUND_HOME_DELIVERY'               => 'FedEx Ground Home Delivery',
				'FEDEX_GROUND'                       => 'FedEx Ground',
				'INTERNATIONAL_ECONOMY'              => 'International Economy',
				'INTERNATIONAL_ECONOMY_DISTRIBUTION'              => 'International Economy Distribution',
				'INTERNATIONAL_FIRST'                => 'International First',
				'INTERNATIONAL_GROUND'                => 'International Ground',
				'INTERNATIONAL_PRIORITY'             => 'International Priority',
				'INTERNATIONAL_PRIORITY_DISTRIBUTION'             => 'International Priority Distribution',
				'EUROPE_FIRST_INTERNATIONAL_PRIORITY' => 'FedEx Europe First International Priority',
				'INTERNATIONAL_PRIORITY_EXPRESS' => 'FedEx International Priority Express',
				'FEDEX_INTERNATIONAL_PRIORITY_PLUS' => 'FedEx First International Priority Plus',
				'FEDEX_INTERNATIONAL_PRIORITY_EXPRESS'  => 'Fedex international priority express',
				'FEDEX_INTERNATIONAL_PRIORITY'          => 'Fedex international priority',
				'FEDEX_INTERNATIONAL_CONNECT_PLUS'      => 'Fedex international connect plus',
				'INTERNATIONAL_DISTRIBUTION_FREIGHT' => 'FedEx International Distribution Fright',
				'FEDEX_1_DAY_FREIGHT'                => 'FedEx 1 Day Freight',
				'FEDEX_2_DAY_FREIGHT'                => 'FedEx 2 Day Freight',
				'FEDEX_3_DAY_FREIGHT'                => 'FedEx 3 Day Freight',
				'INTERNATIONAL_ECONOMY_FREIGHT'      => 'FedEx Economy Freight',
				'INTERNATIONAL_PRIORITY_FREIGHT'     => 'FedEx Priority Freight',
				'SMART_POST'                         => 'FedEx Smart Post',
				'FEDEX_FIRST_FREIGHT'                => 'FedEx First Freight',
				'FEDEX_FREIGHT_ECONOMY'              => 'FedEx Freight Economy',
				'FEDEX_FREIGHT_PRIORITY'             => 'FedEx Freight Priority',
				'FEDEX_CARGO_AIRPORT_TO_AIRPORT'             => 'FedEx CARGO Airport to Airport',
				'FEDEX_CARGO_FREIGHT_FORWARDING'             => 'FedEx CARGO Freight FOrwarding',
				'FEDEX_CARGO_INTERNATIONAL_EXPRESS_FREIGHT'             => 'FedEx CARGO International Express Fright',
				'FEDEX_CARGO_INTERNATIONAL_PREMIUM'             => 'FedEx CARGO International Premium',
				'FEDEX_CARGO_MAIL'             => 'FedEx CARGO Mail',
				'FEDEX_CARGO_REGISTERED_MAIL'             => 'FedEx CARGO Registered Mail',
				'FEDEX_CARGO_SURFACE_MAIL'             => 'FedEx CARGO Surface Mail',
				'FEDEX_CUSTOM_CRITICAL_AIR_EXPEDITE_EXCLUSIVE_USE'             => 'FedEx Custom Critical Air Expedite Exclusive Use',
				'FEDEX_CUSTOM_CRITICAL_AIR_EXPEDITE_NETWORK'             => 'FedEx Custom Critical Air Expedite Network',
				'FEDEX_CUSTOM_CRITICAL_CHARTER_AIR'             => 'FedEx Custom Critical Charter Air',
				'FEDEX_CUSTOM_CRITICAL_POINT_TO_POINT'             => 'FedEx Custom Critical Point to Point',
				'FEDEX_CUSTOM_CRITICAL_SURFACE_EXPEDITE'             => 'FedEx Custom Critical Surface Expedite',
				'FEDEX_CUSTOM_CRITICAL_SURFACE_EXPEDITE_EXCLUSIVE_USE'             => 'FedEx Custom Critical Surface Expedite Exclusive Use',
				'FEDEX_CUSTOM_CRITICAL_TEMP_ASSURE_AIR'             => 'FedEx Custom Critical Temp Assure Air',
				'FEDEX_CUSTOM_CRITICAL_TEMP_ASSURE_VALIDATED_AIR'             => 'FedEx Custom Critical Temp Assure Validated Air',
				'FEDEX_CUSTOM_CRITICAL_WHITE_GLOVE_SERVICES'             => 'FedEx Custom Critical White Glove Services',
				'TRANSBORDER_DISTRIBUTION_CONSOLIDATION'             => 'Fedex Transborder Distribution Consolidation',
				'FEDEX_DISTANCE_DEFERRED'            => 'FedEx Distance Deferred',
				'FEDEX_NEXT_DAY_EARLY_MORNING'       => 'FedEx Next Day Early Morning',
				'FEDEX_NEXT_DAY_MID_MORNING'         => 'FedEx Next Day Mid Morning',
				'FEDEX_NEXT_DAY_AFTERNOON'           => 'FedEx Next Day Afternoon',
				'FEDEX_NEXT_DAY_END_OF_DAY'          => 'FedEx Next Day End of Day',
				'FEDEX_NEXT_DAY_FREIGHT'             => 'FedEx Next Day Freight',
				);

			$general_settings = get_option('hitshippo_fedex_main_settings',array());

			   $json_data = get_option('hitshippo_fedex_return_values_'.$order_id);

			   if(empty($json_data)){

				echo '<b>Choose Service to Return: </b>';
				echo '<br/><select name="hitshippo_fedex_return_service_code_default" class="wc-enhanced-select">';
				if(!empty($general_settings['hitshippo_fedex_carrier'])){
					foreach ($general_settings['hitshippo_fedex_carrier'] as $key => $value) {
						echo "<option value='".$key."'>".$key .' - ' .$_fedex_carriers[$key]."</option>";
					}
				}
				echo '</select>';
				_e('<br><br><b>Duty Payment Type: </b>');
				_e('<select name="hitshippo_fedex_duty_type" class="wc-enhanced-select">');
					if (isset($general_settings['hitshippo_fedex_duty_type']) && $general_settings['hitshippo_fedex_duty_type'] == "R") {
						_e('<option value="R" selected>Recipient</option>');
						_e('<option value="S">Sender</option>');
					} else {
						_e('<option value="R">Recipient</option>');
						_e('<option value="S" selected>Sender</option>');
					}
				_e('</select><br>');

				echo '<br/><b>Products to return</b>';
				echo '<br/>';
				echo '<table>';
				$items = $order->get_items();
				foreach ( $items as $item ) {
					$product_data = $item->get_data();

					$product_variation_id = $item->get_variation_id();
					$product_id = $product_data['product_id'];
					if(!empty($product_variation_id) && $product_variation_id != 0){
						$product_id = $product_variation_id;
					}

					echo "<tr><td><input type='checkbox' name='return_products_fedex[]' checked value='".$product_id."'>
						</td>";
					echo "<td style='width:150px;'><small title='".$product_data['name']."'>". substr($product_data['name'],0,7)."</small></td>";
					echo "<td><input type='number' name='qty_products_fedex[".$product_id."]' style='width:50px;' value='".$product_data['quantity']."'></td>";
					echo "</tr>";


				}
				echo '</table><br/>';

				$notice = get_option('hitshippo_fedex_return_status_'.$order_id, null);
				if($notice && $notice != 'success'){
					echo "<p style='color:red'>".$notice."</p>";
					delete_option('hitshippo_fedex_return_status_'.$order_id);
				}

				echo '<button name="hitshippo_fedex_create_return_label" value="default" style="background:#533e8c; color: #fff;border-color: #533e8c;box-shadow: 0px 1px 0px #533e8c;text-shadow: 0px 1px 0px #fff;" class="button button-primary" type="submit">Create Return Shipment</button>';

			   } else{
				   $array_data = json_decode( $json_data, true );

				   $labels = explode(',',rtrim($array_data[0]['label'],','));
				   foreach($labels as $count=>$label){
					echo '<a href="'.$label.'" target="_blank" style="background:#533e8c; color: #fff;border-color: #533e8c;box-shadow: 0px 1px 0px #533e8c;text-shadow: 0px 1px 0px #fff; margin-top:2px" class="button button-primary"> Return Label '.($count + 1).' </a> ';
				   }

				   echo '</br><a href="'.$array_data[0]['invoice'].'" target="_blank" class="button button-primary" style="margin-top: 2px"> Invoice </a></br>';
				   echo '<button name="hitshippo_fedex_return_reset" class="button button-secondary" style="margin-top:3px;" type="submit"> Reset</button>';

			   }
		}
		    public function create_fedex_shipping_label_genetation($post){
		    	// print_r('expression');
		    	// die();
		        if(!$this->hpos_enabled && $post->post_type !='shop_order' ){
		    		return;
		    	}
		    	$order = (!$this->hpos_enabled) ? wc_get_order( $post->ID ) : $post;
		    	$ship_met = $order->get_shipping_methods();

		    	$order_id = $order->get_id();
		        $_fedex_carriers = array(
							'FIRST_OVERNIGHT'                    => 'FedEx First Overnight',
							'PRIORITY_OVERNIGHT'                 => 'FedEx Priority Overnight',
							'STANDARD_OVERNIGHT'                 => 'FedEx Standard Overnight',
							'FEDEX_2_DAY_AM'                     => 'FedEx 2Day A.M',
							'FEDEX_2_DAY'                        => 'FedEx 2Day',
							'SAME_DAY'                        => 'FedEx Same Day',
							'SAME_DAY_CITY'                        => 'FedEx Same Day City',
							'SAME_DAY_METRO_AFTERNOON'                        => 'FedEx Same Day Metro Afternoon',
							'SAME_DAY_METRO_MORNING'                        => 'FedEx Same Day Metro Morning',
							'SAME_DAY_METRO_RUSH'                        => 'FedEx Same Day Metro Rush',
							'FEDEX_EXPRESS_SAVER'                => 'FedEx Express Saver',
							'GROUND_HOME_DELIVERY'               => 'FedEx Ground Home Delivery',
							'FEDEX_GROUND'                       => 'FedEx Ground',
							'INTERNATIONAL_ECONOMY'              => 'International Economy',
							'INTERNATIONAL_ECONOMY_DISTRIBUTION'              => 'International Economy Distribution',
							'INTERNATIONAL_FIRST'                => 'International First',
							'INTERNATIONAL_GROUND'                => 'International Ground',
							'INTERNATIONAL_PRIORITY'             => 'International Priority',
							'INTERNATIONAL_PRIORITY_DISTRIBUTION'             => 'International Priority Distribution',
							'EUROPE_FIRST_INTERNATIONAL_PRIORITY' => 'Europe First International Priority',
							'INTERNATIONAL_PRIORITY_EXPRESS' => 'FedEx International Priority Express',
							'FEDEX_INTERNATIONAL_PRIORITY_PLUS' => 'FedEx First International Priority Plus',
							'FEDEX_INTERNATIONAL_PRIORITY_EXPRESS'  => 'Fedex international priority express',
							'FEDEX_INTERNATIONAL_PRIORITY'          => 'Fedex international priority',
							'FEDEX_INTERNATIONAL_CONNECT_PLUS'      => 'Fedex international connect plus',
							'INTERNATIONAL_DISTRIBUTION_FREIGHT' => 'FedEx International Distribution Fright',
							'FEDEX_1_DAY_FREIGHT'                => 'FedEx 1 Day Freight',
							'FEDEX_2_DAY_FREIGHT'                => 'FedEx 2 Day Freight',
							'FEDEX_3_DAY_FREIGHT'                => 'FedEx 3 Day Freight',
							'INTERNATIONAL_ECONOMY_FREIGHT'      => 'FedEx Economy Freight',
							'INTERNATIONAL_PRIORITY_FREIGHT'     => 'FedEx Priority Freight',
							'SMART_POST'                         => 'FedEx Smart Post',
							'FEDEX_FIRST_FREIGHT'                => 'FedEx First Freight',
							'FEDEX_FREIGHT_ECONOMY'              => 'FedEx Freight Economy',
							'FEDEX_FREIGHT_PRIORITY'             => 'FedEx Freight Priority',
							'FEDEX_CARGO_AIRPORT_TO_AIRPORT'             => 'FedEx CARGO Airport to Airport',
							'FEDEX_CARGO_FREIGHT_FORWARDING'             => 'FedEx CARGO Freight FOrwarding',
							'FEDEX_CARGO_INTERNATIONAL_EXPRESS_FREIGHT'             => 'FedEx CARGO International Express Fright',
							'FEDEX_CARGO_INTERNATIONAL_PREMIUM'             => 'FedEx CARGO International Premium',
							'FEDEX_CARGO_MAIL'             => 'FedEx CARGO Mail',
							'FEDEX_CARGO_REGISTERED_MAIL'             => 'FedEx CARGO Registered Mail',
							'FEDEX_CARGO_SURFACE_MAIL'             => 'FedEx CARGO Surface Mail',
							'FEDEX_CUSTOM_CRITICAL_AIR_EXPEDITE_EXCLUSIVE_USE'             => 'FedEx Custom Critical Air Expedite Exclusive Use',
							'FEDEX_CUSTOM_CRITICAL_AIR_EXPEDITE_NETWORK'             => 'FedEx Custom Critical Air Expedite Network',
							'FEDEX_CUSTOM_CRITICAL_CHARTER_AIR'             => 'FedEx Custom Critical Charter Air',
							'FEDEX_CUSTOM_CRITICAL_POINT_TO_POINT'             => 'FedEx Custom Critical Point to Point',
							'FEDEX_CUSTOM_CRITICAL_SURFACE_EXPEDITE'             => 'FedEx Custom Critical Surface Expedite',
							'FEDEX_CUSTOM_CRITICAL_SURFACE_EXPEDITE_EXCLUSIVE_USE'             => 'FedEx Custom Critical Surface Expedite Exclusive Use',
							'FEDEX_CUSTOM_CRITICAL_TEMP_ASSURE_AIR'             => 'FedEx Custom Critical Temp Assure Air',
							'FEDEX_CUSTOM_CRITICAL_TEMP_ASSURE_VALIDATED_AIR'             => 'FedEx Custom Critical Temp Assure Validated Air',
							'FEDEX_CUSTOM_CRITICAL_WHITE_GLOVE_SERVICES'             => 'FedEx Custom Critical White Glove Services',
							'TRANSBORDER_DISTRIBUTION_CONSOLIDATION'             => 'Fedex Transborder Distribution Consolidation',
							'FEDEX_DISTANCE_DEFERRED'            => 'FedEx Distance Deferred',
							'FEDEX_NEXT_DAY_EARLY_MORNING'       => 'FedEx Next Day Early Morning',
							'FEDEX_NEXT_DAY_MID_MORNING'         => 'FedEx Next Day Mid Morning',
							'FEDEX_NEXT_DAY_AFTERNOON'           => 'FedEx Next Day Afternoon',
							'FEDEX_NEXT_DAY_END_OF_DAY'          => 'FedEx Next Day End of Day',
							'FEDEX_NEXT_DAY_FREIGHT'             => 'FedEx Next Day Freight',
							);

		        $general_settings = get_option('hitshippo_fedex_main_settings',array());

		        $items = $order->get_items();

    		    $custom_settings = array();
		    	$custom_settings['default'] =  array();
		    	$vendor_settings = array();

		    	// $pack_products = array();

				// foreach ( $items as $item ) {
				// 	$product_data = $item->get_data();
				//     $product = array();
				//     $product['product_name'] = $product_data['name'];
				//     $product['product_quantity'] = $product_data['quantity'];
				//     $product['product_id'] = $product_data['product_id'];

				// 	if ($this->hpos_enabled) {
				// 		$hpos_prod_data = wc_get_product($product_data['product_id']);
				// 		$saved_cc = $hpos_prod_data->get_meta("hits_fedex_cc");
				// 		$saved_desc = $hpos_prod_data->get_meta("hits_fedex_label_dec");
				// 	} else {
				// 		$saved_cc = get_post_meta( $product_data['product_id'], 'hits_fedex_cc', true);
				// 		$saved_desc = get_post_meta( $product_data['product_id'], 'hits_fedex_label_dec', true);
				// 	}
					
				// 	if(!empty($saved_cc)){
				// 		$product['commodity_code'] = $saved_cc;
				// 	}
				// 	if(!empty($saved_desc)){
				// 		$product['invoice_desc'] = $saved_desc;
				// 	}

				//     $pack_products[] = $product;

				// }
				$pack_products = $this->get_products_on_order($general_settings, $order);

				if(isset($general_settings['hitshippo_fedex_v_enable']) && $general_settings['hitshippo_fedex_v_enable'] == 'yes' && isset($general_settings['hitshippo_fedex_v_labels']) && $general_settings['hitshippo_fedex_v_labels'] == 'yes'){
					// Multi Vendor Enabled
					foreach ($pack_products as $key => $value) {

						$product_id = $value['product_id'];
						if ($this->hpos_enabled) {
							$hpos_prod_data = wc_get_product($product_id);
							$fedex_account = $hpos_prod_data->get_meta("fedex_address");
						} else {
							$fedex_account = get_post_meta($product_id,'fedex_address', true);
						}
						if(empty($fedex_account) || $fedex_account == 'default'){
							$fedex_account = 'default';
							$vendor_settings[$fedex_account] = $custom_settings['default'];
							$vendor_settings[$fedex_account]['products'][] = $value;
						}

						if($fedex_account != 'default'){
							$user_account = get_post_meta($fedex_account,'hitshippo_fedex_vendor_settings', true);
							$user_account = empty($user_account) ? array() : $user_account;
							if(!empty($user_account)){
								if(!isset($vendor_settings[$fedex_account])){

									$vendor_settings[$fedex_account] = $custom_settings['default'];
									unset($value['product_id']);
									$vendor_settings[$fedex_account]['products'][] = $value;
								}
							}else{
								$fedex_account = 'default';
								$vendor_settings[$fedex_account] = $custom_settings['default'];
								$vendor_settings[$fedex_account]['products'][] = $value;
							}
						}

					}

				}

				if(empty($vendor_settings)){
					$custom_settings['default']['products'] = $pack_products;
				}else{
					$custom_settings = $vendor_settings;
				}
// echo '<pre>';print_r($custom_settings);die();
		       	$shipment_data = json_decode(get_option('hitshippo_fedex_values_'.$order_id), true); // using "true" to convert stdobject to array
		       	$notice = get_option('hitshippo_fedex_status_'.$order_id, null);
		       	// echo '<pre>';
		       	// print_r($shipment_data);
		       	// echo '<h3>Notice</h3>';
		       	// print_r($notice);
		       	// die();

		       	if ($notice && $notice == 'success') {
			       	echo "<p style='color:green'>Shipment created successfully</p>";
			       	delete_option('hitshippo_fedex_status_'.$order_id);
			    }elseif($notice && $notice != 'success'){
			       	echo "<p style='color:red'>".$notice."</p>";
			       	delete_option('hitshippo_fedex_status_'.$order_id);
			    }

		       	if(!empty($shipment_data)){
		       		if(isset($shipment_data[0])){
			       		foreach ($shipment_data as $key => $value) {
			       			if(isset($value['user_id'])){
		       					unset($custom_settings[$value['user_id']]);
		       				}
		       				if(isset($value['user_id']) && $value['user_id'] == 'default'){
		       					echo '<br/><b>Default Account</b><br/>';
		       				}else{
		       					$user = get_user_by( 'id', $value['user_id'] );
		       					echo '<br/><b>Account:</b> <small>'.$user->display_name.'</small><br/>';
		       				}
			       			echo '<b>Shipment ID: <font style = "color:green;">'.$value['tracking_num'].'</font></b>';
				       		echo '<br><a href="'.$value['label'].'" target="_blank" style="background:#533e8c; color: #fff;border-color: #533e8c;box-shadow: 0px 1px 0px #533e8c;text-shadow: 0px 1px 0px #fff; margin-top: 5px;" class="button button-primary"> Shipping Label '.($key > 0 ? $key: '').' </a> ';
				       		echo '<a href="'.$value['invoice'].'" target="_blank" style = "margin-top: 5px;" class="button button-primary"> Invoice </a>';
			       		}
			        }else {
			        	$custom_settings = array();
			        	echo '<b>Shipment ID: <font style = "color:green;">'.$shipment_data['tracking_num'].'</font></b>';
			       		echo '<br><a href="'.$shipment_data['label'].'" target="_blank" style="background:#533e8c; color: #fff;border-color: #533e8c;box-shadow: 0px 1px 0px #533e8c;text-shadow: 0px 1px 0px #fff; margin-top: 5px;" class="button button-primary"> Shipping Label '.($key > 0 ? $key: '').' </a> ';
			       		echo '<a href="'.$shipment_data['invoice'].'" target="_blank" style = "margin-top: 5px;" class="button button-primary"> Invoice </a>';
			        }
			        echo '<br/><br/> <button name="hitshippo_fedex_reset" class="button button-secondary" style = "margin-top: 5px;" type="submit"> Reset All </button><br/>';
		       	}
// echo '<pre>';print_r($shipment_data);die();
		       	foreach ($custom_settings as $ukey => $settingvalue) {

						if(!empty($shipment_data) && isset($shipment_data[0])){
				       		foreach ($shipment_data as $value) {
				       			if ($value['user_id'] == $ukey) {
				       				continue;
				       			}
				       		}
						}elseif(!empty($shipment_data) && $shipment_data['user_id'] == $ukey){
							continue;
						}
						$select_box_content = "";
						$shipi_rates = get_option('hitshippo_fedex_rates_'.$order_id, false);
						if($shipi_rates){
							$final_price = json_decode($shipi_rates, true);
							$select_box_content = "<br/>";
							foreach($final_price as $key => $value){
								$select_box_content .= "<input type='radio' value='". $value["code"] ."' ". ($key ==0 ? 'checked' : '') ." name='hitshippo_fedex_service_code_". $ukey ."'>". $value["code"] . ' - <b>' . $general_settings["hitshippo_fedex_currency"] .' ' . $value["price"] ."</b><br/>";
							}
							delete_option('hitshippo_fedex_rates_'.$order_id);
						}
						$duty_type = (isset($general_settings['hitshippo_fedex_duty_type'])) ?  $general_settings['hitshippo_fedex_duty_type'] : "S";

						$reason_for_export = array(
							'SOLD' => 'Sold',
							'GIFT' => 'Gift',
							'NOT_SOLD' => 'Donations',
							'PERSONAL_EFFECTS' => 'Personal belongings',
							'REPAIR_AND_RETURN' => 'Repair and Resturn to Sender',
							'SAMPLE' => 'Samples',
						);
						$post_data = get_post_meta($order_id, "_shipi_custom_val_rates", true);
						delete_post_meta($order_id, "_shipi_custom_val_rates");
		       			if($ukey == 'default'){
							$out_post_data = [];
							if($post_data){
								$out_post_data = json_decode($post_data, true);
							}
							
		       				echo '<br/><u><b>Default Account</b></u>';
					        echo '<br/><br/><b>Choose Service to Ship</b>';
					       
							if($select_box_content != ""){
								echo $select_box_content;
							}else{
								echo '<br/><select name="hitshippo_fedex_service_code_default">';
								if(!empty($general_settings['hitshippo_fedex_carrier'])){
									foreach ($general_settings['hitshippo_fedex_carrier'] as $key => $value) {
										echo "<option value='".$key."'>".$_fedex_carriers[$key]."</option>";
									}
								}
								echo '</select>';
							}
					        
							$shipment_content = 'Shipment Number ' . $order_id;
							if($general_settings["hitshippo_fedex_shipment_content"] != ""){
								$shipment_content = $general_settings["hitshippo_fedex_shipment_content"];
							}

							$fedex_packs = $this->shipi_get_fedex_packages($settingvalue['products'], $general_settings, $general_settings['hitshippo_fedex_currency']);
							
							
							if(isset($out_post_data['hitshippo_fedex_shipment_content_default'])){
								$shipment_content = $out_post_data['hitshippo_fedex_shipment_content_default'];
							}

					        echo '<br/><b>Shipment Content</b>';
					        echo '<br/><input type="text" style="width:250px;"  name="hitshippo_fedex_shipment_content_default" value="'. $shipment_content .'" >';
							echo '<br/><b>Reason for Export</b>';
							echo '<br/><select name="hitshippo_fedex_export_reason_default" style="min-width:250px">';
							if(isset($out_post_data['hitshippo_fedex_export_reason_default'])){
								$general_settings['hitshippo_fedex_export_reason'] = $out_post_data['hitshippo_fedex_export_reason_default'];
							}
							foreach ($reason_for_export as $key => $value) {
								if (isset($general_settings['hitshippo_fedex_export_reason']) && ($general_settings['hitshippo_fedex_export_reason'] == $key)) {
									echo "<option value=" . $key . " selected='true'>" . $value . "</option>";
								} else {
									echo "<option value=" . $key . ">" . $value . "</option>";
								}
							}
							echo '</select>';
							echo '<br/><b>Duty Payment Type</b>';
							if(isset($out_post_data['hitshippo_fedex_duty_type_default'])){
								$duty_type = $out_post_data['hitshippo_fedex_duty_type_default'];
							}

							echo '<br/><select name="hitshippo_fedex_duty_type_default" style="min-width:250px">';
							echo "<option value='S' ". ($duty_type == "S" ? 'selected="true"' : '') .">Shipper</option>";
							echo "<option value='R' ". ($duty_type == "R" ? 'selected="true"' : '') .">Recipient</option>";
							echo '</select>';
							$ship_date = current_time('Y-m-d');
							if(isset($out_post_data['ship_date'])){
								$ship_date = $out_post_data['ship_date'];
							}
							
							echo '<br/><b><label for="ship_date">Planned Shipping Date:</label></b><br/>';
							echo '<input type="text" style="width:250px;" id="ship_date" name="ship_date" value="' . esc_attr($ship_date) . '" class="wc_input_datepicker" />';
							?>
							<script type="text/javascript">
								jQuery(function($) {
									// Initialize WooCommerce datepicker
									$('#ship_date').datepicker({
										dateFormat: 'yy-mm-dd' // Use the same format as the saved date
									});
								});
							</script>
							
							<?php
							
							echo apply_filters("shipi_fedex_custom_fields", '', 'default');
							echo "<br/>";
							if(isset($out_post_data["shipi_fedex_add_cus_pack_default"]) && $out_post_data["shipi_fedex_add_cus_pack_default"] == "default" && isset($out_post_data["shipi_fedex_pack_weight_default"][0])){
								_e('<input type="hidden" class="shipi_fedex_add_cus_pack" name="shipi_fedex_add_cus_pack_default" value="default"><b>Customized packages</b><br/>');
								?>
								<div id="shipi_fedex_cus_packs_default" style="overflow-y: scroll; display: block;" hidden="">
								<table>
									<thead>
										<tr>
											<th></th>
											<th>Width</th>
											<th>Height</th>
											<th>Length</th>
											<th>Unit</th>
											<th>Weight</th>
											<th>Unit</th>
											<th></th>
										</tr>
									</thead>
									<tbody id="shipi_fedex_pack_tb_default">
										<?php 
											foreach($out_post_data["shipi_fedex_pack_weight_default"] as $key => $value){
												echo '<tr>';
												echo '<td class="shipi_fedex_pack_id"><input style="width:10px;" type="checkbox"></td>';
												echo '<td><input name="shipi_fedex_pack_width_default[]" style="width:60px;" type="text" value="'. $out_post_data["shipi_fedex_pack_width_default"][$key] .'"></td>';
												echo '<td><input name="shipi_fedex_pack_height_default[]" style="width:60px;" type="text" value="'. $out_post_data["shipi_fedex_pack_height_default"][$key] .'"></td>';
												echo '<td><input name="shipi_fedex_pack_length_default[]" style="width:60px;" type="text" value="'. $out_post_data["shipi_fedex_pack_length_default"][$key] .'"></td>';
												echo '<td>
												<select name="shipi_fedex_pack_dim_unit_default[]" style="width:60px;">
													<option value="IN" '. ($out_post_data["shipi_fedex_pack_dim_unit_default"][$key] == "IN" ? 'selected' : '') .'>IN</option>
													<option value="CM" '. ($out_post_data["shipi_fedex_pack_dim_unit_default"][$key] == "CM" ? 'selected' : '') .'>CM</option>
												</select>
												</td>';
												echo '<td><input name="shipi_fedex_pack_weight_default[]" style="width:60px;" type="text" value="'. $out_post_data["shipi_fedex_pack_weight_default"][$key] .'"></td>';
												echo '<td>
												<select name="shipi_fedex_pack_weg_unit_default[]" style="width:60px;">
													<option value="LB" '. ($out_post_data["shipi_fedex_pack_weg_unit_default"][$key] == "LB" ? 'selected' : '') .'>LB</option>
													<option value="KG" '. ($out_post_data["shipi_fedex_pack_weg_unit_default"][$key] == "KG" ? 'selected' : '') .'>KG</option>
												</select>
												</td>';
												echo '</tr>';
											}
										?>
										
									</tbody>
								</table>
								<button class="button button-secondary" style="margin:5px;" value="default" onclick="addPack(event, this)">Add Pack(s)</button><button class="button button-secondary" style="margin:5px;" value="default" onclick="delPack(event, this)">Remove Pack(s)</button>
								</div>
								<?php
							}else{
								_e('<input type="text" id="shipi_fedex_prods_default" value="' . ((isset($settingvalue["products"]) && !empty($settingvalue["products"])) ? base64_encode(json_encode($settingvalue["products"])) : "") . '" hidden>');
								_e('<input type="text" id="shipi_fedex_packs_default" value="' . ((isset($fedex_packs) && !empty($fedex_packs)) ? base64_encode(json_encode($fedex_packs)) : "") . '" hidden>');
								_e('<input type="checkbox" class="shipi_fedex_add_cus_pack" name="shipi_fedex_add_cus_pack_default" value="default"><b>Customize packages</b><br/>');
								_e('<div id="shipi_fedex_cus_packs_default" style="overflow-y:scroll" hidden></div><br/>');
								
							}
							_e('<p id="shipi_fedex_cus_packs_default_status" hidden></p>');
							echo '<button name="shipi_fedex_get_rates" value="default" style="background:#533e8c; color: #fff;border-color: #533e8c;box-shadow: 0px 1px 0px #533e8c;text-shadow: 0px 1px 0px #fff;" class="button button-primary" type="submit">Get Rates</button> <button name="hitshippo_fedex_create_label" value="default" style="background:#533e8c; color: #fff;border-color: #533e8c;box-shadow: 0px 1px 0px #533e8c;text-shadow: 0px 1px 0px #fff;" class="button button-primary" type="submit">Create Shipment</button><br/>';
		       			}else {
		       				$user = get_user_by( 'id', $ukey );
		       				echo '<br/><u><b>Account:</b> <small>'.$user->display_name.'</small></u>';
		       				echo '<br/><br/><b>Choose Service to Ship</b>';
					        echo '<br/><select name="hitshippo_fedex_service_code_'.$ukey.'">';

					        if($select_box_content != ""){
								echo $select_box_content;
							}else{
								if(!empty($general_settings['hitshippo_fedex_carrier'])){
									foreach ($general_settings['hitshippo_fedex_carrier'] as $key => $value) {
										echo "<option value='".$key."'>".$_fedex_carriers[$key]."</option>";
									}
								}
							}

					        echo '</select>';

							$shipment_content = 'Shipment Number ' . $order_id;
							if($general_settings["hitshippo_fedex_shipment_content"] != ""){
								$shipment_content = $general_settings["hitshippo_fedex_shipment_content"];
							}


					        echo '<br/><b>Shipment Content</b>';
					        echo '<br/><input type="text" style="width:250px;margin-bottom:10px;"  name="hitshippo_fedex_shipment_content_'.$ukey.'" value="' . $shipment_content . '" >';
					        echo '<br/><b>Reason for Export</b>';
							echo '<br/><select name="hitshippo_fedex_export_reason_'. $ukey .'" style="min-width:250px">';
							foreach ($reason_for_export as $key => $value) {
								if (isset($general_settings['hitshippo_fedex_export_reason']) && ($general_settings['hitshippo_fedex_export_reason'] == $key)) {
									echo "<option value=" . $key . " selected='true'>" . $value . "</option>";
								} else {
									echo "<option value=" . $key . ">" . $value . "</option>";
								}
							}
							echo '</select>';
							echo '<br/><b>Duty Payment Type</b>';
							echo '<br/><select name="hitshippo_fedex_duty_type_'. $ukey .'" style="min-width:250px">';
							echo "<option value='S' ". ($duty_type == "S" ? 'selected="true"' : '') .">Shipper</option>";
							echo "<option value='R' ". ($duty_type == "R" ? 'selected="true"' : '') .">Recipient</option>";
							echo '</select>';
							echo apply_filters("shipi_fedex_custom_fields", '', $ukey);
							echo "<br/>";
							_e('<input type="text" id="shipi_fedex_prods_'. $ukey .'" value="' . ((isset($settingvalue["products"]) && !empty($settingvalue["products"])) ? base64_encode(json_encode($settingvalue["products"])) : "") . '" hidden>');
							_e('<input type="text" id="shipi_fedex_packs_'. $ukey .'" value="' . ((isset($fedex_packs) && !empty($fedex_packs)) ? base64_encode(json_encode($fedex_packs)) : "") . '" hidden>');
							_e('<input type="checkbox" class="shipi_fedex_add_cus_pack" name="shipi_fedex_add_cus_pack_'. $ukey .'" value="'. $ukey .'"><b>Customize packages</b><br/>');
							_e('<div id="shipi_fedex_cus_packs_'. $ukey .'" style="overflow-y:scroll" hidden></div><br/>');
							_e('<p id="shipi_fedex_cus_packs_'. $ukey .'_status" hidden></p>');
							echo '<button name="shipi_fedex_get_rates" value="'.$ukey.'" style="background:#533e8c; color: #fff;border-color: #533e8c;box-shadow: 0px 1px 0px #533e8c;text-shadow: 0px 1px 0px #fff;" class="button button-primary" type="submit">Get Rates</button> <button name="hitshippo_fedex_create_label" value="'.$ukey.'" style="background:#533e8c; color: #fff;border-color: #533e8c;box-shadow: 0px 1px 0px #533e8c;text-shadow: 0px 1px 0px #fff;" class="button button-primary" type="submit">Create Shipment</button><br/>';
		       			}
		       		}
		    }

		public function hitshippo_fedex_wc_checkout_order_processed($order_id){
		    if ($this->hpos_enabled) {
				if ('shop_order' !== Automattic\WooCommerce\Utilities\OrderUtil::get_order_type($order_id)) {
					return;
				}
			} else {
				$post = get_post($order_id);

		    	if($post->post_type !='shop_order' ){
		    		return;
		    	}
		    }
		        $order = wc_get_order( $order_id );
		        $service_code = $multi_ven ='';
				$shipping_charge = 0;
		        foreach( $order->get_shipping_methods() as $item_id => $item ){
					$service_code = $item->get_meta('hitshippo_fedex_service');
					$shipping_charge = $item->get_meta('hitshippo_fedex_shipping_charge');
					$multi_ven = $item->get_meta('hitshippo_fedex_multi_ven');
				}

				// if(empty($service_code)){
				// 	return;
				// }

				$general_settings = get_option('hitshippo_fedex_main_settings',array());
		    	$order_data = $order->get_data();

				$desination_country = (isset($order_data['shipping']['country']) && $order_data['shipping']['country'] != '') ? $order_data['shipping']['country'] : $order_data['billing']['country'];
				if(empty($service_code)){
					if( !isset($general_settings['hitshippo_fedex_international_service']) && !isset($general_settings['hitshippo_fedex_Domestic_service'])){
						return;
					}
					if (isset($general_settings['hitshippo_fedex_country']) && $general_settings["hitshippo_fedex_country"] == $desination_country && $general_settings['hitshippo_fedex_Domestic_service'] != 'null'){
						$service_code = $general_settings['hitshippo_fedex_Domestic_service'];
					} elseif (isset($general_settings['hitshippo_fedex_country']) && $general_settings["hitshippo_fedex_country"] != $desination_country && $general_settings['hitshippo_fedex_international_service'] != 'null'){
						$service_code = $general_settings['hitshippo_fedex_international_service'];
					} else {
						return;
					}

				}
		    	if(!isset($general_settings['hitshippo_fedex_shippo_label_gen']) || $general_settings['hitshippo_fedex_shippo_label_gen'] != 'yes'){
		    		return;
		    	}

		    	$cod_services = array('PRIORITY_OVERNIGHT',
								'STANDARD_OVERNIGHT',
								'FEDEX_2_DAY_AM',
								'FEDEX_2_DAY',
								'FEDEX_EXPRESS_SAVER',
								'FEDEX_1_DAY_FREIGHT',
								'FEDEX_2_DAY_FREIGHT',
								'FEDEX_3_DAY_FREIGHT',
								'FEDEX_FIRST_FREIGHT',
								'FEDEX_FREIGHT_ECONOMY',
								'FEDEX_FREIGHT_PRIORITY',
								'FEDEX_GROUND',
								);

	       		$order_id = $order_data['id'];
	       		$order_currency = $order_data['currency'];

	       		// $order_shipping_first_name = $order_data['shipping']['first_name'];
				// $order_shipping_last_name = $order_data['shipping']['last_name'];
				// $order_shipping_company = empty($order_data['shipping']['company']) ? $order_data['shipping']['first_name'] :  $order_data['shipping']['company'];
				// $order_shipping_address_1 = $order_data['shipping']['address_1'];
				// $order_shipping_address_2 = $order_data['shipping']['address_2'];
				// $order_shipping_city = $order_data['shipping']['city'];
				// $order_shipping_state = $order_data['shipping']['state'];
				// $order_shipping_postcode = $order_data['shipping']['postcode'];
				// $order_shipping_country = $order_data['shipping']['country'];
				// $order_shipping_phone = $order_data['billing']['phone'];
				// $order_shipping_email = $order_data['billing']['email'];

				$shipping_arr = (isset($order_data['shipping']['first_name']) && $order_data['shipping']['first_name'] != "") ? $order_data['shipping'] : $order_data['billing'];
				$order_shipping_first_name = $shipping_arr['first_name'];
				$order_shipping_last_name = $shipping_arr['last_name'];
				$order_shipping_company = empty($shipping_arr['company']) ? $shipping_arr['first_name'] :  $shipping_arr['company'];
				$order_shipping_address_1 = $shipping_arr['address_1'];
				$order_shipping_address_2 = $shipping_arr['address_2'];
				$order_shipping_city = $shipping_arr['city'];
				$order_shipping_state = $shipping_arr['state'];
				$order_shipping_postcode = $shipping_arr['postcode'];
				$order_shipping_country = $shipping_arr['country'];
				$order_shipping_phone = $order_data['billing']['phone'];
				$order_shipping_email = $order_data['billing']['email'];

				$pack_products = $this->get_products_on_order($general_settings, $order);
				$total_weg = 0;

				if(empty($pack_products)){
					return;
				}

				$custom_settings = $this->get_vendors_on_order($general_settings, $pack_products, $multi_ven, $order_data, $service_code);

		    	$ship_content = !empty($general_settings['hitshippo_fedex_shipment_content']) ? $general_settings['hitshippo_fedex_shipment_content'] : 'Shipment Content';
				if(!empty($general_settings) && isset($general_settings['hitshippo_fedex_shippo_int_key'])){
					$mode = 'live';
					if(isset($general_settings['hitshippo_fedex_test']) && $general_settings['hitshippo_fedex_test']== 'yes'){
						$mode = 'test';
					}
					$execution = 'manual';
					if(isset($general_settings['hitshippo_fedex_shippo_label_gen']) && $general_settings['hitshippo_fedex_shippo_label_gen']== 'yes'){
						$execution = 'auto';
					}

					$acc_rates = ($general_settings['hitshippo_fedex_account_rates'] == 'yes') ? 'NONE' : 'LIST';
					$residental_del = ($general_settings['hitshippo_fedex_res_f'] == 'yes') ? 'true' : 'false';
					$cod = "N";
					$col_type = (isset($general_settings['hitshippo_fedex_collection_type']) && !empty($general_settings['hitshippo_fedex_collection_type'])) ? $general_settings['hitshippo_fedex_collection_type'] : "CASH";

					$boxes_to_shipo = array();
					if (isset($general_settings['hitshippo_fedex_packing_type']) && $general_settings['hitshippo_fedex_packing_type'] == "box") {
						if (isset($general_settings['hitshippo_fedex_boxes']) && !empty($general_settings['hitshippo_fedex_boxes'])) {
							foreach ($general_settings['hitshippo_fedex_boxes'] as $box) {
								if ($box['enabled'] != 1) {
									continue;
								}else {
									$boxes_to_shipo[] = $box;
								}
							}
						}
					}

						foreach ($custom_settings as $key => $cvalue) {
							if ((isset($general_settings['hitshippo_fedex_cod']) && $general_settings['hitshippo_fedex_cod'] == "yes") && ($cvalue['hitshippo_fedex_country'] == $order_shipping_country) && (in_array($service_code, $cod_services)) ) {
								$cod = "Y";
							}

							$etd= "off";
							if ((isset($general_settings['hitshippo_fedex_etd']) && $general_settings['hitshippo_fedex_etd'] == "yes")) {
								$etd = "on";
							}
							
							$data = array();
							$data['integrated_key'] = $general_settings['hitshippo_fedex_shippo_int_key'];
							$data['order_id'] = $order_id;
							$data['exec_type'] = $execution;
							$data['mode'] = $mode;
							$data['carrier_type'] = "fedex";
							$data['meta'] = array(
								"site_id" => $cvalue['hitshippo_fedex_site_id'],
								"password"  => $cvalue['hitshippo_fedex_site_pwd'],
								"accountnum" => $cvalue['hitshippo_fedex_acc_no'],
								"meternum" => $cvalue['hitshippo_fedex_access_key'],
								"rest_grant_type" => isset($cvalue['hitshippo_fedex_rest_grant_type']) ? $cvalue['hitshippo_fedex_rest_grant_type'] : "",
								"rest_acc_no" => isset($cvalue['hitshippo_fedex_rest_acc_no']) ? $cvalue['hitshippo_fedex_rest_acc_no'] : "",
								"rest_api_key" => isset($cvalue['hitshippo_fedex_rest_api_key']) ? $cvalue['hitshippo_fedex_rest_api_key'] : "",
								"rest_secret_key" => isset($cvalue['hitshippo_fedex_rest_secret_key']) ? $cvalue['hitshippo_fedex_rest_secret_key'] : "",
								"fedex_api_type" => isset($general_settings['hitshippo_fedex_api_type']) && !empty($general_settings['hitshippo_fedex_api_type']) ? $general_settings['hitshippo_fedex_api_type'] : "SOAP",
								"t_company" => $order_shipping_company,
								"t_address1" => $order_shipping_address_1,
								"t_address2" => $order_shipping_address_2,
								"t_city" => $order_shipping_city,
								"t_state" => substr(str_replace($order_shipping_country."-", "", $order_shipping_state), 0,2),
								"t_postal" => $order_shipping_postcode,
								"t_country" => $order_shipping_country,
								"t_name" => $order_shipping_first_name . ' '. $order_shipping_last_name,
								"t_phone" => $order_shipping_phone,
								"t_email" => $order_shipping_email,
								"residential" => $residental_del,
								"drop_off_type" => $general_settings['hitshippo_fedex_drop_off'],
								"packing_type" => $general_settings['hitshippo_fedex_ship_pack_type'],
								"products" => apply_filters("shipi_fedex_prods_to_ship", $cvalue['products'], $order, $key),
								"pack_algorithm" => $general_settings['hitshippo_fedex_packing_type'],
								"boxes" => $boxes_to_shipo,
								"max_weight" => $general_settings['hitshippo_fedex_max_weight'],
								"wight_dim_unit" => $general_settings['hitshippo_fedex_weight_unit'],
								"total_product_weg" => $total_weg,
								"service_code" => $service_code,	//'PRIORITY_OVERNIGHT'
								"shipment_content" => $ship_content,
								"s_company" => $cvalue['hitshippo_fedex_company'],
								"s_address1" => $cvalue['hitshippo_fedex_address1'],
								"s_address2" => $cvalue['hitshippo_fedex_address2'],
								"s_city" => $cvalue['hitshippo_fedex_city'],
								"s_state" => $cvalue['hitshippo_fedex_state'],
								"s_postal" => $cvalue['hitshippo_fedex_zip'],
								"s_country" => $cvalue['hitshippo_fedex_country'],
								// "gstin" => $general_settings['hitshippo_fedex_gstin'],
								"s_name" => $cvalue['hitshippo_fedex_shipper_name'],
								"s_phone" => $cvalue['hitshippo_fedex_mob_num'],
								"s_email" => $cvalue['hitshippo_fedex_email'],
								"label_format" => "PDF",
								"label_format_type" => "COMMON2D",
								"label_size" => $general_settings['hitshippo_fedex_label_size'],
								"account_rates" => $acc_rates,
								"sent_email_to" => $cvalue['hitshippo_fedex_shippo_mail'],
								"label" => $key,
								"cod" => $cod,
								"etd_value" => $etd,
								"sat_del" => (isset($general_settings['hitshippo_fedex_sat_del']) && $general_settings['hitshippo_fedex_sat_del'] == "yes") ? "Y" : "N",
								"insurance" => (isset($general_settings['hitshippo_fedex_insurance']) && $general_settings['hitshippo_fedex_insurance'] == "yes") ? "Y" : "N",
								"woo_curr" => $order->get_currency(),
								"fedex_curr" => $cvalue['hitshippo_fedex_currency'],
								"con_rate" => $cvalue['hitshippo_fedex_con_rate'],
								"col_type" => $col_type,
								"duty_type" => $general_settings['hitshippo_fedex_duty_type'],
								"pickup_type" => isset($general_settings['hitshippo_fedex_pickup_type']) ? $general_settings['hitshippo_fedex_pickup_type'] : 'CONTACT_FEDEX_TO_SCHEDULE',
								"sign_option" => isset($general_settings['hitshippo_fedex_sign']) ? $general_settings['hitshippo_fedex_sign'] : '',
								"export_reason" => isset($general_settings['hitshippo_fedex_export_reason']) ? $general_settings['hitshippo_fedex_export_reason'] : 'SOLD',
								"inv_sign" => isset($general_settings['hitshippo_fedex_inv_img']) ? $general_settings['hitshippo_fedex_inv_img'] : '',
								"inv_letterhead" => isset($general_settings['hitshippo_fedex_inv_letterhead']) ? $general_settings['hitshippo_fedex_inv_letterhead'] : '',
								"ship_price" => $shipping_charge,
								"order_total" => isset($order_data['total']) ? $order_data['total'] : 0,
								"custom" => apply_filters("shipi_fedex_custom_values", array(), $_POST),
								"order_total_tax" => isset($order_data['total_tax']) ? $order_data['total_tax'] : 0,
							);

							//Auto Shipment
							$auto_ship_url = "https://app.myshipi.com/label_api/create_shipment.php";
							wp_remote_post( $auto_ship_url , array(
								'method'      => 'POST',
								'timeout'     => 45,
								'redirection' => 5,
								'httpversion' => '1.0',
								'blocking'    => false,
								'headers'     => array('Content-Type' => 'application/json; charset=utf-8'),
								'body'        => json_encode($data),
								'sslverify' => 0
								)
							);

						}
					}
			}
			public function hitshippo_create_fedex_return_shipping($order_id){
				if ($this->hpos_enabled) {
					if ('shop_order' !== Automattic\WooCommerce\Utilities\OrderUtil::get_order_type($order_id)) {
						return;
					}
				} else {
					$post = get_post($order_id);
			    	if($post->post_type !='shop_order' ){
			    		return;
			    	}
				}

		    	if (  isset( $_POST[ 'hitshippo_fedex_return_reset' ] ) ) {
		    		delete_option('hitshippo_fedex_return_values_'.$order_id);
		    	}

		    	if (isset($_POST['hitshippo_fedex_create_return_label'])) {
		    		$create_shipment_for = $_POST['hitshippo_fedex_create_return_label'];
					
		    		$service_code = $_POST['hitshippo_fedex_return_service_code_'.$create_shipment_for];
					$ship_content = "Return Shipment";
					$enabled_products = isset($_POST['return_products_fedex']) ? sanitize_meta('return_products_fedex',$_POST['return_products_fedex'], 'post') : array();
					$qty_products = isset($_POST['qty_products_fedex']) ? sanitize_meta('qty_products_fedex',$_POST['qty_products_fedex'], 'post') : array();
					
					$order = wc_get_order( $order_id );
			       if($order && !empty($enabled_products)){
		        	$order_data = $order->get_data();

		       		$order_id = $order_data['id'];
		       		$order_currency = $order_data['currency'];

					   $shipping_arr = (isset($order_data['shipping']['first_name']) && $order_data['shipping']['first_name'] != "") ? $order_data['shipping'] : $order_data['billing'];
					   $order_shipping_first_name = $shipping_arr['first_name'];
					   $order_shipping_last_name = $shipping_arr['last_name'];
					   $order_shipping_company = empty($shipping_arr['company']) ? $shipping_arr['first_name'] :  $shipping_arr['company'];
					   $order_shipping_address_1 = $shipping_arr['address_1'];
					   $order_shipping_address_2 = $shipping_arr['address_2'];
					   $order_shipping_city = $shipping_arr['city'];
					   $order_shipping_state = $shipping_arr['state'];
					   $order_shipping_postcode = $shipping_arr['postcode'];
					   $order_shipping_country = $shipping_arr['country'];
					   $order_shipping_phone = $order_data['billing']['phone'];
					   $order_shipping_email = $order_data['billing']['email'];


		       		// $order_shipping_first_name = $order_data['shipping']['first_name'];
					// $order_shipping_last_name = $order_data['shipping']['last_name'];
					// $order_shipping_company = empty($order_data['shipping']['company']) ? $order_data['shipping']['first_name'] :  $order_data['shipping']['company'];
					// $order_shipping_address_1 = $order_data['shipping']['address_1'];
					// $order_shipping_address_2 = $order_data['shipping']['address_2'];
					// $order_shipping_city = $order_data['shipping']['city'];
					// $order_shipping_state = $order_data['shipping']['state'];
					// $order_shipping_postcode = $order_data['shipping']['postcode'];
					// $order_shipping_country = $order_data['shipping']['country'];
					// $order_shipping_phone = $order_data['billing']['phone'];
					// $order_shipping_email = $order_data['billing']['email'];
					$shipping_charge = $order_data['shipping_total'];

					$items = $order->get_items();
					$pack_products = array();
					$total_weg = 0;
					$general_settings = get_option('hitshippo_fedex_main_settings',array());

				//weight conversion wc_get_weight( $weight, $to_unit, $from_unit )
				// $general_settings = get_option('hit_ups_auto_main_settings',array());
				$woo_weg_unit = get_option('woocommerce_weight_unit');
				$woo_dim_unit = get_option('woocommerce_dimension_unit');
				$config_weg_unit = $general_settings['hitshippo_fedex_weight_unit'];
				$mod_weg_unit = (!empty($config_weg_unit) && $config_weg_unit == 'LB_IN') ? 'lbs' : 'kg';
				$mod_dim_unit = (!empty($config_weg_unit) && $config_weg_unit == 'LB_IN') ? 'in' : 'cm';

					foreach ( $items as $item ) {
						$product_data = $item->get_data();
					    $product = array();
					    $product['product_name'] = str_replace('"', '', $product_data['name']);
					    $product['product_quantity'] = $product_data['quantity'];
					    $product['product_id'] = $product_data['product_id'];

						if ($this->hpos_enabled) {
							$hpos_prod_data = wc_get_product($product_data['product_id']);
							$saved_cc = $hpos_prod_data->get_meta("hits_fedex_cc");
							$saved_cc = $hpos_prod_data->get_meta("hits_fedex_label_dec");
						} else {
							$saved_cc = get_post_meta( $product_data['product_id'], 'hits_fedex_cc', true);
							$saved_desc = get_post_meta( $product_data['product_id'], 'hits_fedex_label_dec', true);
						}
						if(!empty($saved_cc)){
							$product['commodity_code'] = $saved_cc;
						}						
						if(!empty($saved_desc)){
							$product['invoice_desc'] = $saved_desc;
						}

					    $product_variation_id = $item->get_variation_id();
					    if(empty($product_variation_id)){
					    	$getproduct = wc_get_product( $product_data['product_id'] );
					    	$product_id = $product_data['product_id'];
					    }else{
							$getproduct = wc_get_product( $product_variation_id );
							$product_id = $product_variation_id;
						}

						if(!in_array($product_id, $enabled_products)){
							continue;
						}else{
							if($qty_products[$product_id] == 0){
								continue;
							}else{
								$product['product_quantity'] = $qty_products[$product_id];

							}
						}
						if (empty($getproduct)) {
							continue;
						}
						$product['price'] = $getproduct->get_price();
						$product['width'] = (!empty($getproduct->get_width())) ? round(wc_get_dimension($getproduct->get_width(),$mod_dim_unit,$woo_dim_unit)) : '';
				    	$product['height'] = (!empty($getproduct->get_height())) ? round(wc_get_dimension($getproduct->get_height(),$mod_dim_unit,$woo_dim_unit)) : '';
				   		$product['depth'] = (!empty($getproduct->get_length())) ? round(wc_get_dimension($getproduct->get_length(),$mod_dim_unit,$woo_dim_unit)) : '';
						$product['weight'] = (!empty($getproduct->get_weight())) ? (float)round(wc_get_weight($getproduct->get_weight(),$mod_weg_unit,$woo_weg_unit),2) : '';
						$total_weg += (!empty($product['weight'])) ? $product['weight'] : 0;

					    $pack_products[] = $product;

					}
					
					if (empty($pack_products)) {
						update_option('hitshippo_fedex_return_status_'.$order_id, "Product data not found");
						return;
					}

					$cod_services = array('PRIORITY_OVERNIGHT',
								'STANDARD_OVERNIGHT',
								'FEDEX_2_DAY_AM',
								'FEDEX_2_DAY',
								'FEDEX_EXPRESS_SAVER',
								'FEDEX_1_DAY_FREIGHT',
								'FEDEX_2_DAY_FREIGHT',
								'FEDEX_3_DAY_FREIGHT',
								'FEDEX_FIRST_FREIGHT',
								'FEDEX_FREIGHT_ECONOMY',
								'FEDEX_FREIGHT_PRIORITY',
								'FEDEX_GROUND',
								);

					$custom_settings = $this->get_vendors_on_order($general_settings, $pack_products, "", $order_data, $service_code);

					if(!empty($general_settings) && isset($general_settings['hitshippo_fedex_shippo_int_key'])){
						$mode = 'live';
						if(isset($general_settings['hitshippo_fedex_test']) && $general_settings['hitshippo_fedex_test']== 'yes'){
							$mode = 'test';
						}
						$execution = 'manual';
						$acc_rates = ($general_settings['hitshippo_fedex_account_rates'] == 'yes') ? 'NONE' : 'LIST';
						$residental_del = ($general_settings['hitshippo_fedex_res_f'] == 'yes') ? 'true' : 'false';
						$col_type = (isset($general_settings['hitshippo_fedex_collection_type']) && !empty($general_settings['hitshippo_fedex_collection_type'])) ? $general_settings['hitshippo_fedex_collection_type'] : "CASH";
						$cod = "N";
						$etd= "off";
						if ((isset($general_settings['hitshippo_fedex_etd']) && $general_settings['hitshippo_fedex_etd'] == "yes")) {
							$etd = "on";
						}
						$boxes_to_shipo = array();
						if (isset($general_settings['hitshippo_fedex_packing_type']) && $general_settings['hitshippo_fedex_packing_type'] == "box") {
							if (isset($general_settings['hitshippo_fedex_boxes']) && !empty($general_settings['hitshippo_fedex_boxes'])) {
								foreach ($general_settings['hitshippo_fedex_boxes'] as $box) {
									if ($box['enabled'] != 1) {
										continue;
									}else {
										$boxes_to_shipo[] = $box;
									}
								}
							}
						}
						
						// RETURN SHIP
						$data = array();
						$data['integrated_key'] = $general_settings['hitshippo_fedex_shippo_int_key'];
						$data['order_id'] = $order_id;
						$data['exec_type'] = $execution;
						$data['mode'] = $mode;
						$data['carrier_type'] = "fedex";
						$data['meta'] = array(
							"site_id" => $custom_settings[$create_shipment_for]['hitshippo_fedex_site_id'],
							"password"  => $custom_settings[$create_shipment_for]['hitshippo_fedex_site_pwd'],
							"accountnum" => $custom_settings[$create_shipment_for]['hitshippo_fedex_acc_no'],
							"meternum" => $custom_settings[$create_shipment_for]['hitshippo_fedex_access_key'],
							"rest_grant_type" => isset($custom_settings[$create_shipment_for]['hitshippo_fedex_rest_grant_type']) ? $custom_settings[$create_shipment_for]['hitshippo_fedex_rest_grant_type'] : "",
							"rest_acc_no" => isset($custom_settings[$create_shipment_for]['hitshippo_fedex_rest_acc_no']) ? $custom_settings[$create_shipment_for]['hitshippo_fedex_rest_acc_no'] : "",
							"rest_api_key" => isset($custom_settings[$create_shipment_for]['hitshippo_fedex_rest_api_key']) ? $custom_settings[$create_shipment_for]['hitshippo_fedex_rest_api_key'] : "",
							"rest_secret_key" => isset($custom_settings[$create_shipment_for]['hitshippo_fedex_rest_secret_key']) ? $custom_settings[$create_shipment_for]['hitshippo_fedex_rest_secret_key'] : "",
							"fedex_api_type" => isset($general_settings['hitshippo_fedex_api_type']) && !empty($general_settings['hitshippo_fedex_api_type']) ? $general_settings['hitshippo_fedex_api_type'] : "SOAP",
							"t_company" => $custom_settings[$create_shipment_for]['hitshippo_fedex_company'],
							"t_address1" => $custom_settings[$create_shipment_for]['hitshippo_fedex_address1'],
							"t_address2" => $custom_settings[$create_shipment_for]['hitshippo_fedex_address2'],
							"t_city" => $custom_settings[$create_shipment_for]['hitshippo_fedex_city'],
							"t_state" => $custom_settings[$create_shipment_for]['hitshippo_fedex_state'],
							"t_postal" => $custom_settings[$create_shipment_for]['hitshippo_fedex_zip'],
							"t_country" => $custom_settings[$create_shipment_for]['hitshippo_fedex_country'],
							"t_name" => $custom_settings[$create_shipment_for]['hitshippo_fedex_shipper_name'],
							"t_phone" => $custom_settings[$create_shipment_for]['hitshippo_fedex_mob_num'],
							"t_email" => $custom_settings[$create_shipment_for]['hitshippo_fedex_email'],
							"residential" => $residental_del,
							"drop_off_type" => $general_settings['hitshippo_fedex_drop_off'],
							"packing_type" => $general_settings['hitshippo_fedex_ship_pack_type'],
							"products" => apply_filters("shipi_fedex_prods_to_ship", $custom_settings[$create_shipment_for]['products'], $order, $create_shipment_for),
							"pack_algorithm" => $general_settings['hitshippo_fedex_packing_type'],
							"boxes" => $boxes_to_shipo,
							"max_weight" => $general_settings['hitshippo_fedex_max_weight'],
							"wight_dim_unit" => $general_settings['hitshippo_fedex_weight_unit'],
							"total_product_weg" => $total_weg,
							"service_code" => $custom_settings[$create_shipment_for]['service_code'],	//'PRIORITY_OVERNIGHT'
							"shipment_content" => $ship_content,
							"s_company" => $order_shipping_company,
							"s_address1" => $order_shipping_address_1,
							"s_address2" => $order_shipping_address_2,
							"s_city" => $order_shipping_city,
							"s_state" => substr(str_replace($order_shipping_country."-", "", $order_shipping_state), 0,2),
							"s_postal" => $order_shipping_postcode,
							"s_country" => $order_shipping_country,
							// "gstin" => $general_settings['hitshippo_fedex_gstin'],
							"s_name" => $order_shipping_first_name . ' '. $order_shipping_last_name,
							"s_phone" => $order_shipping_phone,
							"s_email" => $order_shipping_email,
							"label_format" => "PDF",
							"label_format_type" => "COMMON2D",
							"label_size" => $general_settings['hitshippo_fedex_label_size'],
							"account_rates" => $acc_rates,
							"sent_email_to" => $custom_settings[$create_shipment_for]['hitshippo_fedex_shippo_mail'],
							"cod" => $cod,
							"etd_value" => $etd,
							"sat_del" => (isset($general_settings['hitshippo_fedex_sat_del']) && $general_settings['hitshippo_fedex_sat_del'] == "yes") ? "Y" : "N",
							"insurance" => (isset($general_settings['hitshippo_fedex_insurance']) && $general_settings['hitshippo_fedex_insurance'] == "yes") ? "Y" : "N",
							"woo_curr" => $order->get_currency(),
							"fedex_curr" => $custom_settings[$create_shipment_for]['hitshippo_fedex_currency'],
							"con_rate" => $custom_settings[$create_shipment_for]['hitshippo_fedex_con_rate'],
							"col_type" => $col_type,
							"return" => "1",
							"duty_type" => isset($_POST['hitshippo_fedex_duty_type']) ? $_POST['hitshippo_fedex_duty_type'] : $general_settings['hitshippo_fedex_duty_type'],
							"pickup_type" => isset($general_settings['hitshippo_fedex_pickup_type']) ? $general_settings['hitshippo_fedex_pickup_type'] : 'CONTACT_FEDEX_TO_SCHEDULE',
							"sign_option" => isset($general_settings['hitshippo_fedex_sign']) ? $general_settings['hitshippo_fedex_sign'] : '',
							"export_reason" => isset($general_settings['hitshippo_fedex_export_reason']) ? $general_settings['hitshippo_fedex_export_reason'] : 'SOLD', 
							"inv_sign" => isset($general_settings['hitshippo_fedex_inv_img']) ? $general_settings['hitshippo_fedex_inv_img'] : '',
							"inv_letterhead" => isset($general_settings['hitshippo_fedex_inv_letterhead']) ? $general_settings['hitshippo_fedex_inv_letterhead'] : '',
							"return_type" => isset($general_settings['hitshippo_fedex_return_type']) ? $general_settings['hitshippo_fedex_return_type'] : '',
							"return_type_desc" => isset($general_settings['hitshippo_fedex_return_type_desc']) ? $general_settings['hitshippo_fedex_return_type_desc'] : '',
							"ship_price" => $shipping_charge,
							"order_total" => isset($order_data['total']) ? $order_data['total'] : 0,
							"order_total_tax" => isset($order_data['total_tax']) ? $order_data['total_tax'] : 0,
							"custom" => apply_filters("shipi_fedex_custom_values", array(), $_POST),
							"label" => $create_shipment_for
						);

					
						//Return Shipment
						$return_ship_url = "https://app.myshipi.com/label_api/create_shipment.php";
						$response = wp_remote_post( $return_ship_url , array(
							'method'      => 'POST',
							'timeout'     => 45,
							'redirection' => 5,
							'httpversion' => '1.0',
							'blocking'    => true,
							'headers'     => array('Content-Type' => 'application/json; charset=utf-8'),
							'body'        => json_encode($data),
							'sslverify' => 0
							)
						);

						$output = (is_array($response) && isset($response['body'])) ? json_decode($response['body'],true) : [];
						
						if($output){
							if(isset($output['status'])){
								if(isset($output['status']) && $output['status'] != 'success'){
									update_option('hitshippo_fedex_return_status_'.$order_id, $output['status']);
								}else if(isset($output['status']) && $output['status'] == 'success'){
									$output['user_id'] = $create_shipment_for;
									$val = get_option('hitshippo_fedex_return_values_'.$order_id, []);
									$result_arr = array();
									if(!empty($val)){
										$result_arr = json_decode($val, true);
									}
									$result_arr[] = $output;
									update_option('hitshippo_fedex_return_values_'.$order_id, json_encode($result_arr));
									update_option('hitshippo_fedex_return_status_'.$order_id, $output['status']);
								}
							}else{
								update_option('hitshippo_fedex_return_status_'.$order_id, 'Site not Connected with Shipi. Contact Shipi Team.');
							}
						}else{
							update_option('hitshippo_fedex_return_status_'.$order_id, 'Site not Connected with Shipi. Contact Shipi Team.');
						}

			    	}
			}

		}

			}
		public function hitshippo_create_fedex_shipping($order_id){
			if ($this->hpos_enabled) {
				if ('shop_order' !== Automattic\WooCommerce\Utilities\OrderUtil::get_order_type($order_id)) {
					return;
				}
			} else {
				$post = get_post($order_id);
		    	if($post->post_type !='shop_order' ){
		    		return;
		    	}
			}

		    	if (  isset( $_POST[ 'hitshippo_fedex_reset' ] ) ) {
		    		delete_option('hitshippo_fedex_values_'.$order_id);
		    	}

				$select_box_content = '';

				if (isset($_POST['shipi_fedex_get_rates'])) {

					
					global $fedex_core;
					$get_rates_for = sanitize_text_field($_POST['shipi_fedex_get_rates']);
					$general_settings = get_option('hitshippo_fedex_main_settings',array());
					
					$order = wc_get_order( $order_id );
					if($order){
						$post_data = [
							'shipi_fedex_add_cus_pack_'.$get_rates_for => isset($_POST['shipi_fedex_add_cus_pack_'.$get_rates_for]) ? $_POST['shipi_fedex_add_cus_pack_'.$get_rates_for] : '',
							'shipi_fedex_pack_weight_'.$get_rates_for => isset($_POST['shipi_fedex_pack_weight_'.$get_rates_for]) ? $_POST['shipi_fedex_pack_weight_'.$get_rates_for] : '',
							'shipi_fedex_pack_weg_unit_'.$get_rates_for => isset($_POST['shipi_fedex_pack_weg_unit_'.$get_rates_for]) ? $_POST['shipi_fedex_pack_weg_unit_'.$get_rates_for] : '',
							'shipi_fedex_pack_height_'.$get_rates_for => isset($_POST['shipi_fedex_pack_height_'.$get_rates_for]) ? $_POST['shipi_fedex_pack_height_'.$get_rates_for] : '',
							'shipi_fedex_pack_length_'.$get_rates_for => isset($_POST['shipi_fedex_pack_length_'.$get_rates_for]) ? $_POST['shipi_fedex_pack_length_'.$get_rates_for] : '',
							'shipi_fedex_pack_width_'.$get_rates_for => isset($_POST['shipi_fedex_pack_width_'.$get_rates_for]) ? $_POST['shipi_fedex_pack_width_'.$get_rates_for] : '',
							'shipi_fedex_pack_dim_unit_'.$get_rates_for => isset($_POST['shipi_fedex_pack_dim_unit_'.$get_rates_for]) ? $_POST['shipi_fedex_pack_dim_unit_'.$get_rates_for] : '',
							'shipi_fedex_pack_cost_'.$get_rates_for => isset($_POST['shipi_fedex_pack_cost_'.$get_rates_for]) ? $_POST['shipi_fedex_pack_cost_'.$get_rates_for] : '',
							'hitshippo_fedex_shipment_content_'.$get_rates_for => isset($_POST['hitshippo_fedex_shipment_content_'.$get_rates_for]) ? $_POST['hitshippo_fedex_shipment_content_'.$get_rates_for] : '',
							'hitshippo_fedex_export_reason_'.$get_rates_for => isset($_POST['hitshippo_fedex_export_reason_'.$get_rates_for]) ? $_POST['hitshippo_fedex_export_reason_'.$get_rates_for] : '',
							'hitshippo_fedex_duty_type_'.$get_rates_for => isset($_POST['hitshippo_fedex_duty_type_'.$get_rates_for]) ? $_POST['hitshippo_fedex_duty_type_'.$get_rates_for] : '',
							'ship_date' => isset($_POST['ship_date']) ? $_POST['ship_date'] : '',
									];
						
						$order_data = $order->get_data();
				       	$order_id = $order_data['id'];
				       	update_post_meta($order_id, "_shipi_custom_val_rates", json_encode($post_data));
						
						$order_products = $this->get_products_on_order($general_settings, $order);
				       	if (empty($order_products)) {
				       		update_option('hitshippo_fedex_status_'.$order_id, "No product data found.");
							return;
				       	}

						$custom_settings = $this->get_vendors_on_order($general_settings, $order_products);
						$custom_packs = [];

						if (isset($_POST['shipi_fedex_add_cus_pack_'.$get_rates_for])) {
							if (!isset($_POST['shipi_fedex_pack_weight_'.$get_rates_for])) {
								update_option('hitshippo_fedex_status_'.$order_id, "Need packs for custom packing.");
			    				return;
							}
							foreach ($_POST['shipi_fedex_pack_weight_'.$get_rates_for] as $key => $pack_heights) {
								$curr_pack = [];
								$curr_pack['GroupNumber'] = $key+1;
								$curr_pack['GroupPackageCount'] = $key+1;
								$curr_pack['Weight'] = [
									"Value" => isset($_POST['shipi_fedex_pack_weight_'.$get_rates_for][$key]) ? $_POST['shipi_fedex_pack_weight_'.$get_rates_for][$key] : 0,
									"Units" => isset($_POST['shipi_fedex_pack_weg_unit_'.$get_rates_for][$key]) ? $_POST['shipi_fedex_pack_weg_unit_'.$get_rates_for][$key] : "KG"
								];
								if (isset($_POST['shipi_fedex_pack_height_'.$get_rates_for][$key]) && isset($_POST['shipi_fedex_pack_length_'.$get_rates_for][$key]) && isset($_POST['shipi_fedex_pack_width_'.$get_rates_for][$key])) {
									if (!empty($_POST['shipi_fedex_pack_height_'.$get_rates_for][$key]) && !empty($_POST['shipi_fedex_pack_length_'.$get_rates_for][$key]) && !empty($_POST['shipi_fedex_pack_width_'.$get_rates_for][$key])) {
										$curr_pack['Dimensions'] = [
											"Length" => $_POST['shipi_fedex_pack_length_'.$get_rates_for][$key],
											"Width" => $_POST['shipi_fedex_pack_width_'.$get_rates_for][$key],
											"Height" => $_POST['shipi_fedex_pack_height_'.$get_rates_for][$key],
											"Units" =>$_POST['shipi_fedex_pack_dim_unit_'.$get_rates_for][$key]
										];
									}
								}
								$curr_pack['InsuredValue'] = [
									"Amount" => (isset($_POST['shipi_fedex_pack_cost_'.$get_rates_for][$key]) && !empty($_POST['shipi_fedex_pack_cost_'.$get_rates_for][$key])) ? $_POST['shipi_fedex_pack_cost_'.$get_rates_for][$key] : 1,
									"Currency" => isset($order_data['currency']) ? $order_data['currency'] : ""
								];
								$curr_pack['packed_products'] = [];
								$curr_pack['package_id'] = "Custom_Pack_".$key;
								$curr_pack['packtype'] = "BOX";
								$custom_packs[] = $curr_pack;
							}
						}

						foreach ($custom_settings as $key => $cust_set) {
							$shipping_rates[$key] = array();
							$weight_unit = $dim_unit = '';
							if (!empty($general_settings['hitshippo_fedex_weight_unit']) && $general_settings['hitshippo_fedex_weight_unit'] == 'KG_CM') {
								$weight_unit = 'KG';
								$dim_unit = 'CM';
							} else {
								$weight_unit = 'LB';
								$dim_unit = 'IN';
							}

							$fedex_selected_curr = $cust_set['hitshippo_fedex_currency'];
							if (isset($general_settings['hitshippo_fedex_auto_con_rate']) && $general_settings['hitshippo_fedex_auto_con_rate'] == "yes") {
								$frm_curr = get_option('woocommerce_currency');
								if (isset($fedex_selected_curr) && !empty($fedex_selected_curr) && isset($frm_curr) && !empty($frm_curr) && ($frm_curr != $fedex_selected_curr)) {
									$current_date = date('m-d-Y', time());
									$ex_rate_data = get_option('hitshippo_fedex_ex_rate' . $key, '');
									$ex_rate_data = !empty($ex_rate_data) ? $ex_rate_data : array();
									if (empty($ex_rate_data) || (isset($ex_rate_data['date']) && $ex_rate_data['date'] != $current_date) || (isset($ex_rate_data['to_currency']) && ($ex_rate_data['to_currency'] != $fedex_selected_curr)) || (isset($ex_rate_data['from_currency']) && ($ex_rate_data['from_currency'] != $frm_curr))) {
										if (isset($general_settings['hitshippo_fedex_shippo_int_key']) && !empty($general_settings['hitshippo_fedex_shippo_int_key'])) {
											$to_curr = $fedex_selected_curr;
											$ex_rate_Request = json_encode(array(
												'integrated_key' => $general_settings['hitshippo_fedex_shippo_int_key'],
												'from_curr' => $frm_curr,
												'to_curr' => $to_curr
											));
		
											$response = wp_remote_post(
												"https://app.myshipi.com/get_exchange_rate.php",
												array(
													'method'      => 'POST',
													'timeout'     => 45,
													'redirection' => 5,
													'httpversion' => '1.0',
													'blocking'    => true,
													'headers'     => array('Content-Type' => 'application/json; charset=utf-8'),
													'body'        => $ex_rate_Request,
													'sslverify' => 0,
												)
											);
		
											$ex_rate_result = isset($response['body']) ? json_decode($response['body'], true) : [];
		
											if (!empty($ex_rate_result) && isset($ex_rate_result['ex_rate']) && $ex_rate_result['ex_rate'] != "Not Found") {
												$ex_rate_result['date'] = $current_date;
												update_option('hitshippo_fedex_ex_rate' . $key, $ex_rate_result);
											} else {
												if (!empty($ex_rate_data)) {
													$ex_rate_data['date'] = $current_date;
													update_option('hitshippo_fedex_ex_rate' . $key, $ex_rate_data);
												}
											}
										}
									}
								}
							}
							$rec_address = (isset($order_data['shipping']['first_name']) && !empty($order_data['shipping']['first_name'])) ? $order_data['shipping'] : $order_data['billing'];
							$pack_aft_hook['destination'] = $rec_address;
							if (isset($general_settings['hitshippo_fedex_api_type']) && $general_settings['hitshippo_fedex_api_type'] == "REST") {
								$auth_token = get_transient("hitshipo_fedex_rest_auth_token_".$key);
								if (!class_exists("fedex_rest")) {
									include_once("controllors/hitshipo_fedex_rest_main.php");
								}
								$fedex_rest_obj = new fedex_rest();
								$fedex_rest_obj->mode = (isset($general_settings['hitshippo_fedex_test']) && $general_settings['hitshippo_fedex_test'] == 'yes') ? 'test' : 'live';
								if (empty($auth_token)) {
									$auth_token = $fedex_rest_obj->gen_access_token($cust_set['hitshippo_fedex_rest_grant_type'], $cust_set['hitshippo_fedex_rest_api_key'], $cust_set['hitshippo_fedex_rest_secret_key']);
									set_transient("hitshipo_fedex_rest_auth_token_".$key, $auth_token, 3500);
								}
								$fedex_packs =  !empty($custom_packs) ? $custom_packs : $this->shipi_get_fedex_packages($cust_set['products'], $general_settings, $general_settings['hitshippo_fedex_currency']);
								
								$xmlRequest = $fedex_rest_obj->make_rate_req_rest($general_settings, $cust_set, $pack_aft_hook['destination'], $fedex_packs, isset($order_data['total']) ? $order_data['total'] : 0);
								
								$request_url = ($fedex_rest_obj->mode == "test") ? $fedex_rest_obj->test_rate_url : $fedex_rest_obj->live_rate_url;
								
								$result = $fedex_rest_obj->get_rate_res_rest($xmlRequest, $auth_token);
								
								if (isset($general_settings['hitshippo_fedex_developer_rate']) && $general_settings['hitshippo_fedex_developer_rate'] == 'yes' && $admin_user) {
									echo "<pre><b style='color:red;'>Debug log will be shown only for admins and it can be disabled by unchecking 'Enable Debug Mode' option in configuration.</b>";
									echo "<h3> Request </h3><br/>";
									print_r($request_url);
									if (is_array($xmlRequest)) {
										echo "<br/>";
										print_r($auth_token);
										echo "<br/>";
										print_r(json_encode($xmlRequest));
										echo "<br/>";
										print_r($xmlRequest);
									} else {
										print_r(htmlspecialchars($xmlRequest));
									}
									echo '<br/><h3>Response</h3> <br/>';
									if (isset($result) && !empty($result)) {
										print_r($result);
									} else {
										print_r("No rate response from Fedex");
										die();
									}
									// echo "<br/><h1> Response - XML </h1><br/>";
									// print_r($xml->SOAPENVBody->RateReply);
			
								}

								libxml_use_internal_errors(true);
								if (!empty($result) && is_array($result) && isset($result['body'])) {
									$xml = str_replace(array(':', '-'), '', $result['body']);
									$xml = simplexml_load_string($xml);

									if (isset($general_settings['hitshippo_fedex_developer_rate']) && $general_settings['hitshippo_fedex_developer_rate'] == 'yes' && $admin_user) {
										echo '<br/><h3>Response Body</h3> <br/>';
										print_r($xml);
										die();
									}
								} elseif (!empty($result) && isset($result->output)) {
									$xml = $result->output;
									if (isset($general_settings['hitshippo_fedex_developer_rate']) && $general_settings['hitshippo_fedex_developer_rate'] == 'yes' && $admin_user) {
										die();
									}
								} else {

									if (isset($general_settings['hitshippo_fedex_developer_rate']) && $general_settings['hitshippo_fedex_developer_rate'] == 'yes' && $admin_user) {
										echo '<br/><h3>Response Body</h3> <br/>';
										print_r("Empty");
										die();
									}
								}
								if (isset($xml) && isset($xml->SOAPENVBody->RateReply)) {
									$xml = $xml->SOAPENVBody->RateReply;
								}
								$carriers_available = isset($general_settings['hitshippo_fedex_carrier']) && is_array($general_settings['hitshippo_fedex_carrier']) ? $general_settings['hitshippo_fedex_carrier'] : array();
								
									
								if (isset($xml->rateReplyDetails) && !empty($xml->rateReplyDetails)) {
									foreach ($xml->rateReplyDetails as $quote) {
									$rate_code = ((string) $quote->serviceType);
										$rate_cost = 0;
										
										
											$shipment_details = '';
											foreach ($quote->ratedShipmentDetails as $shipment_deta) {
												if ($hitshippo_fedex_account_rates == "LIST") {
													if (strstr($shipment_deta->rateType, 'PAYOR_LIST')) {
														$shipment_details = $shipment_deta;
														break;
													} elseif (strstr($shipment_deta->rateType, 'LIST')) {
														$shipment_details = $shipment_deta;
														break;
													}
												} else {
													if (strstr($shipment_deta->rateType, 'PAYOR_ACCOUNT')) {
														$shipment_details = $shipment_deta;
														break;
													} elseif (strstr($shipment_deta->rateType, 'ACCOUNT_PACKAGE')) {
														$shipment_details = $shipment_deta;
														break;
													} elseif (strstr($shipment_deta->rateType, 'ACCOUNT')) {
														$shipment_details = $shipment_deta;
														break;
													}
												}
											}

											if (empty($shipment_details)) {
												$shipment_details = isset($quote->ratedShipmentDetails[0]) ? $quote->ratedShipmentDetails[0] : $quote->ratedShipmentDetails;
											}
			
											if (empty($shipment_details)) {
												continue;
											}
											if (isset($general_settings['hitshippo_fedex_include_tax']) && $general_settings['hitshippo_fedex_include_tax'] == "yes" && isset($shipment_details->totalNetChargeWithDutiesAndTaxes)) {
												$rate_cost = (float)((string) $shipment_details->totalNetChargeWithDutiesAndTaxes);
												$rate_curr = (string) $shipment_details->shipmentRateDetail->currency;
											}else{
												$rate_cost = (float)((string) $shipment_details->totalNetCharge);
												$rate_curr = (string) $shipment_details->shipmentRateDetail->currency;
											}
											if ($fedex_selected_curr == 'AED' || $fedex_selected_curr == 'DHS') {
												$rate_curr = (string)$fedex_selected_curr;
											}
				
											if ($rate_curr != $fedex_selected_curr) {
												if($frm_curr != $rate_curr){
													$ex_rate_Request = json_encode(array(
														'integrated_key' => $general_settings['hitshippo_fedex_shippo_int_key'],
														'from_curr' => $rate_curr,
														'to_curr' => $fedex_selected_curr
													));

													$response = wp_remote_post(
														"https://app.myshipi.com/get_exchange_rate.php",
														array(
															'method'      => 'POST',
															'timeout'     => 45,
															'redirection' => 5,
															'httpversion' => '1.0',
															'blocking'    => true,
															'headers'     => array('Content-Type' => 'application/json; charset=utf-8'),
															'body'        => $ex_rate_Request,
															'sslverify' => 0,
														)
													);

													$ex_rate_result = isset($response['body']) ? json_decode($response['body'], true) : [];
													
													if(!empty($ex_rate_result) && isset($ex_rate_result["ex_rate"])){
														$ex_rate = $ex_rate_result["ex_rate"];
														$rate_cost *= $ex_rate;
													}else{
														continue;
													}
													
												}
											}

											if (($rate_curr == $fedex_selected_curr) && ($frm_curr != $fedex_selected_curr) && ($ex_rate > 0)) {
												$rate_cost /= $ex_rate;
											}
			
											$rate[$rate_code] = $rate_cost;
												
											$del_dates[$rate_code] = isset($quote->operationalDetail->deliveryDate) ? (string)$quote->operationalDetail->deliveryDate : "";
										
									}
								}
								$shipping_rates[$key] = $rate;
								
							}

							if (!empty($shipping_rates)) {
								$i = 0;
								$final_price = array();
								foreach ($shipping_rates as $mkey => $rate) {
									$cheap_p = 0;
									$cheap_s = '';
									foreach ($rate as $key => $cvalue) {
										if ($i > 0) {
											if ($cheap_p == 0 && $cheap_s == '') {
												$cheap_p = $cvalue;
												$cheap_s = $key;
											} else if ($cheap_p > $cvalue) {
												$cheap_p = $cvalue;
												$cheap_s = $key;
											}
										} else {
											$final_price[] = array('price' => $cvalue, 'code' => $key, 'multi_v' => $mkey . '_' . $key);
										}
									}
			
									if ($cheap_p != 0 && $cheap_s != '') {
										foreach ($final_price as $key => $value) {
											$value['price'] = $value['price'] + $cheap_p;
											$value['multi_v'] = $value['multi_v'] . '|' . $mkey . '_' . $cheap_s;
											$final_price[$key] = $value;
										}
									}
			
									$i++;
								}
								
								if($final_price){
									update_option('hitshippo_fedex_rates_'.$order_id, json_encode($final_price));
								}else{
									update_option('hitshippo_fedex_status_'.$order_id, "No service data found.");
									return;
								}
							}else{
								update_option('hitshippo_fedex_status_'.$order_id, "No service data found.");
								return;
							}
						
							}
						}
						
				}

		    	if (isset($_POST['hitshippo_fedex_create_label'])) {
		    		$create_shipment_for = $_POST['hitshippo_fedex_create_label'];

		    		$service_code = $_POST['hitshippo_fedex_service_code_'.$create_shipment_for];
		        	$ship_content = !empty($_POST['hitshippo_fedex_shipment_content_'.$create_shipment_for]) ? $_POST['hitshippo_fedex_shipment_content_'.$create_shipment_for] : 'Shipment Content';
		        	$duty_type = !empty($_POST['hitshippo_fedex_duty_type_'.$create_shipment_for]) ? $_POST['hitshippo_fedex_duty_type_'.$create_shipment_for] : 'S';
		        	$export_reason = !empty($_POST['hitshippo_fedex_export_reason_'.$create_shipment_for]) ? $_POST['hitshippo_fedex_export_reason_'.$create_shipment_for] : 'SOLD';
					
					$order = wc_get_order( $order_id );
			       if($order){
		        	$order_data = $order->get_data();

		       		$order_id = $order_data['id'];
		       		$order_currency = $order_data['currency'];

					$custom_packs = [];

					if (isset($_POST['shipi_fedex_add_cus_pack_'.$create_shipment_for])) {
						if (!isset($_POST['shipi_fedex_pack_weight_'.$create_shipment_for])) {
							update_option('hitshippo_fedex_status_'.$order_id, "Need packs for custom packing.");
							return;
						}
						foreach ($_POST['shipi_fedex_pack_weight_'.$create_shipment_for] as $key => $pack_heights) {
							$curr_pack = [];
							$curr_pack['GroupNumber'] = $key+1;
							$curr_pack['GroupPackageCount'] = $key+1;
							$curr_pack['Weight'] = [
								"Value" => isset($_POST['shipi_fedex_pack_weight_'.$create_shipment_for][$key]) ? $_POST['shipi_fedex_pack_weight_'.$create_shipment_for][$key] : 0,
								"Units" => isset($_POST['shipi_fedex_pack_weg_unit_'.$create_shipment_for][$key]) ? $_POST['shipi_fedex_pack_weg_unit_'.$create_shipment_for][$key] : "KG"
							];
							if (isset($_POST['shipi_fedex_pack_height_'.$create_shipment_for][$key]) && isset($_POST['shipi_fedex_pack_length_'.$create_shipment_for][$key]) && isset($_POST['shipi_fedex_pack_width_'.$create_shipment_for][$key])) {
								if (!empty($_POST['shipi_fedex_pack_height_'.$create_shipment_for][$key]) && !empty($_POST['shipi_fedex_pack_length_'.$create_shipment_for][$key]) && !empty($_POST['shipi_fedex_pack_width_'.$create_shipment_for][$key])) {
									$curr_pack['Dimensions'] = [
										"Length" => $_POST['shipi_fedex_pack_length_'.$create_shipment_for][$key],
										"Width" => $_POST['shipi_fedex_pack_width_'.$create_shipment_for][$key],
										"Height" => $_POST['shipi_fedex_pack_height_'.$create_shipment_for][$key],
										"Units" =>$_POST['shipi_fedex_pack_dim_unit_'.$create_shipment_for][$key]
									];
								}
							}
							$curr_pack['InsuredValue'] = [
								"Amount" => (isset($_POST['shipi_fedex_pack_cost_'.$create_shipment_for][$key]) && !empty($_POST['shipi_fedex_pack_cost_'.$create_shipment_for][$key])) ? $_POST['shipi_fedex_pack_cost_'.$create_shipment_for][$key] : 1,
								"Currency" => isset($order_data['currency']) ? $order_data['currency'] : ""
							];
							$curr_pack['packed_products'] = [];
							$curr_pack['package_id'] = "Custom_Pack_".$key;
							$curr_pack['packtype'] = "BOX";
							$custom_packs[] = $curr_pack;
						}
					}
					
					   $shipping_arr = (isset($order_data['shipping']['first_name']) && $order_data['shipping']['first_name'] != "") ? $order_data['shipping'] : $order_data['billing'];
					   $order_shipping_first_name = $shipping_arr['first_name'];
					   $order_shipping_last_name = $shipping_arr['last_name'];
					   $order_shipping_company = empty($shipping_arr['company']) ? $shipping_arr['first_name'] . " " . $shipping_arr['last_name'] :  $shipping_arr['company'];
					   $order_shipping_address_1 = $shipping_arr['address_1'];
					   $order_shipping_address_2 = $shipping_arr['address_2'];
					   $order_shipping_city = $shipping_arr['city'];
					   $order_shipping_state = $shipping_arr['state'];
					   $order_shipping_postcode = $shipping_arr['postcode'];
					   $order_shipping_country = $shipping_arr['country'];
					   $order_shipping_phone = $order_data['billing']['phone'];
					   $order_shipping_email = $order_data['billing']['email'];


		       		// $order_shipping_first_name = $order_data['shipping']['first_name'];
					// $order_shipping_last_name = $order_data['shipping']['last_name'];
					// $order_shipping_company = empty($order_data['shipping']['company']) ? $order_data['shipping']['first_name'] :  $order_data['shipping']['company'];
					// $order_shipping_address_1 = $order_data['shipping']['address_1'];
					// $order_shipping_address_2 = $order_data['shipping']['address_2'];
					// $order_shipping_city = $order_data['shipping']['city'];
					// $order_shipping_state = $order_data['shipping']['state'];
					// $order_shipping_postcode = $order_data['shipping']['postcode'];
					// $order_shipping_country = $order_data['shipping']['country'];
					// $order_shipping_phone = $order_data['billing']['phone'];
					// $order_shipping_email = $order_data['billing']['email'];

					$shipping_charge = $order_data['shipping_total'];
					$handling_charge = (float) $order_data['discount_total'];

					$items = $order->get_items();
					$general_settings = get_option('hitshippo_fedex_main_settings',array());
					$pack_products = $this->get_products_on_order($general_settings, $order);
					$total_weg = 0;

					// If products empty just return
					if(empty($pack_products)){

						update_option('hitshippo_fedex_status_'.$order_id, "Product data not found");
						return;
					}

					$cod_services = array('PRIORITY_OVERNIGHT',
								'STANDARD_OVERNIGHT',
								'FEDEX_2_DAY_AM',
								'FEDEX_2_DAY',
								'FEDEX_EXPRESS_SAVER',
								'FEDEX_1_DAY_FREIGHT',
								'FEDEX_2_DAY_FREIGHT',
								'FEDEX_3_DAY_FREIGHT',
								'FEDEX_FIRST_FREIGHT',
								'FEDEX_FREIGHT_ECONOMY',
								'FEDEX_FREIGHT_PRIORITY',
								'FEDEX_GROUND',
								);

					$custom_settings = $this->get_vendors_on_order($general_settings, $pack_products, "", $order_data, $service_code);

					if(!empty($general_settings) && isset($general_settings['hitshippo_fedex_shippo_int_key'])){
						$mode = 'live';
						if(isset($general_settings['hitshippo_fedex_test']) && $general_settings['hitshippo_fedex_test']== 'yes'){
							$mode = 'test';
						}
						$execution = 'manual';
						// if(isset($general_settings['hitshippo_fedex_shippo_label_gen']) && $general_settings['hitshippo_fedex_shippo_label_gen']== 'yes'){
						// 	$execution = 'auto';
						// }

						$acc_rates = ($general_settings['hitshippo_fedex_account_rates'] == 'yes') ? 'NONE' : 'LIST';
						$residental_del = ($general_settings['hitshippo_fedex_res_f'] == 'yes') ? 'true' : 'false';
						$col_type = (isset($general_settings['hitshippo_fedex_collection_type']) && !empty($general_settings['hitshippo_fedex_collection_type'])) ? $general_settings['hitshippo_fedex_collection_type'] : "CASH";
						$cod = "N";

						if ((isset($general_settings['hitshippo_fedex_cod']) && $general_settings['hitshippo_fedex_cod'] == "yes") && ($custom_settings[$create_shipment_for]['hitshippo_fedex_country'] == $order_shipping_country) && (in_array($service_code, $cod_services)) ) {
							$cod = "Y";
						}
						$etd= "off";
						if ((isset($general_settings['hitshippo_fedex_etd']) && $general_settings['hitshippo_fedex_etd'] == "yes")) {
							$etd = "on";
						}
						$boxes_to_shipo = array();
						if (isset($general_settings['hitshippo_fedex_packing_type']) && $general_settings['hitshippo_fedex_packing_type'] == "box") {
							if (isset($general_settings['hitshippo_fedex_boxes']) && !empty($general_settings['hitshippo_fedex_boxes'])) {
								foreach ($general_settings['hitshippo_fedex_boxes'] as $box) {
									if ($box['enabled'] != 1) {
										continue;
									}else {
										$boxes_to_shipo[] = $box;
									}
								}
							}
						}

						$data = array();
						$data['integrated_key'] = $general_settings['hitshippo_fedex_shippo_int_key'];
						$data['order_id'] = $order_id;
						$data['exec_type'] = $execution;
						$data['mode'] = $mode;
						$data['carrier_type'] = "fedex";
						$data['meta'] = array(
							"site_id" => $custom_settings[$create_shipment_for]['hitshippo_fedex_site_id'],
							"password"  => $custom_settings[$create_shipment_for]['hitshippo_fedex_site_pwd'],
							"accountnum" => $custom_settings[$create_shipment_for]['hitshippo_fedex_acc_no'],
							"meternum" => $custom_settings[$create_shipment_for]['hitshippo_fedex_access_key'],
							"rest_grant_type" => isset($custom_settings[$create_shipment_for]['hitshippo_fedex_rest_grant_type']) ? $custom_settings[$create_shipment_for]['hitshippo_fedex_rest_grant_type'] : "",
							"rest_acc_no" => isset($custom_settings[$create_shipment_for]['hitshippo_fedex_rest_acc_no']) ? $custom_settings[$create_shipment_for]['hitshippo_fedex_rest_acc_no'] : "",
							"rest_api_key" => isset($custom_settings[$create_shipment_for]['hitshippo_fedex_rest_api_key']) ? $custom_settings[$create_shipment_for]['hitshippo_fedex_rest_api_key'] : "",
							"rest_secret_key" => isset($custom_settings[$create_shipment_for]['hitshippo_fedex_rest_secret_key']) ? $custom_settings[$create_shipment_for]['hitshippo_fedex_rest_secret_key'] : "",
							"fedex_api_type" => isset($general_settings['hitshippo_fedex_api_type']) && !empty($general_settings['hitshippo_fedex_api_type']) ? $general_settings['hitshippo_fedex_api_type'] : "SOAP",
							"t_company" => $order_shipping_company,
							"t_address1" => $order_shipping_address_1,
							"t_address2" => $order_shipping_address_2,
							"t_city" => $order_shipping_city,
							"t_state" => substr(str_replace($order_shipping_country."-", "", $order_shipping_state), 0,2),
							"t_postal" => $order_shipping_postcode,
							"t_country" => $order_shipping_country,
							"t_name" => $order_shipping_first_name . ' '. $order_shipping_last_name,
							"t_phone" => $order_shipping_phone,
							"t_email" => $order_shipping_email,
							"residential" => $residental_del,
							"drop_off_type" => $general_settings['hitshippo_fedex_drop_off'],
							"packing_type" => $general_settings['hitshippo_fedex_ship_pack_type'],
							"products" => apply_filters("shipi_fedex_prods_to_ship", $custom_settings[$create_shipment_for]['products'], $order, $create_shipment_for),
							"pack_algorithm" => $general_settings['hitshippo_fedex_packing_type'],
							"boxes" => $boxes_to_shipo,
							"max_weight" => $general_settings['hitshippo_fedex_max_weight'],
							"wight_dim_unit" => $general_settings['hitshippo_fedex_weight_unit'],
							"total_product_weg" => $total_weg,
							"service_code" => $custom_settings[$create_shipment_for]['service_code'],	//'PRIORITY_OVERNIGHT'
							"shipment_content" => $ship_content,
							"s_company" => $custom_settings[$create_shipment_for]['hitshippo_fedex_company'],
							"s_address1" => $custom_settings[$create_shipment_for]['hitshippo_fedex_address1'],
							"s_address2" => $custom_settings[$create_shipment_for]['hitshippo_fedex_address2'],
							"s_city" => $custom_settings[$create_shipment_for]['hitshippo_fedex_city'],
							"s_state" => $custom_settings[$create_shipment_for]['hitshippo_fedex_state'],
							"s_postal" => $custom_settings[$create_shipment_for]['hitshippo_fedex_zip'],
							"s_country" => $custom_settings[$create_shipment_for]['hitshippo_fedex_country'],
							// "gstin" => $general_settings['hitshippo_fedex_gstin'],
							"s_name" => $custom_settings[$create_shipment_for]['hitshippo_fedex_shipper_name'],
							"s_phone" => $custom_settings[$create_shipment_for]['hitshippo_fedex_mob_num'],
							"s_email" => $custom_settings[$create_shipment_for]['hitshippo_fedex_email'],
							"label_format" => "PDF",
							"label_format_type" => "COMMON2D",
							"label_size" => $general_settings['hitshippo_fedex_label_size'],
							"account_rates" => $acc_rates,
							"sent_email_to" => $custom_settings[$create_shipment_for]['hitshippo_fedex_shippo_mail'],
							"cod" => $cod,
							"etd_value" => $etd,
							"sat_del" => (isset($general_settings['hitshippo_fedex_sat_del']) && $general_settings['hitshippo_fedex_sat_del'] == "yes") ? "Y" : "N",
							"insurance" => (isset($general_settings['hitshippo_fedex_insurance']) && $general_settings['hitshippo_fedex_insurance'] == "yes") ? "Y" : "N",
							"woo_curr" => $order->get_currency(),
							"fedex_curr" => $custom_settings[$create_shipment_for]['hitshippo_fedex_currency'],
							"con_rate" => $custom_settings[$create_shipment_for]['hitshippo_fedex_con_rate'],
							"col_type" => $col_type,
							"duty_type" => $duty_type,
							"pickup_type" => isset($general_settings['hitshippo_fedex_pickup_type']) ? $general_settings['hitshippo_fedex_pickup_type'] : 'CONTACT_FEDEX_TO_SCHEDULE',
							"sign_option" => isset($general_settings['hitshippo_fedex_sign']) ? $general_settings['hitshippo_fedex_sign'] : '',
							"export_reason" => $export_reason, 
							"inv_sign" => isset($general_settings['hitshippo_fedex_inv_img']) ? $general_settings['hitshippo_fedex_inv_img'] : '',
							"inv_letterhead" => isset($general_settings['hitshippo_fedex_inv_letterhead']) ? $general_settings['hitshippo_fedex_inv_letterhead'] : '',
							"ship_price" => $shipping_charge,
							"handling_price" => ($handling_charge > 0 ? ((-1) * $handling_charge) : 0),
							"ship_date" => isset($_POST["ship_date"]) ? $_POST["ship_date"] : "",
							"order_total" => isset($order_data['total']) ? $order_data['total'] : 0,
							"order_total_tax" => isset($order_data['total_tax']) ? $order_data['total_tax'] : 0,
							"custom" => apply_filters("shipi_fedex_custom_values", array(), $_POST),
							"label" => $create_shipment_for,
							"custom_packs" => $custom_packs
						);
						//Manual Shipment
						$manual_ship_url = "https://app.myshipi.com/label_api/create_shipment.php";
						// $manual_ship_url = "http://localhost/shipi/label_api/create_shipment.php";
						$response = wp_remote_post( $manual_ship_url , array(
							'method'      => 'POST',
							'timeout'     => 45,
							'redirection' => 5,
							'httpversion' => '1.0',
							'blocking'    => true,
							'headers'     => array('Content-Type' => 'application/json; charset=utf-8'),
							'body'        => json_encode($data),
							'sslverify' => 0
							)
						);
						
						
						$output = (is_array($response) && isset($response['body'])) ? json_decode($response['body'],true) : [];
						
						if($output){
							if(isset($output['status'])){
								if(isset($output['status']) && $output['status'] != 'success'){
									update_option('hitshippo_fedex_status_'.$order_id, $output['status']);
								}else if(isset($output['status']) && $output['status'] == 'success'){
									$output['user_id'] = $create_shipment_for;
									$result_arr = array();
									$data = get_option('hitshippo_fedex_values_'.$order_id, array());

									if(!empty($data)){
										$result_arr = json_decode($data, true);
									}

									$result_arr[] = $output;

									update_option('hitshippo_fedex_values_'.$order_id, json_encode($result_arr));
									update_option('hitshippo_fedex_status_'.$order_id, $output['status']);
								}
							}else{
								update_option('hitshippo_fedex_status_'.$order_id, 'Site not Connected with Shipi. Contact Shipi Team.');
							}
						}else{
							update_option('hitshippo_fedex_status_'.$order_id, 'Site not Connected with Shipi. Contact Shipi Team.');
						}

			    	}
			}

		}
		}
		private function get_products_on_order($general_settings = [], $order = [])
		{
			$pack_products = [];
			$items = $order->get_items();
			$woo_weg_unit = get_option('woocommerce_weight_unit');
			$woo_dim_unit = get_option('woocommerce_dimension_unit');
			$config_weg_unit = $general_settings['hitshippo_fedex_weight_unit'];
			$mod_weg_unit = (!empty($config_weg_unit) && $config_weg_unit == 'LB_IN') ? 'lbs' : 'kg';
			$mod_dim_unit = (!empty($config_weg_unit) && $config_weg_unit == 'LB_IN') ? 'in' : 'cm';

			foreach ( $items as $item ) {
				$product_data = $item->get_data();
								
				$product = array();
			    $product['product_quantity'] = $product_data['quantity'];
			    $product['product_id'] = $product_data['product_id'];

				if ($this->hpos_enabled) {
					$hpos_prod_data = wc_get_product($product_data['product_id']);
					$saved_cc = $hpos_prod_data->get_meta("hits_fedex_cc");
					$saved_desc = $hpos_prod_data->get_meta("hits_fedex_label_dec");
				} else {
					$saved_cc = get_post_meta( $product_data['product_id'], 'hits_fedex_cc', true);
					$saved_desc = get_post_meta( $product_data['product_id'], 'hits_fedex_label_dec', true);
				}
				if(!empty($saved_cc)){
					$product['commodity_code'] = $saved_cc;
				}
				if(!empty($saved_desc)){
					$product['invoice_desc'] = $saved_desc;
				}

			    $product_variation_id = $item->get_variation_id();
			    if(empty($product_variation_id)){
			    	$getproduct = wc_get_product( $product_data['product_id'] );
			    }else{
			    	$getproduct = wc_get_product( $product_variation_id );
			    }

				if ($getproduct->is_virtual()) {
					continue;
				}
		
				if (empty($getproduct)) {
					continue;
				}
				$skip = apply_filters("a2z_fedex_skip_sku_from_label", false, $getproduct->get_sku());
				if($skip){
					continue;
				}
			    $product['product_name'] = apply_filters("shipi_product_name_change", str_replace('"', '', $getproduct->get_name()), $getproduct->get_id());
				$product['price'] = (isset($product_data['total']) && $product_data['total'] > 0) ? round($product_data['total']/$product_data['quantity'], 2): $getproduct->get_price();
				$product['width'] = (!empty($getproduct->get_width())) ? round(wc_get_dimension($getproduct->get_width(),$mod_dim_unit,$woo_dim_unit)) : '';
			    $product['height'] = (!empty($getproduct->get_height())) ? round(wc_get_dimension($getproduct->get_height(),$mod_dim_unit,$woo_dim_unit)) : '';
			    $product['depth'] = (!empty($getproduct->get_length())) ? round(wc_get_dimension($getproduct->get_length(),$mod_dim_unit,$woo_dim_unit)) : '';
				$product['weight'] = (!empty($getproduct->get_weight())) ? (float)round(wc_get_weight($getproduct->get_weight(),$mod_weg_unit,$woo_weg_unit),2) : '';
				// $total_weg += (!empty($product['weight'])) ? $product['weight'] : 0;
			    $pack_products[] = $product;
			}
			return $pack_products;
		}

		public function shipi_get_fedex_packages($package, $general_settings, $orderCurrency, $chk = false)
			{
				if (empty($package)) {
					return;
				}
				$sel_algo = isset($general_settings['hitshippo_fedex_packing_type']) ? $general_settings['hitshippo_fedex_packing_type'] : "per_item";
				switch ($sel_algo) {
					case 'box':
						return $this->box_shipping($package, $general_settings, $orderCurrency, $chk);
						break;
					case 'weight_based':
						return $this->weight_based_shipping($package, $general_settings, $orderCurrency, $chk);
						break;
					case 'per_item':
					default:
						return $this->per_item_shipping($package, $general_settings, $orderCurrency, $chk);
						break;
				}
			}
			private function weight_based_shipping($package, $general_settings, $orderCurrency, $chk = false)
			{
				// echo '<pre>';
				// print_r($package);
				// die();
				if (!class_exists('WeightPack')) {
					include_once 'controllors/classes/weight_pack/class-hit-weight-packing.php';
				}
				$max_weight = isset($general_settings['hitshippo_fedex_max_weight']) && $general_settings['hitshippo_fedex_max_weight'] != ''  ? $general_settings['hitshippo_fedex_max_weight'] : 10;
				$weight_pack = new WeightPack('pack_ascending');
				$weight_pack->set_max_weight($max_weight);

				$package_total_weight = 0;
				$insured_value = 0;

				$ctr = 0;
				foreach ($package as $item_id => $product_data) {
					$ctr++;

					$chk_qty = $product_data['product_quantity'];
					$prod_wt = (isset($product_data['weight']) && !empty($product_data['weight'])) ? $product_data['weight'] : 1;
					$weight_pack->add_item($prod_wt, $product_data, $chk_qty);
				}

				$pack   =   $weight_pack->pack_items();
				$errors =   $pack->get_errors();
				if (!empty($errors)) {
					//do nothing
					return;
				} else {
					$boxes    =   $pack->get_packed_boxes();
					$unpacked_items =   $pack->get_unpacked_items();

					$insured_value        =   0;

					$packages      =   array_merge($boxes, $unpacked_items); // merge items if unpacked are allowed
					$package_count  =   sizeof($packages);
					// get all items to pass if item info in box is not distinguished
					$packable_items =   $weight_pack->get_packable_items();
					$all_items    =   array();
					if (is_array($packable_items)) {
						foreach ($packable_items as $packable_item) {
							$all_items[]    =   $packable_item['data'];
						}
					}
					//pre($packable_items);
					$order_total = '';

					$to_ship  = array();
					$group_id = 1;
					foreach ($packages as $package) {
						$packed_products = array();
						if (isset($package['items'])) {
							foreach ($package['items'] as $key => $value) {
								$insured_value += isset($value['price']) ? $value['price'] : 0;
							}
						}
						$packed_products    =   isset($package['items']) ? $package['items'] : $all_items;
						// Creating package request
						$package_total_weight   = $package['weight'];

						$insurance_array = array(
							'Amount' => $insured_value,
							'Currency' => $orderCurrency
						);

						$group = array(
							'GroupNumber' => $group_id,
							'GroupPackageCount' => 1,
							'Weight' => array(
								'Value' => round($package_total_weight, 3),
								'Units' => (isset($general_settings['hitshippo_fedex_weight_unit']) && $general_settings['hitshippo_fedex_weight_unit'] === 'KG_CM') ? 'KG' : 'LBS'
							),
							'packed_products' => $packed_products,
						);
						$group['InsuredValue'] = $insurance_array;
						$group['packtype'] = 'BOX';

						$to_ship[] = $group;
						$group_id++;
					}
				}
				return $to_ship;
			}
			private function box_shipping($package, $general_settings, $orderCurrency, $chk = false)
			{
				if (!class_exists('HIT_Boxpack')) {
					include_once 'controllors/classes/hit-box-packing.php';
				}
				$boxpack = new HIT_Boxpack();
				$boxes = isset($general_settings['hitshippo_fedex_boxes']) ? $general_settings['hitshippo_fedex_boxes'] : array();
				if (empty($boxes)) {
					return false;
				}
				// $boxes = unserialize($boxes);
				// Define boxes
				foreach ($boxes as $key => $box) {
					if (!$box['enabled']) {
						continue;
					}
					$box['pack_type'] = !empty($box['pack_type']) ? $box['pack_type'] : 'BOX';

					$newbox = $boxpack->add_box($box['length'], $box['width'], $box['height'], $box['box_weight'], $box['pack_type']);

					if (isset($box['id'])) {
						$newbox->set_id(current(explode(':', $box['id'])));
					}

					if ($box['max_weight']) {
						$newbox->set_max_weight($box['max_weight']);
					}

					if ($box['pack_type']) {
						$newbox->set_packtype($box['pack_type']);
					}
				}

				// Add items
				foreach ($package as $item_id => $product_data) {

					if (isset($product_data['weight']) && !empty($product_data['weight'])) {
						$item_weight = round($product_data['weight'] > 0.001 ? $product_data['weight'] : 0.001, 3);
					}

					if (isset($product_data['width']) && isset($product_data['height']) && isset($product_data['depth']) && !empty($product_data['width']) && !empty($product_data['height']) && !empty($product_data['depth'])) {
						$item_dimension = array(
							'Length' => max(1, round($product_data['depth'], 3)),
							'Width' => max(1, round($product_data['width'], 3)),
							'Height' => max(1, round($product_data['height'], 3))
						);
					}

					if (isset($item_weight) && isset($item_dimension)) {

						// $dimensions = array($values['depth'], $values['height'], $values['width']);
						$chk_qty = $product_data['product_quantity'];
						for ($i = 0; $i < $chk_qty; $i++) {
							$boxpack->add_item($item_dimension['Width'], $item_dimension['Height'], $item_dimension['Length'], $item_weight, round($product_data['price']), array(
								'data' => $product_data
							));
						}
					} else {
						//    $this->debug(sprintf(__('Product #%s is missing dimensions. Aborting.', 'wf-shipping-fedex'), $item_id), 'error');
						return;
					}
				}

				// Pack it
				$boxpack->pack();
				$packages = $boxpack->get_packages();
				$to_ship = array();
				$group_id = 1;
				foreach ($packages as $package) {
					if ($package->unpacked === true) {
						//$this->debug('Unpacked Item');
					} else {
						//$this->debug('Packed ' . $package->id);
					}

					$dimensions = array($package->length, $package->width, $package->height);

					sort($dimensions);
					$insurance_array = array(
						'Amount' => round($package->value),
						'Currency' => $orderCurrency
					);


					$group = array(
						'GroupNumber' => $group_id,
						'GroupPackageCount' => 1,
						'Weight' => array(
							'Value' => round($package->weight, 3),
							'Units' => (isset($general_settings['hitshippo_fedex_weight_unit']) && $general_settings['hitshippo_fedex_weight_unit'] === 'KG_CM') ? 'KG' : 'LBS'
						),
						'Dimensions' => array(
							'Length' => max(1, round($dimensions[2], 3)),
							'Width' => max(1, round($dimensions[1], 3)),
							'Height' => max(1, round($dimensions[0], 3)),
							'Units' => (isset($general_settings['hitshippo_fedex_weight_unit']) && $general_settings['hitshippo_fedex_weight_unit'] === 'KG_CM') ? 'CM' : 'IN'
						),
						'InsuredValue' => $insurance_array,
						'packed_products' => array(),
						'package_id' => $package->id,
						'packtype' => 'BOX'
					);

					if (!empty($package->packed) && is_array($package->packed)) {
						foreach ($package->packed as $packed) {
							$group['packed_products'][] = $packed->get_meta('data');
						}
					}

					if (!$package->packed) {
						foreach ($package->unpacked as $unpacked) {
							$group['packed_products'][] = $unpacked->get_meta('data');
						}
					}

					$to_ship[] = $group;

					$group_id++;
				}

				return $to_ship;
			}
			private function per_item_shipping($package, $general_settings, $orderCurrency, $chk = false)
			{
				// echo '<pre>';
				// print_r($package);
				// die();
				$to_ship = array();
				$group_id = 1;

				// Get weight of order
				foreach ($package as $item_id => $product_data) {

					$group = array();
					$insurance_array = array(
						'Amount' => round($product_data['price']),
						'Currency' => $orderCurrency
					);
					$fedex_per_item_weight = 0 ;
					if (isset($product_data['weight']) && !empty($product_data['weight'])) {
						$fedex_per_item_weight = round($product_data['weight'] > 0.001 ? $product_data['weight'] : 0.001, 3);
					}

					$group = array(
						'GroupNumber' => $group_id,
						'GroupPackageCount' => 1,
						'Weight' => array(
							'Value' => $fedex_per_item_weight,
							'Units' => (isset($general_settings['hitshippo_fedex_weight_unit']) && $general_settings['hitshippo_fedex_weight_unit'] == 'KG_CM') ? 'KG' : 'LBS'
						),
						'packed_products' => $product_data
					);

					if (isset($product_data['width']) && isset($product_data['height']) && isset($product_data['depth']) && !empty($product_data['width']) && !empty($product_data['height']) && !empty($product_data['depth'])) {

						$group['Dimensions'] = array(
							'Length' => max(1, round($product_data['depth'], 3)),
							'Width' => max(1, round($product_data['width'], 3)),
							'Height' => max(1, round($product_data['height'], 3)),
							'Units' => (isset($general_settings['hitshippo_fedex_weight_unit']) && $general_settings['hitshippo_fedex_weight_unit'] == 'KG_CM') ? 'CM' : 'IN'
						);
					}

					$group['packtype'] = 'BOX';

					$group['InsuredValue'] = $insurance_array;

					$chk_qty = $product_data['product_quantity'];

					for ($i = 0; $i < $chk_qty; $i++)
						$to_ship[] = $group;

					$group_id++;
				}

				return $to_ship;
			}
			public function a2z_fedex_is_eu_country ($countrycode, $destinationcode) {
				$eu_countrycodes = array(
					'AT', 'BE', 'BG', 'CY', 'CZ', 'DE', 'DK', 'EE', 
					'ES', 'FI', 'FR', 'HU', 'IE', 'IT', 'LT', 'LU', 'LV',
					'MT', 'NL', 'PL', 'PT', 'RO', 'SE', 'SI', 'SK',
					'HR', 'GR'

				);
				return(in_array($countrycode, $eu_countrycodes) && in_array($destinationcode, $eu_countrycodes));
			}
		private function get_vendors_on_order($general_settings = [], $pack_products = [], $multi_ven = '', $order_data = [], $service_code = '')
		{
			$custom_settings = array();
			$custom_settings['default'] = array(
												'hitshippo_fedex_site_id' => $general_settings['hitshippo_fedex_site_id'],
												'hitshippo_fedex_site_pwd' => $general_settings['hitshippo_fedex_site_pwd'],
												'hitshippo_fedex_acc_no' => $general_settings['hitshippo_fedex_acc_no'],
												'hitshippo_fedex_access_key' => $general_settings['hitshippo_fedex_access_key'],
												'hitshippo_fedex_rest_grant_type' => isset($general_settings['hitshippo_fedex_rest_grant_type']) ? $general_settings['hitshippo_fedex_rest_grant_type'] : "",
												'hitshippo_fedex_rest_acc_no' => isset($general_settings['hitshippo_fedex_rest_acc_no']) ? $general_settings['hitshippo_fedex_rest_acc_no'] : "",
												'hitshippo_fedex_rest_api_key' => isset($general_settings['hitshippo_fedex_rest_api_key']) ? $general_settings['hitshippo_fedex_rest_api_key'] : "",
												'hitshippo_fedex_rest_secret_key' => isset($general_settings['hitshippo_fedex_rest_secret_key']) ? $general_settings['hitshippo_fedex_rest_secret_key'] : "",
												'hitshippo_fedex_shipper_name' => $general_settings['hitshippo_fedex_shipper_name'],
												'hitshippo_fedex_company' => $general_settings['hitshippo_fedex_company'],
												'hitshippo_fedex_mob_num' => $general_settings['hitshippo_fedex_mob_num'],
												'hitshippo_fedex_email' => $general_settings['hitshippo_fedex_email'],
												'hitshippo_fedex_address1' => $general_settings['hitshippo_fedex_address1'],
												'hitshippo_fedex_address2' => $general_settings['hitshippo_fedex_address2'],
												'hitshippo_fedex_city' => $general_settings['hitshippo_fedex_city'],
												'hitshippo_fedex_state' => $general_settings['hitshippo_fedex_state'],
												'hitshippo_fedex_zip' => $general_settings['hitshippo_fedex_zip'],
												'hitshippo_fedex_country' => $general_settings['hitshippo_fedex_country'],
												'hitshippo_fedex_con_rate' => isset($general_settings['hitshippo_fedex_con_rate']) ? $general_settings['hitshippo_fedex_con_rate'] : '',
												'service_code' => $service_code,
												'hitshippo_fedex_shippo_mail' => $general_settings['hitshippo_fedex_shippo_mail'],
												'hitshippo_fedex_currency' => $general_settings['hitshippo_fedex_currency']
											);
			$vendor_settings = array();

			if(isset($general_settings['hitshippo_fedex_v_enable']) && $general_settings['hitshippo_fedex_v_enable'] == 'yes' && isset($general_settings['hitshippo_fedex_v_labels']) && $general_settings['hitshippo_fedex_v_labels'] == 'yes'){
				// Multi Vendor Enabled
				foreach ($pack_products as $key => $value) {

					$product_id = $value['product_id'];
					if ($this->hpos_enabled) {
						$hpos_prod_data = wc_get_product($product_id);
						$fedex_account = $hpos_prod_data->get_meta("fedex_address");
					} else {
						$fedex_account = get_post_meta($product_id,'fedex_address', true);
					}
					if(empty($fedex_account) || $fedex_account == 'default'){
						$fedex_account = 'default';
						if (!isset($vendor_settings[$fedex_account])) {
							$vendor_settings[$fedex_account] = $custom_settings['default'];
						}
						$vendor_settings[$fedex_account]['products'][] = $value;
					}

					if($fedex_account != 'default'){
						$user_account = get_post_meta($fedex_account,'hitshippo_fedex_vendor_settings', true);
						$user_account = empty($user_account) ? array() : $user_account;
						if(!empty($user_account)){
							if(!isset($vendor_settings[$fedex_account])){

								$vendor_settings[$fedex_account] = $custom_settings['default'];

								if($user_account['hitshippo_fedex_site_id'] != '' && $user_account['hitshippo_fedex_site_pwd'] != '' && $user_account['hitshippo_fedex_acc_no'] != '' && $user_account['hitshippo_fedex_access_key'] != ''){
									$vendor_settings[$fedex_account]['hitshippo_fedex_site_id'] = $user_account['hitshippo_fedex_site_id'];
									$vendor_settings[$fedex_account]['hitshippo_fedex_site_pwd'] = $user_account['hitshippo_fedex_site_pwd'];
									$vendor_settings[$fedex_account]['hitshippo_fedex_acc_no'] = $user_account['hitshippo_fedex_acc_no'];
									$vendor_settings[$fedex_account]['hitshippo_fedex_access_key'] = $user_account['hitshippo_fedex_access_key'];
								}
								if (isset($user_account['hitshippo_fedex_rest_grant_type']) && isset($user_account['hitshippo_fedex_rest_acc_no']) && isset($user_account['hitshippo_fedex_rest_api_key']) && isset($user_account['hitshippo_fedex_rest_secret_key'])) {
									if ($user_account['hitshippo_fedex_rest_grant_type'] != '' && $user_account['hitshippo_fedex_rest_acc_no'] != '' && $user_account['hitshippo_fedex_rest_api_key'] != '' && $user_account['hitshippo_fedex_rest_secret_key'] != '') {
										$vendor_settings[$fedex_account]['hitshippo_fedex_rest_grant_type'] = $user_account['hitshippo_fedex_rest_grant_type'];
										$vendor_settings[$fedex_account]['hitshippo_fedex_rest_acc_no'] = $user_account['hitshippo_fedex_rest_acc_no'];
										$vendor_settings[$fedex_account]['hitshippo_fedex_rest_api_key'] = $user_account['hitshippo_fedex_rest_api_key'];
										$vendor_settings[$fedex_account]['hitshippo_fedex_rest_secret_key'] = $user_account['hitshippo_fedex_rest_secret_key'];
									}
								}

								if ($user_account['hitshippo_fedex_shipper_name'] != '' && $user_account['hitshippo_fedex_address1'] != '' && $user_account['hitshippo_fedex_city'] != '' && $user_account['hitshippo_fedex_state'] != '' && $user_account['hitshippo_fedex_zip'] != '' && $user_account['hitshippo_fedex_country'] != ''){

									if($user_account['hitshippo_fedex_shipper_name'] != ''){
										$vendor_settings[$fedex_account]['hitshippo_fedex_shipper_name'] = $user_account['hitshippo_fedex_shipper_name'];
									}

									if($user_account['hitshippo_fedex_company'] != ''){
										$vendor_settings[$fedex_account]['hitshippo_fedex_company'] = $user_account['hitshippo_fedex_company'];
									}

									if($user_account['hitshippo_fedex_mob_num'] != ''){
										$vendor_settings[$fedex_account]['hitshippo_fedex_mob_num'] = $user_account['hitshippo_fedex_mob_num'];
									}

									if($user_account['hitshippo_fedex_email'] != ''){
										$vendor_settings[$fedex_account]['hitshippo_fedex_email'] = $user_account['hitshippo_fedex_email'];
									}

									if($user_account['hitshippo_fedex_address1'] != ''){
										$vendor_settings[$fedex_account]['hitshippo_fedex_address1'] = $user_account['hitshippo_fedex_address1'];
									}

									$vendor_settings[$fedex_account]['hitshippo_fedex_address2'] = !empty($user_account['hitshippo_fedex_address2']) ? $user_account['hitshippo_fedex_address2'] : '';

									if($user_account['hitshippo_fedex_city'] != ''){
										$vendor_settings[$fedex_account]['hitshippo_fedex_city'] = $user_account['hitshippo_fedex_city'];
									}

									if($user_account['hitshippo_fedex_state'] != ''){
										$vendor_settings[$fedex_account]['hitshippo_fedex_state'] = $user_account['hitshippo_fedex_state'];
									}

									if($user_account['hitshippo_fedex_zip'] != ''){
										$vendor_settings[$fedex_account]['hitshippo_fedex_zip'] = $user_account['hitshippo_fedex_zip'];
									}

									if($user_account['hitshippo_fedex_country'] != ''){
										$vendor_settings[$fedex_account]['hitshippo_fedex_country'] = $user_account['hitshippo_fedex_country'];
									}
									if (isset($user_account['hitshippo_fedex_con_rate'])) {
										$vendor_settings[$fedex_account]['hitshippo_fedex_con_rate'] = $user_account['hitshippo_fedex_con_rate'];
									}
									if (isset($user_account['hitshippo_fedex_currency'])) {
										$vendor_settings[$fedex_account]['hitshippo_fedex_currency'] = $user_account['hitshippo_fedex_currency'];
									}
								}

								if(isset($general_settings['hitshippo_fedex_v_email']) && $general_settings['hitshippo_fedex_v_email'] == 'yes'){
									$user_dat = get_userdata($fedex_account);
									$vendor_settings[$fedex_account]['hitshippo_fedex_shippo_mail'] = $user_dat->data->user_email;
								}


								if (empty($service_code)) {
									if($multi_ven !=''){
										$array_ven = explode('|',$multi_ven);
										$scode = '';
										foreach ($array_ven as $key => $svalue) {
											$ex_service = explode("_", $svalue);
											if($ex_service[0] == $fedex_account){
												$vendor_settings[$fedex_account]['service_code'] = $ex_service[1];
											}
										}
										if($scode == ''){
											if($order_data['shipping']['country'] != $vendor_settings[$fedex_account]['hitshippo_fedex_country']){
												$vendor_settings[$fedex_account]['service_code'] = $user_account['hitshippo_fedex_def_inter'];
											}else{
												$vendor_settings[$fedex_account]['service_code'] = $user_account['hitshippo_fedex_def_dom'];
											}
										}
									}else{
										if($order_data['shipping']['country'] != $vendor_settings[$fedex_account]['hitshippo_fedex_country']){
											$vendor_settings[$fedex_account]['service_code'] = $user_account['hitshippo_fedex_def_inter'];
										}else{
											$vendor_settings[$fedex_account]['service_code'] = $user_account['hitshippo_fedex_def_dom'];
										}
									}
								} else {
									$vendor_settings[$fedex_account]['service_code'] = $service_code;
								}
							}
							// unset($value['product_id']);
							$vendor_settings[$fedex_account]['products'][] = $value;
						}else {
							$fedex_account = 'default';
							if (!isset($vendor_settings[$fedex_account])) {
								$vendor_settings[$fedex_account] = $custom_settings['default'];
							}
							$vendor_settings[$fedex_account]['products'][] = $value;
						}
					}
				}
			}

			if(empty($vendor_settings)){
				$custom_settings['default']['products'] = $pack_products;
			}else{
				$custom_settings = $vendor_settings;
			}
			return $custom_settings;
		}
	}
	new hitshippo_fedex_parent();
}
}
