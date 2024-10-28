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

	if(!class_exists('WeightPacketUtil')){
		class WeightPacketUtil{		
			public function pack_items_into_weight_box($items,	$max_weight){
				$boxes		=	array();
				$unpacked	=	array();
				foreach($items as $item){
					$fitted			=	false;
					$item_weight	=	$item['weight'];
					foreach($boxes as $box_key	=>	$box){
						if(($max_weight-$box['weight'])	>=	$item_weight){
							$boxes[$box_key]['weight']				=	$boxes[$box_key]['weight']+$item_weight;
							$boxes[$box_key]['items'][]				=	$item['data'];
							$fitted=true;
						}
					}
					if(!$fitted){
						if($item_weight	<=	$max_weight){
							$boxes[]	=	array(
								'weight'				=>	$item_weight,
								'items'					=>	array($item['data']),
							);
						}else{
							$unpacked[]	=	array(
								'weight'				=>	$item_weight,
								'items'					=>	array($item['data']),
							);
						}					
					}
				}
				$result	=	new WeightPackResult();
				$result->set_packed_boxes($boxes);
				$result->set_unpacked_items($unpacked);
				return $result;
			}

			public function pack_all_items_into_one_box($items){
				$boxes			=	array();
				$total_weight	=	0;
				$box_items			=	array();
				foreach($items as $item){
					$total_weight	=	$total_weight	+	$item['weight'];
					$box_items[]		=	$item['data'];
				}
				$boxes[]	=	array(
					'weight'	=>	$total_weight,
					'items'		=>	$box_items
				);
				$result	=	new WeightPackResult();
				$result->set_packed_boxes($boxes);
				return $result;
			}
		}
	}