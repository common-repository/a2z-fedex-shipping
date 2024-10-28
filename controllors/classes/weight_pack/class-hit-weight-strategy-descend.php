<?php
	/**
* 2007-2018 PrestaShop
*
* NOTICE OF LICENSE
*
* This source file is subject to the Academic Free License (AFL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/afl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade PrestaShop to newer
* versions in the future. If you wish to customize PrestaShop for your
* needs please refer to http://www.prestashop.com for more information.
*
*  @author    PrestaShop SA <contact@prestashop.com>
*  @copyright 2007-2018 PrestaShop SA
*  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*/

	if(!class_exists('WeightPackDescend')){
		class WeightPackDescend extends WeightPackStrategy{
			public function __construct(){
				parent::__construct();
			}

			public function pack_items(){
				$items=$this->get_packable_items();
				usort($items,	array($this,	'sort_items'));

				$max_weight	=	$this->get_max_weight();
				if(is_numeric($max_weight)){
					$result	=	$this->pack_util->pack_items_into_weight_box($items,	$max_weight);
				}else{
					$result	=	$this->pack_util->pack_all_items_into_one_box($items);
				}			
				$this->set_result($result);
			}

			private function sort_items($a,	$b){
				$weight_a	=	(float) ($a['weight']);
				$weight_b	=	(float) ($b['weight']);
				if ($weight_a == $weight_b) {
					return 0;
				}
				return ($weight_a < $weight_b) ? +1 : -1;
			}
		}
	}