<?php
/**
 * Woocommerce product calendar plugin.
 */

class Woocommerce_product_calendar {

	const VERSION = '1.0';
	
	protected $plugin_slug = 'woocommerce-product-calendar';

	protected static $instance = null;

	private function __construct() {

		/* Load java scripts & style sheet. */
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_styles_scripts' ) );

		/**
		 *  @action hook woocommerce_single_product_summary defined in custom woocommerce/content-single-product.php template file (in active theme folder)
		 */
		add_action( 'woocommerce_before_add_to_cart_button', array( $this,'checkout_woocommerce_single_product_calendar' ) );	/* displaying datepicker on single product page */

		/* updating delivery date in cart and woo subscription */
		add_filter( 'woocommerce_add_cart_item_data', 			array( $this, 'wdm_add_item_data' ), 1, 2);
		add_filter( 'woocommerce_get_cart_item_from_session',	array( $this, 'wdm_get_cart_items_from_session' ), 1, 3 );
		add_action( 'woocommerce_add_order_item_meta', 			array( $this, 'wdm_add_values_to_order_item_meta' ), 1, 2);
		add_filter( 'woocommerce_order_items_meta_display', 	array( $this, 'aopmc_custom_order_items_meta_display' ), 1, 2 );
		add_action( 'subscriptions_activated_for_order', 		array( $this, 'aopmc_activated_subscription' ), 10, 1 );
		/* add_action( 'subscriptions_created_for_order', 		array( $this, 'aopmc_activated_subscription' ), 10, 1 ); */
		
		add_filter( 'woocommerce_subscription_price_string', array( $this, 'cart_subscription_price03_format' ), 10, 2 );
		remove_filter( 'wcs_cart_totals_order_total_html', 'wcs_add_cart_first_renewal_payment_date' );

		if ( is_admin() ) {	add_action( 'woocommerce_admin_order_data_after_billing_address', array( $this, 'my_custom_checkout_field_display_admin_order_meta' ), 10, 1 ); }


		/* Add ajax handler for choosing shipping address on checkout */
		add_action( 'wp_ajax_process_request', array( $this, 'ajax_checkout_process_request' ) );
		add_action( 'wp_ajax_nopriv_process_request', array( $this, 'ajax_checkout_process_request' ) );

		add_action('wp_ajax_add_user_custom_deliveryDate', array( $this, 'add_user_custom_deliveryDate_callback' ) );
		add_action('wp_ajax_nopriv_add_user_custom_deliveryDate', array( $this, 'add_user_custom_deliveryDate_callback' ) );
		
	}

	public function cart_subscription_price03_format( $subscription_string, $subscription_details ) {
		/* following can be customized. No need yet to do so. */
		return $subscription_string;
	}
	
	public static function activate(  ) {

		self::single_activate();

	}

	private static function single_activate() {
		global $woocommerce;

		$page_id = woocommerce_get_page_id( 'custom_pickup_address' );

		if ( $page_id == - 1 ) {
			// get the checkout page
			$account_id = woocommerce_get_page_id( 'myaccount' );

			// add page and assign
			$page = array(
				'menu_order'     => 0,
				'comment_status' => 'closed',
				'ping_status'    => 'closed',
				'post_author'    => 1,
				'post_content'   => '[woocommerce_custom_pickup_address]',
				'post_name'      => 'custom-pickup-address',
				'post_parent'    => $account_id,
				// TODO: add textdomain as plugin slug
				'post_title'     => __( 'Edit Pickup Address', 'woocommerce' ),
				'post_type'      => 'page',
				'post_status'    => 'publish',
				'post_category'  => array( 1 )
			);

			$page_id = wp_insert_post( $page );

			update_option( 'woocommerce_multiple_shipping_addresses_page_id', $page_id );
		}
	}

	public static function deactivate( $network_wide ) {

		self::single_deactivate();


	}

	private static function single_deactivate() {
		// Nothing here for now...
	}

	/**
	 * Return an instance of this class.
	 */
	public static function get_instance() {

		// If the single instance hasn't been set, set it now.
		if ( null == self::$instance ) {
			self::$instance = new self;
		}

		return self::$instance;
	}

	/**
	 * Register and enqueue public-facing style sheet.
	 *
	 * Register and enqueues public-facing JavaScript files.
	 */
	public function enqueue_styles_scripts() {
		
		wp_enqueue_script('jquery-ui-datepicker');
		
		wp_enqueue_script( $this->plugin_slug . '-plugin-script', plugins_url( 'js/public.js', __FILE__ ), array( 'jquery' ), self::VERSION );
		wp_localize_script( $this->plugin_slug . '-plugin-script', 'WCMA_Ajax', array(
				'ajaxurl'               => admin_url( 'admin-ajax.php' ),
				'id'                    => 0,
				'wc_ajax_function' => wp_create_nonce( 'wc_ajax_function-ajax-nonce' )
			)
		);

		wp_enqueue_style( $this->plugin_slug . '-plugin-styles', plugins_url( 'css/public.css', __FILE__ ), array(), self::VERSION );
		wp_enqueue_style('jquery-style', '//ajax.googleapis.com/ajax/libs/jqueryui/1.8.2/themes/smoothness/jquery-ui.css');

	}
	
	public function checkout_woocommerce_single_product_calendar(){
		global $woocommerce;
		include( 'variable.php' );
	}

	/**
	 * Functions used to update custom delivery date in cart
	 */
    function wdm_add_item_data($cart_item_data,$product_id) {
	        global $woocommerce;
	        session_start();
			
			$new_value = array();
			
	        if ( isset( $_SESSION['user_custom_deliveryDate'] ) ) {
				$new_value['user_custom_deliveryDate'] = $_SESSION['user_custom_deliveryDate'];
	        }

			
	        if( empty( $new_value ) ) {
	            return $cart_item_data;
	        } else {    
	            if( empty( $cart_item_data ) ){
	                return $new_value;
	            }else{
	                return array_merge( $cart_item_data, $new_value );
				}
	        }
			
			unset( $_SESSION['user_custom_deliveryDate'] );

	    }

    function wdm_get_cart_items_from_session( $item, $values, $key ) {

			if ( array_key_exists( 'user_custom_deliveryDate', $values ) ) {
				$item['user_custom_deliveryDate'] = $values['user_custom_deliveryDate'];
	        } 

	        return $item;
	    }


	function wdm_add_values_to_order_item_meta( $item_id, $values ) {
	        global $woocommerce, $wpdb;
			
	        $user_custom_deliveryDate = $values['user_custom_deliveryDate'];
	        if( !empty( $user_custom_deliveryDate ) ) {
	            wc_add_order_item_meta( $item_id,'user_custom_deliveryDate', $user_custom_deliveryDate );
	        }

	  }

	/**
	 *  Displaying delivery date and pickup dates in order details on ordera page + emails
	 */
	function aopmc_custom_order_items_meta_display( $output, $order_items_meta ) {
		
			$output     = '';
			$flat 		= false; 
			$hideprefix = '_';
			
			$formatted_meta = $order_items_meta->get_formatted( $hideprefix );
			if ( ! empty( $formatted_meta ) ) {
				$meta_list = array();
				foreach ( $formatted_meta as $meta ) {
					if ( $flat ) {
						$meta_list[] = wp_kses_post( $meta['label'] . ': ' . $meta['value'] );
					} else {
						$meta_key = (array_key_exists( 'key', $meta ))?$meta['key']:strtolower($meta['label']);
						
						if ( $meta_key == 'user_custom_deliveryDate' ){
							$meta_lable = 'Delivery date';
						} else {
							$meta_lable = $meta['label'];
						}
						
						$meta_list[] = '
							<dt class="variation-' . sanitize_html_class( sanitize_text_field( $meta_key ) ) . '">' . $meta_lable . ':</dt>
							<dd class="variation-' . sanitize_html_class( sanitize_text_field( $meta_key ) ) . '">' . wp_kses_post( wpautop( make_clickable( $meta['value'] ) ) ) . '</dd>
						';
					}
				}
				if ( ! empty( $meta_list ) ) {
					if ( $flat ) {
						$output .= implode( $delimiter, $meta_list );
					} else {
						$output .= '<dl class="variation">' . implode( '', $meta_list ) . '</dl>';
					}
				}
			}
			return $output;
		}


	/**
	 *  Display custom data in order detail page in admin
	 */
	function my_custom_checkout_field_display_admin_order_meta( $order ) {

		$orderID = $order->id;
		$userID = $order->user_id;
		$items = $order->get_items();

		foreach( $items as $itemID => $oItem ){
			$product_id = $oItem['product_id'];
			$subscription_key = WC_Subscriptions_Manager::get_subscription_key( $orderID, $product_id );
			$subscriptionObj = WC_Subscriptions_Manager::get_subscription( $subscription_key );

			$user_custom_deliveryDate = '';
			
			if( isset ( $oItem['user_custom_deliveryDate'] ) ){
				$user_custom_deliveryDate = $oItem['user_custom_deliveryDate'];
			}

			echo '<p><strong>'. __('Product'). ':</strong> '. $oItem['name'] .'</p>';

			if( !empty( $user_custom_deliveryDate ) ){ echo '<p><strong>'. __( 'Delivery date' ) .':</strong> '. $user_custom_deliveryDate .'</p>';}

		}

	}

	/**
	 *  updating subscription dates:
	 *  start date
	 *  expiry date
	 *  next payment date
	 */
	function aopmc_activated_subscription( $order ) {
	
		if ( ! is_object( $order ) ) {
			if( !function_exists( 'wc_get_order' ) ){
				$order = new WC_Order( $order );
			} else {
				$order = wc_get_order( $order );
			}
		}
		
		$orderID = $order->id;
		$userID = $order->user_id;
		$items = $order->get_items();
														
		foreach( $items as $itemID => $oItem ){
			$product_id = $oItem['product_id'];
			$subscription_key = WC_Subscriptions_Manager::get_subscription_key( $orderID, $product_id );
			$subscriptionObj = WC_Subscriptions_Manager::get_subscription( $subscription_key );


///////////////////////////
///////

				$datetime1 = null;
				$datetime2 = null;
				
				if( $subscriptionObj['start_date'] != 0 ){
					$datetime1 = new DateTime( $subscriptionObj['start_date'] );
				}
				if( $subscriptionObj['trial_expiry_date'] != 0 ){
					$datetime2 = new DateTime( $subscriptionObj['trial_expiry_date'] );
				} else {
					$datetime2 = $datetime1;
				}
				$dateinterval = $datetime1->diff($datetime2);

				$trial_period_length_in_days = $dateinterval->days;



            
error_log( 'updating subscription dates1>>>'. print_r( array( "subscription_key" => $subscription_key, "oItem" => $oItem, "subscriptionObj" => $subscriptionObj, "dateinterval" => $dateinterval ), true ) );	
	

				if( isset( $oItem['user_custom_deliveryDate'] ) ) {
					$start_date = $oItem['user_custom_deliveryDate'];
					
					$start_date = strtotime($start_date);
										
					
					$new_payment_timestamp = $start_date - (4 * 24 * 60 * 60);

					$trial_end_timestamp = $start_date - ( (4 * 24 * 60 * 60) + 30 );

					$start_date_timestamp = $start_date - ( (4 * 24 * 60 * 60) + 60 );
				
				} else {
					/* $start_date = $oItem['subscription_start_date']; */
					$start_date = false;
				}
				
				/* only for log purposes */
				$old_next_payment_date = strtotime( WC_Subscriptions_Order::get_next_payment_date( $order, $subscriptionObj['product_id'] ) );
				
				/* only for log purposes */
				$last_payment = date("Y-m-d H:i:s");
				
				$start_date				= date( "Y-m-d H:i:s", $start_date_timestamp );
					
				$trial_period_end_date	= date( 'Y-m-d H:i:s', $trial_end_timestamp );

				$new_payment_date 		= date( 'Y-m-d H:i:s', $new_payment_timestamp );
				
				
				$subscription = wcs_get_subscription_from_key( $subscription_key );

				try{	
/* trial_end can be used with $subscription->update_dates( array() );*/
					$users_subscriptions = WC_Subscriptions_Manager::update_users_subscriptions( $userID, array( $subscription_key => array( 'trial_expiry_date' => $trial_period_end_date ) ) );

//					$next_payment_date = WC_Subscriptions_Manager::update_next_payment_date( $trial_period_end_date, $subscription_key );


					error_log( 'updating subscription dates2>>>'. print_r( array( 
														'start' => $start_date,
														'next_payment' => $new_payment_date,
														'last_payment' => $last_payment,
														'trial_end' => $trial_period_end_date,
														'$new_payment_timestamp' => $new_payment_timestamp
					 	), true ) );
					 

					 
				} catch (Exception $e) {
				    error_log( 'updating subscription dates3>>>'. print_r( array( 'Caught exception: '.  $e->getMessage(), array( 
														'start' => $start_date,
														'next_payment' => $new_payment_date,
														'last_payment' => $last_payment,
														'trial_end' => $trial_period_end_date,
														'$new_payment_timestamp' => $new_payment_timestamp
					 	)), true ) );
				}
				
				try {
					$subscription->update_dates( array( next_payment => $new_payment_date ) );
					$response = $subscription->get_time( 'next_payment' );

				} catch ( Exception $e ) {
					$response = new WP_Error( 'invalid-date', $e->getMessage() );
				}
				
				error_log( 'updating subscription dates6>>>'. print_r( array($response,array( 'start' => $start_date, next_payment => $new_payment_date )), true ) );
				
				
/*
				try {
					$subscription->update_dates( array( 'start' => $start_date ) );
					$response = $subscription->get_time( 'start' );

				} catch ( Exception $e ) {
					$response = new WP_Error( 'invalid-date', $e->getMessage() );
				}
				
				error_log( 'updating subscription dates7>>>'. print_r( array($response,array( 'start' => $start_date, next_payment => $new_payment_date )), true ) );
*/				
				
				error_log( 'updating subscription dates4>>>'. print_r( $subscription01->id, true ) );
				error_log( 'updating subscription dates5>>>'. print_r( array( 
					'start_date' => $start_date,
					'trial_expiry_date' => $trial_period_end_date,
					'next_payment_date' => $new_payment_date,
					'old_next_payment_date' => $old_next_payment_date
					 ), true ) );


/////////
////////////////////////

		}

	}


	/* Ajax callback functions below */
	
	public function add_user_custom_deliveryDate_callback() {
		/* Custom data - Sent Via AJAX post method */
		$user_custom_deliveryDate 	= $_POST['deliveryDate'];

		session_start();
		$_SESSION['user_custom_deliveryDate'] = $user_custom_deliveryDate;
		
		die();
	}

	public function ajax_checkout_process_request() {

		// check nonce
		$nonce = $_POST['wc_ajax_function'];
		if ( ! wp_verify_nonce( $nonce, 'wc_ajax_function-ajax-nonce' ) ) {
			die ( 'Busted!' );
		}

		$response = '';
		
		/* At this point we have $_POST with data sent from javascript. This can be used to proceed further */
		global $current_user;
		global $woocommerce;

		// response output
		header( "Content-Type: application/json" );
		echo $response;

		exit;
	}
}