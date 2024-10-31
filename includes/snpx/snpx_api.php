<?php

class snpx_api
{
    var $snpx_url = "";
    
    var $client_id = "";
    var $secret_id = "";
    var $show_one_price = 1;
    
    public function __construct($snpx_url,$client_id,$secret_id) {

        if(trim($snpx_url)=="")
        {
            throw new Exception("Senpex API url cannot be empty");
        }

        if(trim($client_id)=="")
        {
            throw new Exception("Client ID cannot be empty");
        }
        
        if(trim($secret_id)=="")
        {
            throw new Exception("Secret ID cannot be empty");
        }

        $this->snpx_url = $snpx_url;
        $this->client_id = $client_id;
        $this->secret_id = $secret_id;
    }
    
    public function get_price($order_name,$pack_from_text, $routes,
                              $transport_id =1 , $pack_size_id = 1, $item_value = 200, $taken_asap = 0,  $schedule_date = null ,$order_desc='', $email=''
                             )
    {   
        if($schedule_date == null) $schedule_date = gmdate('Y-m-d H:i:s', strtotime('+ 1 minutes'));
        
        $post['client_id'] = $this->client_id;
        $post['secret_id'] = $this->secret_id;
        $post['transport_id'] = $transport_id;
        $post['item_value'] = $item_value;
        $post['pack_size_id'] = $pack_size_id;
        $post['taken_asap'] = $taken_asap;
        $post['schedule_date'] = $schedule_date;        
        $post['show_one_price'] = $item_value;
      //  $post['distance_miles'] = $distance_miles;
      //  $post['distance_time_seconds'] = $distance_time_seconds;
        $post['show_one_price'] = $this->show_one_price;
        $post['order_name'] = $order_name;        
        $post['pack_from_text'] = $pack_from_text;
        $post['routes'] = $routes;

        $post['order_desc'] = $order_desc;
        
        if($email!='')$post['email'] = $email;
        
        $results = $this->api_post("get_price", $post);
       
        return json_decode($results);
    }
    
    var $snpx_user_email=0;
    var $snpx_order_email=1;
    var $snpx_order_not=1;
    var $search_courier=1;

    var $snpx_emails=1;
    var $snpx_nots=1;
    
    public function create_quick_order($api_token,$email,$routes=array(),$name='',$surname='',$phone_number='',$tip_amount = null)
    {
      
        $post['client_id'] = $this->client_id;
        $post['secret_id'] = $this->secret_id;
        $post['api_token'] = $api_token;
        $post['email'] = $email;
        $post['name'] = $name;
        $post['surname'] = $surname;
        $post['phone_number'] = $phone_number;            
        $post['routes'] = $routes;

        $post['tip_amount'] = $tip_amount;

        
        $post['snpx_user_email'] = $this->snpx_user_email;
        $post['snpx_order_email'] = $this->snpx_order_email;
        $post['snpx_order_not'] = $this->snpx_order_not;
        $post['snpx_emails'] = $this->snpx_emails;
        $post['snpx_nots'] = $this->snpx_nots;
        $post['search_courier'] = $this->search_courier;
        
        if($email!='') $post['email'] = $email;
       
        $results = $this->api_post("create_quick_order", $post);

        return json_decode($results);
    }
    
    public function get_order_list($search_array=array(),$toarr=false)
    {
        $post['client_id'] = $this->client_id;
        $post['secret_id'] = $this->secret_id;
        $post['search_array'] = $search_array;
        
       
        $results = $this->api_post("get_order_list", $post);
     
        return json_decode($results,$toarr);
    }
    
    public function get_order_details($order_id)
    {
        $post['client_id'] = $this->client_id;
        $post['secret_id'] = $this->secret_id;
        $post['order_id'] = $order_id;
        $post['include_images'] = '1';
        
       
        $results = $this->api_post("get_order_details", $post);

       
        return json_decode($results);
    }
    
    public function update_order_nots($order_id,$not_array)
    {
        $post['client_id'] = $this->client_id;
        $post['secret_id'] = $this->secret_id;
        $post['order_id'] = $order_id;
        
        foreach($not_array as $key=>$value) $post[$key] = $value;
        
        $results = $this->api_post("update_order_nots", $post);
        return json_decode($results);
    }
    
    public function get_courier_place($order_id)
    {
        $post['client_id'] = $this->client_id;
        $post['secret_id'] = $this->secret_id;
        $post['order_id'] = $order_id;                
        
        $results = $this->api_post("get_courier_place", $post);
        return json_decode($results);
    }
    
    public function cancel_order($order_id)
    {
        $post['client_id'] = $this->client_id;
        $post['secret_id'] = $this->secret_id;
        $post['order_id'] = $order_id;                
        
        $results = $this->api_post("cancel_order", $post);   
        return json_decode($results);
    }
    
    private function api_post($service_url,$data)
    {    
        $url = $this->snpx_url.$service_url;
        $data = json_encode($data);

        $args = [
            'method' => 'POST',
            'httpversion' => '1.0',
            'sslverify' => false,
            'body'      => $data
        ];

        $response = wp_remote_post( $url, $args);

        if ( is_wp_error( $response ) ) {
            $error_message = $response->get_error_message();
            throw new Exception("Cannot connect to snpx server . Error message : ".$error_message);
        }

        $response_body = $response['body'];
        return $response_body;
    }
}

?>