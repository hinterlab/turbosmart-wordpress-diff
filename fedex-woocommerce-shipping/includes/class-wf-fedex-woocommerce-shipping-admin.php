<?php
class wf_fedex_woocommerce_shipping_admin{
	
	public function __construct(){
		add_action('init', array($this, 'wf_init'));
		
		if (is_admin()) {
			$this->init_bulk_printing();
			add_action('add_meta_boxes', array($this, 'wf_add_fedex_metabox'));
		}
		if ( isset( $_GET['wf_fedex_generate_packages'] ) ) {
			add_action('init', array($this, 'wf_fedex_generate_packages'));
		}

		if (isset($_GET['wf_fedex_createshipment'])) {
			add_action('init', array($this, 'wf_fedex_createshipment'));
		}
		
		if (isset($_GET['wf_fedex_generate_packages_rates'])) {
			add_action('init', array($this, 'wf_fedex_generate_packages_rates'));
		}
		
		if (isset($_GET['wf_fedex_additional_label'])) {
			add_action('init', array($this, 'wf_fedex_additional_label'));
		}
		
		if (isset($_GET['wf_fedex_viewlabel'])) {
			add_action('init', array($this, 'wf_fedex_viewlabel'));
		}

		if (isset($_GET['wf_fedex_void_shipment'])) {
			add_action('init', array($this, 'wf_fedex_void_shipment'));
		}
		
		if (isset($_GET['wf_clear_history'])) {
			add_action('init', array($this, 'wf_clear_history'));
		}

		if (isset($_GET['wf_create_return_label'])) {
			add_action('init', array($this, 'wf_create_return_label'));
		}
		
		if (isset($_GET['wf_fedex_viewReturnlabel'])) {
			add_action('init', array($this, 'wf_fedex_viewReturnlabel'));
		}

		add_action( 'wp_ajax_xa_fedex_validate_credential', array($this,'xa_fedex_validate_credentials'), 10, 1 );
		
		add_action('admin_notices',array(new wf_admin_notice, 'throw_notices'));
		add_action('woocommerce_admin_order_actions_end', array(&$this, 'fedex_action_column'),2);
	}

	public function wf_init(){
		global $woocommerce;
		$this->rateservice_version			  = 22;
		$this->settings 			= get_option( 'woocommerce_'.WF_Fedex_ID.'_settings', null );
		
		$this->weight_dimensions_manual 	= isset($this->settings['manual_wgt_dimensions']) ? $this->settings['manual_wgt_dimensions'] : 'no';
		$this->custom_services 			= isset($this->settings['services']) ? $this->settings['services'] : '';
		$this->image_type 			= isset($this->settings['image_type']) ? $this->settings['image_type'] : '';
		$this->packing_method 			= isset($this->settings[ 'packing_method']) ? $this->settings[ 'packing_method'] : '';
		$this->debug 				= ( $bool = $this->settings[ 'debug' ] ) && $bool == 'yes' ? true : false;
		$this->xa_show_all_shipping_methods	= isset( $this->settings['xa_show_all_shipping_methods'] ) && $this->settings['xa_show_all_shipping_methods'] == 'yes' ? true : false;
		$this->display_fedex_meta_box_on_order = isset($this->settings['display_fedex_meta_box_on_order']) ? $this->settings['display_fedex_meta_box_on_order'] : 'yes';

		if(isset($this->settings['dimension_weight_unit']) && $this->settings['dimension_weight_unit'] == 'LBS_IN'){
			$this->dimension_unit 			= 	'in';
			$this->weight_unit 				= 	'lbs';
		}else{
			$this->dimension_unit 			= 	'cm';
			$this->weight_unit 				= 	'kg';
		}
		
		$this->set_origin_country_state();
	}

	private function set_origin_country_state(){
		$origin_country_state 		= isset( $this->settings['origin_country'] ) ? $this->settings['origin_country'] : '';
		if ( strstr( $origin_country_state, ':' ) ) :
			// WF: Following strict php standards.
			$origin_country_state_array 	= explode(':',$origin_country_state);
			$origin_country 		= current($origin_country_state_array);
			$origin_country_state_array 	= explode(':',$origin_country_state);
			$origin_state   		= end($origin_country_state_array);
		else :
			$origin_country = $origin_country_state;
			$origin_state   = '';
		endif;

		$this->origin_country  	= apply_filters( 'woocommerce_fedex_origin_country_code', $origin_country );
		$this->origin_state 	= !empty($origin_state) ? $origin_state : ( isset($this->settings[ 'freight_shipper_state' ]) ? $this->settings[ 'freight_shipper_state' ] : '') ;
	}

	/**
	 * Display the messages in debug mode.
	 * @param $message mixed Message to display.
	 */
	public function print_debug_message( $message ) {
		if ( $this->debug ) {
			echo "<pre>".print_r($message,true)."</pre>";
		}
	}

	function init_bulk_printing(){
		add_action('admin_footer', 	array($this, 'add_bulk_print_option'));
		add_action('load-edit.php',	array($this, 'perform_bulk_label_actions'));
	}

	function add_bulk_print_option(){
		global $post_type;

		if($post_type == 'shop_order') {
			?>
			<script type="text/javascript">
				jQuery(document).ready(function() {
					jQuery('<option>').val('fedex_print_label').text('<?php _e('Print FedEx label', 'wf-shipping-fedex');?>').appendTo("select[name='action']");
					jQuery('<option>').val('fedex_print_label').text('<?php _e('Print FedEx label', 'wf-shipping-fedex');?>').appendTo("select[name='action2']");

					jQuery('<option>').val('wf_create_shipment').text('<?php _e('Create FedEx label', 'wf-shipping-fedex');?>').appendTo("select[name='action']");
					jQuery('<option>').val('wf_create_shipment').text('<?php _e('Create FedEx label', 'wf-shipping-fedex');?>').appendTo("select[name='action2']");
				});
			</script>
			<?php
		}
	}

	function perform_bulk_label_actions(){
		$wp_list_table = _get_list_table('WP_Posts_List_Table');
		$action = $wp_list_table->current_action();

		if( $action == 'fedex_print_label' ){		
			if( isset($_REQUEST['post']) && is_array($_REQUEST['post']) ){
				$shipping_labels =array();
				
				foreach($_REQUEST['post'] as $order_id){
					$shipmentIds = get_post_meta($order_id, 'wf_woo_fedex_shipmentId', false);
					
					foreach($shipmentIds as $shipmentId) {
						$shipping_labels[] = get_post_meta($order_id, 'wf_woo_fedex_shippingLabel_'.$shipmentId, true);
						$shipping_label_image_type = get_post_meta($order_id, 'wf_woo_fedex_shippingLabel_image_type_'.$shipmentId, true);
						if( $shipping_label_image_type !='PNG' ){
							wf_admin_notice::add_notice( __("Bulk label printing will work with only PNG format, You have selected label format '$shipping_label_image_type'", 'wf-shipping-fedex') );
							return;
						}
					}
				}

				if( empty($shipping_labels) ){
					wf_admin_notice::add_notice( __('No Fedex label found on selected order', 'wf-shipping-fedex') );
					return;
				}

				echo "
				<html>
				<head>
				<script src='https://ajax.googleapis.com/ajax/libs/jquery/3.3.1/jquery.min.js'></script>
				<script>
				$(document).ready(function(){
					$(document).on('click', '#print_all', function(){
						PrintElem('content');
					})
				});
				function PrintElem(elem)
				{
					var mywindow = window.open('', 'PRINT', 'height=400,width=600');

					mywindow.document.write('<html><head><title>' + document.title  + '</title>');
					mywindow.document.write('</head><body >');
					mywindow.document.write('<h1>' + document.title  + '</h1>');
					mywindow.document.write(document.getElementById(elem).innerHTML);
					mywindow.document.write('</body></html>');

					mywindow.document.close(); // necessary for IE >= 10
					mywindow.focus(); // necessary for IE >= 10*/

					mywindow.print();
					mywindow.close();

					return true;
				}
				</script>
				</head>
				<body>
				<style>
				#print_all{
				    text-decoration: none;
				    display: inline-block;
				    width: 75px;
				    margin: 20px auto;
				    background: linear-gradient(#e3647e, #DC143C);
				    text-align: center;
				    color: #fff;
				    padding: 3px 6px;
				    border-radius: 3px;
				    border: 1px solid #e3647e;
				}
				#print_all:hover{
					background: linear-gradient(#e3647e, #dc143c73);
					cursor: pointer;
				}
				</style>
				<div style='text-align: center;padding: 30px;background: #f3f3f3;margin: 0px 10px 10px 10px;'>
					<a id='print_all'>Print all</a><br/>
					<a id='go_back' href='".admin_url('edit.php?post_type=shop_order')."'>Go back</a>
				</div>
				<div id='content' style='text-align: center;'>";
				foreach ($shipping_labels as $key => $label) {
					echo "<img src='data:image/gif;base64,". $label. "' />";
				}
				echo"
				</div>
				</body>
				</html>";
				/*echo"<script>
					window.location = '".admin_url('edit.php?post_type=shop_order')."';
				</script>";*/
				exit();
				// sleep(5);
			}
			else{
				wf_admin_notice::add_notice( __('Please select atleast one order', 'wf-shipping-fedex') );
			}
		}elseif( $action == 'wf_create_shipment' ){
			if( isset($_REQUEST['post']) && is_array($_REQUEST['post']) ){
				foreach($_REQUEST['post'] as $order_id){
					$order = $this->wf_load_order($order_id);
					if($order) {
						$package = get_post_meta( $order->get_id(), '_wf_fedex_stored_packages', true );		
						if( empty($package) ){
							$this->xa_generate_package($order);
						}

						$shipmentIds = get_post_meta($order->get_id(), 'wf_woo_fedex_shipmentId', false);
						if( !empty($shipmentIds) ){
							wf_admin_notice::add_notice( __("Label already generated for order $order_id", 'wf-shipping-fedex'), 'notice' );
							continue;
						}

						$this->wf_create_shipment($order);
						
						$shipmentIds = get_post_meta($order->get_id(), 'wf_woo_fedex_shipmentId', false);
						if( !empty($shipmentIds) ){
							wf_admin_notice::add_notice( __("Label has been generated for order $order_id", 'wf-shipping-fedex'), 'notice' );
						}else{
							wf_admin_notice::add_notice( __("There is some error occured while creating the shiment for order $order_id", 'wf-shipping-fedex'), 'error' );
						}
					}
				}
			}
		}
	}

	public function xa_fedex_validate_credentials(){
		$production			= ( isset($_POST['production']) ) ? $_POST['production'] =='true' : false;
		$account_number		= ( isset($_POST['account_number']) ) ? $_POST['account_number'] : '';
		$meter_number		= ( isset($_POST['meter_number']) ) ? $_POST['meter_number'] : '';
		$api_key			= ( isset($_POST['api_key']) ) ? $_POST['api_key'] : '';
		$api_pass			= ( isset($_POST['api_pass']) ) ? $_POST['api_pass'] : '';
		
		$origin_country_state		= ( isset($_POST['origin_country']) ) ? $_POST['origin_country'] : '';
		$origin						= ( isset($_POST['origin']) ) ? $_POST['origin'] : '';

		$origin_country_state_array 	= explode(':',$origin_country_state);
		$origin_country 		= current($origin_country_state_array);

		$request = array(
			'WebAuthenticationDetail' => array(
				'UserCredential' => array(
					'Key' => $api_key,
					'Password' => $api_pass,
				),
			),
			'ClientDetail' => array(
				'AccountNumber' => $account_number,
				'MeterNumber' => $meter_number,
			),
			'TransactionDetail' => array(
				'CustomerTransactionId' =>  '*** WooCommerce Rate Request ***',
			),
			'Version' => array(
				'ServiceId' => 'crs',
				'Major' => 22,
				'Intermediate' => 0,
				'Minor' => 0,
			),
			'ReturnTransitAndCommit' => 1,
			'RequestedShipment' => array(
				'EditRequestType' => 1,
				'PreferredCurrency' => 'USD',
				'DropoffType' => 'REGULAR_PICKUP',
				'Shipper' => array(
					'Address' => array(
						'PostalCode' => $origin,
						'CountryCode' => $origin_country,
					),
				),
				'Recipient' => array(
					'Address' => array(
						'PostalCode' => '90017',
						'City' => 'LOSE ANGELES',
						'StateOrProvinceCode' => 'CA',
						'CountryCode' => 'US',
					),
				),
				'RequestedPackageLineItems' => array(
					0 => array(
						'SequenceNumber' => 1,
						'GroupNumber' => 1,
						'GroupPackageCount' => 1,
						'Weight' => array(
							'Value' => '5.52',
							'Units' => 'LB',
						),
					),
				),
			),
		);

		

		$this->soap_method = $this->is_soap_available() ? 'soap' : 'nusoap';
		if( $this->soap_method == 'nusoap' && !class_exists('nusoap_client') ){
			require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/nusoap/lib/nusoap.php';
		}
		$client = $this->wf_create_soap_client( plugin_dir_path( dirname( __FILE__ ) ) . 'fedex-wsdl/' . ( $production ? 'production' : 'test' ) . '/RateService_v' . $this->rateservice_version. '.wsdl' );

		$log = new WC_Logger();
		try {
			if( $this->soap_method == 'nusoap' ){
				$response = $client->call( 'getRates', array( 'RateRequest' => $request ) );
				$response = json_decode( json_encode( $response ), false );
				
				if( $client->fault ) {
					$log->add('Fedex Soap Details', " Nusoap Fault : ".print_r($response,true));
				}
				elseif( $client->getError() ) {
					$log->add('Fedex Soap Details', " Nusoap Error : ".print_r($client->getError(),true));
				}
				if( empty($response) ) {
					$log->add( 'Fedex Soap Details', "NuSoap Debug Data : ".print_r(htmlspecialchars($client->debug_str, ENT_QUOTES),true));
				}
			}
			else{
				$response = $client->getRates( $request );
				if( is_soap_fault($response) ) {
					$log->add( 'Fedex Soap Details', " Soap Fault ".print_r($response,true) );
				}
			}
		}
		catch(Exception $e) {
			$log->add( 'Fedex Soap Details', 'Exception Occured - '.print_r($e->getMessage(),true));
		}
		
		$result = array();
		if ( $response  ) {
			if( isset($response->HighestSeverity) && $response->HighestSeverity === 'ERROR' ){
				$error_message = '';
				if( isset($response->Notifications->Message) ) {
					$result = array(
						'message' 	=> $response->Notifications->Message,
						'success'	=> 'no',
					);
				}elseif( isset($response->Notifications[0]->Message) ) {
					$result = array(
						'message' 	=> $response->Notifications[0]->Message,
						'success'	=> 'no',
					);
				}
			}else{
				$result = array(
					'message' 	=> "Successfully authenticated, The credentials are valid. Soapmethod : $this->soap_method",
					'success'	=> 'yes',
				);
			}
		}else{
			$result = array(
				'message' 	=> "An unexpected error occurred. No response from soap client. Unable to authenticate. Soapmethod : $this->soap_method",
				'success'	=> 'no',
			);
		}
		wp_die( json_encode($result) );
	}


	public function wf_fedex_viewReturnlabel(){
		$shipmentDetails = explode('|', base64_decode($_GET['wf_fedex_viewReturnlabel']));

		if (count($shipmentDetails) != 2) {
			exit;
		}
		
		$shipmentId			= $shipmentDetails[0]; 
		$post_id			= $shipmentDetails[1]; 
		$shipping_label			= get_post_meta($post_id, 'wf_woo_fedex_returnLabel_'.$shipmentId, true);
		$shipping_label_image_type	= get_post_meta($post_id, 'wf_woo_fedex_returnLabel_image_type_'.$shipmentId, true);
		
		
		if( empty($shipping_label_image_type) ){
			$shipping_label_image_type = $this->image_type;
		}
		header('Content-Type: application/'.$shipping_label_image_type);
		$label_name = apply_filters( 'ph_fedex_label_name', 'ShipmentArtifact-'.$shipmentId, $shipmentId, $post_id, 'return_label' );
		header('Content-disposition: attachment; filename="' . $label_name . '.'.$shipping_label_image_type.'"');
		print(base64_decode($shipping_label)); 
		exit;
	}

	private function is_soap_available(){
		if( extension_loaded( 'soap' ) ){
			return true;
		}
		return false;
	}

	private function wf_create_soap_client( $wsdl ){
		if( $this->soap_method=='nusoap' ){
			$soapclient = new nusoap_client( $wsdl, 'wsdl' );
		}else{
			$soapclient = new SoapClient( $wsdl, 
				array(
					'trace' =>	true
				)
			);
		}
		return $soapclient;
	}

	public function wf_create_return_label(){

		$user_ok = $this->wf_user_permission();
		if (!$user_ok) 			
			return;

		$return_params = explode('|', base64_decode($_GET['wf_create_return_label']));
		
		if(empty($return_params) || !is_array($return_params) || count($return_params) != 2)
			return;
		
		$shipment_id = $return_params[0]; 
		$order_id =  $return_params[1];


		$this->wf_create_return_shipment( $shipment_id, $order_id );

		if ( $this->debug ) {
			//dont redirect when debug is printed
			die();
		}
		else{
		  wp_redirect(admin_url('/post.php?post='.$order_id.'&action=edit'));
		  exit;
		}

	}
	
	public function wf_clear_history(){
		$user_ok = $this->wf_user_permission();
		if (!$user_ok) 			
			return;
			
		$order_id = base64_decode($_GET['wf_clear_history']);
		
		if(empty($order_id))
			return;
		
		$void_shipments = get_post_meta($order_id, 'wf_woo_fedex_shipment_void',false);
		if(empty($void_shipments))
			return;
		
		foreach($void_shipments as $void_shipment_id){
			delete_post_meta($order_id, 'wf_woo_fedex_packageDetails_'.$void_shipment_id);
			delete_post_meta($order_id, 'wf_woo_fedex_shippingLabel_'.$void_shipment_id);
			delete_post_meta($order_id, 'wf_woo_fedex_service_code'.$void_shipment_id);
			delete_post_meta($order_id, 'wf_woo_fedex_shippingLabel_image_type_'.$void_shipment_id);
			delete_post_meta($order_id, 'wf_woo_fedex_shipmentId',$void_shipment_id);
			delete_post_meta($order_id, 'wf_woo_fedex_shipment_void',$void_shipment_id);
			delete_post_meta($order_id, 'wf_fedex_additional_label_',$void_shipment_id);
		}
		
		delete_post_meta($order_id, 'wf_woo_fedex_shipment_void_errormessage');		
		delete_post_meta($order_id, 'wf_woo_fedex_service_code');
		delete_post_meta($order_id, 'wf_woo_fedex_shipmentErrorMessage');		
					
		wp_redirect(admin_url('/post.php?post='.$order_id.'&action=edit'));
		exit;	
	}	
	
	public function wf_fedex_void_shipment(){
		$user_ok = $this->wf_user_permission();
		if (!$user_ok) 			
			return;
			
		$void_params = explode('||', base64_decode($_GET['wf_fedex_void_shipment']));
		
		if(empty($void_params) || !is_array($void_params) || count($void_params) != 2)
			return;
		
		$shipment_id = $void_params[0]; 
		$order_id =  $void_params[1];			
			
			
		if ( ! class_exists( 'wf_fedex_woocommerce_shipping_admin_helper' ) )
			include_once 'class-wf-fedex-woocommerce-shipping-admin-helper.php';
		
		$woofedexwrapper = new wf_fedex_woocommerce_shipping_admin_helper();
		$tracking_completedata = get_post_meta($order_id, 'wf_woo_fedex_tracking_full_details_'.$shipment_id, true);
		if(!empty($tracking_completedata)){
			$woofedexwrapper->void_shipment($order_id,$shipment_id,$tracking_completedata);
		}
		
		if ( $this->debug ) {
			//dont redirect when debug is printed
			die();
		}
		else{
		  wp_redirect(admin_url('/post.php?post='.$order_id.'&action=edit'));
		  exit;
		}
	}
	
	function wf_load_order( $orderId ){
		if( !$orderId ){
			return false;
		}
		if(!class_exists('wf_order')){
			include_once('class-wf-legacy.php');
		}
		return ( WC()->version < '2.7.0' ) ? new WC_Order( $orderId ) : new wf_order( $orderId );	
	}
	
	private function wf_user_permission($auto_generate=null){
		// Check if user has rights to generate invoices
		$current_minute=(integer)date('i');
		if(!empty($auto_generate) && ($auto_generate==md5($current_minute) || $auto_generate==md5($current_minute+1) ))
		{
			return true;
		}
		$current_user = wp_get_current_user();
		$user_ok = false;
		$wf_roles = apply_filters( 'wf_user_permission_roles', array('administrator', 'shop_manager') );

		if ($current_user instanceof WP_User) {
			$role_ok = array_intersect($wf_roles, $current_user->roles);
			if( !empty( $role_ok ) ){
				$user_ok = true;
			}
		}
		return $user_ok;
	}
	
	public function wf_fedex_createshipment(){
		$user_ok = $this->wf_user_permission(isset($_GET['auto_generate'])?$_GET['auto_generate']:null);
		if (!$user_ok) 			
			return;
		
		$order = $this->wf_load_order($_GET['wf_fedex_createshipment']);
		if (!$order) 
			return;
		
		$shipment_ids = get_post_meta( $_GET['wf_fedex_createshipment'], 'wf_woo_fedex_shipmentId', true );
		if( empty($shipment_ids) ) {
			$this->wf_create_shipment($order);
		}
		else{
			if( $this->debug ) {
				_e( 'Fedex label generation Suspended. Label has been already generated.', 'wf-shipping-fedex' );
			}
			if( class_exists('WC_Admin_Meta_Boxes') ) {
				WC_Admin_Meta_Boxes::add_error( 'Fedex label generation Suspended. Label has been already generated.', 'wf-shipping-fedex' );
			}
		}

		if ( $this->debug ) {
			//dont redirect when debug is printed
			die();
		}
		else{
		  wp_redirect(admin_url('/post.php?post='.$_GET['wf_fedex_createshipment'].'&action=edit'));
		  exit;
		}
	}
	
	public function wf_fedex_viewlabel(){
		$shipmentDetails = explode('|', base64_decode($_GET['wf_fedex_viewlabel']));

		if (count($shipmentDetails) != 2) {
			exit;
		}
		
		$shipmentId = $shipmentDetails[0]; 
		$post_id = $shipmentDetails[1]; 
		$shipping_label = get_post_meta($post_id, 'wf_woo_fedex_shippingLabel_'.$shipmentId, true);
		$shipping_label_image_type = get_post_meta($post_id, 'wf_woo_fedex_shippingLabel_image_type_'.$shipmentId, true);
		
		
		if( empty($shipping_label_image_type) ){
			$shipping_label_image_type = $this->image_type;
		}
		header('Content-Type: application/'.$shipping_label_image_type);
		$label_name = apply_filters( 'ph_fedex_label_name', 'ShipmentArtifact-'.$shipmentId, $shipmentId, $post_id, 'normal_label' );
		header('Content-disposition: attachment; filename="' . $label_name . '.'.$shipping_label_image_type.'"');
		print(base64_decode($shipping_label)); 
		exit;
	}
	
	public function wf_fedex_additional_label(){
		$shipmentDetails = explode('|', base64_decode($_GET['wf_fedex_additional_label']));

		if (count($shipmentDetails) != 3) {
			exit;
		}
		
		$shipmentId = $shipmentDetails[0]; 
		$post_id = $shipmentDetails[1];
		$add_key = $shipmentDetails[2];		
		$additional_labels = get_post_meta($post_id, 'wf_fedex_additional_label_'.$shipmentId, true);
		$additional_label_image_type = get_post_meta($post_id, 'wf_fedex_additional_label_image_type_'.$shipmentId, true);
		
		if( !empty($additional_label_image_type[$add_key])){
			$image_type = $additional_label_image_type[$add_key];
		}else{
			$image_type = $this->image_type;
		}
		if( !empty($additional_labels) && isset($additional_labels[$add_key]) ){
			header('Content-Type: application/'.$image_type);
			$label_name = apply_filters( 'ph_fedex_label_name', 'Addition-doc-'. $add_key .'-'.$shipmentId, $add_key.'-'.$shipmentId, $post_id, 'additional_label' );
			header('Content-disposition: attachment; filename="' . $label_name . '.'.$image_type.'"');
			print(base64_decode($additional_labels[$add_key])); 
		}
		exit;		
	}
	
	private function wf_is_service_valid_for_country($order, $service_code, $dest_country=''){
		$uk_domestic_services = array('FEDEX_DISTANCE_DEFERRED', 'FEDEX_NEXT_DAY_EARLY_MORNING', 'FEDEX_NEXT_DAY_MID_MORNING', 'FEDEX_NEXT_DAY_AFTERNOON', 'FEDEX_NEXT_DAY_END_OF_DAY', 'FEDEX_NEXT_DAY_FREIGHT' );
		
		$shipper_country = $this->origin_country;
		$shipping_country = !empty($dest_country) ? $dest_country : $order->shipping_country;
		
		if( 'GB'==$shipper_country && 'GB'==$shipping_country && in_array($service_code,$uk_domestic_services) ){
			return true;
		}
		$exception_list = array('FEDEX_GROUND','FEDEX_FREIGHT_ECONOMY','FEDEX_FREIGHT_PRIORITY');
		$exception_country = array('US','CA');
		if(in_array($shipping_country,$exception_country) && in_array($service_code,$exception_list)){
			return true;
		}
		
		if( $shipping_country == $this->origin_country ){
			return strpos($service_code, 'INTERNATIONAL_') === false;
		}
		else{
			return  strpos($service_code, 'INTERNATIONAL_') !== false;
		}
		return false; 
	}

	private function is_domestic($order){
		return $this->origin_country == $order->shipping_country;
	}

	private function wf_get_shipping_service($order,$retrive_from_order = false, $shipment_id=false){
		
		if($retrive_from_order == true){
			$service_code = get_post_meta($order->id, 'wf_woo_fedex_service_code'.$shipment_id, true);
			if(!empty($service_code)) return $service_code;
		}
		
		if(!empty($_GET['service'])){			
			$service_arr	=   json_decode(stripslashes(html_entity_decode($_GET["service"])));
			// If all the generated packages has been removed from order then services will be empty
			if( ! empty($service_arr[0]) ) {
				return $service_arr[0];
			}
			elseif( ! $this->debug ){
				$order_id = ( WC()->version < '3.0' ) ? $order->ID : $order->get_id();
				if ( class_exists( 'WC_Admin_Meta_Boxes' ) ) {
					WC_Admin_Meta_Boxes::add_error( __( "FedEx services missing. Label generation has been terminated.", "wf-shipping-fedex" ) );
 				}
				wp_redirect( admin_url( '/post.php?post='.$order_id.'&action=edit') );
				exit();
			}
			else{
				$this->print_debug_message( __( "FedEx services missing. Label generation has been terminated.", "wf-shipping-fedex" ) );
				exit();
			}
		}

		//TODO: Take the first shipping method. It doesnt work if you have item wise shipping method
		$shipping_methods = $order->get_shipping_methods();
		if( ! empty($shipping_methods) ) {
			$shipping_method 		= array_shift($shipping_methods);
			$shipping_method_meta 	= $shipping_method->get_meta('_xa_fedex_method');
			$shipping_method_id 	= ! empty($shipping_method_meta) ? $shipping_method_meta['id'] : $shipping_method['method_id'];

			if( strstr( $shipping_method_id, WF_Fedex_ID ) ) {
				return str_replace( WF_Fedex_ID.':', '', $shipping_method_id );
			}
		}

		if( $this->is_domestic($order) ){
			if( !empty($this->settings['default_dom_service']) ){
				return $this->settings['default_dom_service'];
			}
		}else{
			if( !empty($this->settings['default_int_service']) ){
				return $this->settings['default_int_service'];
			}
		}
	}
	
	public function wf_create_shipment($order){		
		if ( ! class_exists( 'wf_fedex_woocommerce_shipping_admin_helper' ) )
			include_once 'class-wf-fedex-woocommerce-shipping-admin-helper.php';
		
		$woofedexwrapper = new wf_fedex_woocommerce_shipping_admin_helper();
		$serviceCode = $this->wf_get_shipping_service($order,false);
		if( empty($serviceCode) ){
			wf_admin_notice::add_notice( __("Not found any service code", 'wf-shipping-fedex') );
			return false;
		}

		$woofedexwrapper->print_label($order,$serviceCode,$order->id);
	}
	
	public function wf_create_return_shipment( $shipment_id, $order_id ){		
		if ( ! class_exists( 'wf_fedex_woocommerce_shipping_admin_helper' ) )
			include_once 'class-wf-fedex-woocommerce-shipping-admin-helper.php';
		
		$woofedexwrapper = new wf_fedex_woocommerce_shipping_admin_helper();
		$serviceCode = $this->wf_get_shipping_service( $this->wf_load_order($order_id),false);
		$woofedexwrapper->print_return_label( $shipment_id, $order_id, $serviceCode  );		
	}

	public function wf_add_fedex_metabox(){
		// Check whether to show meta box on order is enabled or not
		if( $this->display_fedex_meta_box_on_order == 'no' ) {
			return;
		}
		global $post;
		if (!$post) {
			return;
		}
		if ( in_array( $post->post_type, array('shop_order') )) {
			$order = $this->wf_load_order($post->ID);
			if (!$order) 
				return;
			
			add_meta_box('wf_fedex_metabox', __('Fedex', 'wf-shipping-fedex'), array($this, 'wf_fedex_metabox_content'), 'shop_order', 'advanced', 'default');
		}
	}

	public function wf_fedex_generate_packages(){
		if( !$this->wf_user_permission(isset($_GET['auto_generate'])?$_GET['auto_generate']:null)) {
			echo "You don't have admin privileges to view this page.";
			exit;
		}
		
		$post_id	=	base64_decode($_GET['wf_fedex_generate_packages']);
		$order = $this->wf_load_order( $post_id );
		if ( !$order ) return;
		
		$this->xa_generate_package( $order );
		
		if( ( ! $this->debug ) || ( $this->settings['automate_label_generation'] == 'no' ) ) {
			wp_redirect( admin_url( '/post.php?post='.$post_id.'&action=edit#wf_fedex_metabox') );
		}
		exit;
	}

	private function xa_generate_package( $order ){
		if ( ! class_exists( 'wf_fedex_woocommerce_shipping_admin_helper' ) )
			include_once 'class-wf-fedex-woocommerce-shipping-admin-helper.php';

		$woofedexwrapper	= new wf_fedex_woocommerce_shipping_admin_helper();
		$packages		= $woofedexwrapper->wf_get_package_from_order($order);

		foreach($packages as $package){
			
			$package = apply_filters( 'wf_customize_package_on_generate_label', $package, $order->get_id() );		//Filter to customize the package
			$package_data[] = $woofedexwrapper->get_fedex_packages($package);
		}
		update_post_meta( $order->get_id(), '_wf_fedex_stored_packages', $package_data );		
		do_action( 'wf_after_package_generation', $order->get_id(), $package_data );			//For automatic label generation 
	}

	public function wf_fedex_metabox_content(){
		global $post;
		
		if (!$post) {
			return;
		}

		$order = $this->wf_load_order($post->ID);
		if (!$order) 
			return;			

		$shipmentIds = get_post_meta($order->id, 'wf_woo_fedex_shipmentId', false);
		$shipment_void_ids = get_post_meta($order->id, 'wf_woo_fedex_shipment_void', false);
		
		$shipmentErrorMessage = get_post_meta($order->id, 'wf_woo_fedex_shipmentErrorMessage',true);
		$shipment_void_error_message = get_post_meta($order->id, 'wf_woo_fedex_shipment_void_errormessage',true);
		
		//Only Display error message if the process is not complete. If the Invoice link available then Error Message is unnecessary
		if(!empty($shipmentErrorMessage))
		{
			echo '<div class="error"><p>' . sprintf( __( 'Fedex Create Shipment Error:%s', 'wf-shipping-fedex' ), $shipmentErrorMessage) . '</p></div>';
		}

		if(!empty($shipment_void_error_message)){
			echo '<div class="error"><p>' . sprintf( __( 'Void Shipment Error:%s', 'wf-shipping-fedex' ), $shipment_void_error_message) . '</p></div>';
		}			
		echo '<ul>';
		if (!empty($shipmentIds)) {
			foreach($shipmentIds as $shipmentId) {
				$selected_sevice = $this->wf_get_shipping_service($order,true,$shipmentId);	
				if(!empty($selected_sevice))
					echo "<li>Shipping Service: <strong>$selected_sevice</strong></li>";		
				
				echo '<li><strong>Shipment #:</strong> '.$shipmentId.'</li>';
				$usps_trackingid = get_post_meta($order->id, 'wf_woo_fedex_usps_trackingid_'.$shipmentId, true);
				if(!empty($usps_trackingid)){
					echo "<br><strong>USPS Tracking #:</strong> ".$usps_trackingid;
				}
				if((is_array($shipment_void_ids) && in_array($shipmentId,$shipment_void_ids))){
					echo "<br> This shipment $shipmentId is terminated.";
				}
				$additional_labels = get_post_meta($post->ID, 'wf_fedex_additional_label_'.$shipmentId, true);
				if( ! empty($additional_labels) ) {
					$additional_label_tracking_number = get_post_meta( $post->ID, '_ph_woo_fedex_additional_tracking_number_'.$shipmentId, true );
					if( ! empty($additional_label_tracking_number ) )	echo "<li> Additional Tracking Number #: $additional_label_tracking_number</li>";
				}
				echo '<hr>';
				$packageDetailForTheshipment = get_post_meta($order->id, 'wf_woo_fedex_packageDetails_'.$shipmentId, true);
				if(!empty($packageDetailForTheshipment)){
					foreach($packageDetailForTheshipment as $dimentionKey => $dimentionValue){
						if($dimentionValue){
							echo '<strong>' . $dimentionKey . ': ' . '</strong>' . $dimentionValue ;
							echo '<br />';
						}						
					}
					echo '<hr>';
				}
				$shipping_label = get_post_meta($post->ID, 'wf_woo_fedex_shippingLabel_'.$shipmentId, true);
				if(!empty($shipping_label)){
					$download_url = admin_url('/post.php?wf_fedex_viewlabel='.base64_encode($shipmentId.'|'.$post->ID));?>
					<a class="button tips" href="<?php echo $download_url; ?>" data-tip="<?php _e('Print Label', 'wf-shipping-fedex'); ?>"><?php _e('Print Label', 'wf-shipping-fedex'); ?></a>
					<?php 
				}
				
				if(!empty($additional_labels) && is_array($additional_labels)){
					foreach($additional_labels as $additional_key => $additional_label){
						$download_add_label_url = admin_url('/post.php?wf_fedex_additional_label='.base64_encode($shipmentId.'|'.$post->ID.'|'.$additional_key));?>
						<a class="button tips" href="<?php echo $download_add_label_url; ?>" data-tip="<?php _e('Additional Label', 'wf-shipping-fedex'); ?>"><?php _e('Additional Label', 'wf-shipping-fedex'); ?></a>
						<?php
					}		
				}
				if((!is_array($shipment_void_ids) || !in_array($shipmentId,$shipment_void_ids))){
					$void_shipment_link = admin_url('/post.php?wf_fedex_void_shipment=' . base64_encode($shipmentId.'||'.$post->ID));?>				
					<a class="button tips" href="<?php echo $void_shipment_link; ?>" data-tip="<?php _e('Void Shipment', 'wf-shipping-fedex'); ?>"><?php _e('Void Shipment', 'wf-shipping-fedex'); ?></a>
					<?php 
				}
				$shipping_return_label = get_post_meta($post->ID, 'wf_woo_fedex_returnLabel_'.$shipmentId, true);
				$return_shipment_id = get_post_meta($post->ID, 'wf_woo_fedex_returnShipmetId', true);
				echo '<hr>';
				if(!empty($shipping_return_label)){
					$download_url = admin_url('/post.php?wf_fedex_viewReturnlabel='.base64_encode($shipmentId.'|'.$post->ID) );
					echo '<li><strong>Return Shipment #:</strong> '.$return_shipment_id.'</li>';?>
					<a class="button tips" href="<?php echo $download_url; ?>" data-tip="<?php _e('Print Return Label', 'wf-shipping-fedex'); ?>"><?php _e('Print Return Label', 'wf-shipping-fedex'); ?></a>
					<?php 
				}else{
					$selected_sevice = $this->wf_get_shipping_service($order);	
					echo '<select class="fedex_return_service select">';
					foreach($this->custom_services as $service_code => $service){
						if($service['enabled'] == true ){
							echo '<option value="'.$service_code.'" ' . selected($selected_sevice,$service_code) . ' >'.$service_code.'</option>';
						}	
					}
					echo'</select>'?>
					<a class="button button-primary fedex_create_return_shipment tips" href="<?php echo admin_url( '/post.php?wf_create_return_label='.base64_encode($shipmentId.'|'.$post->ID) ); ?>" data-tip="<?php _e( 'Generate return label', 'wf-shipping-fedex' ); ?>"><?php _e( 'Generate return label', 'wf-shipping-fedex' ); ?></a><?php
				}
				echo '<hr style="border-color:#0074a2"></li>';
			} ?>		
			<?php 
			if(count($shipmentIds) == count($shipment_void_ids)){
				$clear_history_link = admin_url('/post.php?wf_clear_history=' . base64_encode($post->ID));?>				
					<a class="button button-primary tips" href="<?php echo $clear_history_link; ?>" data-tip="<?php _e('Clear History', 'wf-shipping-fedex'); ?>"><?php _e('Clear History', 'wf-shipping-fedex'); ?></a>
					<?php 
			}					
		}
		else {
			$stored_packages	=	get_post_meta( $post->ID, '_wf_fedex_stored_packages', true );
			if(empty($stored_packages)	&&	!is_array($stored_packages)){
				echo '<strong>'.__( 'Auto generate packages.', 'wf-shipping-fedex' ).'</strong></br>';
				?>
				<a class="button button-primary tips fedex_generate_packages" href="<?php echo admin_url( '/post.php?wf_fedex_generate_packages='.base64_encode($post->ID) ); ?>" data-tip="<?php _e( 'Regenerate all the packages', 'wf-shipping-fedex' ); ?>"><?php _e( 'Generate Packages', 'wf-shipping-fedex' ); ?></a><hr style="border-color:#0074a2">
				<?php
			}else{
				$generate_url = admin_url('/post.php?wf_fedex_createshipment='.$post->ID);
 
				echo '<li>';
					echo '<h4>'.__( 'Package(s)' , 'wf-shipping-fedex').': </h4>';
					echo '<table id="wf_fedex_package_list" class="wf-shipment-package-table">';					
						echo '<tr>';
							echo '<th>'.__('Wt.', 'wf-shipping-fedex').'</br>('.$this->weight_unit.')</th>';
							echo '<th>'.__('L', 'wf-shipping-fedex').'</br>('.$this->dimension_unit.')</th>';
							echo '<th>'.__('W', 'wf-shipping-fedex').'</br>('.$this->dimension_unit.')</th>';
							echo '<th>'.__('H', 'wf-shipping-fedex').'</br>('.$this->dimension_unit.')</th>';
							echo '<th>'.__('Insur.', 'wf-shipping-fedex').'</th>';
							echo '<th>';
								echo __('Select Service', 'wf-shipping-fedex');
								echo '<img class="help_tip" style="float:none;" data-tip="'.__( 'Select the FedEx service.', 'wf-shipping-fedex' ).'" src="'.WC()->plugin_url().'/assets/images/help.png" height="16" width="16" />';
							echo '</th>';
							echo '<th>';
								_e('Remove', 'wf-shipping-fedex');
								echo '<img class="help_tip" style="float:none;" data-tip="'.__( 'Remove FedEx generated packages (Beta Version).', 'wf-shipping-fedex' ).'" src="'.WC()->plugin_url().'/assets/images/help.png" height="16" width="16" />';
							echo '</th>';;
						echo '</tr>';
						
						//case of multiple shipping address
						$multiship = get_post_meta( $order->id, '_multiple_shipping', true);
						if( $multiship ){
							$multi_ship_packages  = get_post_meta($post->ID, '_wcms_packages', true);
						}
						
						foreach($stored_packages as $package_group_key	=>	$package_group){
							if( !is_array($package_group) ){
								$package_group = array();
							}
							foreach($package_group as $stored_package_key	=>	$stored_package){
								$dimensions	=	$this->get_dimension_from_package($stored_package);
								$insurance_amount = ! empty($stored_package['InsuredValue']['Amount']) ? $stored_package['InsuredValue']['Amount'] : null;
								
								$insurance_style = ( $this->settings['insure_contents'] == 'yes' ) ? null : 'style="visibility:hidden"';
								if(is_array($dimensions)){
									?>
									<tr>
										<td><input type="text" id="fedex_manual_weight" name="fedex_manual_weight[]" size="2" value="<?php echo $dimensions['Weight'];?>" /></td>	 
										<td><input type="text" id="fedex_manual_length" name="fedex_manual_length[]" size="2" value="<?php echo $dimensions['Length'];?>" /></td>
										<td><input type="text" id="fedex_manual_width" name="fedex_manual_width[]" size="2" value="<?php echo $dimensions['Width'];?>" /></td>
										<td><input type="text" id="fedex_manual_height" name="fedex_manual_height[]" size="2" value="<?php echo $dimensions['Height'];?>" /></td>
										<td><input <?php echo $insurance_style; ?> type="text" id="fedex_manual_insurance" name="fedex_manual_insurance[]" size="2" value="<?php echo $insurance_amount;?>" /></td>
										<td><?php
											$package_dest_country = ( isset( $multi_ship_packages[$package_group_key]['destination']['country'] ) ) ? $multi_ship_packages[$package_group_key]['destination']['country'] : false;
											// $stored_package['service'] is setted by Multivendor Plugin
											if( ! empty($stored_package['service']) ) {
												$selected_sevice = $stored_package['service'];
											}
											else{
												$selected_sevice = $this->wf_get_shipping_service($order);
											}
											echo '<select class="fedex_manual_service select">';
											if($this->xa_show_all_shipping_methods==true)
											{
												$services = include('data-wf-service-codes.php');
												foreach($services as $service_code => $service)
												{
													echo '<option value="'.$service_code.'" ' . selected($selected_sevice,$service_code) . ' >'.$service_code.'</option>';
												
												}
											}
											else
											{   
												foreach($this->custom_services as $service_code => $service)
												{
												if($service['enabled'] == true && $this->wf_is_service_valid_for_country($order,$service_code, $package_dest_country) == true)
												{
													echo '<option value="'.$service_code.'" ' . selected($selected_sevice,$service_code) . ' >'.$service_code.'</option>';
												}
												}
											}?>
										</td>
										<td><a class="wf_fedex_package_line_remove" id="<?php echo $package_group_key.'_'.$stored_package_key; ?>">&#x26D4;</a></td>
										<td>&nbsp;</td>
									</tr>
									<?php
								}
							}
						}
					echo '</table>';
					echo '<a class="button wf-action-button wf-add-button" style="font-size: 12px; margin: 4px;" id="wf_fedex_add_package">Add Package</a>';
				?>
				<a style="margin: 4px;" class="button tips fedex_generate_packages" href="<?php echo admin_url( '/post.php?wf_fedex_generate_packages='.base64_encode($post->ID) ); ?>" data-tip="<?php _e( 'Regenerate all the packages', 'wf-shipping-fedex' ); ?>"><?php _e( 'Generate Packages', 'wf-shipping-fedex' ); ?></a><li/>
				<script type="text/javascript">
					jQuery(document).ready(function(){
						
						
						jQuery('#wf_fedex_add_package').on("click", function(){
							var new_row = '<tr>';
								new_row 	+= '<td><input type="text" id="fedex_manual_weight" name="fedex_manual_weight[]" size="2" value="0"></td>';
								new_row 	+= '<td><input type="text" id="fedex_manual_length" name="fedex_manual_length[]" size="2" value="0"></td>';								
								new_row 	+= '<td><input type="text" id="fedex_manual_width" name="fedex_manual_width[]" size="2" value="0"></td>';
								new_row 	+= '<td><input type="text" id="fedex_manual_height" name="fedex_manual_height[]" size="2" value="0"></td>';
								new_row 	+= '<td><input type="text" id="fedex_manual_insurance" name="fedex_manual_insurance[]" size="2" value=""></td>';
								new_row		+= '<td>';
								new_row		+= '	<select class="fedex_manual_service select">';
								<?php
								if($this->xa_show_all_shipping_methods==true)
											{
												$services = include('data-wf-service-codes.php');
												foreach($services as $service_code => $service)
												{?>
												new_row	+=  '<option value="<?php echo $service_code ?>"><?php echo $service_code ?></option>';
												<?php
												}
											}
											else
											{
												if( ! isset($package_dest_country) ) {
													$package_dest_country = '';
												}
											   foreach($this->custom_services as $service_code => $service)
												   {
													if($service['enabled'] == true && $this->wf_is_service_valid_for_country($order,$service_code, $package_dest_country) == true)
													{
												?>
													new_row		+= '<option value="<?php echo $service_code?>"><?php echo $service_code ?></option>';
												<?php
													}
												}
											} ?>
								new_row		+= '</td>';
								new_row 	+= '<td><a class="wf_fedex_package_line_remove">&#x26D4;</a></td>';
							new_row 	+= '</tr>';
							
							jQuery('#wf_fedex_package_list tr:last').after(new_row);
						});
						
						jQuery(document).on('click', '.wf_fedex_package_line_remove', function(){
							jQuery(this).closest('tr').remove();
						});
					});
				</script><?php
				
				// Rates on order page
				$generate_packages_rates = get_post_meta( $_GET['post'], 'wf_fedex_generate_packages_rates_response', true );
				echo '<li><table id="wf_fedex_service_select" class="wf-shipment-package-table" style="margin-bottom: 5px;margin-top: 15px;box-shadow:.5px .5px 5px lightgrey;">';					
					echo '<tr>';

						echo '<th>Select Service</th>';
						echo '<th style="text-align:left;padding:5px; font-size:13px;">'.__('Service Name', 'wf-shipping-fedex').'</th>';
						echo '<th style="text-align:left; font-size:13px;">'.__('Delivery Time', 'wf-shipping-fedex').' </th>';
						echo '<th style="text-align:left;font-size:13px;">'.__('Cost (', 'wf-shipping-fedex').get_woocommerce_currency_symbol().__(')', 'wf-shipping-fedex').' </th>';
					echo '</tr>';
					
					echo '<tr>';
						echo "<td style = 'padding-bottom: 10px; padding-left: 15px; '><input name='wf_fedex_service_choosing_radio' id='wf_fedex_service_choosing_radio' value='wf_fedex_individual_service' type='radio' checked='true'></td>";
						echo "<td colspan = '3' style= 'padding-bottom: 10px; text-align:left;'><b>Choose Shipping Methods</b> - Select this option to choose FedEx services for each package (Shipping rates will be applied accordingly).</td>";
					echo "</tr>";
					
					if( ! empty($generate_packages_rates) ) {
						$wp_date_format = get_option('date_format');
						foreach( $generate_packages_rates as $key => $rates ) {
							$fedex_service = explode( ':', $rates['id']);
							$est_date_style = empty($rates['meta_data']['fedex_delivery_time']) ? "style=visibility:hidden;" : null;
							echo '<tr style="padding:10px;">';
								echo "<td style = 'padding-left: 15px;'><input name='wf_fedex_service_choosing_radio' id='wf_fedex_service_choosing_radio' value='".end($fedex_service)."' type='radio' ></td>";
								echo "<td>".$rates['label']."</td>";
								echo "<td $est_date_style>".date( $wp_date_format, strtotime($rates['meta_data']['fedex_delivery_time']) )."</td>";
								echo "<td>".( ! empty($this->settings['conversion_rate']) ? $this->settings['conversion_rate'] * $rates['cost'] : $rates['cost'] )."</td>";
							echo "</tr>";
						}
					}

				echo '</table></li>';
				//End of Rates on order page
				?>
				<a style="margin: 4px" class="button tips wf_fedex_generate_packages_rates button-secondary" href="<?php echo admin_url( '/post.php?wf_fedex_generate_packages_rates='.base64_encode($post->ID) ); ?>" data-tip="<?php _e( 'Calculate the Shipping Cost.', 'wf-shipping-fedex' ); ?>"><?php _e( 'Calculate Cost', 'wf-shipping-fedex' ); ?></a>
				<?php

				//If payment method is COD, check COD by default.
				$order_payment_method = get_post_meta( $post->ID, '_payment_method', true );
				$cod_checked = $order_payment_method == 'cod' ? 'checked': '';

				echo '<li><label for="wf_fedex_cod"><input type="checkbox" style="" id="wf_fedex_cod" '.$cod_checked.' name="wf_fedex_cod" class="">' . __('Cash On Delivery', 'wf-shipping-fedex') . '</label></li>';
				echo '<li><label for="wf_fedex_sat_delivery"><input type="checkbox" style="" id="wf_fedex_sat_delivery" name="wf_fedex_sat_delivery" class="">' . __('Saturday Delivery', 'wf-shipping-fedex') . '</label></li>';
				?>
				<li>
					<a style="margin: 4px 4px;" class="button button-primary tips onclickdisable fedex_create_shipment" href="<?php echo $generate_url; ?>" data-tip="<?php _e('Create shipment for the packages', 'wf-shipping-fedex'); ?>"><?php _e('Create Shipment', 'wf-shipping-fedex'); ?></a><hr style="border-color:#0074a2">
				</li>
				<?php
			} ?>
			
			<script type="text/javascript">
				jQuery("a.fedex_generate_packages").on("click", function() {
					location.href = this.href;
				});
				
				// To get rates on order page
				jQuery("a.wf_fedex_generate_packages_rates").one("click", function() {		
					jQuery(this).click(function () { return false; });
						var manual_weight_arr 	= 	jQuery("input[id='fedex_manual_weight']").map(function(){return jQuery(this).val();}).get();
						var manual_height_arr 	= 	jQuery("input[id='fedex_manual_height']").map(function(){return jQuery(this).val();}).get();
						var manual_width_arr 	= 	jQuery("input[id='fedex_manual_width']").map(function(){return jQuery(this).val();}).get();
						var manual_length_arr 	= 	jQuery("input[id='fedex_manual_length']").map(function(){return jQuery(this).val();}).get();

						location.href = this.href + '&weight=' + manual_weight_arr +
							 '&length=' + manual_length_arr
							 + '&width=' + manual_width_arr
							 + '&height=' + manual_height_arr;
						return false;
				});
				jQuery(document).ready( function() {
					jQuery(document).on("change", "#wf_fedex_service_choosing_radio", function(){
						if (jQuery("#wf_fedex_service_choosing_radio:checked").val() == 'wf_fedex_individual_service') {
						jQuery(".fedex_manual_service").prop("disabled", false);
					} else {
						jQuery(".fedex_manual_service").val(jQuery("#wf_fedex_service_choosing_radio:checked").val()).change();
						jQuery(".fedex_manual_service").prop("disabled", true);  
					}
				});
				});
			</script>
			<?php
		}
		echo '</ul>';?>
		<script>
		jQuery("a.fedex_create_return_shipment").one("click", function(e) {
			e.preventDefault();
			service = jQuery(this).prev("select").val();
			var manual_service 		=	'[' + JSON.stringify( service ) + ']';
			location.href = this.href + '&service=' +  manual_service;
		});

		jQuery("a.fedex_create_shipment").one("click", function() {
			
			jQuery(this).click(function () { return false; });
				var manual_weight_arr 	= 	jQuery("input[id='fedex_manual_weight']").map(function(){return jQuery(this).val();}).get();
				var manual_weight 		=	JSON.stringify(manual_weight_arr);
				
				var manual_height_arr 	= 	jQuery("input[id='fedex_manual_height']").map(function(){return jQuery(this).val();}).get();
				var manual_height 		=	JSON.stringify(manual_height_arr);
				
				var manual_width_arr 	= 	jQuery("input[id='fedex_manual_width']").map(function(){return jQuery(this).val();}).get();
				var manual_width 		=	JSON.stringify(manual_width_arr);
				
				var manual_length_arr 	= 	jQuery("input[id='fedex_manual_length']").map(function(){return jQuery(this).val();}).get();
				var manual_length 		=	JSON.stringify(manual_length_arr);
				
				var manual_insurance_arr 	= 	jQuery("input[id='fedex_manual_insurance']").map(function(){return jQuery(this).val();}).get();
				var manual_insurance 		=	JSON.stringify(manual_insurance_arr);

				var manual_service_arr		= [];
				var manual_single_service_arr	= [];
				jQuery('.fedex_manual_service').each(function(){
					manual_service_arr.push( jQuery(this).val() );
					manual_single_service_arr.push(jQuery("input[id='wf_fedex_service_choosing_radio']:checked").val());
				});
				var manual_service 		=	JSON.stringify(manual_service_arr);

				if( jQuery("input[id='wf_fedex_service_choosing_radio']:checked").val() != 'wf_fedex_individual_service' ){
					manual_service	= JSON.stringify(manual_single_service_arr);
				}

				let package_key_arr = [];
				jQuery('.wf_fedex_package_line_remove').each(function () {
					package_key_arr.push(this.id);
				});
				let package_key = JSON.stringify(package_key_arr);


			   location.href = this.href + '&weight=' + manual_weight +
				'&length=' + manual_length
				+ '&width=' + manual_width
				+ '&height=' + manual_height
				+ '&cod=' + jQuery('#wf_fedex_cod').is(':checked')
				+ '&sat_delivery=' + jQuery('#wf_fedex_sat_delivery').is(':checked')
				+ '&insurance=' + manual_insurance
				+ '&service=' + manual_service
				+ '&package_key=' + package_key;
			return false;			
		});
		</script>
		<?php
	}	
	public function get_dimension_from_package($package){
		$dimensions	=	array(
			'Length'	=>	'',
			'Width'		=>	'',
			'Height'	=>	'',
			'Weight'	=>	'',
		);
		
		if(!is_array($package)){ // Package is not valid
			return $dimensions;
		}
		if(isset($package['Dimensions'])){
			$dimensions['Length']	=	$package['Dimensions']['Length'];
			$dimensions['Width']	=	$package['Dimensions']['Width'];
			$dimensions['Height']	=	$package['Dimensions']['Height'];
			$dimensions['dim_unit']	=	$package['Dimensions']['Units'];
		}
		
		$dimensions['Weight']	=	$package['Weight']['Value'];
		$dimensions['weight_unit']	=	$package['Weight']['Units'];
		return $dimensions;
	}

	/**
	 * To calculate the shipping cost on order page.
	 */
	public function wf_fedex_generate_packages_rates() {
		if( ! $this->wf_user_permission() ) {
			echo "You don't have admin privileges to view this page.";
			exit;
		}
		
		$post_id		= base64_decode($_GET['wf_fedex_generate_packages_rates']);
		$length_arr		= explode(',',$_GET['length']);
		$width_arr		= explode(',',$_GET['width']);
		$height_arr		= explode(',',$_GET['height']);
		$weight_arr		= explode(',',$_GET['weight']);

		$get_stored_packages	= get_post_meta( $post_id, '_wf_fedex_stored_packages', true );
		
		if ( ! class_exists( 'wf_fedex_woocommerce_shipping_method' ) ) {
			include_once 'class-wf-fedex-woocommerce-shipping.php';
		}
		$shipping_obj		= new wf_fedex_woocommerce_shipping_method();
		$order			= wc_get_order($post_id);
		
		$shipping_address	= $order->get_address('shipping');

		$address_package	= array(
			'destination'	=> array(
				'country'	=>	$shipping_address['country'],
				'state'		=>	$shipping_address['state'],
				'postcode'	=>	$shipping_address['postcode'],
				'city'		=>	$shipping_address['city'],

			),
		);

		$fedex_requests = array();
		foreach ($get_stored_packages as $package) {
			if(!empty($package))
			{
					foreach ($package as $key => $value) {
						if( ! empty($weight_arr[$key] ) ) {
							$package[$key]['Weight']['Value']	 = $weight_arr[$key];
							$package[$key]['Weight']['Units']	= $shipping_obj->labelapi_weight_unit;
						}
						else {
							wf_admin_notice::add_notice( sprintf( __( 'Fedex rate request failed - Weight is missing in the pacakge. Aborting.', 'wf-shipping-fedex' ) ), 'error' );
							// Redirect to same order page
							wp_redirect( admin_url( '/post.php?post='.$post_id.'&action=edit') );
							exit;		//To stay on same order page
						}
						
						if( ! empty($length_arr[$key]) && ! empty($width_arr[$key]) && ! empty($height_arr[$key]) ) {
							$package[$key]['Dimensions']['Length']	= $length_arr[$key] ;
							$package[$key]['Dimensions']['Width']	= $width_arr[$key] ;
							$package[$key]['Dimensions']['Height']	= $height_arr[$key] ;
							$package[$key]['Dimensions']['Units']	= $shipping_obj->labelapi_dimension_unit;
						}
						else {
							unset($package[$key]['Dimensions']);
						}
					}
			}
			$package_data[] = $package;
			$fedex_reqs = $shipping_obj->get_fedex_requests( $package, $address_package );
			
			if(is_array($fedex_reqs)){
				$fedex_requests	=	array_merge($fedex_requests,	$fedex_reqs);
			}

			// SmartPost Request
			if( $this->custom_services['SMART_POST']['enabled'] && $this->settings['smartpost_hub'] && $address_package['destination']['country'] == 'US') {
				$smart_post_request = $shipping_obj->get_fedex_requests( $package, $address_package, 'smartpost');
				$fedex_requests = array_merge( $fedex_requests, $smart_post_request );
			}
		}
		
		$_GET['order_id'] = $post_id;		// To save the rate request response
		if( $get_stored_packages != $package_data) {
			update_post_meta( $post_id, '_wf_fedex_stored_packages', $package_data );	// Update the packages in database
		}
		
		$shipping_obj->run_package_request( $fedex_requests );
		
		// Redirect to same order page
		wp_redirect( admin_url( '/post.php?post='.$post_id.'&action=edit#wf_fedex_metabox') );
		exit;		//To stay on same order page
	}
	/**
     * Display fedex action button on orders table
     *
     * @access public
     * @return string
     */
    function fedex_action_column($order) {
		$order = $this->wf_load_order( $order );
        $shipmentIds = get_post_meta($order->id, 'wf_woo_fedex_shipmentId', false);
		if (!empty($shipmentIds)) {
			foreach($shipmentIds as $shipmentId) {
				$shipping_label = get_post_meta($order->id, 'wf_woo_fedex_shippingLabel_'.$shipmentId, true);
				if(!empty($shipping_label)){
					$download_url = admin_url('/post.php?wf_fedex_viewlabel='.base64_encode($shipmentId.'|'.$order->id));
					printf('<a class="button tips" href="'.$download_url.'" target="_blank" data-tip="'.__('Fedex Print Label', 'wf-shipping-fedex').'"><img src="'.plugin_dir_url(__DIR__).'resources/images/fedex.png" style="width:auto;"/></a>');
				}
				$additional_labels = get_post_meta($order->id, 'wf_fedex_additional_label_'.$shipmentId, true);
				if(!empty($additional_labels) && is_array($additional_labels)){
					foreach($additional_labels as $additional_key => $additional_label){
						$download_add_label_url = admin_url('/post.php?wf_fedex_additional_label='.base64_encode($shipmentId.'|'.$order->id.'|'.$additional_key));
						printf('<a class="button tips" href="'.$download_add_label_url.'" target="_blank" data-tip="'.__('FedEx Additional Label', 'wf-shipping-fedex').'"><img src="'.plugin_dir_url(__DIR__).'resources/images/fedex_invoice.png" style="width:auto;"/></a>');
					}		
				}
			}
		}
    }
}
new wf_fedex_woocommerce_shipping_admin();
?>
