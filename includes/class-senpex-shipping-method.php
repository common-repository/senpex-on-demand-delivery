<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'WC_Shipping_Senpex_Delivery' ) ) {

    class WC_Shipping_Senpex_Delivery extends WC_Shipping_Method {

        protected $api;

        protected $available_rates;

        public $notice = '';

        public function __construct( $instance_id = 0 ) {
            $this->id           = 'senpex_delivery';
            $this->instance_id  = absint( $instance_id );
            $this->method_title = __( 'Senpex Delivery', 'senpex-delivery' );
            $this->method_description = __( '' );
            $this->enabled = isset( $this->settings['enabled'] ) ? $this->settings['enabled'] : 'yes';
            $this->title              = "Senpex Shipping Method";
            $this->supports     = array(
                'shipping-zones',
                'instance-settings',
                'settings',
            );

            add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );

            $this->init();
        }



        public function calculate_shipping( $package = array() ) {

            $this->rates = array();

            $cost = 0;
            $err = '';
            $label = $this->title;
            $api_token = '';

            $cart_id = WC()->session->get('cart_id');
            if( is_null($cart_id) ) {
                WC()->session->set('cart_id', uniqid());
            }

            if ( $this->get_customer_address_string( $package ) == 4 ) {
                $err = 'Please enter your full address';
            }

            if (!$err) {
                //$order_name = $this->order_name?preg_replace("/[^a-zA-Z0-9]+/", "", $this->order_name):'Order for Senpex';
                $order_name = $this->order_name?$this->order_name:'Order for Senpex';
                $customer_address = $this->get_customer_address_string( $package )?$this->get_customer_address_string( $package ):'';
                $from_address = $this->get_shipping_address_string()?$this->get_shipping_address_string():'';
                $customer_name = $this->get_customer_name()?$this->get_customer_name():'';

                $cart_price = WC()->cart->subtotal?WC()->cart->subtotal:'';
                $desc_text = WC()->session->get('checkout_note')?WC()->session->get('checkout_note'):'';


                $checkout      = WC()->checkout();
                $delivery_date = $checkout->get_value( 'delivery_date' )?$checkout->get_value( 'delivery_date' ):'';
                $delivery_time = WC()->session->get('delivery_time')?WC()->session->get('delivery_time'):'';

                $delivery_date_time = $delivery_date||$delivery_time?$delivery_date.' '.$delivery_time:'';

                $data_from_api = $this->get_data_from_api($order_name, $from_address, $customer_address,$customer_name,$cart_price,$desc_text,$delivery_date_time);
                
                $order_price = '';
                if (isset($data_from_api['order_price'])) {
                    $order_price = $data_from_api['order_price'];
                }
                $api_token = '';
                if (isset($data_from_api['api_token'])) {
                    $api_token = $data_from_api['api_token'];
                }

                if ($order_price){
                    if (preg_match("/[a-z]/i", $order_price)){
                        $label = $label.': '.$order_price;
                    }else {
                        $cost = $order_price;
                        if ( ! empty( $api_token ) ) {
                            update_option('_cart_'.WC()->session->get('cart_id').'_token', $api_token, 'no');
                        }
                    }
                }
            }else {
                $label = $label.': '.$err;
            }

            $rate = array(
                'id' => $this->get_rate_id(),
                'label' => $label,
                'cost' => $cost,
                'calc_tax' => 'per_order'
            );



            $this->add_rate( $rate );
        }




        public function is_available( $package ) {
            return apply_filters( 'woocommerce_shipping_' . $this->id . '_is_available', true, $package );
        }

        private function set_settings() {

            $this->title         = $this->get_option( 'title', $this->method_title );
            $this->test_mode     = ( 'yes' === $this->get_option( 'test_mode', 'no' ) ) ? true : false;
            $this->client_id     = $this->get_option( 'client_id', '' );
            $this->secret_id     = $this->get_option( 'secret_id', '' );
            $this->store_email   = $this->get_option( 'store_email', '' );
            $this->order_name    = $this->get_option( 'order_name', '' );
            $this->address_1     = $this->get_option( 'address_1', '' );
            $this->address_2     = $this->get_option( 'address_2', '' );
            $this->city          = $this->get_option( 'city', '' );
            $this->postal_code   = $this->get_option( 'postal_code', '' );
            $this->state_country = $this->get_option( 'state_country', '' );
            $this->country       = $this->get_option( 'country', '' );
            $this->api_key       = $this->get_option( 'api_key', '' );
            $this->map_interval  = $this->get_option( 'map_interval', '' );

            return true;
        }

        private function init() {

            $this->init_form_fields();
            $this->set_settings();
            $this->init_settings();
            $this->woocommerce_cart_init();

            add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );
            add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'clear_transients' ) );
        }

        public function process_admin_options() {
            parent::process_admin_options();

            $this->set_settings();
        }

        public function init_form_fields() {
            $this->instance_form_fields = array(
                'title'            => array(
                    'title'           => __( 'Method Title', 'senpex-delivery' ),
                    'type'            => 'text',
                    'description'     => __( 'This controls the title which the user sees during checkout.', 'senpex-delivery' ),
                    'default'         => __( 'Delivery', 'senpex-delivery' ),
                ),
                'senpex_delivery_address' => array(
                    'title' 		=> __( 'Shipping Address', 'senpex-delivery' ),
                    'type' 			=> 'title',
                    'description' 	=> __( 'Please enter the address that you are shipping from below to work out the distance of the customer from the shipping location.', 'senpex-delivery' ),
                ),
                'address_1' => array(
                    'title'           => __( 'Address 1', 'senpex-delivery' ),
                    'type'            => 'text',
                    'description'     => __( 'First address line of where you are shipping from.', 'senpex-delivery' ),
                ),
                'address_2' => array(
                    'title'           => __( 'Address 2', 'senpex-delivery' ),
                    'type'            => 'text',
                    'description'     => __( 'Second address line of where you are shipping from.', 'senpex-delivery' ),
                ),
                'city' => array(
                    'title'           => __( 'City', 'senpex-delivery' ),
                    'type'            => 'text',
                    'description'     => __( 'City of where you are shipping from.', 'senpex-delivery' ),
                ),
                'postal_code'             => array(
                    'title'           => __( 'Zip/Postal Code', 'senpex-delivery' ),
                    'type'            => 'text',
                    'description'     => __( 'Zip or Postal Code of where you are shipping from.', 'senpex-delivery' ),
                ),
                'state_province' => array(
                    'title'           => __( 'State/Province', 'senpex-delivery' ),
                    'type'            => 'text',
                    'description'     => __( 'State/Province of where you are shipping from.', 'senpex-delivery' ),
                ),
                'country' => array(
                    'title'           => __( 'Country', 'senpex-delivery' ),
                    'type'            => 'text',
                    'description'     => __( 'Country of where you are shipping from.', 'senpex-delivery' ),
                ),
            );

            $this->form_fields = array(
                'order_name'		=> array(
                    'title' => __( 'Title for delivery orders', 'senpex-delivery' ),
                    'type'	=> 'text',
                    'description'	=> __( 'Titles for orders for Senpex API.', 'senpex-delivery' ),
                ),
                'store_email'		=> array(
                    'title' => __( 'Store Email', 'senpex-delivery' ),
                    'type'	=> 'text',
                    'description'	=> __( 'Your Store Email', 'senpex-delivery' ),
                ),
                'client_id'		=> array(
                    'title' => __( 'Client ID', 'senpex-delivery' ),
                    'type'	=> 'text',
                    'description'	=> __( 'Your Senpex Client ID', 'senpex-delivery' ),
                ),
                'secret_id'		=> array(
                    'title' => __( 'Secret ID', 'senpex-delivery' ),
                    'type'	=> 'text',
                    'description'	=> __( 'Your Senpex Secret ID', 'senpex-delivery' ),
                ),
                'api_key'		=> array(
                    'title' => __( 'Google Maps Api Key', 'senpex-delivery' ),
                    'type'	=> 'text',
                    'description'	=> __( 'Assign Google Maps Api Key', 'senpex-delivery' ),
                ),
                'map_interval' => array(
                    'title'			=> __( 'Map Update Interval', 'senpex-delivery' ),
                    'type'			=> 'text',
                    'description'	=> __( 'Assign Map Update Interval in seconds', 'senpex-delivery' ),
                    'default'		=> '10',
                ),
                'test_mode' => array(
                    'title'			=> __( 'Test Mode', 'senpex-delivery' ),
                    'type'			=> 'checkbox',
                    'label'			=> __( 'Enable Test Mode', 'senpex-delivery' ),
                    'default'		=> 'no',
                ),
            );
        }

        public function woocommerce_cart_init() {

        }


        public function show_notice( $notice = '', $cart_checkout = true ) {
            $this->notice = $notice;

            add_filter( 'woocommerce_no_shipping_available_html', array( $this, 'get_notice' ) );

            if ( $cart_checkout ) {
                add_filter( 'woocommerce_cart_no_shipping_available_html', array( $this, 'get_notice' ) );
            }
        }

        public function get_notice() {
            return $this->notice;
        }

        public function get_customer_address_string( $package ) {
            $address = array();

            if (!isset( $package['destination']['country'] ) || !isset( $package['destination']['state'] ) || !isset( $package['destination']['city'] ) || empty( $package['destination']['country'] ) || empty( $package['destination']['state'] ) || empty( $package['destination']['city'] )){
                return 4;
            }

            if (empty( $package['destination']['postcode']) && empty( $package['destination']['address'])) {
                return 4;
            }

            if ( isset( $package['destination']['postcode'] ) && ! empty( $package['destination']['postcode'] ) ) {
                $address[] = $package['destination']['postcode'];
            }

            if ( isset( $package['destination']['address'] ) && ! empty( $package['destination']['address'] ) ) {
                $address[] = $package['destination']['address'];
            }

            if ( isset( $package['destination']['city'] ) && ! empty( $package['destination']['city'] ) ) {
                $address[] = $package['destination']['city'];
            }

            if ( isset( $package['destination']['state'] ) && ! empty( $package['destination']['state'] ) ) {
                $state = $package['destination']['state'];
                $country = $package['destination']['country'];

                if ( isset( WC()->countries->states[ $country ], WC()->countries->states[ $country ][ $state ] ) ) {
                    $state = WC()->countries->states[ $country ][ $state ];
                    $country = WC()->countries->countries[ $country ];
                }
                $address[] = $state;
            }



            if ( isset( $package['destination']['country'] ) && ! empty( $package['destination']['country'] ) ) {
                $country = $package['destination']['country'];

                if ( isset( WC()->countries->countries[ $country ] ) ) {
                    $country = WC()->countries->countries[ $country ];
                }
                $address[] = $country;
            }

            /*if ( is_checkout() ) {
                if ( isset( $package['destination']['address'] ) && ! empty( $package['destination']['address'] ) ) {
                    $address[] = $package['destination']['address'];
                }

                if ( isset( $package['destination']['address_2'] ) && ! empty( $package['destination']['address_2'] ) ) {
                    $address[] = $package['destination']['address_2'];
                }

                if ( isset( $package['destination']['city'] ) && ! empty( $package['destination']['city'] ) ) {
                    $address[] = $package['destination']['city'];
                }
            }*/

            return implode( ', ', $address );
        }

        public function get_shipping_address_string() {
            $address = array();
            if ( isset( $this->address_1 ) && ! empty( $this->address_1 ) ) {
                $address[] = $this->address_1;
            }

            if ( isset( $this->address_2 ) && ! empty( $this->address_2 ) ) {
                $address[] = $this->address_2;
            }

            if ( isset( $this->city ) && ! empty( $this->city ) ) {
                $address[] = $this->city;
            }

            if ( isset( $this->postal_code ) && ! empty( $this->postal_code ) ) {
                $address[] = $this->postal_code;
            }

            if ( isset( $this->state_province ) && ! empty( $this->state_province ) ) {
                $address[] = $this->state_province;
            }

            if ( isset( $this->country ) && ! empty( $this->country ) ) {
                $address[] = $this->country;
            }

            return implode( ', ', $address );
        }

        public function get_customer_name( ) {

            global $current_user; wp_get_current_user();
            if ( is_user_logged_in() && ($current_user->first_name!=''||$current_user->last_name!='') ) {
                $customer_name = $current_user->first_name.' '.$current_user->last_name;
            } elseif(is_user_logged_in() && $current_user->display_name!='') {
                $customer_name = $current_user->display_name;
            } else {
                $customer_name = 'Guest User';
            }
            return $customer_name;
        }


        public function get_data_from_api($order_name,$from_address,$customer_address,$customer_name,$cart_price,$desc_text,$delivery_date) {
            require_once ( dirname( __FILE__ ) . '/snpx/snpx_api.php');

            $api_data = array();

            $routes[0]['route_to_text'] = $customer_address;
            $routes[0]['rec_name']=$customer_name;
            $routes[0]['rec_phone']='';

            $order_desc = '';

            if ($desc_text != '') {
                $routes[0]['route_desc'] = $desc_text;
                $order_desc = $desc_text;
            }

            $snpx_url=$this->test_mode?'https://api.sandbox.senpex.com/senpex/api/rest/v1/':'https://api.production.senpex.com/senpex/api/rest/v1/';
            $client_id=$this->client_id;
            $secret_id=$this->secret_id;

            if($client_id =='' || $secret_id=='' || $this->api_key==''){
                $api_data['order_price'] = 'Please enter all Senpex API keys';
                return $api_data;
            }

            $snpx_api = new snpx_api($snpx_url, $client_id, $secret_id);

            $taken_asap = '0';
            $schedule_date = null;

            if ($delivery_date!='') {
                $curdate = new DateTime("now", new DateTimeZone('PDT') );
                $curdate = $curdate->modify('+ 1 hour');
                $cur_date = $curdate->format('Y-m-d H:i:s');
                $cur_time = $curdate->getTimestamp();

                //$time = time() + 3600;
                $d_time = new DateTime($delivery_date, new DateTimeZone('PDT'));
                $calc_time = $d_time->getTimestamp() - $cur_time;

                if ($calc_time>0) {
                    $taken_asap = $calc_time > 3600 ? '0' : '1';
                }
                $schedule_date = $taken_asap!='0' ? null : $delivery_date;

                if ($schedule_date){
                    $schedule_date = gmdate('Y-m-d H:i:s', $d_time->getTimestamp() - 3600);
                }
            }

            $cart_vars = array(
                'order_name' => $order_name,
                'from_address' => $from_address,
                'routes' => $routes,
                'transport_id' => 1,
                'pack_size_id' => 1,
                'cart_price' => $cart_price,
                'taken_asap' => $taken_asap,
                'schedule_date' => $schedule_date,
                'order_desc' => $order_desc,
            );

            update_option('_cart_'.WC()->session->get('cart_id').'_vars', json_encode($cart_vars), 'no');

            $results  = $snpx_api->get_price($order_name, $from_address, $routes, 1, 1, $cart_price, $taken_asap, $schedule_date, $order_desc);

            if(isset($results->{'code'}) && $results->{'code'}=="0")
            {
                for($i=0;$i<count($results->{'details'});$i++)
                {
                    $api_data['order_price'] = $results->{'details'}[$i]->{'order_price'};
                    if (isset($results->{'details'}[$i]->{'api_token'})) {
                        $api_data['api_token'] = $results->{'details'}[$i]->{'api_token'};
                    }
                }

            }
            else
            {
                if (isset($results->{'msgtext'})) {
                    $api_data['order_price'] = $results->{'msgtext'};
                }
            }

            return $api_data;
        }

        public function clear_transients() {
            global $wpdb;

            $wpdb->query( "DELETE FROM `$wpdb->options` WHERE `option_name` LIKE ('_transient_senpex_delivery_%') OR `option_name` LIKE ('_transient_timeout_senpex_delivery_%')" );
        }

    }


}
