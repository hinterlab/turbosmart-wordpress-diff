jQuery(function($){
	xa_fedex_show_selected_tab($(".tab_general"),"general");
	$(".tab_general").on("click",function(){
		return xa_fedex_show_selected_tab($(this),"general");
	});
	$(".tab_rates").on("click",function(){
		return xa_fedex_show_selected_tab($(this),"rates");
	});
	$(".tab_labels").on("click",function(){
		return xa_fedex_show_selected_tab($(this),"label");
	});
	$(".tab_packaging").on("click",function(){
		return xa_fedex_show_selected_tab($(this),"packaging");
	});
	$(".tab_pickup").on("click",function(){
		return xa_fedex_show_selected_tab($(this),"pickup");
	});
	$(".tab_freight").on("click",function(){
		return xa_fedex_show_selected_tab($(this),"freight");
	});
	$(".tab_licence").on("click",function(){						
		return xa_fedex_show_selected_tab($(this),"license");
	});

	function xa_fedex_show_selected_tab($element,$tab)
	{	
		$(".nav-tab").removeClass("nav-tab-active");
		$element.addClass("nav-tab-active");
			   
		$(".fedex_rates_tab").closest("tr,h3").hide();
		$(".fedex_rates_tab").next("p").hide();

		$(".fedex_general_tab").closest("tr,h3").hide();
		$(".fedex_general_tab").next("p").hide();

		$(".fedex_label_tab").closest("tr,h3").hide();
		$(".fedex_label_tab").next("p").hide();

		$(".fedex_packaging_tab").closest("tr,h3").hide();
		$(".fedex_packaging_tab").next("p").hide();

		$(".fedex_pickup_tab").closest("tr,h3").hide();
		$(".fedex_pickup_tab").next("p").hide();

		$(".fedex_freight_tab").closest("tr,h3").hide();
		$(".fedex_freight_tab").next("p").hide();

		$(".license_tab_field").closest("tr,h3").hide();
		$(".license_tab_field").next("p").hide();
		
		if($tab=="license"){
			$(".fedex_licence_tab").show();
		}else{
			$(".fedex_licence_tab").hide();
		}

		$(".fedex_"+$tab+"_tab").closest("tr,h3").show();
		$(".fedex_"+$tab+"_tab").next("p").show();

		if( $tab == 'label' ){
			wf_fedex_load_commercialinvoice_image_uploader();
			wf_fedex_return_label_options();
			wf_fedex_custom_shipment_message();
			wf_fedex_automatic_label_generation();
		}
		if( $tab == 'general' ){
			xa_fedex_duties_payer_options();
			xa_fedex_payment_type_options();
			xa_fedex_uk_payment_type_options();
			xa_fedex_au_payment_type_options();
		}
		if( $tab == 'pickup' ){
			wf_fedex_load_pickup_options();
			wf_fedex_load_pickup_address_options();
		}
		if( $tab == 'packaging' ){
			wf_fedex_load_packing_method_options();
			xa_fedex_packing_method_options();
		}
		if( $tab == 'freight' ){
			wf_fedex_load_freight_options()
		}
		if( $tab == 'rates' ){
			wf_fedex_load_availability_options()
		}

		if($tab=="license"){
			$(".woocommerce-save-button").hide();
		}else{
			$(".woocommerce-save-button").show();
		}
		return false;
	}

	// Toggle pickup options pickup
	jQuery('#woocommerce_wf_fedex_woocommerce_shipping_pickup_enabled').click(function(){
		wf_fedex_load_pickup_options();
		wf_fedex_load_pickup_address_options();
	});

	// Toggle Freight options pickup
	jQuery('#woocommerce_wf_fedex_woocommerce_shipping_freight_enabled').click(function(){
		wf_fedex_load_freight_options();
	});

	// Toggle Image uploader
	jQuery('#woocommerce_wf_fedex_woocommerce_shipping_commercial_invoice').click(function(){
		wf_fedex_load_commercialinvoice_image_uploader();
	});

	// Toggle pickup options pickup address
	jQuery('#woocommerce_wf_fedex_woocommerce_shipping_use_pickup_address').click(function(){
		wf_fedex_load_pickup_address_options();
	});

	jQuery('#woocommerce_wf_fedex_woocommerce_shipping_charges_payment_type').change(function(){
		xa_fedex_payment_type_options();
	});

	jQuery('#woocommerce_wf_fedex_woocommerce_shipping_uk_charges_payment_type').change(function(){
		xa_fedex_uk_payment_type_options();
	});

	jQuery('#woocommerce_wf_fedex_woocommerce_shipping_au_charges_payment_type').change(function(){
		xa_fedex_au_payment_type_options();
	});


	//myaccount return label
	jQuery('#woocommerce_wf_fedex_woocommerce_shipping_frontend_retun_label').click(function(){
		wf_fedex_return_label_options();
	});


	jQuery('#woocommerce_wf_fedex_woocommerce_shipping_customs_duties_payer').change(function(){
		xa_fedex_duties_payer_options()
	});

	jQuery('.packing_method').change(function(){
		wf_fedex_load_packing_method_options();
		xa_fedex_packing_method_options();
	});
	jQuery('#woocommerce_wf_fedex_woocommerce_shipping_tracking_shipmentid').click(function(){
		wf_fedex_custom_shipment_message();
	});
	jQuery('#woocommerce_wf_fedex_woocommerce_shipping_automate_package_generation').click(function(){
		wf_fedex_automatic_label_generation();
	});

});

jQuery( document ).ready( function( $ ) {
	if($('#_wf_dry_ice').is(':checked'))
	{
		$('#shipping_product_data ._wf_dry_ice_weight_field').show();
	}
	else
	{
		$('#shipping_product_data ._wf_dry_ice_weight_field').hide();
	}
	$('#_wf_dry_ice').click(function(){
		if($('#_wf_dry_ice').is(':checked'))
		{
			$('#shipping_product_data ._wf_dry_ice_weight_field').show();
		}
		else
		{
			$('#shipping_product_data ._wf_dry_ice_weight_field').hide();
		}
	});
});
function wf_fedex_load_packing_method_options(){
	pack_method	=	jQuery('#woocommerce_wf_fedex_woocommerce_shipping_packing_method').val();      // class packing_method
	jQuery('#packing_options').hide();
	jQuery('.weight_based_option').closest('tr').hide();
	switch(pack_method){
		case 'per_item':
		default:
			break;
			
		case 'box_packing':
			jQuery('#packing_options').show();
			break;
			
		case 'weight_based':
			jQuery('.weight_based_option').closest('tr').show();
			break;
	}
}


function xa_fedex_packing_method_options(){
	pack_method	=	jQuery('.packing_method').val();
	if( pack_method != 'box_packing'){
		jQuery('.speciality_box').closest('tr').hide();
	}

	jQuery('.packing_method').change(function(){
		if( pack_method == 'box_packing'){
			jQuery('.speciality_box').closest('tr').show();
		}else{
			jQuery('.speciality_box').closest('tr').hide();
		}
	});
}

function xa_fedex_payment_type_options(){
	me = jQuery('#woocommerce_wf_fedex_woocommerce_shipping_charges_payment_type');
	if( me.val() =='THIRD_PARTY' ){
		jQuery('.thirdparty_grp').closest('tr').show();
	}else{
		jQuery('.thirdparty_grp').closest('tr').hide();
	}
}

function xa_fedex_uk_payment_type_options(){
	me = jQuery('#woocommerce_wf_fedex_woocommerce_shipping_uk_charges_payment_type');
	if( me.val() =='THIRD_PARTY' ){
		jQuery('.uk_thirdparty_grp').closest('tr').show();
	}else{
		jQuery('.uk_thirdparty_grp').closest('tr').hide();
	}
}

function xa_fedex_au_payment_type_options(){
	me = jQuery('#woocommerce_wf_fedex_woocommerce_shipping_au_charges_payment_type');
	if( me.val() =='THIRD_PARTY' ){
		jQuery('.au_thirdparty_grp').closest('tr').show();
	}else{
		jQuery('.au_thirdparty_grp').closest('tr').hide();
	}
}

function xa_fedex_duties_payer_options(){
	me = jQuery('#woocommerce_wf_fedex_woocommerce_shipping_customs_duties_payer');
	if( me.val() =='THIRD_PARTY' ){
		jQuery('.broker_grp').closest('tr').show();
	}else{
		jQuery('.broker_grp').closest('tr').hide();
	}
}

function wf_fedex_return_label_options(){
	var checked	=	jQuery('#woocommerce_wf_fedex_woocommerce_shipping_frontend_retun_label').is(":checked");
	if(checked){
		jQuery('.wf_settings_return_label').closest('tr').show();
	}else{
		jQuery('.wf_settings_return_label').closest('tr').hide();
	}
}
function wf_fedex_load_freight_options(){
	var checked	=	jQuery('#woocommerce_wf_fedex_woocommerce_shipping_freight_enabled').is(":checked");
	if(checked){
		jQuery('.freight_group').closest('tr').show();
	}else{
		jQuery('.freight_group').closest('tr').hide();
	}
}
function wf_fedex_load_pickup_options(){
	var checked	=	jQuery('#woocommerce_wf_fedex_woocommerce_shipping_pickup_enabled').is(":checked");
	if(checked){
		jQuery('.wf_fedex_pickup_grp').closest('tr').show();
	}else{
		jQuery('.wf_fedex_pickup_grp').closest('tr').hide();
	}
}
function wf_fedex_load_pickup_address_options(){
	var pickup_checked	=	jQuery('#woocommerce_wf_fedex_woocommerce_shipping_use_pickup_address').is(":checked");
	var address_checked	=	jQuery('#woocommerce_wf_fedex_woocommerce_shipping_pickup_enabled').is(":checked");
	if( pickup_checked && address_checked ){
		jQuery('.wf_fedex_pickup_address_grp').closest('tr').show();
	}else{
		jQuery('.wf_fedex_pickup_address_grp').closest('tr').hide();
	}
}

function wf_fedex_load_commercialinvoice_image_uploader(){
	var checked	=	jQuery('#woocommerce_wf_fedex_woocommerce_shipping_commercial_invoice').is(":checked");
	if(checked){
		jQuery('.commercialinvoice-image-uploader').closest('tr').show();
	}else{
		jQuery('.commercialinvoice-image-uploader').closest('tr').hide();
	}
}

function wf_fedex_load_availability_options(){
	me = jQuery('#woocommerce_wf_fedex_woocommerce_shipping_availability');
	if( me.val() =='all' ){
		jQuery('#woocommerce_wf_fedex_woocommerce_shipping_countries').closest('tr').hide();
	}else{
		jQuery('#woocommerce_wf_fedex_woocommerce_shipping_countries').closest('tr').show();
	}
}
function wf_fedex_custom_shipment_message(){
	checked = jQuery('#woocommerce_wf_fedex_woocommerce_shipping_tracking_shipmentid').is(":checked");
	if(checked){
		jQuery('#woocommerce_wf_fedex_woocommerce_shipping_custom_message').closest('tr').show();
	}else{
		jQuery('#woocommerce_wf_fedex_woocommerce_shipping_custom_message').closest('tr').hide();
	}
}
function wf_fedex_automatic_label_generation(){
	checked = jQuery('#woocommerce_wf_fedex_woocommerce_shipping_automate_package_generation').is(":checked");
	if(checked){
		jQuery('#woocommerce_wf_fedex_woocommerce_shipping_automate_label_generation').closest('tr').show();
	}else{
		jQuery('#woocommerce_wf_fedex_woocommerce_shipping_automate_label_generation').closest('tr').hide();
	}
}
jQuery( document ).ready( function( $ ) {
	$('#xa_fedex_validate_credentials, #xa_fedex_uk_validate_credentials, #xa_fedex_au_validate_credentials').on('click', function( event ){
		jQuery( ".fedex-validation-result").html('<span style="float:left" class="spinner is-active"&nbsp;</span>' );
		event.preventDefault();
		var country = $(this).data('country');
		console.log(country);
		var data = {
			'action'		: 'xa_fedex_validate_credential',
			'production'	: $('#woocommerce_wf_fedex_woocommerce_shipping_production').is(":checked") ? true : false,
			'account_number': $('#woocommerce_wf_fedex_woocommerce_shipping_account_number').val(),
			'meter_number'	: $('#woocommerce_wf_fedex_woocommerce_shipping_meter_number').val(),
			'api_key'		: $('#woocommerce_wf_fedex_woocommerce_shipping_api_key').val(),
			'api_pass'		: $('#woocommerce_wf_fedex_woocommerce_shipping_api_pass').val(),
			'origin'		: $('#woocommerce_wf_fedex_woocommerce_shipping_origin').val(),
			'origin_country': $('#woocommerce_origin_country_state').val(),
		};
		if( country != '' ){
			if( country == 'uk' ){
				var data = {
					'action'		: 'xa_fedex_validate_credential',
					'production'	: $('#woocommerce_wf_fedex_woocommerce_shipping_uk_production').is(":checked") ? true : false,
					'account_number': $('#woocommerce_wf_fedex_woocommerce_shipping_uk_account_number').val(),
					'meter_number'	: $('#woocommerce_wf_fedex_woocommerce_shipping_uk_meter_number').val(),
					'api_key'		: $('#woocommerce_wf_fedex_woocommerce_shipping_uk_api_key').val(),
					'api_pass'		: $('#woocommerce_wf_fedex_woocommerce_shipping_uk_api_pass').val(),
					'origin'		: $('#woocommerce_wf_fedex_woocommerce_shipping_uk_origin').val(),
					'origin_country': $('#woocommerce_uk_origin_country_state').val(),
				};
			} else if( country == 'au' ){
				var data = {
					'action'		: 'xa_fedex_validate_credential',
					'production'	: $('#woocommerce_wf_fedex_woocommerce_shipping_au_production').is(":checked") ? true : false,
					'account_number': $('#woocommerce_wf_fedex_woocommerce_shipping_au_account_number').val(),
					'meter_number'	: $('#woocommerce_wf_fedex_woocommerce_shipping_au_meter_number').val(),
					'api_key'		: $('#woocommerce_wf_fedex_woocommerce_shipping_au_api_key').val(),
					'api_pass'		: $('#woocommerce_wf_fedex_woocommerce_shipping_au_api_pass').val(),
					'origin'		: $('#woocommerce_wf_fedex_woocommerce_shipping_au_origin').val(),
					'origin_country': $('#woocommerce_au_origin_country_state').val(),
				};
			}
		}

		jQuery.post(ajaxurl, data, function(response) {
			response = JSON.parse(response);
			if( response.success=='yes' ){
				$(".fedex-validation-result-"+country).html('<span style="color: green;">'+response.message+'</span>')
			}else{
				$(".fedex-validation-result-"+country).html('<span style="color: red">'+response.message+'</span>')
			}
		});
	});
	

	
	var file_frame;
	$('#company_logo_picker').on('click', function( event ){

		file_frame = wp.media.frames.file_frame = wp.media({
			title: 'Select a image to set Company Logo on Commercial invoice',
			button: {
				text: 'Use this image',
			},
			multiple: false
		});
		file_frame.on( 'select', function() {
			$( "#company_logo_result").html('<span style="float:left" class="spinner is-active"&nbsp;</span>' );

			attachment = file_frame.state().get('selection').first().toJSON();
			$( '#woocommerce_wf_fedex_woocommerce_shipping_company_logo' ).val( attachment.url );
			
			var data = {
				'action': 'xa_fedex_upload_image',
				'image': attachment.url,
				'image_id': 'IMAGE_1' //If changed image id here, Change in admin_helper.php also
			};

			// since 2.8 ajaxurl is always defined in the admin header and points to admin-ajax.php
			jQuery.post(ajaxurl, data, function(response) {
				response = JSON.parse(response);
				if( response.success==true ){
					$("#company_logo_result").html('<span style="color: #35a335;">'+response.message+'</span>')
				}else{
					$("#company_logo_result").html('<span style="color: #d83434;">'+response.message+'</span>')
				}
			});
		});
		file_frame.open();
	});
	
	$('#digital_signature_picker').on('click', function( event ){
		file_frame = wp.media.frames.file_frame = wp.media({
			title: 'Select a image to set Digital Signature on Commercial invoice',
			button: {
				text: 'Use this image',
			},
			multiple: false
		});
		file_frame.on( 'select', function() {
			$( "#digital_signature_result").html('<span style="float:left" class="spinner is-active"&nbsp;</span>' );
			attachment = file_frame.state().get('selection').first().toJSON();
			$( '#woocommerce_wf_fedex_woocommerce_shipping_digital_signature' ).val( attachment.url );
			var data = {
				'action': 'xa_fedex_upload_image',
				'image': attachment.url,
				'image_id': 'IMAGE_2' //If changed image id here, Change in admin_helper.php also
			};

			// since 2.8 ajaxurl is always defined in the admin header and points to admin-ajax.php
			jQuery.post(ajaxurl, data, function(response) {
				response = JSON.parse(response);
				if( response.success==true ){
					$("#digital_signature_result").html('<span style="color: #35a335;">'+response.message+'</span>')
				}else{
					$("#digital_signature_result").html('<span style="color: #d83434;">'+response.message+'</span>')
				}
			});
		});
		file_frame.open();
	});

});