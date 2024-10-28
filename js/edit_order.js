jQuery(document).ready(function(){
	jQuery('.shipi_fedex_add_cus_pack').click(function() {
		var ven = jQuery(this).val();
		console.log(ven, "ven");
		if (jQuery(this).is(":checked")) {
			var prods = jQuery("#shipi_fedex_prods_"+ven).val();
			console.log(prods, "prods");
			var packs = jQuery("#shipi_fedex_packs_"+ven).val();
			console.log(packs, "packs");
			if (prods != "") {
				prods = JSON.parse(atob(prods));
			}
			console.log(prods, "prods1");
			if (packs != "") {
				packs = JSON.parse(atob(packs));
				var packs_html = "<table>";
				packs_html += '<thead><tr><th></th><th>Width</th><th>Height</th><th>Length</th><th>Unit</th><th>Weight</th><th>Unit</th><th></th></tr></thead><tbody id="shipi_fedex_pack_tb_'+ven+'">';
				for (var i = 0; i < packs.length; i++) {
					packs_html += '<tr>';
					packs_html += '<td class="shipi_fedex_pack_id">';
					packs_html += '<input style="width:10px;" type="checkbox">';
					packs_html += '</td>';
					packs_html += '<td>';
					var height = (packs[i]["Dimensions"] !== undefined) ? packs[i]["Dimensions"]["Height"] : 1;
					packs_html += '<input name="shipi_fedex_pack_height_'+ven+'[]" style="width:60px;" type="text" value="'+height+'">';
					packs_html += '</td>';
					packs_html += '<td>';
					var length= (packs[i]["Dimensions"] !== undefined) ? packs[i]["Dimensions"]["Length"] : 1;
					packs_html += '<input name="shipi_fedex_pack_length_'+ven+'[]" style="width:60px;" type="text" value="'+length+'">';
					packs_html += '</td>';
					packs_html += '<td>';
					var width = (packs[i]["Dimensions"] !== undefined) ? packs[i]["Dimensions"]["Width"] : 1;
					packs_html += '<input name="shipi_fedex_pack_width_'+ven+'[]" style="width:60px;" type="text" value="'+width+'">';
					packs_html += '</td>';
					packs_html += '<td>';
					packs_html += '<select name="shipi_fedex_pack_dim_unit_'+ven+'[]" style="width:60px;">';
					if (packs[i]["Dimensions"] !== undefined && packs[i]["Dimensions"]["Units"] == "CM") {
						packs_html += '<option value="CM" selected>CM</option>';
						packs_html += '<option value="IN">IN</option>';
					} else {
						packs_html += '<option value="CM">CM</option>';
						packs_html += '<option value="IN" selected>IN</option>';
					}
					packs_html += '</select>';
					packs_html += '</td>';
					packs_html += '<td>';
					packs_html += '<input name="shipi_fedex_pack_weight_'+ven+'[]" style="width:60px;" type="text" value="'+packs[i]["Weight"]["Value"]+'" required>';
					packs_html += '</td>';
					packs_html += '<td>';
					packs_html += '<select class="shipi_fedex_pack_multi" name="shipi_fedex_pack_weg_unit_'+ven+'[]">';
					if (packs[i]["Weight"]["Units"] == "KG") {
						packs_html += '<option value="KG" selected>KG</option>';
						packs_html += '<option value="LB">LB</option>';
					} else {
						packs_html += '<option value="KG">KG</option>';
						packs_html += '<option value="LB" selected>LB</option>';
					}
					packs_html += '</select>';
					packs_html += '</td>';
					packs_html += '<td>';
					packs_html += '<input name="shipi_fedex_pack_cost_'+ven+'[]" style="width:60px;" type="number" value="'+packs[i]["InsuredValue"]["Amount"]+'" hidden>';
					packs_html += '</td>';
					packs_html += '</tr>';
				}
				packs_html += '</tbody></table>';
				packs_html += '<button class="button button-secondary" style="margin:5px;" value="'+ven+'" onClick="addPack(event, this)">Add Pack(s)</button><button class="button button-secondary" style="margin:5px;" value="'+ven+'" onClick="delPack(event, this)">Remove Pack(s)</button>';
				
				jQuery("#shipi_fedex_cus_packs_"+ven).html(packs_html);
				jQuery("#shipi_fedex_cus_packs_"+ven).show();
			}
		}else{
			jQuery("#shipi_fedex_cus_packs_"+ven).html("");
			jQuery("#shipi_fedex_cus_packs_"+ven).hide();
		}
	});
});

function addPack(event, data) {
	event.preventDefault();
	var ven = jQuery(data).val();
	var i = jQuery("#shipi_fedex_pack_tb_"+ven).children().length;
	packs_html = '<tr>';
	packs_html += '<td class="shipi_fedex_pack_id">';
	packs_html += '<input style="width:10px;" type="checkbox">';
	packs_html += '</td>';
	packs_html += '<td>';
	packs_html += '<input name="shipi_fedex_pack_width_'+ven+'[]" style="width:60px;" type="text" value="1">';
	packs_html += '</td>';
	packs_html += '<td>';
	packs_html += '<input name="shipi_fedex_pack_height_'+ven+'[]" style="width:60px;" type="text" value="1">';
	packs_html += '</td>';
	packs_html += '<td>';
	packs_html += '<input name="shipi_fedex_pack_length_'+ven+'[]" style="width:60px;" type="text" value="1">';
	packs_html += '</td>';
	packs_html += '<td>';
	packs_html += '<select name="shipi_fedex_pack_dim_unit_'+ven+'[]" style="width:60px;">';
	packs_html += '<option value="CM">CM</option>';
	packs_html += '<option value="IN">IN</option>';
	packs_html += '</select>';
	packs_html += '</td>';
	packs_html += '<td>';
	packs_html += '<input name="shipi_fedex_pack_weight_'+ven+'[]" style="width:60px;" type="text" value="1" required>';
	packs_html += '</td>';
	packs_html += '<td>';
	packs_html += '<select name="shipi_fedex_pack_weg_unit_'+ven+'[]" style="width:60px;">';
	packs_html += '<option value="KG">KG</option>';
	packs_html += '<option value="LB">LB</option>';
	packs_html += '</select>';
	packs_html += '</td>';
	packs_html += '<td>';
	packs_html += '<input name="shipi_fedex_pack_cost_'+ven+'[]" style="width:60px;" type="number" value="" hidden>';
	packs_html += '</td>';
	packs_html += '</tr>';
	jQuery("#shipi_fedex_pack_tb_"+ven).append(packs_html);
}
function delPack(event, data) {
	event.preventDefault();
	var ven = jQuery(data).val();
	jQuery("#shipi_fedex_pack_tb_"+ven).find('.shipi_fedex_pack_id input:checked').each(function(){
    	jQuery(this).closest('tr').remove().find('input').val('');
    });
}