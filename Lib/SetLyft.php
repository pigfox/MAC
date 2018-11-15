<?php
App::uses('CakeSession', 'Model/Datasource');
App::uses('Component', 'Controller');
APP::import('Model', 'Multipaicartypes');
App::uses('MultiapiMappings', 'Lib');
App::uses('Curl', 'Lib');
App::uses('Util', 'Lib');
App::uses('MultiapiHelpers', 'Lib');
App::uses('Multiapi', 'Model');

class SetLyft
{
    private $access_token;
    private $refresh_token;
    private $token_refresh_time = 35;
    private $class = 'SetLyft';
    private $name = 'Lyft';
    private $mappings;
    private $curl;
    private $request_type;
    private $generic_error = 'E007';
    private $multi_api_helpers;
    private $multi_api_model;
    private $configs;
    private $util;
    private $sandbox;
    private $scope;
    private $asap = 1;

    public function __construct() {
        $this->multi_api_model = new Multiapi();

        $this->configs = CakeSession::read('set_lyft');
        if(!isset($this->configs) || $this->configs == null){
            $this->configs = $this->multi_api_model->getClassSetters('set_lyft');
            CakeSession::write('set_lyft', $this->configs);
        }

        if(isset($this->configs['sandbox'])){
            $this->sandbox = $this->configs['sandbox'];
        }else{
            $this->sandbox = '';
        }

        $this->curl = new Curl();
        $this->util = new Util();
        $this->multi_api_helpers = new MultiapiHelpers();
        $this->multiApiCarTypes = new Multipaicartypes();
        $this->mappings = new MultiapiMappings();
        $this->mappings->setCarTypes($this->multiApiCarTypes->getAll($this->configs['multi_api_providers_id']));
        $this->scope = 'public privileged.rides.dispatch';
        $this->manageTokens();
    } 

    public function getParamters(){
        $this->configs['access_token'] = $this->access_token;
        $this->configs['refresh_token'] = $this->refresh_token;
        return $this->configs;
    }

    private function manageTokens(){
        $now = new DateTime(date("Y-m-d H:i:s"));

        //ACCESS TOKEN
        $lyft_access_token_created_time_stamp = CakeSession::read('lyft_access_token_created_time_stamp');
        $refresh = 1;
        if(isset($lyft_access_token_created_time_stamp)){
            $diff = $now->diff($lyft_access_token_created_time_stamp);
            $refresh = 0;
        }

        if($refresh == 1 || strlen(CakeSession::read('lyft_access_token')) != 120 || $this->token_refresh_time < $diff->i){
            $this->access_token = $this->getAccessToken();
            CakeSession::write('lyft_access_token', $this->access_token);
            CakeSession::write('lyft_access_token_created_time_stamp', $now);
        }else{
            $this->access_token = CakeSession::read('lyft_access_token');
        }

        //REFRESH TOKEN
        $lyft_refresh_token_created_time_stamp = CakeSession::read('lyft_refresh_token_created_time_stamp');
        $refresh = 1;
        if(isset($lyft_refresh_token_created_time_stamp)){
            $diff = $now->diff($lyft_refresh_token_created_time_stamp);
            $refresh = 0;
        }

        if($refresh == 1 || strlen(CakeSession::read('lyft_refresh_token')) != 120 || $this->token_refresh_time < $diff->i){
            $this->refresh_token = $this->getRefreshToken();
            CakeSession::write('lyft_refresh_token', $this->refresh_token);
            CakeSession::write('lyft_refresh_token_created_time_stamp', $now);
        }else{
            $this->refresh_token = CakeSession::read('lyft_refresh_token');
        }
    }

    public function run($provider, $input){
        if(!$this->runBookingSearchRequest($provider['name'], $input))
            return false;

        $this->setRequestType($input['request_type']);
        $this->multi_api_helpers->setFlatRateIncrease($provider['flat_rate_increase']);
        $this->multi_api_helpers->setPercentageRateIncrease($provider['percentage_rate_increase']);

        if($this->request_type == 'booking_search_request'){
            return $this->bookingSearchRequest($input);
        }elseif($this->request_type == 'booking_placement_request'){
            return $this->bookingPlacementRequest($input);
        }
    }

    public function setRequestType($rt){
        $this->request_type = $rt;
    }

    private function getRequestType(){
        return $this->request_type;
    }

    public function runBookingSearchRequest($name, $input){
        if($name != $this->name){
            return false;
        }

        if(isset($input['asap']) && $input['asap'] == 0){
            $this->asap = 0;
        }

        return true;
    }

    private function getAccessToken(){
        $params = array('url'=>$this->configs['access_token_url'],'client_id'=>$this->configs['client_id'],'client_secret'=>$this->sandbox.$this->configs['client_secret'],'payload'=>array('grant_type'=>'client_credentials','scope'=>$this->scope));
        $r = json_decode($this->curl->request($params));
        return $r->access_token;
    }

    private function getRefreshToken(){
        $params = array('url'=>$this->configs['access_token_url'],'client_id'=>$this->configs['client_id'],'client_secret'=>$this->sandbox.$this->configs['client_secret'],'payload'=>array('grant_type'=>'refresh_token','refresh_token'=>$this->configs['refresh_token']));
        $r = json_decode($this->curl->request($params));
        return $r->access_token;
    }

    public function getResponseCode($in){
        $out = $this->generic_error;

        if($this->getRequestType() == 'booking_search_request')
            if(isset($in['cars'][0]['request_id']) && strlen($in['cars'][0]['request_id']) == 64)
                $out = 200;

        if($this->getRequestType() == 'booking_placement_request')
            if(1)
                $out = 200;

        return $out;
    }

    public function getResponseStatus($in){
        $out = $this->generic_error;

        if($this->getRequestType() == 'booking_search_request')
            if(isset($in['cars'][0]['request_id']) && strlen($in['cars'][0]['request_id']) == 64)
                $out = true;

        if($this->getRequestType() == 'booking_placement_request')
            if(1)
                $out = true;

        return $out;
    }

    public function formatResponseData($in){
        return $in;
    }

    public function bookingSearchRequest($a){
        return array(
            'url' => $this->configs['server'].'/cost?start_lat='.$a['lat_from'].'&start_lng='.$a['long_from'].'&end_lat='.$a['lat_to'].'&end_lng='.$a['long_to'],
            'method' => 'GET',
            'class' => $this->class,
            'fctn' => 'bookingSearchResponse',
            'name' => $this->name,
            'request_type' => $a['request_type'],
            'authorization' => $this->access_token
        );
    }

    public function bookingSearchResponse($in){
        $out = array();
        foreach($in->cost_estimates as $ride){
            if($ride->ride_type != 'lyft_line'){
                $car = array();
                $car['rate_vehicle_id'] = $this->mappings->carTypeProviderToFlitways($ride->ride_type);
                $car['rate_meet_and_greet'] = 0;
                $car['rate_total'] = $this->multi_api_helpers->markUpPrice($ride->estimated_cost_cents_max/100);
                $car['successful_fee'] = $this->multi_api_helpers->successfulFee($ride->estimated_cost_cents_max/100);
                $car['request_id'] = $this->util->randomString(64);
                $car['processing_fee'] = '0.00';
                $cars[] = $car;
            }
        }
        $out['cars'] = $cars;

        return $out;
    }

    public function bookingPlacementRequest($a){
        $name = explode(' ', $a['passenger_name']);
        if(!isset($name[1]))
            $name[1] = '';

        $car_type = $this->mappings->carTypeFlitwaysToProvider($a['vehicle_fleet_id']);

        if(strstr($car_type, 'lyft') && $this->asap == 1){
            return array('payload'=>'{"ride_type":"'.$this->mappings->carTypeFlitwaysToProvider($a['vehicle_fleet_id']).'","origin":{"lat":'.$a['lat_from'].',"lng":'.$a['long_from'].',"address":"'.$a['pickup_address'].'"},"destination":{"lat":'.$a['lat_to'].',"lng":'.$a['long_to'].',"address":"'.$a['destination_address'].'"},"passenger":{"first_name":"'.$name[0].'","last_name":"'.$name[1].'","phone_number":"+1'.$a['passenger_phone'].'"},"external_note":"'.$a['passenger_name'].'"}',
                'url' => $this->configs['server'].'/dispatches',
                'method' => 'POST',
                'class' => $this->class,
                'fctn'=>'bookingPlacementResponse',
                'name'=> $this->name,
                'request_type' => $a['request_type'],
                'authorization' => $this->refresh_token
            );
        }elseif(strstr($car_type, 'lyft') && $this->asap == 0){
            $url = $_SERVER['REQUEST_SCHEME'].'://'.$_SERVER['SERVER_NAME'].'/lyft_mac_pre_book';
            return array(
                'url' => $url,
                'method' => 'GET',
                'class' => $this->class,
                'fctn' => 'preBookingPlacementResponse',
                'name' => $this->name,
                'request_type' => $a['request_type'],
            );
        }
    }

    public function preBookingPlacementResponse($in){
        $out = array();
        if(isset($in) && strlen($in->pre_booking_id) == 64){
            $out['api_ride_id'] = $in->pre_booking_id;
            $out['sent_api_no'] = $in->pre_booking_id;
        }else{
            $out['api_ride_id'] = null;
            $out['sent_api_no'] = null;
        }

        return $out;
    }

    public function cronBookingPlacementRequest($a){
        $name = explode(' ', $a['passenger_name']);
        if(!isset($name[1]))
            $name[1] = '';

        return array(
            'payload'=>'{"ride_type":"'.$this->mappings->carTypeFlitwaysToProvider($a['vehicle_fleet_id']).'","origin":{"lat":'.$a['lat_from'].',"lng":'.$a['long_from'].',"address":"'.$a['pickup_address'].'"},"destination":{"lat":'.$a['lat_to'].',"lng":'.$a['long_to'].',"address":"'.$a['destination_address'].'"},"passenger":{"first_name":"'.$name[0].'","last_name":"'.$name[1].'","phone_number":"+1'.$a['passenger_phone'].'"},"external_note":"'.$a['passenger_name'].'"}',
            'url' => $this->configs['server'].'/dispatches',
            'method' => 'POST',
            'authorization' => $this->refresh_token
        );
    }

    public function bookingPlacementResponse($in){
        $out = array();
        if(isset($in) && $in->status == 'pending'){
            $out['api_ride_id'] = $in->ride_id;
            $out['sent_api_no'] = $in->ride_id;
        }else{
            $out['api_ride_id'] = null;
            $out['sent_api_no'] = null;
        }

        return $out;
    }

    public function bookingUpdateRequest($a){

    }

    public function bookingUpdateResponse($in){

    }
}
