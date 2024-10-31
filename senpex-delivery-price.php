<?php
/**
 * Plugin Name: Senpex On-Demand Delivery
 * Plugin URI: http://wordpress.org/plugins/senpex-on-demand-delivery/
 * Version: 1.0
 * Description: Calculates delivery price based on route
 * Author: Senpex LLC
 * Author URI: https://web.senpex.com/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: senpex-on-demand-delivery
 *
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
    class Senpex_Delivery
    {

        public function __construct()
        {
            add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this, 'plugin_action_links'));
            add_action('woocommerce_shipping_init', array($this, 'senpex_shipping_method_init'));
            add_filter('woocommerce_shipping_methods', array($this, 'shipping_methods'));

            add_action('wp_enqueue_scripts', array($this, 'senpex_scripts'));

            add_action('woocommerce_review_order_after_shipping', array($this, 'add_tip_option_to_checkout'));
            add_action('wp_ajax_woo_get_ajax_data', array($this, 'senpex_get_ajax_data'));
            add_action('wp_ajax_nopriv_woo_get_ajax_data', array($this, 'senpex_get_ajax_data'));
            add_action('woocommerce_cart_calculate_fees', array($this, 'calculate_tip_fee'), 20, 1);

            add_action('woocommerce_thankyou', array($this, 'create_senpex_delivery_order'), 30);
            add_action('woocommerce_view_order', array($this, 'senpex_view_order_page'), 30);

            add_action('wp_ajax_getCourierCoords', array($this, 'getCourierCoords'));
            add_action('wp_ajax_nopriv_getCourierCoords', array($this, 'getCourierCoords'));

            //add_filter('woocommerce_cart_ready_to_calc_shipping', array($this, 'disable_shipping_calc_on_cart'), 99);

            add_filter('woocommerce_thankyou_order_received_text', array($this, 'get_order_link'), 20, 2);
        }

        public function senpex_scripts()
        {
            $shipping_for_package = WC()->session->get('shipping_for_package_0');

            if (isset($shipping_for_package['rates']) && !empty($shipping_for_package['rates'])) {
                $shipping_methods = array();
                foreach ($shipping_for_package['rates'] as $rate) {
                    $shipping_methods[] = $rate->method_id;
                }
                if (!in_array('senpex_delivery', $shipping_methods)) {
                    return;
                }
            }

            if (is_checkout()) {
                wp_register_script('senpex_script', plugins_url('assets/js/script.js', __FILE__), array('jquery'), '1.04');
                wp_enqueue_script('senpex_script');
                wp_localize_script('senpex_script', 'ajaxurl', array('url' => admin_url('admin-ajax.php')));
            }
        }

        public function plugin_action_links($links)
        {
            $plugin_links = array(
                '<a href="' . admin_url((function_exists('WC') ? 'admin.php?page=wc-settings&tab=shipping&section=senpex_delivery' : 'admin.php?page=woocommerce_settings&tab=shipping&section=senpex_delivery')) . '">' . __('Settings', 'senpex-delivery') . '</a>',
            );

            return array_merge($plugin_links, $links);
        }

        public function senpex_shipping_method_init()
        {
            include_once(dirname(__FILE__) . '/includes/class-senpex-shipping-method.php');
        }

        public function shipping_methods($methods)
        {
            $methods['senpex_delivery'] = 'WC_Shipping_Senpex_Delivery';
            return $methods;
        }

        function add_tip_option_to_checkout($checkout)
        {
            $shipping_for_package = WC()->session->get('shipping_for_package_0');

            if (isset($shipping_for_package['rates']) && !empty($shipping_for_package['rates'])) {
                $shipping_methods = array();
                foreach ($shipping_for_package['rates'] as $rate) {
                    $shipping_methods[] = $rate->method_id;
                }
                if (!in_array('senpex_delivery', $shipping_methods)) {
                    return;
                }
            }

            echo '<tr class="checkout-tip"><th>' . __('Tip (optional)', 'senpex-delivery') . '</th><td>';

            $tip = WC()->session->get('checkout_tip') ? WC()->session->get('checkout_tip') : '';
            $checkout_note = WC()->session->get('checkout_note') ? WC()->session->get('checkout_note') : '';

            // Add a custom checkbox field
            woocommerce_form_field('checkout_tip', array(
                'type' => 'number',
                'placeholder' => '$ 0.00',
                'required' => false,
                'custom_attributes' => ['min' => '0']
            ), $tip);

            echo '</td></tr>';

        }


        function senpex_get_ajax_data()
        {
            if (isset($_POST['checkout_tip'])) {
                $tip_result = '';
                $checkout_tip = str_replace(',', '.', sanitize_text_field($_POST['checkout_tip']));
                $checkout_tip = number_format((float)$checkout_tip, 2, '.', '');
                $checkout_tip = abs($checkout_tip);
                if ($checkout_tip) {
                    WC()->session->set('checkout_tip', $checkout_tip);
                    $tip_result = $checkout_tip;
                } else {
                    WC()->session->__unset('checkout_tip');
                    $tip_result = '0';
                }
                echo json_encode($tip_result);
            } elseif (isset($_POST['checkout_note'])) {
                $note_result = '';
                $checkout_note = sanitize_text_field($_POST['checkout_note']);
                if ($checkout_note) {
                    WC()->session->set('checkout_note', $checkout_note);
                    $note_result = $checkout_note;
                } else {
                    WC()->session->__unset('checkout_note');
                    $note_result = '0';
                }
                $packages = WC()->cart->get_shipping_packages();
                foreach ($packages as $package_key => $package) {
                    $session_key = 'shipping_for_package_' . $package_key;
                    $stored_rates = WC()->session->__unset($session_key);
                }
                echo json_encode($note_result);
            } elseif (isset($_POST['delivery_time'])) {
                $delivery_time_result = '';
                $delivery_time = sanitize_text_field($_POST['delivery_time']);
                if ($delivery_time) {
                    WC()->session->set('delivery_time', $delivery_time);
                    $delivery_time_result = $delivery_time;
                } else {
                    WC()->session->__unset('delivery_time');
                    $delivery_time_result = '0';
                }

                $packages = WC()->cart->get_shipping_packages();
                foreach ($packages as $package_key => $package) {
                    $session_key = 'shipping_for_package_' . $package_key;
                    $stored_rates = WC()->session->__unset($session_key);
                }
                echo json_encode($delivery_time_result);
            }
            die();
        }


        function calculate_tip_fee($cart)
        {
            $domain = "senpex-delivery";
            $checkout_tip = WC()->session->get('checkout_tip');

            $label = __("Tip", $domain);
            $cost = $checkout_tip;

            if (isset($cost))
                $cart->add_fee($label, $cost);
        }

        function disable_shipping_calc_on_cart($show_shipping)
        {
            if (is_cart()) {
                return false;
            }
            return $show_shipping;
        }

        function get_order_link($esc_html__, $order)
        {

            return $esc_html__ . ' Click <a href="' . $order->get_view_order_url() . '" style="text-decoration: underline;color: #0e5297;">here</a> to open your order.';

        }

        function getCourierCoords()
        {
            if (isset($_POST['order_from_api'])) {

                require_once(dirname(__FILE__) . '/includes/class-senpex-shipping-method.php');
                require_once(dirname(__FILE__) . '/includes/snpx/snpx_api.php');

                $senpex_delivery_options = new WC_Shipping_Senpex_Delivery;

                $snpx_url = $senpex_delivery_options->test_mode ? 'https://api.sandbox.senpex.com/senpex/api/rest/v1/' : 'https://api.production.senpex.com/senpex/api/rest/v1/';
                $client_id = $senpex_delivery_options->client_id;
                $secret_id = $senpex_delivery_options->secret_id;

                if($client_id =='' || $secret_id=='' || $senpex_delivery_options->api_key==''){
                    return;
                }

                $snpx_api = new snpx_api($snpx_url, $client_id, $secret_id);
                $courier_data = $snpx_api->get_courier_place(sanitize_text_field($_POST['order_from_api']));

                $courierPos = array();
                if ($courier_data->{'code'} == "0") {
                    $courierPos['pack_status'] = $courier_data->{'data'}->{'pack_status'};
                    $courierPos['last_lat'] = $courier_data->{'data'}->{'last_lat'};
                    $courierPos['last_lng'] = $courier_data->{'data'}->{'last_lng'};
                }
                echo json_encode($courierPos);
                die();
            }
        }


        public function create_senpex_delivery_order($order_id)
        {

            $order = wc_get_order($order_id);
            $order_data = $order->get_data();

            if (!$order->has_shipping_method('senpex_delivery')) {
                return;
            }

            require_once( dirname( __FILE__ ) . '/includes/class-senpex-shipping-method.php' );
            require_once(dirname(__FILE__) . '/includes/snpx/snpx_api.php');

            $cart_id = WC()->session->get('cart_id');

            if (!$cart_id) {
                return;
            }

            $senpex_delivery_options = new WC_Shipping_Senpex_Delivery;

            $snpx_url = $senpex_delivery_options->test_mode ? 'https://api.sandbox.senpex.com/senpex/api/rest/v1/' : 'https://api.production.senpex.com/senpex/api/rest/v1/';
            $client_id = $senpex_delivery_options->client_id;
            $secret_id = $senpex_delivery_options->secret_id;

            if($client_id =='' || $secret_id=='' || $senpex_delivery_options->api_key==''){
                return;
            }

            $snpx_api = new snpx_api($snpx_url, $client_id, $secret_id);

            $vars = json_decode(get_option('_cart_' . $cart_id . '_vars'));

            $order_name = $vars->order_name . ' - Order #: ' . $order_id;
            $from_address = $vars->from_address;
            $price_routes = $vars->routes;
            $transport_id = $vars->transport_id;
            $pack_size_id = $vars->pack_size_id;
            $cart_price = $vars->cart_price;
            $taken_asap = $vars->taken_asap;
            $schedule_date = $vars->schedule_date;
            $order_desc = $vars->order_desc;

            $price_results = $snpx_api->get_price($order_name, $from_address, $price_routes, $transport_id, $pack_size_id, $cart_price, $taken_asap, $schedule_date, $order_desc);

            if ($price_results->{'code'} == "0") {
                for ($i = 0; $i < count($price_results->{'details'}); $i++) {
                    $api_token = $price_results->{'details'}[$i]->{'api_token'};
                }
            } else {
                return;
            }

            update_option('_cart_' . $cart_id . '_token', $api_token, 'no');
            $token = get_option('_cart_' . $cart_id . '_token');

            if (!$token) {
                return;
            }

            //$email = $order_data['billing']['email']?$order_data['billing']['email']:'';
            $email = $senpex_delivery_options->store_email ? $senpex_delivery_options->store_email : '';

            $firstname = $order_data['shipping']['first_name'] ? $order_data['shipping']['first_name'] : ($order_data['billing']['first_name'] ? $order_data['billing']['first_name'] : '');
            $lastname = $order_data['shipping']['last_name'] ? $order_data['shipping']['last_name'] : ($order_data['billing']['last_name'] ? $order_data['billing']['last_name'] : '');
            $phone_number = $order->get_meta('_shipping_phone') ? $order->get_meta('_shipping_phone') : ($order_data['billing']['phone'] ? $order_data['billing']['phone'] : '');

            $routes[0]['rec_name'] = $firstname . ' ' . $lastname;
            $routes[0]['rec_phone'] = $phone_number;

            $fee_array = array();

            foreach ($order->get_items('fee') as $item_id => $item_fee) {
                $fee_array[$item_fee->get_name()] = $item_fee->get_amount();
            }

            $tip_amount = isset($fee_array['Tip']) && is_numeric($fee_array['Tip']) ? $fee_array['Tip'] : null;
            $order_desc = $order_data['customer_note'] ? $order_data['customer_note'] : '';

            $results = $snpx_api->create_quick_order($token, $email, $routes, $firstname, $lastname, $phone_number, $tip_amount, $order_desc);

            if ($results->{'code'} == "0") {
                update_option('_order_' . $order_id . '_from_api', $results->{'inserted_id'}, 'no');
            }

            delete_option('_cart_' . $cart_id . '_token');
            delete_option('_cart_' . $cart_id . '_vars');
            WC()->session->__unset('checkout_tip');
            WC()->session->__unset('checkout_note');
            WC()->session->__unset('delivery_time');

        }

        function senpex_view_order_page($order_id)
        {
            $order = wc_get_order($order_id);
            $order_data = $order->get_data();

            if (!$order->has_shipping_method('senpex_delivery')) {
                return;
            }
            ?>

            <h2>Delivery status</h2>

            <?php

            require_once( dirname( __FILE__ ) . '/includes/class-senpex-shipping-method.php' );
            require_once(dirname(__FILE__) . '/includes/snpx/snpx_api.php');

            $senpex_delivery_options = new WC_Shipping_Senpex_Delivery;

            $snpx_url = $senpex_delivery_options->test_mode ? 'https://api.sandbox.senpex.com/senpex/api/rest/v1/' : 'https://api.production.senpex.com/senpex/api/rest/v1/';
            $client_id = $senpex_delivery_options->client_id;
            $secret_id = $senpex_delivery_options->secret_id;
            $img_url = $senpex_delivery_options->test_mode ? 'https://app3.senpex.com/senpex/images/' : 'https://www.senpex.com/senpex/images/';

            $order_from_api = get_option('_order_' . $order_id . '_from_api');

            if (!$order_from_api) {
                echo 'Error: no order_id';
                return;
            }

            if($client_id =='' || $secret_id=='' || $senpex_delivery_options->api_key==''){
                return;
            }

            $snpx_api = new snpx_api($snpx_url, $client_id, $secret_id);

            $results = $snpx_api->get_order_details($order_from_api);

            if ($results->{'code'} == "0") {
                echo "Order id : " . $results->{'data'}[0]->{'id'};
                echo "<br />";
                echo "Order name : " . $results->{'data'}[0]->{'order_name'};
                echo "<br />";
                echo "Pick up location: " . $results->{'data'}[0]->{'pack_from_text'};
                echo "<br />";
                echo "Order status : " . $results->{'data'}[0]->{'order_status_text'};
                echo "<br />";

                $courier_self_img_thumb = '';
                if (isset($results->{'data'}[0]->{'courier_self_img_thumb'})) {
                    $courier_self_img_thumb = $results->{'data'}[0]->{'courier_self_img_thumb'};
                }

                for ($i = 0; $i < count($results->{'data'}[0]->{'routes'}); $i++) {
                    $route_to_text = $results->{'data'}[0]->{'routes'}[$i]->{'route_to_text'};
                    $route_to_lat = $results->{'data'}[0]->{'routes'}[$i]->{'route_to_lat'};
                    $route_to_lng = $results->{'data'}[0]->{'routes'}[$i]->{'route_to_lng'};

                    echo "Drop of location " . ($i + 1) . " : " . $route_to_text;
                    echo "<br />";
                }

            } else {
                echo $results->{'msgtext'};
            }

            $courier_data = $snpx_api->get_courier_place($order_from_api);

            if ($courier_data->{'code'} == "0") {
                $pack_status = $courier_data->{'data'}->{'pack_status'};
                $courier_name = $courier_data->{'data'}->{'courier_name'} . ' ' . $courier_data->{'data'}->{'courier_surname'};
                $courier_phone_number = $courier_data->{'data'}->{'courier_phone_number'};
                $last_lat = $courier_data->{'data'}->{'last_lat'};
                $last_lng = $courier_data->{'data'}->{'last_lng'};
                $last_timezone = $courier_data->{'data'}->{'last_timezone'};
                $last_location_date = $courier_data->{'data'}->{'last_location_date'};
                $last_seen_date = $courier_data->{'data'}->{'last_seen_date'};


                /*echo "Pack_status : ".$pack_status;
                echo "<br />";*/
                echo "Courier name : " . $courier_name;
                echo "<br />";
                echo "Courier phone number: " . $courier_phone_number;
                echo "<br />";
            } else {
                //echo $courier_data->{'msgtext'};
            }

            if ($results->{'code'} == "0") {
                $api_key = $secret_id = $senpex_delivery_options->api_key;
                ?>

                <style>
                    #snpx_main {
                        width: 50%;
                        height: 300px;
                        margin: 20px 0 0;
                        display: inline-block;
                        vertical-align: top;
                    }

                    .order_images {
                        width: 100%;
                        margin: 20px 0 0;
                        display: inline-block;
                        vertical-align: top;
                    }

                    #pack_status, #last_lat, #last_lng {
                        display: none;
                    }

                    .order_images p {
                        margin: 0 0 10px 0;
                    }

                    .order_images a {
                        margin: 0 10px 10px 0;
                        width: 150px;
                        height: 150px;
                        max-width: 100%;
                        display: inline-block;
                        vertical-align: top;
                        overflow: hidden;
                        border-radius: 5px;
                    }

                    .order_images img {

                    }

                    .courier_img {
                        width: 50px;
                        margin: 0 10px 10px 0;
                        float: left;
                    }

                    @media screen and (max-width: 768px) {
                        #snpx_main {
                            width: 100%;
                        }

                        .order_images {
                            width: 100%;
                            margin: 20px 0 0;
                        }

                        .order_images a {
                            max-width: 45%;
                        }
                    }
                </style>
                <div id="snpx_main"></div>
                <div id="pack_status"></div>
                <div id="last_lat"></div>
                <div id="last_lng"></div>
                <script>
                    function initMap() {
                        var directionsService = new google.maps.DirectionsService;
                        var directionsDisplay = new google.maps.DirectionsRenderer;
                        var map = new google.maps.Map(document.getElementById('snpx_main'), {
                            zoom: 7,
                            center: {lat: 40.409264, lng: 49.867092},
                            gestureHandling: 'greedy'
                        });

                        <?php if($courier_data->{'code'} == "0" && $last_lat != '' && $last_lng != '' && ($pack_status == "20" || $pack_status == "25" || $pack_status == "30")){
                        $courier_img = $courier_self_img_thumb != '' ? '<img src="' . $img_url . $courier_self_img_thumb . '" class="courier_img" alt="">' : '';
                        ?>

                        var courier_content = '<div style="color:#000"><?php echo $courier_img;?>Courier: <?php echo $courier_name . '<br/><br/> Phone: ' . $courier_phone_number;?></div>';
                        var infowindow = new google.maps.InfoWindow({
                            content: courier_content
                        });
                        var lastLatLng = {lat: <?php echo $last_lat;?>, lng: <?php echo $last_lng;?>};
                        var image = '<?php echo plugin_dir_url(__FILE__) . 'img/icon.png';?>';
                        var marker = new google.maps.Marker({
                            position: lastLatLng,
                            map: map,
                            draggable: true,
                            icon: image
                        });

                        <?php if(($pack_status == "20" || $pack_status == "25" || $pack_status == "30") && $senpex_delivery_options->map_interval != '' && is_numeric($senpex_delivery_options->map_interval) && (int)$senpex_delivery_options->map_interval > 0){
                        $interval = $senpex_delivery_options->map_interval * 1000;
                        $status = $pack_status;
                        ?>
                        window.setInterval(function () {
                            getCourierCoords();
                            pack_status = jQuery('#pack_status').text();

                            if (pack_status != '' && pack_status != <?php echo $status;?>) {
                                location.reload(true);
                            }
                            last_lat = jQuery('#last_lat').text();
                            last_lng = jQuery('#last_lng').text();
                            if (last_lat && last_lng && (pack_status == '20' || pack_status == '25' || pack_status == '30')) {
                                marker.setPosition(new google.maps.LatLng(last_lat, last_lng));
                            }
                        },<?php echo $interval;?>);
                        <?php }?>

                        infowindow.open(map, marker);
                        google.maps.event.addListener(marker, 'click', function () {
                            infowindow.open(map, marker);
                        });

                        <?php }?>

                        directionsDisplay.setMap(map);
                        calculateAndDisplayRoute(directionsService, directionsDisplay);
                    }

                    function calculateAndDisplayRoute(directionsService, directionsDisplay) {
                        directionsService.route({
                            origin: {
                                lat: <?php echo $results->{'data'}[0]->{'pack_from_lat'};?>,
                                lng: <?php echo $results->{'data'}[0]->{'pack_from_lng'};?>},
                            destination: {lat: <?php echo $route_to_lat;?>, lng: <?php echo $route_to_lng;?>},
                            travelMode: 'DRIVING'
                        }, function (response, status) {
                            if (status === 'OK') {
                                directionsDisplay.setDirections(response);
                            } else {
                                window.alert('Directions request failed due to ' + status);
                            }
                        });
                    }

                    function getCourierCoords() {
                        jQuery.ajax({
                            type: 'POST',
                            url: woocommerce_params.ajax_url,
                            data: {
                                'action': 'getCourierCoords',
                                'order_from_api': <?php echo $order_from_api; ?>,
                            },
                            success: function (result) {
                                res = jQuery.parseJSON(result);
                                jQuery('#pack_status').text(res.pack_status);
                                jQuery('#last_lat').text(res.last_lat);
                                jQuery('#last_lng').text(res.last_lng);
                            },
                            error: function (error) {
                            }
                        });
                    }

                </script>
                <script type="text/javascript" src="https://maps.googleapis.com/maps/api/js?key=<?php echo $api_key; ?>&callback=initMap" async defer></script>
                <?php

                $order_images = array();
                if (isset($results->{'data'}[0]->{'order_images'})) {
                    for ($i = 0; $i < count($results->{'data'}[0]->{'order_images'}); $i++) {
                        $order_images[] = $results->{'data'}[0]->{'order_images'}[$i]->{'pack_img'};
                    }
                    if (!empty($order_images)) {
                        echo '<div class="order_images"><p>Order images:</p>';
                        foreach ($order_images as $order_image) {
                            echo '<a href="' . $img_url . $order_image . '" target="_blank"><img src="' . $img_url . $order_image . '.thumb.png" width="150" height="150" alt=""></a>';
                        }
                        echo '</div>';
                    }
                }

                ?>

            <?php }
            ?>
        <?php }
    }

    new Senpex_Delivery();
}