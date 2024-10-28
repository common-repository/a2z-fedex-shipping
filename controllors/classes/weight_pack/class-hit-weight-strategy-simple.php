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

	if(!class_exists('WeightPackSimple')){
		class WeightPackSimple extends WeightPackStrategy{
			public function __construct(){
				parent::__construct();
			}

			public function pack_items(){
				$items=$this->get_packable_items();
				$boxes			=	array();
				$total_weight	=	0;
				foreach($items as $item){
					$total_weight	+=	$item['weight'];					
				}
				$max_weight	=	$this->get_max_weight();
				if(!is_numeric($max_weight)){
					$result	=	$this->pack_util->pack_all_items_into_one_box($items);
				}else{
					if(!$total_weight || !$max_weight){
						$result	=	new WeightPackResult();
						$result->set_error('Invalid weight entered for box or order total weight is zero');
					}else{
						do{
							$pack_weight	=	($total_weight/$max_weight)>1?$max_weight:$total_weight;
							$boxes[]	=	array(
								'weight'	=>	$pack_weight
							);
							$total_weight	=	$total_weight-$pack_weight;
						}while(	$total_weight	);

						$result	=	new WeightPackResult();
						$result->set_packed_boxes($boxes);
					}
				}
				$this->set_result($result);
			}
		}
	}