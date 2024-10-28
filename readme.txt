=== Automated FedEx live/manual rates with shipping labels - HPOS supported ===
Contributors: aarsiv
Tags: fedex, fedex woocommerce, woocommerce fedex, fedex shipping, shipping
Requires at least: 4.6.1
Tested up to: 6.6
Requires PHP: 5.6
Stable tag: 4.7.8
License: GPLv3 or later License
URI: http://www.gnu.org/licenses/gpl-3.0.html

Integrate the FedEx for Domestic and international Shipping. Shipping rates, label, tracking.

== Description ==

FedEx shipping plugin, Integrate the FedEx for Domestic and international Shipping. According to the destination, We are Providing all kind of FedEx Services.

Calculate shipping costs in real time through the FedEx's online quote, based on the products of the cart and the postal codes of origin and destination. It also shows the estimated delivery times based on the moment of calculation.

> Trusted shipping costs obtained directly from FedEx, with different variants or types of services available.
> In case your products do not have dimensions or weight, the module allows you to set dimensions and weight of customized products to calculate the shipping costs.
> Option to add an impact or adjustment to the shipping costs displayed by the carrier before showing it to the customer.
> Improve the shopping experience by allowing you to show estimated delivery times.
> Simple configuration focused on obtaining accurate and adjusted shipping costs for your business model.
> At the moment, no type of contract with the provider is required to make use of this module.
> No override files are required for proper functionality of the module.

= Our Guarantees =

* All our developments are validated by A2Z Group Team.
* Support warranty in the plugin's bugs.
* We can customize the module or make the necessary modifications. Contact us to request an estimate.

= Features =

* Fedex shipping plugin supports SOAP API
* Fedex shipping plugin supports REST API
* Calculates shipping costs in real time through the FedEx online quote service.
* Use of cache in the results to improve the loading speed of the site and to make calculation of the costs only when there is a change in the shopping cart.
* Shipping costs based 100% on the zip code.
* Compatible with orders generated directly from the Back Office.
* Option to perform shipping cost calculations only on the order confirmation page to improve site navigation performance.
* Option to specify the post code of origin for shipments.
* The Shipping cost can be determined in 2 different ways:
	1). Based on weight and cubic volume.
	2). Based on weight and an optimized three-dimensional volume, which simulates the packaging of all products in a 3D box.
* Option to set custom dimensions for your products to be used when calculating shipping costs.
* Option to establish a custom weight for your products to be used when calculating shipping costs.
* Option that allows you to establish an impact on the cost of the shipment before showing it to the customer.
* Option to show estimated delivery times at checkout in each of the available services, with multiple customization options that will allow you to provide even more accurate estimated times.
* Multi-currency compatible.
* Multi-store compatible.
* Full and detailed documentation.

= Where to get API Credentials =

Our module support SOAP and REST API’s.
* To create SOAP API credentials, You have to follow the steps from here [https://www.fedex.com/en-us/developer/web-services.html].
* To create REST API credentials, You have to check here [https://developer.fedex.com/api/en-us/catalog/authorization/docs.html].

= FAQ's =

Q). Do I need credentials provided by FedEx to use the module?
A). Yes, this module makes the estimates directly from the FedEx's online Account.

Q). Does it work with other currencies?
A). Yes, Its done by hooks.

Q). Are the prices updated?
A). Yes, costs are obtained directly from FedEx's online quote in real time.

Q). Is it compatible with other carrier modules?
A). Yes, it is completely compatible.

= What your customers will like =

* Knowing the cost of shipping in real time based on the products of the shopping cart and the delivery address, as well as knowing at the time the estimated delivery times, undoubtedly generates greater confidence to the customer and helps closing the sale.
* Reduce communication time with the store manager.
* It has a greater list of options for shipping services.

= Recommendation =

* It is mandatory that your products have specified dimensions and weight in the "Shipping" tab within the product edition, or that you use the module options to consider these custom values ​​at the time of calculation.
* The quotation system only works for addresses in Anywhere.
* The quotes are based on postal codes, it is mandatory to have this option active in your country settings.

= Useful filters =

1) Filter to adjust shipping cost

> add_filter('hitstacks_fedex_shipping_cost_conversion','fedex_shipping_cost_conversion',10,1);
> function fedex_shipping_cost_conversion($ship_cost){
> 	return $ship_cost+1000;
> }

2) Filter to set Flat rate

> function fedex_shipping_cost_conversion($ship_cost, $pack_weight = 0, $to_country = '', $rate_code = ''){
>	$sample_flat_rates = array("GB"=>array(		//Use ISO 3166-1 alpha-2 as country code
>								"weight_from" => 10,
>								"weight_upto" => 30,
>								"rate" => 2000,
>								"rate_code" => "INTERNATIONAL_FIRST",		//You can add fedex service type here. Get this from Fedex Dev docs - Appendix -> Service Types
>								),
>							"US"=>array(
>								"weight_from" => 10,
>								"weight_upto" => 30,
>								"rate" => 5000,
>								),
>							);
>
>	if(!empty($to_country) && !empty($sample_flat_rates)){
>		if(isset($sample_flat_rates[$to_country]) && ($pack_weight >= $sample_flat_rates[$to_country]['weight_from']) && ($pack_weight <= $sample_flat_rates[$to_country]['weight_upto'])){
>			$flat_rate = $sample_flat_rates[$to_country]['rate'];
>			return $flat_rate;
>		}else{
>			return $ship_cost;
>		}
>	}else{
>		return $ship_cost;
>	}
>
> }
> add_filter('hitstacks_fedex_shipping_cost_conversion','fedex_shipping_cost_conversion',10,4);

3) Set specific service as default on checkout

> add_filter('hitshipo_fedex_default_rate_service', 'hitshipo_fedex_default_rate_service_fun', 10, 3);
> function hitshipo_fedex_default_rate_service_fun($default="no", $service_code="", $dest_country=""){
> 	if(($dest_country=="CA") && ($service_code=="FEDEX_INTERNATIONAL_PRIORITY")){
>		$default = "yes";
>	}
>	return $default;
> }

(Note: Flat rate filter example code will set flat rate for all fedex carriers. Have to add code to check and alter rate for specific carrier.
 While copy paste the code from worpress plugin page may throw error "Undefined constant". It can be fixed by replacing backtick (`) to apostrophe (') inside add_filter())

= About FedEx =

FedEx Corporation is an American multinational courier delivery services company headquartered in Memphis, Tennessee. The name "FedEx" is a syllabic abbreviation of the name of the company's original air division, Federal Express, which was used from 1973 until 2000.

= About [Shipi](https://myshipi.com/) =

We are Web Development Company in France. We are planning for High Quality WordPress, Woocommerce, Edd Downloads Plugins. We are launched on 4th Nov 2018.

= What a2Z Plugins Group Tell to Customers? =

> "Make Your Shop With Smile"

== Screenshots ==
1. Fedex integration settings.
2. Configure Shipper Address.
3. Configure packing algorithm.
4. FedEx Shipping Rates Confguration.
5. Shipping label configuration.
6. Live rates in cart page.
7. Order placed via Fedex carrier.
8. Create manual label/view label on edit order page.

== Changelog ==
= 4.7.8 =
	> [clear] Removed some unusual fields now.
= 4.7.7 =
	> [Imporvement] Handled virtual products have to be skipped.
= 4.7.6 =
	> [Bug Fix] currency conversion issue solved. now this will take a order currecy and convert to the fedex currency. 
= 4.7.5 =
	> [New] If company name is not found, then we sent full name as company name. Previouslly, we sent the first name alone. 
= 4.7.4 =
	> [New] Handled the discount as handling cost.
= 4.7.3 =
	> [Bug Fix] Order total is change while invoice.
= 4.7.2 =
	> [New] Improvement on Shipping label generation UI.
= 4.7.1 =
	> [New] ship price and Order product info fetching ability improved.
= 4.7.0 =
	> [New] Tracking UI Modified
