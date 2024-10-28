<?php
if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly.
}
if (!class_exists('hitshippo_fedex')) {
	class hitshippo_fedex extends WC_Shipping_Method
	{
		/**
		 * Constructor for your shipping class
		 *
		 * @access public
		 * @return void
		 */
		public $hpos_enabled = false;
		public function __construct($instance_id = 0)
		{
			$this->id                 = 'hitshippo_fedex';
			$this->method_title       = __('FedEx');  // Title shown in admin
			$this->title       = __('Fedex');
			$this->method_description = __('Fedex shipping method for calculating shipping rates.');
			$this->instance_id        = absint($instance_id);
			$this->enabled            = "yes"; // This can be added as an setting but for this example its forced enabled
			$this->supports           = array(
				'shipping-zones',
				'instance-settings',
				'instance-settings-modal',
			);
			$this->init();
			if (get_option("woocommerce_custom_orders_table_enabled") === "yes") {
				$this->hpos_enabled = true;
			}
		}

		/**
		 * Init your settings
		 *
		 * @access public
		 * @return void
		 */
		function init()
		{
			// Load the settings API
			$this->init_form_fields(); // This is part of the settings API. Override the method to add your own settings
			$this->init_settings(); // This is part of the settings API. Loads settings you previously init.
			$this->title      = $this->get_option('title');

			// Save settings in admin if you have any defined
			add_action('woocommerce_update_options_shipping_' . $this->id, array($this, 'process_admin_options'));
		}

		/**
		 * calculate_shipping function.
		 *
		 * @access public
		 * @param mixed $package
		 * @return void
		 */
		public function calculate_shipping($package = array())
		{
			$pack_aft_hook = apply_filters('hitshippo_fedex_rate_packages', $package);

		
			if (empty($pack_aft_hook)) {
				return;
			}

			$execution_status = get_option('hitshipo_fedex_working_status');
			if (!empty($execution_status)) {
				if ($execution_status == 'stop_working') {
					return;
				}
			}


			$fedex_core = array();
			$_carriers = array(
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

			$fedex_core['AD'] = array('currency' => 'EUR', 'weight' => 'KG_CM');
			$fedex_core['AE'] = array('currency' => 'AED', 'weight' => 'KG_CM');
			$fedex_core['AF'] = array('currency' => 'AFN', 'weight' => 'KG_CM');
			$fedex_core['AG'] = array('currency' => 'XCD', 'weight' => 'LB_IN');
			$fedex_core['AI'] = array('currency' => 'XCD', 'weight' => 'LB_IN');
			$fedex_core['AL'] = array('currency' => 'EUR', 'weight' => 'KG_CM');
			$fedex_core['AM'] = array('currency' => 'AMD', 'weight' => 'KG_CM');
			$fedex_core['AN'] = array('currency' => 'ANG', 'weight' => 'KG_CM');
			$fedex_core['AO'] = array('currency' => 'AOA', 'weight' => 'KG_CM');
			$fedex_core['AR'] = array('currency' => 'ARS', 'weight' => 'KG_CM');
			$fedex_core['AS'] = array('currency' => 'USD', 'weight' => 'LB_IN');
			$fedex_core['AT'] = array('currency' => 'EUR', 'weight' => 'KG_CM');
			$fedex_core['AU'] = array('currency' => 'AUD', 'weight' => 'KG_CM');
			$fedex_core['AW'] = array('currency' => 'AWG', 'weight' => 'LB_IN');
			$fedex_core['AZ'] = array('currency' => 'AZN', 'weight' => 'KG_CM');
			$fedex_core['AZ'] = array('currency' => 'AZN', 'weight' => 'KG_CM');
			$fedex_core['GB'] = array('currency' => 'GBP', 'weight' => 'KG_CM');
			$fedex_core['BA'] = array('currency' => 'BAM', 'weight' => 'KG_CM');
			$fedex_core['BB'] = array('currency' => 'BBD', 'weight' => 'LB_IN');
			$fedex_core['BD'] = array('currency' => 'BDT', 'weight' => 'KG_CM');
			$fedex_core['BE'] = array('currency' => 'EUR', 'weight' => 'KG_CM');
			$fedex_core['BF'] = array('currency' => 'XOF', 'weight' => 'KG_CM');
			$fedex_core['BG'] = array('currency' => 'BGN', 'weight' => 'KG_CM');
			$fedex_core['BH'] = array('currency' => 'BHD', 'weight' => 'KG_CM');
			$fedex_core['BI'] = array('currency' => 'BIF', 'weight' => 'KG_CM');
			$fedex_core['BJ'] = array('currency' => 'XOF', 'weight' => 'KG_CM');
			$fedex_core['BM'] = array('currency' => 'BMD', 'weight' => 'LB_IN');
			$fedex_core['BN'] = array('currency' => 'BND', 'weight' => 'KG_CM');
			$fedex_core['BO'] = array('currency' => 'BOB', 'weight' => 'KG_CM');
			$fedex_core['BR'] = array('currency' => 'BRL', 'weight' => 'KG_CM');
			$fedex_core['BS'] = array('currency' => 'BSD', 'weight' => 'LB_IN');
			$fedex_core['BT'] = array('currency' => 'BTN', 'weight' => 'KG_CM');
			$fedex_core['BW'] = array('currency' => 'BWP', 'weight' => 'KG_CM');
			$fedex_core['BY'] = array('currency' => 'BYR', 'weight' => 'KG_CM');
			$fedex_core['BZ'] = array('currency' => 'BZD', 'weight' => 'KG_CM');
			$fedex_core['CA'] = array('currency' => 'CAD', 'weight' => 'LB_IN');
			$fedex_core['CF'] = array('currency' => 'XAF', 'weight' => 'KG_CM');
			$fedex_core['CG'] = array('currency' => 'XAF', 'weight' => 'KG_CM');
			$fedex_core['CH'] = array('currency' => 'CHF', 'weight' => 'KG_CM');
			$fedex_core['CI'] = array('currency' => 'XOF', 'weight' => 'KG_CM');
			$fedex_core['CK'] = array('currency' => 'NZD', 'weight' => 'KG_CM');
			$fedex_core['CL'] = array('currency' => 'CLP', 'weight' => 'KG_CM');
			$fedex_core['CM'] = array('currency' => 'XAF', 'weight' => 'KG_CM');
			$fedex_core['CN'] = array('currency' => 'CNY', 'weight' => 'KG_CM');
			$fedex_core['CO'] = array('currency' => 'COP', 'weight' => 'KG_CM');
			$fedex_core['CR'] = array('currency' => 'CRC', 'weight' => 'KG_CM');
			$fedex_core['CU'] = array('currency' => 'CUC', 'weight' => 'KG_CM');
			$fedex_core['CV'] = array('currency' => 'CVE', 'weight' => 'KG_CM');
			$fedex_core['CY'] = array('currency' => 'EUR', 'weight' => 'KG_CM');
			$fedex_core['CZ'] = array('currency' => 'CZF', 'weight' => 'KG_CM');
			$fedex_core['DE'] = array('currency' => 'EUR', 'weight' => 'KG_CM');
			$fedex_core['DJ'] = array('currency' => 'DJF', 'weight' => 'KG_CM');
			$fedex_core['DK'] = array('currency' => 'DKK', 'weight' => 'KG_CM');
			$fedex_core['DM'] = array('currency' => 'XCD', 'weight' => 'LB_IN');
			$fedex_core['DO'] = array('currency' => 'DOP', 'weight' => 'LB_IN');
			$fedex_core['DZ'] = array('currency' => 'DZD', 'weight' => 'KG_CM');
			$fedex_core['EC'] = array('currency' => 'USD', 'weight' => 'KG_CM');
			$fedex_core['EE'] = array('currency' => 'EUR', 'weight' => 'KG_CM');
			$fedex_core['EG'] = array('currency' => 'EGP', 'weight' => 'KG_CM');
			$fedex_core['ER'] = array('currency' => 'ERN', 'weight' => 'KG_CM');
			$fedex_core['ES'] = array('currency' => 'EUR', 'weight' => 'KG_CM');
			$fedex_core['ET'] = array('currency' => 'ETB', 'weight' => 'KG_CM');
			$fedex_core['FI'] = array('currency' => 'EUR', 'weight' => 'KG_CM');
			$fedex_core['FJ'] = array('currency' => 'FJD', 'weight' => 'KG_CM');
			$fedex_core['FK'] = array('currency' => 'GBP', 'weight' => 'KG_CM');
			$fedex_core['FM'] = array('currency' => 'USD', 'weight' => 'LB_IN');
			$fedex_core['FO'] = array('currency' => 'DKK', 'weight' => 'KG_CM');
			$fedex_core['FR'] = array('currency' => 'EUR', 'weight' => 'KG_CM');
			$fedex_core['GA'] = array('currency' => 'XAF', 'weight' => 'KG_CM');
			$fedex_core['GB'] = array('currency' => 'GBP', 'weight' => 'KG_CM');
			$fedex_core['GD'] = array('currency' => 'XCD', 'weight' => 'LB_IN');
			$fedex_core['GE'] = array('currency' => 'GEL', 'weight' => 'KG_CM');
			$fedex_core['GF'] = array('currency' => 'EUR', 'weight' => 'KG_CM');
			$fedex_core['GG'] = array('currency' => 'GBP', 'weight' => 'KG_CM');
			$fedex_core['GH'] = array('currency' => 'GBS', 'weight' => 'KG_CM');
			$fedex_core['GI'] = array('currency' => 'GBP', 'weight' => 'KG_CM');
			$fedex_core['GL'] = array('currency' => 'DKK', 'weight' => 'KG_CM');
			$fedex_core['GM'] = array('currency' => 'GMD', 'weight' => 'KG_CM');
			$fedex_core['GN'] = array('currency' => 'GNF', 'weight' => 'KG_CM');
			$fedex_core['GP'] = array('currency' => 'EUR', 'weight' => 'KG_CM');
			$fedex_core['GQ'] = array('currency' => 'XAF', 'weight' => 'KG_CM');
			$fedex_core['GR'] = array('currency' => 'EUR', 'weight' => 'KG_CM');
			$fedex_core['GT'] = array('currency' => 'GTQ', 'weight' => 'KG_CM');
			$fedex_core['GU'] = array('currency' => 'USD', 'weight' => 'LB_IN');
			$fedex_core['GW'] = array('currency' => 'XOF', 'weight' => 'KG_CM');
			$fedex_core['GY'] = array('currency' => 'GYD', 'weight' => 'LB_IN');
			$fedex_core['HK'] = array('currency' => 'HKD', 'weight' => 'KG_CM');
			$fedex_core['HN'] = array('currency' => 'HNL', 'weight' => 'KG_CM');
			$fedex_core['HR'] = array('currency' => 'HRK', 'weight' => 'KG_CM');
			$fedex_core['HT'] = array('currency' => 'HTG', 'weight' => 'LB_IN');
			$fedex_core['HU'] = array('currency' => 'HUF', 'weight' => 'KG_CM');
			$fedex_core['IC'] = array('currency' => 'EUR', 'weight' => 'KG_CM');
			$fedex_core['ID'] = array('currency' => 'IDR', 'weight' => 'KG_CM');
			$fedex_core['IE'] = array('currency' => 'EUR', 'weight' => 'KG_CM');
			$fedex_core['IL'] = array('currency' => 'ILS', 'weight' => 'KG_CM');
			$fedex_core['IN'] = array('currency' => 'INR', 'weight' => 'KG_CM');
			$fedex_core['IQ'] = array('currency' => 'IQD', 'weight' => 'KG_CM');
			$fedex_core['IR'] = array('currency' => 'IRR', 'weight' => 'KG_CM');
			$fedex_core['IS'] = array('currency' => 'ISK', 'weight' => 'KG_CM');
			$fedex_core['IT'] = array('currency' => 'EUR', 'weight' => 'KG_CM');
			$fedex_core['JE'] = array('currency' => 'GBP', 'weight' => 'KG_CM');
			$fedex_core['JM'] = array('currency' => 'JMD', 'weight' => 'KG_CM');
			$fedex_core['JO'] = array('currency' => 'JOD', 'weight' => 'KG_CM');
			$fedex_core['JP'] = array('currency' => 'JPY', 'weight' => 'KG_CM');
			$fedex_core['KE'] = array('currency' => 'KES', 'weight' => 'KG_CM');
			$fedex_core['KG'] = array('currency' => 'KGS', 'weight' => 'KG_CM');
			$fedex_core['KH'] = array('currency' => 'KHR', 'weight' => 'KG_CM');
			$fedex_core['KI'] = array('currency' => 'AUD', 'weight' => 'KG_CM');
			$fedex_core['KM'] = array('currency' => 'KMF', 'weight' => 'KG_CM');
			$fedex_core['KN'] = array('currency' => 'XCD', 'weight' => 'LB_IN');
			$fedex_core['KP'] = array('currency' => 'KPW', 'weight' => 'LB_IN');
			$fedex_core['KR'] = array('currency' => 'KRW', 'weight' => 'KG_CM');
			$fedex_core['KV'] = array('currency' => 'EUR', 'weight' => 'KG_CM');
			$fedex_core['KW'] = array('currency' => 'KWD', 'weight' => 'KG_CM');
			$fedex_core['KY'] = array('currency' => 'KYD', 'weight' => 'KG_CM');
			$fedex_core['KZ'] = array('currency' => 'KZF', 'weight' => 'LB_IN');
			$fedex_core['LA'] = array('currency' => 'LAK', 'weight' => 'KG_CM');
			$fedex_core['LB'] = array('currency' => 'USD', 'weight' => 'KG_CM');
			$fedex_core['LC'] = array('currency' => 'XCD', 'weight' => 'KG_CM');
			$fedex_core['LI'] = array('currency' => 'CHF', 'weight' => 'LB_IN');
			$fedex_core['LK'] = array('currency' => 'LKR', 'weight' => 'KG_CM');
			$fedex_core['LR'] = array('currency' => 'LRD', 'weight' => 'KG_CM');
			$fedex_core['LS'] = array('currency' => 'LSL', 'weight' => 'KG_CM');
			$fedex_core['LT'] = array('currency' => 'EUR', 'weight' => 'KG_CM');
			$fedex_core['LU'] = array('currency' => 'EUR', 'weight' => 'KG_CM');
			$fedex_core['LV'] = array('currency' => 'EUR', 'weight' => 'KG_CM');
			$fedex_core['LY'] = array('currency' => 'LYD', 'weight' => 'KG_CM');
			$fedex_core['MA'] = array('currency' => 'MAD', 'weight' => 'KG_CM');
			$fedex_core['MC'] = array('currency' => 'EUR', 'weight' => 'KG_CM');
			$fedex_core['MD'] = array('currency' => 'MDL', 'weight' => 'KG_CM');
			$fedex_core['ME'] = array('currency' => 'EUR', 'weight' => 'KG_CM');
			$fedex_core['MG'] = array('currency' => 'MGA', 'weight' => 'KG_CM');
			$fedex_core['MH'] = array('currency' => 'USD', 'weight' => 'LB_IN');
			$fedex_core['MK'] = array('currency' => 'MKD', 'weight' => 'KG_CM');
			$fedex_core['ML'] = array('currency' => 'COF', 'weight' => 'KG_CM');
			$fedex_core['MM'] = array('currency' => 'USD', 'weight' => 'KG_CM');
			$fedex_core['MN'] = array('currency' => 'MNT', 'weight' => 'KG_CM');
			$fedex_core['MO'] = array('currency' => 'MOP', 'weight' => 'KG_CM');
			$fedex_core['MP'] = array('currency' => 'USD', 'weight' => 'LB_IN');
			$fedex_core['MQ'] = array('currency' => 'EUR', 'weight' => 'KG_CM');
			$fedex_core['MR'] = array('currency' => 'MRO', 'weight' => 'KG_CM');
			$fedex_core['MS'] = array('currency' => 'XCD', 'weight' => 'LB_IN');
			$fedex_core['MT'] = array('currency' => 'EUR', 'weight' => 'KG_CM');
			$fedex_core['MU'] = array('currency' => 'MUR', 'weight' => 'KG_CM');
			$fedex_core['MV'] = array('currency' => 'MVR', 'weight' => 'KG_CM');
			$fedex_core['MW'] = array('currency' => 'MWK', 'weight' => 'KG_CM');
			$fedex_core['MX'] = array('currency' => 'MXN', 'weight' => 'KG_CM');
			$fedex_core['MY'] = array('currency' => 'MYR', 'weight' => 'KG_CM');
			$fedex_core['MZ'] = array('currency' => 'MZN', 'weight' => 'KG_CM');
			$fedex_core['NA'] = array('currency' => 'NAD', 'weight' => 'KG_CM');
			$fedex_core['NC'] = array('currency' => 'XPF', 'weight' => 'KG_CM');
			$fedex_core['NE'] = array('currency' => 'XOF', 'weight' => 'KG_CM');
			$fedex_core['NG'] = array('currency' => 'NGN', 'weight' => 'KG_CM');
			$fedex_core['NI'] = array('currency' => 'NIO', 'weight' => 'KG_CM');
			$fedex_core['NL'] = array('currency' => 'EUR', 'weight' => 'KG_CM');
			$fedex_core['NO'] = array('currency' => 'NOK', 'weight' => 'KG_CM');
			$fedex_core['NP'] = array('currency' => 'NPR', 'weight' => 'KG_CM');
			$fedex_core['NR'] = array('currency' => 'AUD', 'weight' => 'KG_CM');
			$fedex_core['NU'] = array('currency' => 'NZD', 'weight' => 'KG_CM');
			$fedex_core['NZ'] = array('currency' => 'NZD', 'weight' => 'KG_CM');
			$fedex_core['OM'] = array('currency' => 'OMR', 'weight' => 'KG_CM');
			$fedex_core['PA'] = array('currency' => 'USD', 'weight' => 'KG_CM');
			$fedex_core['PE'] = array('currency' => 'PEN', 'weight' => 'KG_CM');
			$fedex_core['PF'] = array('currency' => 'XPF', 'weight' => 'KG_CM');
			$fedex_core['PG'] = array('currency' => 'PGK', 'weight' => 'KG_CM');
			$fedex_core['PH'] = array('currency' => 'PHP', 'weight' => 'KG_CM');
			$fedex_core['PK'] = array('currency' => 'PKR', 'weight' => 'KG_CM');
			$fedex_core['PL'] = array('currency' => 'PLN', 'weight' => 'KG_CM');
			$fedex_core['PR'] = array('currency' => 'USD', 'weight' => 'LB_IN');
			$fedex_core['PT'] = array('currency' => 'EUR', 'weight' => 'KG_CM');
			$fedex_core['PW'] = array('currency' => 'USD', 'weight' => 'KG_CM');
			$fedex_core['PY'] = array('currency' => 'PYG', 'weight' => 'KG_CM');
			$fedex_core['QA'] = array('currency' => 'QAR', 'weight' => 'KG_CM');
			$fedex_core['RE'] = array('currency' => 'EUR', 'weight' => 'KG_CM');
			$fedex_core['RO'] = array('currency' => 'RON', 'weight' => 'KG_CM');
			$fedex_core['RS'] = array('currency' => 'RSD', 'weight' => 'KG_CM');
			$fedex_core['RU'] = array('currency' => 'RUB', 'weight' => 'KG_CM');
			$fedex_core['RW'] = array('currency' => 'RWF', 'weight' => 'KG_CM');
			$fedex_core['SA'] = array('currency' => 'SAR', 'weight' => 'KG_CM');
			$fedex_core['SB'] = array('currency' => 'SBD', 'weight' => 'KG_CM');
			$fedex_core['SC'] = array('currency' => 'SCR', 'weight' => 'KG_CM');
			$fedex_core['SD'] = array('currency' => 'SDG', 'weight' => 'KG_CM');
			$fedex_core['SE'] = array('currency' => 'SEK', 'weight' => 'KG_CM');
			$fedex_core['SG'] = array('currency' => 'SGD', 'weight' => 'KG_CM');
			$fedex_core['SH'] = array('currency' => 'SHP', 'weight' => 'KG_CM');
			$fedex_core['SI'] = array('currency' => 'EUR', 'weight' => 'KG_CM');
			$fedex_core['SK'] = array('currency' => 'EUR', 'weight' => 'KG_CM');
			$fedex_core['SL'] = array('currency' => 'SLL', 'weight' => 'KG_CM');
			$fedex_core['SM'] = array('currency' => 'EUR', 'weight' => 'KG_CM');
			$fedex_core['SN'] = array('currency' => 'XOF', 'weight' => 'KG_CM');
			$fedex_core['SO'] = array('currency' => 'SOS', 'weight' => 'KG_CM');
			$fedex_core['SR'] = array('currency' => 'SRD', 'weight' => 'KG_CM');
			$fedex_core['SS'] = array('currency' => 'SSP', 'weight' => 'KG_CM');
			$fedex_core['ST'] = array('currency' => 'STD', 'weight' => 'KG_CM');
			$fedex_core['SV'] = array('currency' => 'USD', 'weight' => 'KG_CM');
			$fedex_core['SY'] = array('currency' => 'SYP', 'weight' => 'KG_CM');
			$fedex_core['SZ'] = array('currency' => 'SZL', 'weight' => 'KG_CM');
			$fedex_core['TC'] = array('currency' => 'USD', 'weight' => 'LB_IN');
			$fedex_core['TD'] = array('currency' => 'XAF', 'weight' => 'KG_CM');
			$fedex_core['TG'] = array('currency' => 'XOF', 'weight' => 'KG_CM');
			$fedex_core['TH'] = array('currency' => 'THB', 'weight' => 'KG_CM');
			$fedex_core['TJ'] = array('currency' => 'TJS', 'weight' => 'KG_CM');
			$fedex_core['TL'] = array('currency' => 'USD', 'weight' => 'KG_CM');
			$fedex_core['TN'] = array('currency' => 'TND', 'weight' => 'KG_CM');
			$fedex_core['TO'] = array('currency' => 'TOP', 'weight' => 'KG_CM');
			$fedex_core['TR'] = array('currency' => 'TRY', 'weight' => 'KG_CM');
			$fedex_core['TT'] = array('currency' => 'TTD', 'weight' => 'LB_IN');
			$fedex_core['TV'] = array('currency' => 'AUD', 'weight' => 'KG_CM');
			$fedex_core['TW'] = array('currency' => 'TWD', 'weight' => 'KG_CM');
			$fedex_core['TZ'] = array('currency' => 'TZS', 'weight' => 'KG_CM');
			$fedex_core['UA'] = array('currency' => 'UAH', 'weight' => 'KG_CM');
			$fedex_core['UG'] = array('currency' => 'USD', 'weight' => 'KG_CM');
			$fedex_core['US'] = array('currency' => 'USD', 'weight' => 'LB_IN');
			$fedex_core['UY'] = array('currency' => 'UYU', 'weight' => 'KG_CM');
			$fedex_core['UZ'] = array('currency' => 'UZS', 'weight' => 'KG_CM');
			$fedex_core['VC'] = array('currency' => 'XCD', 'weight' => 'LB_IN');
			$fedex_core['VE'] = array('currency' => 'VEF', 'weight' => 'KG_CM');
			$fedex_core['VG'] = array('currency' => 'USD', 'weight' => 'LB_IN');
			$fedex_core['VI'] = array('currency' => 'USD', 'weight' => 'LB_IN');
			$fedex_core['VN'] = array('currency' => 'VND', 'weight' => 'KG_CM');
			$fedex_core['VU'] = array('currency' => 'VUV', 'weight' => 'KG_CM');
			$fedex_core['WS'] = array('currency' => 'WST', 'weight' => 'KG_CM');
			$fedex_core['XB'] = array('currency' => 'EUR', 'weight' => 'LB_IN');
			$fedex_core['XC'] = array('currency' => 'EUR', 'weight' => 'LB_IN');
			$fedex_core['XE'] = array('currency' => 'ANG', 'weight' => 'LB_IN');
			$fedex_core['XM'] = array('currency' => 'EUR', 'weight' => 'LB_IN');
			$fedex_core['XN'] = array('currency' => 'XCD', 'weight' => 'LB_IN');
			$fedex_core['XS'] = array('currency' => 'SIS', 'weight' => 'KG_CM');
			$fedex_core['XY'] = array('currency' => 'ANG', 'weight' => 'LB_IN');
			$fedex_core['YE'] = array('currency' => 'YER', 'weight' => 'KG_CM');
			$fedex_core['YT'] = array('currency' => 'EUR', 'weight' => 'KG_CM');
			$fedex_core['ZA'] = array('currency' => 'ZAR', 'weight' => 'KG_CM');
			$fedex_core['ZM'] = array('currency' => 'ZMW', 'weight' => 'KG_CM');
			$fedex_core['ZW'] = array('currency' => 'USD', 'weight' => 'KG_CM');
			$general_settings = get_option('hitshippo_fedex_main_settings');

			if (empty($general_settings)) {
				return false;
			}

			$general_settings = empty($general_settings) ? array() : $general_settings;

			//excluded Countries

			if (isset($general_settings['hitshippo_fedex_exclude_countries'])) {

				if (in_array($pack_aft_hook['destination']['country'], $general_settings['hitshippo_fedex_exclude_countries'])) {
					return;
				}
			}

			$custom_settings = array();
			$custom_settings['default'] = array(
				'hitshippo_fedex_site_id' => $general_settings['hitshippo_fedex_site_id'],
				'hitshippo_fedex_site_pwd' => $general_settings['hitshippo_fedex_site_pwd'],
				'hitshippo_fedex_acc_no' => $general_settings['hitshippo_fedex_acc_no'],
				'hitshippo_fedex_access_key' => $general_settings['hitshippo_fedex_access_key'],
				'hitshippo_fedex_rest_grant_type' => $general_settings['hitshippo_fedex_rest_grant_type'],
				'hitshippo_fedex_rest_acc_no' => $general_settings['hitshippo_fedex_rest_acc_no'],
				'hitshippo_fedex_rest_api_key' => $general_settings['hitshippo_fedex_rest_api_key'],
				'hitshippo_fedex_rest_secret_key' => $general_settings['hitshippo_fedex_rest_secret_key'],
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
				'hitshippo_fedex_currency' => $general_settings['hitshippo_fedex_currency'],
			);
			$vendor_settings = array();

			if (isset($general_settings['hitshippo_fedex_rates']) && $general_settings['hitshippo_fedex_rates'] == 'yes' && isset($pack_aft_hook['destination']['country']) && !empty($pack_aft_hook['destination']['country'])) {
				if (isset($general_settings['hitshippo_fedex_v_enable']) && $general_settings['hitshippo_fedex_v_enable'] == 'yes' && isset($general_settings['hitshippo_fedex_v_rates']) && $general_settings['hitshippo_fedex_v_rates'] == 'yes') {
					// Multi Vendor Enabled
					foreach ($pack_aft_hook['contents'] as $key => $value) {
						$product_id = $value['product_id'];
						if ($this->hpos_enabled) {
							$hpos_prod_data = wc_get_product($product_id);
							$fedex_account = $hpos_prod_data->get_meta("fedex_address");
						} else {
							$fedex_account = get_post_meta($product_id, 'fedex_address', true);
						}
						if (empty($fedex_account) || $fedex_account == 'default') {
							$fedex_account = 'default';
							if (!isset($vendor_settings[$fedex_account])) {
								$vendor_settings[$fedex_account] = $custom_settings['default'];
							}
							$vendor_settings[$fedex_account]['products'][] = $value;
						}

						if ($fedex_account != 'default') {
							$user_account = get_post_meta($fedex_account, 'hitshippo_fedex_vendor_settings', true);
							$user_account = empty($user_account) ? array() : $user_account;
							if (!empty($user_account)) {
								if (!isset($vendor_settings[$fedex_account])) {

									$vendor_settings[$fedex_account] = $custom_settings['default'];

									if ($user_account['hitshippo_fedex_site_id'] != '' && $user_account['hitshippo_fedex_site_pwd'] != '' && $user_account['hitshippo_fedex_acc_no'] != '' && $user_account['hitshippo_fedex_access_key'] != '') {
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

									if ($user_account['hitshippo_fedex_shipper_name'] != '' && $user_account['hitshippo_fedex_address1'] != '' && $user_account['hitshippo_fedex_city'] != '' && $user_account['hitshippo_fedex_state'] != '' && $user_account['hitshippo_fedex_zip'] != '' && $user_account['hitshippo_fedex_country'] != '') {

										if ($user_account['hitshippo_fedex_shipper_name'] != '') {
											$vendor_settings[$fedex_account]['hitshippo_fedex_shipper_name'] = $user_account['hitshippo_fedex_shipper_name'];
										}

										if ($user_account['hitshippo_fedex_company'] != '') {
											$vendor_settings[$fedex_account]['hitshippo_fedex_company'] = $user_account['hitshippo_fedex_company'];
										}

										if ($user_account['hitshippo_fedex_mob_num'] != '') {
											$vendor_settings[$fedex_account]['hitshippo_fedex_mob_num'] = $user_account['hitshippo_fedex_mob_num'];
										}

										if ($user_account['hitshippo_fedex_email'] != '') {
											$vendor_settings[$fedex_account]['hitshippo_fedex_email'] = $user_account['hitshippo_fedex_email'];
										}

										if ($user_account['hitshippo_fedex_address1'] != '') {
											$vendor_settings[$fedex_account]['hitshippo_fedex_address1'] = $user_account['hitshippo_fedex_address1'];
										}

										$vendor_settings[$fedex_account]['hitshippo_fedex_address2'] = !empty($user_account['hitshippo_fedex_address2']) ? $user_account['hitshippo_fedex_address2'] : '';

										if ($user_account['hitshippo_fedex_city'] != '') {
											$vendor_settings[$fedex_account]['hitshippo_fedex_city'] = $user_account['hitshippo_fedex_city'];
										}

										if ($user_account['hitshippo_fedex_state'] != '') {
											$vendor_settings[$fedex_account]['hitshippo_fedex_state'] = $user_account['hitshippo_fedex_state'];
										}

										if ($user_account['hitshippo_fedex_zip'] != '') {
											$vendor_settings[$fedex_account]['hitshippo_fedex_zip'] = $user_account['hitshippo_fedex_zip'];
										}

										if ($user_account['hitshippo_fedex_country'] != '') {
											$vendor_settings[$fedex_account]['hitshippo_fedex_country'] = $user_account['hitshippo_fedex_country'];
										}

										if (isset($user_account['hitshippo_fedex_con_rate'])) {
											$vendor_settings[$fedex_account]['hitshippo_fedex_con_rate'] = $user_account['hitshippo_fedex_con_rate'];
										}

										if (isset($user_account['hitshippo_fedex_currency'])) {
											$vendor_settings[$fedex_account]['hitshippo_fedex_currency'] = $user_account['hitshippo_fedex_currency'];
										}
									}
								}

								$vendor_settings[$fedex_account]['products'][] = $value;
							} else {
								$fedex_account = 'default';
								if (!isset($vendor_settings[$fedex_account])) {
									$vendor_settings[$fedex_account] = $custom_settings['default'];
								}
								$vendor_settings[$fedex_account]['products'][] = $value;
							}
						}
					}
				}

				if (empty($vendor_settings)) {
					$custom_settings['default']['products'] = $pack_aft_hook['contents'];
				} else {
					$custom_settings = $vendor_settings;
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
					// if(isset($pack_aft_hook['destination']['country']) && !empty($pack_aft_hook['destination']['country']))
					// {
					// 	$fedex_selected_curr = $fedex_core[$cust_set['hitshippo_fedex_country']]['currency'];
					// }
					
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

					$xmlRequest =  file_get_contents(dirname(__FILE__) . '/xml/rate.xml');
					$xmlRequest = str_replace('{key}', $cust_set['hitshippo_fedex_site_id'], $xmlRequest);
					$xmlRequest = str_replace('{pwd}', $cust_set['hitshippo_fedex_site_pwd'], $xmlRequest);
					$xmlRequest = str_replace('{Account_num}', $cust_set['hitshippo_fedex_acc_no'], $xmlRequest);
					$xmlRequest = str_replace('{meter_number}', $cust_set['hitshippo_fedex_access_key'], $xmlRequest);
					$xmlRequest = str_replace('{timestramp}', date('c', strtotime('+1 Weekday')), $xmlRequest);
					$xmlRequest = str_replace('{s_postal}', $cust_set['hitshippo_fedex_zip'], $xmlRequest);
					$xmlRequest = str_replace('{s_countr}', $cust_set['hitshippo_fedex_country'], $xmlRequest);

					if (isset($general_settings['hitshippo_fedex_res_f']) && $general_settings['hitshippo_fedex_res_f'] == 'yes') {
						$xmlRequest = str_replace('{r_res}', 'true', $xmlRequest);
					} else {
						$xmlRequest = str_replace('{r_res}', 'false', $xmlRequest);
					}

					$hitshippo_fedex_account_rates = ($general_settings['hitshippo_fedex_account_rates'] != 'yes') ? 'LIST' : 'NONE';

					$xmlRequest = str_replace('{ac_rate}', $hitshippo_fedex_account_rates, $xmlRequest);
					$xmlRequest = str_replace('{r_city}', $pack_aft_hook['destination']['city'], $xmlRequest);
					$xmlRequest = str_replace('{r_state}', $pack_aft_hook['destination']['state'], $xmlRequest);
					$xmlRequest = str_replace('{r_postal}', $pack_aft_hook['destination']['postcode'], $xmlRequest);
					$xmlRequest = str_replace('{r_county}', $pack_aft_hook['destination']['country'], $xmlRequest);
					$xmlRequest = str_replace('{currency}', $fedex_selected_curr, $xmlRequest);
					$xmlRequest = str_replace('{weightunit}', $weight_unit, $xmlRequest);

					$total_xml = '';
					$my_par = 0;
					$total_weight_for_rate = 0;
					$total_packages = 0;
					$ex_rate = 0;
					$ex_rate_data = array();

					$woo_weg_unit = get_option('woocommerce_weight_unit');
					$woo_dim_unit = get_option('woocommerce_dimension_unit');
					$woo_curr = get_option('woocommerce_currency');
					$config_weg_unit = $general_settings['hitshippo_fedex_weight_unit'];
					$mod_weg_unit = (!empty($config_weg_unit) && $config_weg_unit == 'LB_IN') ? 'lbs' : 'kg';
					$mod_dim_unit = (!empty($config_weg_unit) && $config_weg_unit == 'LB_IN') ? 'in' : 'cm';

					if ($woo_curr != $fedex_selected_curr) {
						if (isset($general_settings['hitshippo_fedex_auto_con_rate']) && $general_settings['hitshippo_fedex_auto_con_rate'] == "yes") {
							$ex_rate_data = get_option('hitshippo_fedex_ex_rate' . $key, array());
							$ex_rate_data = !empty($ex_rate_data) ? $ex_rate_data : array();
							if (!empty($ex_rate_data)) {
								$ex_rate = $ex_rate_data['ex_rate'];
							}
						} else {
							if (isset($cust_set['hitshippo_fedex_con_rate']) && !empty($cust_set['hitshippo_fedex_con_rate'])) {
								$ex_rate = $cust_set['hitshippo_fedex_con_rate'];
							}
						}
					}

					$products = [];
					foreach ($cust_set['products'] as $value) {
						$prod_data = $value['data']->get_data();
						
						$get_prod = wc_get_product($value['product_id']);

						if (!empty($prod_data['weight']) && !empty($prod_data['length']) && !empty($prod_data['width']) && !empty($prod_data['height'])) {

							$products[] = array(
								'product_id' => $value['product_id'],
								'id' => $prod_data['id'],
								'quantity' => $value['quantity'],
								'name' => $prod_data['name'],
								'price' => $prod_data['price'],
								'regular_price' => $prod_data['regular_price'],
								'weight' => ($woo_weg_unit != $mod_weg_unit) ? (float)round(wc_get_weight((!empty($prod_data['weight']) ? $prod_data['weight'] : 0.5), $mod_weg_unit, $woo_weg_unit), 2) : (!empty($prod_data['weight']) ? $prod_data['weight'] : 0.5),
								'length' => ($woo_dim_unit != $mod_dim_unit) ? round(wc_get_dimension((!empty($prod_data['length']) ? $prod_data['length'] : 1), $mod_dim_unit, $woo_dim_unit), 2) : (!empty($prod_data['length']) ? $prod_data['length'] : 1),
								'width' => ($woo_dim_unit != $mod_dim_unit) ? round(wc_get_dimension((!empty($prod_data['width']) ? $prod_data['width'] : 1), $mod_dim_unit, $woo_dim_unit), 2) : (!empty($prod_data['width']) ? $prod_data['width'] : 1),
								'height' => ($woo_dim_unit != $mod_dim_unit) ? round(wc_get_dimension((!empty($prod_data['height']) ? $prod_data['height'] : 1), $mod_dim_unit, $woo_dim_unit), 2) : (!empty($prod_data['height']) ? $prod_data['height'] : 1)
							);
						} elseif ($get_prod->is_type('variable')) {
							
							$parent_prod_data = $value['data']->get_parent_data();
							
							$products[] = array(
								'product_id' => $value['product_id'],
								'id' => $prod_data['id'],
								'quantity' => $value['quantity'],
								'name' => $prod_data['name'],
								'price' => $prod_data['price'],
								'regular_price' => $prod_data['regular_price'],
								'weight' => ($woo_weg_unit != $mod_weg_unit) ? (float)round(wc_get_weight((!empty($prod_data['weight']) ? $prod_data['weight'] : (!empty($parent_prod_data['weight']) ? $parent_prod_data['weight'] : 1)), $mod_weg_unit, $woo_weg_unit), 2) : (!empty($prod_data['weight']) ? $prod_data['weight'] : (!empty($parent_prod_data['weight']) ? $parent_prod_data['weight'] : 1)),
								
								'length' => ($woo_dim_unit != $mod_dim_unit) ? round(wc_get_dimension((!empty($prod_data['length']) ? $prod_data['length'] : (!empty($parent_prod_data['length']) ? $parent_prod_data['length'] : 1)), $mod_dim_unit, $woo_dim_unit), 2) : (!empty($prod_data['length']) ? $prod_data['length'] : (!empty($parent_prod_data['length']) ? $parent_prod_data['length'] : 1)),
								
								'width' => ($woo_dim_unit != $mod_dim_unit) ? round(wc_get_dimension((!empty($prod_data['width']) ? $prod_data['width'] : (!empty($parent_prod_data['width']) ? $parent_prod_data['width'] : 1)), $mod_dim_unit, $woo_dim_unit), 2) : (!empty($prod_data['width']) ? $prod_data['width'] : (!empty($parent_prod_data['width']) ? $parent_prod_data['width'] : 1)),
								'height' => ($woo_dim_unit != $mod_dim_unit) ? round(wc_get_dimension((!empty($prod_data['height']) ? $prod_data['height'] : (!empty($parent_prod_data['height']) ? $parent_prod_data['height'] : 1)), $mod_dim_unit, $woo_dim_unit), 2) : (!empty($prod_data['height']) ? $prod_data['height'] : (!empty($parent_prod_data['height']) ? $parent_prod_data['height'] : 1))
							);
						} else {
							$products[] = array(
								'product_id' => $value['product_id'],
								'id' => $prod_data['id'],
								'quantity' => $value['quantity'],
								'name' => $prod_data['name'],
								'price' => $prod_data['price'],
								'regular_price' => $prod_data['regular_price'],
								'weight' => ($woo_weg_unit != $mod_weg_unit) ? (float)round(wc_get_weight((!empty($prod_data['weight']) ? $prod_data['weight'] : 0.5), $mod_weg_unit, $woo_weg_unit), 2) : (!empty($prod_data['weight']) ? $prod_data['weight'] : 0.5),
								'length' => ($woo_dim_unit != $mod_dim_unit) ? round(wc_get_dimension((!empty($prod_data['length']) ? $prod_data['length'] : 1), $mod_dim_unit, $woo_dim_unit), 2) : (!empty($prod_data['length']) ? $prod_data['length'] : 1),
								'width' => ($woo_dim_unit != $mod_dim_unit) ? round(wc_get_dimension((!empty($prod_data['width']) ? $prod_data['width'] : 1), $mod_dim_unit, $woo_dim_unit), 2) : (!empty($prod_data['width']) ? $prod_data['width'] : 1),
								'height' => ($woo_dim_unit != $mod_dim_unit) ? round(wc_get_dimension((!empty($prod_data['height']) ? $prod_data['height'] : 1), $mod_dim_unit, $woo_dim_unit), 2) : (!empty($prod_data['height']) ? $prod_data['height'] : 1)
							);
						}
					}


					// echo '<pre>';print_r($products); die();

					$fedex_packs = $this->hit_get_fedex_packages($products, $general_settings, $fedex_selected_curr);
					// echo '<pre>';print_r($products);die();
					if (empty($fedex_packs)) {
						return;
					}
					foreach ($fedex_packs as $parcel) {

						$my_par = $my_par + 1;

						// $product = $parcel['data']->get_data();

						// if ($woo_weg_unit != $mod_weg_unit) {
						// 	$total_weight = (float)round(wc_get_weight($parcel['Weight']['Value'],$mod_weg_unit,$woo_weg_unit),2);
						// }else{
						$total_weight = !empty($parcel['Weight']['Value'] && $parcel['Weight']['Value'] > 0) ? $parcel['Weight']['Value'] : 0;
						// }

						$total_xml .= '<RequestedPackageLineItems><SequenceNumber>' . $my_par . '</SequenceNumber><GroupNumber>' . $my_par . '</GroupNumber><GroupPackageCount>1</GroupPackageCount><Weight><Units>' . $weight_unit . '</Units><Value>' . $total_weight . '</Value></Weight>';
						// if( !empty($product['height']) && !empty($product['length']) && !empty($product['width']) ){
						// 	if ($woo_dim_unit != $mod_dim_unit) {
						// 		$total_xml .='<Dimensions><Length>'.round(wc_get_dimension($product['length'],$mod_dim_unit,$woo_dim_unit)).'</Length><Width>'.round(wc_get_dimension($product['width'],$mod_dim_unit,$woo_dim_unit)).'</Width><Height>'.round(wc_get_dimension($product['height'],$mod_dim_unit,$woo_dim_unit)).'</Height><Units>'.$dim_unit.'</Units></Dimensions>';
						// 	}else{
						// 		$total_xml .='<Dimensions><Length>'.$product['length'] .'</Length><Width>'. $product['width'].'</Width><Height>'. $product['height'].'</Height><Units>'.$dim_unit.'</Units></Dimensions>';
						// 	}
						// }

						if (isset($parcel['Dimensions'])) {
							$total_xml .= '<Dimensions><Length>' . round($parcel['Dimensions']['Length']) . '</Length><Width>' . round($parcel['Dimensions']['Width']) . '</Width><Height>' . round($parcel['Dimensions']['Height']) . '</Height><Units>' . $dim_unit . '</Units></Dimensions>';
						}

						$total_xml .= '</RequestedPackageLineItems>';
						//$total_packages = $my_par;


						if ($total_weight < 0.001) {
							$total_weight = 0.001;
						} else {
							$total_weight = round((float)$total_weight, 3);
						}
						$total_weight_for_rate += $total_weight;
					}
					$xmlRequest = str_replace('{totalweight}', $total_weight_for_rate, $xmlRequest);
					$xmlRequest = str_replace('{package_count}', $my_par, $xmlRequest);
					$xmlRequest = str_replace('{line_items}', $total_xml, $xmlRequest);


					if ($cust_set['hitshippo_fedex_country'] == $pack_aft_hook['destination']['country']) {
						if (isset($general_settings['hitshippo_fedex_one_rates']) && $general_settings['hitshippo_fedex_one_rates'] == "yes") {
							$one_rate = '<VariableOptions>FEDEX_ONE_RATE</VariableOptions>';
							$xmlRequest = str_replace('{one_rate}', $one_rate, $xmlRequest);

							if (isset($general_settings['hitshippo_fedex_send_pack_as_ship']) && $general_settings['hitshippo_fedex_send_pack_as_ship'] == "yes") {
								if (isset($general_settings['hitshippo_fedex_ship_pack_type']) && !empty($general_settings['hitshippo_fedex_ship_pack_type'])) {
									$xmlRequest = str_replace('YOUR_PACKAGING', $general_settings['hitshippo_fedex_ship_pack_type'], $xmlRequest);
								}
							} else {
								if ($weight_unit == "KG") {
									$nor_weight = $total_weight * 2.205;		//Kg to Lbs conversion
								} else {
									$nor_weight = $total_weight;
								}

								if ($nor_weight > 10 && $nor_weight <= 50) {
									$xmlRequest = str_replace('YOUR_PACKAGING', 'FEDEX_MEDIUM_BOX', $xmlRequest);
								} elseif ($nor_weight <= 10) {
									$xmlRequest = str_replace('YOUR_PACKAGING', 'FEDEX_SMALL_BOX', $xmlRequest);
								}
							}
						} else {
							$xmlRequest = str_replace('{one_rate}', '', $xmlRequest);
							if (isset($general_settings['hitshippo_fedex_send_pack_as_ship']) && $general_settings['hitshippo_fedex_send_pack_as_ship'] == "yes") {
								if (isset($general_settings['hitshippo_fedex_ship_pack_type']) && !empty($general_settings['hitshippo_fedex_ship_pack_type'])) {
									$xmlRequest = str_replace('YOUR_PACKAGING', $general_settings['hitshippo_fedex_ship_pack_type'], $xmlRequest);
								}
							}
						}
					} else {
						$xmlRequest = str_replace('{one_rate}', '', $xmlRequest);
						if (isset($general_settings['hitshippo_fedex_send_pack_as_ship']) && $general_settings['hitshippo_fedex_send_pack_as_ship'] == "yes") {
							if (isset($general_settings['hitshippo_fedex_ship_pack_type']) && !empty($general_settings['hitshippo_fedex_ship_pack_type'])) {
								$xmlRequest = str_replace('YOUR_PACKAGING', $general_settings['hitshippo_fedex_ship_pack_type'], $xmlRequest);
							}
						}
					}


					$order_total = 0;
					foreach ($pack_aft_hook['contents'] as $item_id => $values) {
						$order_total += (float) $values['line_subtotal'];
						if (($woo_curr != $fedex_selected_curr) && ($ex_rate > 0)) {
							$order_total *= $ex_rate;
						}
					}
					$pay_type = (isset($general_settings['duty_type']) && $general_settings['duty_type'] == "R") ? "RECIPIENT" : "SENDER";
					$ind_cus = "";
					if (in_array($cust_set['hitshippo_fedex_country'], array("IN", "AE"))) {
						$payer_xml = "";
						if ($pay_type == "SENDER") {
							$payer_xml = "<Payor>
											<ResponsibleParty>
												<AccountNumber>" . $cust_set['hitshippo_fedex_acc_no'] . "</AccountNumber>
												<Address>
													<CountryCode>" . $cust_set['hitshippo_fedex_country'] . "</CountryCode>
												</Address>
											</ResponsibleParty>
										</Payor>";
						}

						$ind_cus = "<CustomsClearanceDetail>
	                    <DutiesPayment>
	                        <PaymentType>". $pay_type ."</PaymentType>
	                        ". $payer_xml ."
	                    </DutiesPayment>
	                    <CommercialInvoice>
	                        <Purpose>SOLD</Purpose>
	                    </CommercialInvoice>
	                    <Commodities>
	                        <NumberOfPieces>" . $my_par . "</NumberOfPieces>
	                        <Description>" . $cust_set['hitshippo_fedex_company'] . "</Description>
	                        <CountryOfManufacture>" . $cust_set['hitshippo_fedex_country'] . "</CountryOfManufacture>
	                        <Weight>
	                            <Units>" . $weight_unit . "</Units>
	                            <Value>" . $total_weight_for_rate . "</Value>
	                        </Weight>
	                        <Quantity>" . $my_par . "</Quantity>
	                        <QuantityUnits>PACKAGE</QuantityUnits>
	                        <UnitPrice>
	                            <Currency>" . $fedex_selected_curr . "</Currency>
	                            <Amount>" . $order_total . "</Amount>
	                        </UnitPrice>
	                        <CustomsValue>
	                            <Currency>" . $fedex_selected_curr . "</Currency>
	                            <Amount>" . $order_total . "</Amount>
	                        </CustomsValue>
	                    </Commodities>
	                </CustomsClearanceDetail>";
					}

					$xmlRequest = str_replace('{ind_customs}', $ind_cus, $xmlRequest);
					// Spl service
					$spl_ser_xml = $req_spl_ser_xml = "";
					if (isset($general_settings['hitshippo_fedex_sat_del']) && $general_settings['hitshippo_fedex_sat_del'] == 'yes') {
						$spl_ser_xml .= "<SpecialServiceTypes>SATURDAY_DELIVERY</SpecialServiceTypes>";
					}

					if(!empty($spl_ser_xml)){
						$req_spl_ser_xml = "<SpecialServicesRequested>";
						$req_spl_ser_xml .= $spl_ser_xml;
						$req_spl_ser_xml .= "</SpecialServicesRequested>";
						$xmlRequest = str_replace('{sig_req}', $req_spl_ser_xml, $xmlRequest);
					} else {
						$xmlRequest = str_replace('{sig_req}', $req_spl_ser_xml, $xmlRequest);
					}

					// Insurance
					$ins_xml = "";
					if (isset($general_settings['hitshippo_fedex_insurance']) && ($general_settings['hitshippo_fedex_insurance'] == "yes")) {
						$ins_xml = "<TotalInsuredValue><Currency>" . $fedex_selected_curr . "</Currency><Amount>" . $order_total . "</Amount></TotalInsuredValue>";
					}
					$xmlRequest = str_replace('{insurance}', $ins_xml, $xmlRequest);

					$manual_rates = apply_filters('hithipo_fedex_manual_rates', false);

					if ($manual_rates) {

						$rate_cost = apply_filters("hitstacks_fedex_shipping_cost_conversion", -1, $total_weight_for_rate, $pack_aft_hook['destination']['country'], 'P');
						if ($rate_cost >= 0) {
							// This is where you'll add your rates
							$rate = array(
								'id'       => 'hitshippo_fedex',
								'label'    => 'Fedex Shipping',
								'cost'     => $rate_cost,
								'meta_data' => array('hitshippo_fedex_multi_ven' => $multi_ven, 'hitshippo_fedex_shipping_charge' => $rate_cost)
							);

							// Register the rate
							$this->add_rate($rate);
							return;
						}
					}

					if (isset($general_settings['hitshippo_fedex_api_type']) && $general_settings['hitshippo_fedex_api_type'] == "REST") {
						$auth_token = get_transient("hitshipo_fedex_rest_auth_token_".$key);
						if (!class_exists("fedex_rest")) {
							include_once("hitshipo_fedex_rest_main.php");
						}
						$fedex_rest_obj = new fedex_rest();
						$fedex_rest_obj->mode = (isset($general_settings['hitshippo_fedex_test']) && $general_settings['hitshippo_fedex_test'] == 'yes') ? 'test' : 'live';
						if (empty($auth_token)) {
							$auth_token = $fedex_rest_obj->gen_access_token($cust_set['hitshippo_fedex_rest_grant_type'], $cust_set['hitshippo_fedex_rest_api_key'], $cust_set['hitshippo_fedex_rest_secret_key']);
							set_transient("hitshipo_fedex_rest_auth_token_".$key, $auth_token, 3000);
						}
						
						$xmlRequest = $fedex_rest_obj->make_rate_req_rest($general_settings, $cust_set, $pack_aft_hook['destination'], $fedex_packs, $order_total);
						$request_url = ($fedex_rest_obj->mode == "test") ? $fedex_rest_obj->test_rate_url : $fedex_rest_obj->live_rate_url;
						$result = $fedex_rest_obj->get_rate_res_rest($xmlRequest, $auth_token);
					} else {
						$request_url = (isset($general_settings['hitshippo_fedex_test']) && $general_settings['hitshippo_fedex_test'] == 'yes') ? 'https://wsbeta.fedex.com:443/web-services/rate' : 'https://ws.fedex.com:443/web-services/rate';

						$result = wp_remote_post(
							$request_url,
							array(
								'method' => 'POST',
								'timeout' => 70,
								'sslverify' => 0,
								'body' => $xmlRequest
							)
						);
					}
					$admin_user = false;
					if (is_user_logged_in()) {
						$user = wp_get_current_user();
						if (isset($user->roles) && !empty($user->roles) && in_array("administrator", $user->roles)) {
							$admin_user = true;
						}
					}
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

						return false;
					}


					if (isset($xml) && isset($xml->SOAPENVBody->RateReply)) {
						$xml = $xml->SOAPENVBody->RateReply;
					} elseif (isset($xml) && isset($xml->rateReplyDetails)) {
						// do nothing
					} else {
						return false;
					}

					$carriers_available = isset($general_settings['hitshippo_fedex_carrier']) && is_array($general_settings['hitshippo_fedex_carrier']) ? $general_settings['hitshippo_fedex_carrier'] : array();
					$rate = array();
					$del_dates = array();
					if (isset($xml->RateReplyDetails) && !empty($xml->RateReplyDetails)) {
						foreach ($xml->RateReplyDetails as $quote) {
							$rate_code = ((string) $quote->ServiceType);
							// For international shipments fedex returning service code as FEDEX_GROUND -> same as domestic service code. So, need to alter it here to show international and return back to original before passing to add_rate().
							if (isset($cust_set['hitshippo_fedex_country']) && isset($pack_aft_hook['destination']['country'])) {
								if ( ($rate_code == "FEDEX_GROUND") && ($cust_set['hitshippo_fedex_country'] != $pack_aft_hook['destination']['country']) ) {
									$rate_code = "INTERNATIONAL_GROUND";
								}
							}
							$rate_cost = 0;
							if (array_key_exists($rate_code, $carriers_available)) {
								$shipment_details = '';
								foreach ($quote->RatedShipmentDetails as $shipment_deta) {
									if ($hitshippo_fedex_account_rates == "LIST") {
										if (strstr($shipment_deta->ShipmentRateDetail->RateType, 'PAYOR_LIST')) {
											$shipment_details = $shipment_deta;
											break;
										}
									} else {
										if (strstr($shipment_deta->ShipmentRateDetail->RateType, 'PAYOR_ACCOUNT')) {
											$shipment_details = $shipment_deta;
											break;
										} elseif (strstr($shipment_deta->ShipmentRateDetail->RateType, 'PAYOR_ACCOUNT_PACKAGE')) {
											$shipment_details = $shipment_deta;
											break;
										}
									}
								}

								if (empty($shipment_details)) {
									$shipment_details = $quote->RatedShipmentDetails;
								}

								if (empty($shipment_details)) {
									continue;
								}
								if (isset($general_settings['hitshippo_fedex_include_tax']) && $general_settings['hitshippo_fedex_include_tax'] == "yes") {
									$rate_cost = (float)((string) $shipment_details->ShipmentRateDetail->TotalNetChargeWithDutiesAndTaxes->Amount);
									$rate_curr = (string) $shipment_details->ShipmentRateDetail->TotalNetChargeWithDutiesAndTaxes->Currency;
								}else{
									$rate_cost = (float)((string) $shipment_details->ShipmentRateDetail->TotalNetCharge->Amount);
									$rate_curr = (string) $shipment_details->ShipmentRateDetail->TotalNetCharge->Currency;
								}
								if ($fedex_selected_curr == 'AED' || $fedex_selected_curr == 'DHS') {
									$rate_curr = (string)$fedex_selected_curr;
								}

								if ($rate_curr != $fedex_selected_curr) {
									if($woo_curr != $rate_curr){
										continue;
									}
								}

								if (($rate_curr == $fedex_selected_curr) && ($woo_curr != $fedex_selected_curr) && ($ex_rate > 0)) {
									$rate_cost /= $ex_rate;
								}

								$rate[$rate_code] = $rate_cost;
								$del_dates[$rate_code] = isset($quote->DeliveryTimestamp) ? (string)$quote->DeliveryTimestamp : "";
							} else {
								continue;
							}
						}
					} elseif (isset($xml->rateReplyDetails) && !empty($xml->rateReplyDetails)) {
						foreach ($xml->rateReplyDetails as $quote) {
							$rate_code = ((string) $quote->serviceType);
							$rate_cost = 0;
							if (array_key_exists($rate_code, $carriers_available)) {
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
									if($woo_curr != $rate_curr){
										continue;
									}
								}

								if (($rate_curr == $fedex_selected_curr) && ($woo_curr != $fedex_selected_curr) && ($ex_rate > 0)) {
									$rate_cost /= $ex_rate;
								}

								$rate[$rate_code] = $rate_cost;
								$del_dates[$rate_code] = isset($quote->operationalDetail->deliveryDate) ? (string)$quote->operationalDetail->deliveryDate : "";
							} else {
								continue;
							}
						}
					} else {
						return false;
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

					foreach ($final_price as $key => $value) {

						$rate_cost = $value['price'];
						$rate_code = $value['code'];
						$multi_ven = $value['multi_v'];

						// $rate_cost = apply_filters('hitshippo_fedex_rate_cost',$rate_cost,$rate_code);

						// if($rate_cost > 0)
						// {

						// 	$rate_name = $_carriers[$rate_code];
						// 	$name = isset($carriers_name_available[$rate_code]) && !empty($carriers_name_available[$rate_code]) ? $carriers_name_available[$rate_code] : $rate_name;

						// 	$rate = array(
						// 		'id'       => 'hitshippo'.$rate_code,
						// 		'label'    => $name,
						// 		'cost'     => $rate_cost,
						// 		'meta_data' => array('hitshippo_fedex_service' => $rate_code, 'hitshippo_fedex_shipping_charge' => $rate_cost));
						// 	$this->add_rate( $rate );

						// }

						$rate_cost = round($rate_cost, 2);

						// $carriers_available = isset($general_settings['hitshippo_fedex_carrier']) && is_array($general_settings['hitshippo_fedex_carrier']) ? $general_settings['hitshippo_fedex_carrier'] : array();

						$carriers_name_available = isset($general_settings['hitshippo_fedex_carrier_name']) && is_array($general_settings['hitshippo_fedex_carrier']) ? $general_settings['hitshippo_fedex_carrier_name'] : array();

						if (array_key_exists($rate_code, $carriers_available)) {
							$name = isset($carriers_name_available[$rate_code]) && !empty($carriers_name_available[$rate_code]) ? $carriers_name_available[$rate_code] : $_carriers[$rate_code];
							$dest_zip_code = isset($pack_aft_hook['destination']['postcode']) ? $pack_aft_hook['destination']['postcode'] : '';
							$rate_cost = apply_filters('hitshippo_fedex_rate_cost', $rate_cost, $rate_code, $order_total, $pack_aft_hook['destination']['country'], $dest_zip_code);
							if ($rate_cost < 1) {
								$name .= ' - Free';
							}

							if (isset($general_settings['hitshippo_fedex_del_date']) && $general_settings['hitshippo_fedex_del_date'] == "yes") {
								if (isset($del_dates[$rate_code]) && !empty($del_dates[$rate_code])) {
									$rc_date = str_replace(["-", ":", "T"], "", $del_dates[$rate_code] );
									$formated_date = DateTime::createFromFormat('YmdHis', (string)$rc_date);
									$etd_date = $formated_date->format('d/m/Y');
									$etd = apply_filters('hitshippo_fedex_delivery_date', " (Etd.Delivery " . $etd_date . ")", $etd_date, "");
									$name .= $etd;
								}
							}

							if (!isset($general_settings['hitshippo_fedex_v_rates']) || $general_settings['hitshippo_fedex_v_rates'] != 'yes') {
								$multi_ven = '';
							}

							if ($rate_code == "INTERNATIONAL_GROUND") {
								$rate_code = "FEDEX_GROUND";
							}
							// This is where you'll add your rates
							$rate = array(
								'id'       => 'hitshippo' . $rate_code,
								'label'    => $name,
								'cost'     => apply_filters("hitstacks_fedex_shipping_cost_conversion", $rate_cost, $total_weight_for_rate, $pack_aft_hook['destination']['country'], $rate_code),
								'meta_data' => array('hitshippo_fedex_multi_ven' => $multi_ven, 'hitshippo_fedex_service' => $rate_code, 'hitshippo_fedex_shipping_charge' => $rate_cost)
							);
							$is_default_service = apply_filters("hitshipo_fedex_default_rate_service", "no", $rate_code, $pack_aft_hook['destination']['country']);
							if ($is_default_service == "yes") {
								WC()->session->set('chosen_shipping_methods', array($rate['id']));
							}
							// Register the rate

							$this->add_rate($rate);
						}
					}
				}
			}
		}
		private function hitshippo_get_zipcode_or_city($country, $city, $postcode)
		{
			$no_postcode_country = array(
				'AE', 'AF', 'AG', 'AI', 'AL', 'AN', 'AO', 'AW', 'BB', 'BF', 'BH', 'BI', 'BJ', 'BM', 'BO', 'BS', 'BT', 'BW', 'BZ', 'CD', 'CF', 'CG', 'CI', 'CK',
				'CL', 'CM', 'CO', 'CR', 'CV', 'DJ', 'DM', 'DO', 'EC', 'EG', 'ER', 'ET', 'FJ', 'FK', 'GA', 'GD', 'GH', 'GI', 'GM', 'GN', 'GQ', 'GT', 'GW', 'GY', 'HK', 'HN', 'HT', 'IE', 'IQ', 'IR',
				'JM', 'JO', 'KE', 'KH', 'KI', 'KM', 'KN', 'KP', 'KW', 'KY', 'LA', 'LB', 'LC', 'LK', 'LR', 'LS', 'LY', 'ML', 'MM', 'MO', 'MR', 'MS', 'MT', 'MU', 'MW', 'MZ', 'NA', 'NE', 'NG', 'NI',
				'NP', 'NR', 'NU', 'OM', 'PA', 'PE', 'PF', 'PY', 'QA', 'RW', 'SA', 'SB', 'SC', 'SD', 'SL', 'SN', 'SO', 'SR', 'SS', 'ST', 'SV', 'SY', 'TC', 'TD', 'TG', 'TL', 'TO', 'TT', 'TV', 'TZ',
				'UG', 'UY', 'VC', 'VE', 'VG', 'VN', 'VU', 'WS', 'XA', 'XB', 'XC', 'XE', 'XL', 'XM', 'XN', 'XS', 'YE', 'ZM', 'ZW'
			);

			$postcode_city = !in_array($country, $no_postcode_country) ? $postcode_city = "<Postalcode>{$postcode}</Postalcode>" : '';
			if (!empty($city)) {
				$postcode_city .= "<City>{$city}</City>";
			}
			return $postcode_city;
		}
		/**
		 * Initialise Gateway Settings Form Fields
		 */
		public function init_form_fields()
		{
			$this->form_fields = array(
				'title'      => array(
					'title'       => __('Name', 'shipi'),
					'type'        => 'text',
					'description' => __('Your customers will see the name of this shipping method during checkout.', 'shipi'),
					'default'     => $this->method_title,
					'placeholder' => __('e.g. Fedex Shipping', 'shipi'),
					'desc_tip'    => true,
				)
			);
		}
		public function generate_hitshippo_fedex_html()
		{
			$general_settings = get_option('hitshippo_fedex_main_settings');
			$general_settings = empty($general_settings) ? array() : $general_settings;
			if (!empty($general_settings)) {
				wp_redirect(admin_url('options-general.php?page=hit-fedex-configuration'));
			}

			if (isset($_POST['configure_the_plugin'])) {
				// global $woocommerce;
				// $countries_obj   = new WC_Countries();
				// $countries   = $countries_obj->__get('countries');
				// $default_country = $countries_obj->get_base_country();

				// if (!isset($general_settings['hitshippo_fedex_country'])) {
				// 	$general_settings['hitshippo_fedex_country'] = $default_country;
				// 	update_option('hitshippo_fedex_main_settings', $general_settings);
				// }
				wp_redirect(admin_url('options-general.php?page=hit-fedex-configuration'));
			}
?>
			<style>
				.card {
					background-color: #fff;
					border-radius: 5px;
					width: 800px;
					max-width: 800px;
					height: auto;
					text-align: center;
					margin: 10px auto 100px auto;
					box-shadow: 0px 1px 20px 1px hsla(213, 33%, 68%, .6);
				}

				.content {
					padding: 20px 20px;
				}


				h2 {
					text-transform: uppercase;
					color: #000;
					font-weight: bold;
				}


				.boton {
					text-align: center;
				}

				.boton button {
					font-size: 18px;
					border: none;
					outline: none;
					color: #166DB4;
					text-transform: capitalize;
					background-color: #fff;
					cursor: pointer;
					font-weight: bold;
				}

				button:hover {
					text-decoration: underline;
					text-decoration-color: #166DB4;
				}
			</style>
			<!-- Fuente Mulish -->

			<div class="card">
				<div class="content">
					<div class="logo">
						<img src="<?php echo plugin_dir_url(__FILE__); ?>views/fedex.png" style="width:150px;" alt="logo DELL" />
					</div>
					<h2><strong>Shipi + FedEx</strong></h2>
					<p style="font-size: 14px;line-height: 27px;">
						<?php _e('Welcome to Shipi! You are at just one-step ahead to configure the FedEx with Shipi.', 'a2z_dhlexpress') ?><br>
						<?php _e('We have lot of features that will take your e-commerce store to another level.', 'a2z_dhlexpress') ?><br><br>
						<?php _e('Shipi helps you to save time, reduce errors, and worry less when you automate your tedious, manual tasks. Shipi + our plugin can generate shipping labels, Commercial invoice, display real time rates, track orders, audit shipments, and supports both domestic & international DHL services.', 'a2z_dhlexpress') ?><br><br>
						<?php _e('Make your customers happier by reacting faster and handling their service requests in a timely manner, meaning higher store reviews and more revenue.', 'a2z_dhlexpress') ?><br>
					</p>

				</div>
				<div class="boton" style="padding-bottom:10px;">
					<button class="button-primary" name="configure_the_plugin" style="padding:8px;">Configure the plugin</button>
				</div>
			</div>
<?php
			echo '<style>button.button-primary.woocommerce-save-button{display:none;}</style>';
		}

		public function hit_get_fedex_packages($package, $general_settings, $orderCurrency, $chk = false)
		{
			switch ($general_settings['hitshippo_fedex_packing_type']) {
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
				include_once 'classes/weight_pack/class-hit-weight-packing.php';
			}
			$max_weight = isset($general_settings['hitshippo_fedex_max_weight']) && $general_settings['hitshippo_fedex_max_weight'] != ''  ? $general_settings['hitshippo_fedex_max_weight'] : 10;
			$weight_pack = new WeightPack('pack_ascending');
			$weight_pack->set_max_weight($max_weight);

			$package_total_weight = 0;
			$insured_value = 0;

			$ctr = 0;
			foreach ($package as $item_id => $values) {
				$ctr++;
				// $product = $values['data'];
				// $product_data = $product->get_data();
				$product_data = $values;
				// echo '<pre>';print_r($values);die();

				if (!$product_data['weight']) {
					$product_data['weight'] = 0.001;
				}
				$chk_qty = $chk ? $values['product_quantity'] : $values['quantity'];

				$weight_pack->add_item($product_data['weight'], $values, $chk_qty);
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
				foreach ($packages as $package) { //pre($package);
					$packed_products = array();
					if (($package_count  ==  1) && isset($order_total)) {
						$insured_value  =  (isset($product_data['product_price']) ? $product_data['product_price'] : $product_data['price']) * (isset($values['product_quantity']) ? $values['product_quantity'] : $values['quantity']);
					} else {
						$insured_value  =   0;
						if (!empty($package['items'])) {
							foreach ($package['items'] as $item) {

								$insured_value        =   $insured_value; //+ $item->price;
							}
						} else {
							if (isset($order_total) && $package_count) {
								$insured_value  =   $order_total / $package_count;
							}
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
				include_once 'classes/hit-box-packing.php';
			}
			$boxpack = new HIT_Boxpack();
			$boxes = isset($general_settings['hitshippo_fedex_boxes']) ? $general_settings['hitshippo_fedex_boxes'] : [];
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

				// $product = $values['data'];
				// $product_data = $product->get_data();
				// $get_prod = wc_get_product($values['product_id']);
				// $parent_prod_data = [];

				// if ($get_prod->is_type('variable')) {
				// 	$parent_prod_data = $product->get_parent_data();
				// }

				if (isset($product_data['weight']) && !empty($product_data['weight'])) {
					$item_weight = round($product_data['weight'] > 0.001 ? $product_data['weight'] : 0.001, 3);
				}

				if (isset($product_data['width']) && isset($product_data['height']) && isset($product_data['length']) && !empty($product_data['width']) && !empty($product_data['height']) && !empty($product_data['length'])) {
					$item_dimension = array(
						'Length' => max(1, round($product_data['length'], 3)),
						'Width' => max(1, round($product_data['width'], 3)),
						'Height' => max(1, round($product_data['height'], 3))
					);
				}

				if (isset($item_weight) && isset($item_dimension)) {

					// $dimensions = array($values['depth'], $values['height'], $values['width']);
					$chk_qty = $chk ? $product_data['product_quantity'] : $product_data['quantity'];
					for ($i = 0; $i < $chk_qty; $i++) {
						$boxpack->add_item($item_dimension['Width'], $item_dimension['Height'], $item_dimension['Length'], $item_weight, round($product_data['price']), array(
							'data' => $product_data
						));
					}
				} else {
					//    $this->debug(sprintf(__('Product #%s is missing dimensions. Aborting.', 'wf-shipping-dhl'), $item_id), 'error');
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
						'Length' => max(1, round((float)$dimensions[2], 3)),
						'Width' => max(1, round((float)$dimensions[1], 3)),
						'Height' => max(1, round((float)$dimensions[0], 3)),
						'Units' => (isset($general_settings['hitshippo_fedex_weight_unit']) && $general_settings['hitshippo_fedex_weight_unit'] === 'KG_CM') ? 'KG' : 'LBS'
					),
					'InsuredValue' => $insurance_array,
					'packed_products' => array(),
					'package_id' => $package->id,
					'packtype' => 'BOX'
				);
				// echo '<pre>';print_r($packages);die();
				if (!empty($package->packed) && is_array($package->packed)) {
					foreach ($package->packed as $packed) {
						$group['packed_products'][] = $packed->meta['data'];
					}
				}

				if (!isset($package->packed)) {
					foreach ($package->unpacked as $unpacked) {
						$group['packed_products'][] = $unpacked->meta['data'];
					}
				}

				$to_ship[] = $group;

				$group_id++;
			}

			return $to_ship;
		}
		private function per_item_shipping($package, $general_settings, $orderCurrency, $chk = false)
		{
			$to_ship = array();
			$group_id = 1;

			// Get weight of order
			foreach ($package as $item_id => $values) {
				// $product = $values['data'];
				// $product_data = $product->get_data();

				$product_data = $values;
				$group = array();
				$insurance_array = array(
					'Amount' => round($product_data['price']),
					'Currency' => $orderCurrency
				);

				if ($product_data['weight'] < 0.001) {
					$dhl_per_item_weight = 0.001;
				} else {
					$dhl_per_item_weight = round($product_data['weight'], 3);
				}
				$group = array(
					'GroupNumber' => $group_id,
					'GroupPackageCount' => 1,
					'Weight' => array(
						'Value' => $dhl_per_item_weight,
						'Units' => (isset($general_settings['hitshippo_fedex_weight_unit']) && $general_settings['hitshippo_fedex_weight_unit'] == 'KG_CM') ? 'KG' : 'LBS'
					),
					'packed_products' => $product_data
				);

				if ($product_data['width'] && $product_data['height'] && $product_data['length']) {

					$group['Dimensions'] = array(
						'Length' => max(1, round($product_data['length'], 3)),
						'Width' => max(1, round($product_data['width'], 3)),
						'Height' => max(1, round($product_data['height'], 3)),
						'Units' => (isset($general_settings['hitshippo_fedex_weight_unit']) && $general_settings['hitshippo_fedex_weight_unit'] == 'KG_CM') ? 'CM' : 'IN'
					);
				}

				$group['packtype'] = 'BOX';

				$group['InsuredValue'] = $insurance_array;

				$chk_qty = $chk ? $values['product_quantity'] : $values['quantity'];

				for ($i = 0; $i < $chk_qty; $i++)
					$to_ship[] = $group;

				$group_id++;
			}

			return $to_ship;
		}
	}
}
function shipi_fedex_method( $methods )
{
	$methods['hitshippo_fedex'] = 'hitshippo_fedex'; 
	return $methods;
}
add_filter( 'woocommerce_shipping_methods', 'shipi_fedex_method' );
