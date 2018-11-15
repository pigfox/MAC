<?php
APP::import('Model', 'Multipaicartypes');
App::uses('Curl', 'Lib');
App::uses('Countrycode', 'Model');
App::uses('Multiapi', 'Model');
App::uses('MultiapiHelpers', 'Lib');
App::uses('MultiapiMappings', 'Lib');
App::uses('RandomEmail', 'Lib');

class SetWingz
{
    private $class = 'SetWingz';
    private $name = 'Wingz';
    private $curl;
    private $country_code;
    private $tmp = null;
    private $request_type;
    private $generic_error = 'E007';
    private $multi_api_helpers;
    private $multi_api_model;
    private $configs;
    private $random_email;

    public function __construct() {
        $this->multi_api_model = new Multiapi();

        $this->configs = CakeSession::read('set_wingz');
        if(!isset($this->configs) || $this->configs == null){
            $this->configs = $this->multi_api_model->getClassSetters('set_wingz');
            CakeSession::write('set_wingz', $this->configs);
        }

        $this->curl = new Curl();
        $this->random_email = new RandomEmail();
        $this->country_code = new Countrycode();
        $this->multi_api_helpers = new MultiapiHelpers();
        $this->multiApiCarTypes = new Multipaicartypes();
        $this->mappings = new MultiapiMappings();
        $this->mappings->setCarTypes($this->multiApiCarTypes->getAll($this->configs['multi_api_providers_id']));
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

    public function getParamters(){
        return array('server'=>$this->configs['server'],'key'=>$this->configs['server_key'],'path'=>$this->configs['path']);
    }

    public function getResponseCode($in){
        $out = $this->generic_error;

        if($this->getRequestType() == 'booking_search_request')
            if(isset($in['cars'][0]['request_id']) && is_numeric($in['cars'][0]['request_id']))
                $out = 200;

        if($this->getRequestType() == 'booking_placement_request')
            if(isset($in['api_ride_id']) && is_numeric($in['api_ride_id']) && isset($in['sent_api_no']) && is_numeric($in['sent_api_no']))
                $out = 200;

        return $out;
    }

    public function getResponseStatus($in){
        $out = $this->generic_error;

        if($this->getRequestType() == 'booking_search_request')
            if(isset($in['cars'][0]['request_id']) && is_numeric($in['cars'][0]['request_id']))
                $out = true;

        if($this->getRequestType() == 'booking_placement_request')
            if(isset($in['api_ride_id']) && is_numeric($in['api_ride_id']) && isset($in['sent_api_no']) && is_numeric($in['sent_api_no']))
                $out = true;

        return $out;
    }

    public function formatResponseData($in){
        return $in;
    }

    private function mapPickUpAirport($v){
        if($v == 1)
            return 'AIRPORT';
        elseif($v == 0)
            return 'ADDRESS';
    }

    private function mapDestinationAirport($v){
        if($v == 1)
            return 'AIRPORT';
        elseif($v == 0)
            return 'ADDRESS';
    }

    public function runBookingSearchRequest($name, $input){
        if($name != $this->name){
            return false;
        }

        if($input['asap'] == 1){
            return false;
        }
        
        return true;
    }

    private function getPlace($place_id){
        $p['url'] = $this->configs['server'].$this->configs['path'].'places/'.$place_id.'?api_key='.$this->configs['server_key'];
        return $this->curl->request($p);
    }

    public function bookingSearchRequest($a){
        if($a['is_pickup_airport'] == 1){
            $place = json_decode($this->getPlace($a['airport_code']));
            if(!isset($place->code))
                return;

            $fromplaceId = $place->code;
            $fromformattedAddress = $place->name;
            $fromlat = $place->lat;
            $fromlng = $place->lng;
            $toplaceId = '';
        }elseif($a['is_destination_airport'] == 1){
            $place = json_decode($this->getPlace($a['airport_code']));
            $toplaceId = $place->code;
            $fromplaceId = '';
            $fromformattedAddress = $a['pickup_address'];
            $fromlat = $a['lat_from'];
            $fromlng = $a['long_from'];
        }elseif($a['is_pickup_airport'] == 0 && $a['is_destination_airport'] == 0){
            $toplaceId = '';
            $fromplaceId = '';
            $fromformattedAddress = $a['pickup_address'];
            $fromlat = $a['lat_from'];
            $fromlng = $a['long_from'];
        }

        return array('payload' => '{"request":{"from":{"type":"'.$this->mapPickUpAirport($a['is_pickup_airport']).'","placeId":"'.$fromplaceId.'","placeName":"","formattedAddress":"'.$fromformattedAddress.'","lat":'.$fromlat.',"lng":'.$fromlng.'},"to":{"type":"'.$this->mapDestinationAirport($a['is_destination_airport']).'","placeId":"'.$toplaceId.'","placeName":"","formattedAddress":"'.$a['destination_address'].'","lat":'.$a['lat_to'].',"lng":'.$a['long_to'].'},"at":"'.str_replace (' ', 'T',$a['trip_date']).'","seats":3,"bags":1,"tips":0}}',
           'url' => $this->configs['server'].$this->configs['path'].'quotations?api_key='.$this->configs['server_key'],
           'method' => 'POST',
           'class' => $this->class,
           'fctn'=>'bookingSearchResponse',
           'name'=> $this->name,
           'request_type' => $a['request_type']
       );
    }

    public function bookingSearchResponse($in){
        $out = array('cars'=>'');
        $cars = array();

        if(isset($in) && $in->status == 'QUOTATION'){
            $car = array();
            $car['rate_vehicle_id'] = $this->configs['wingz_flitways_fleet_id'];
            $car['rate_meet_and_greet'] = 0;
            $car['rate_total'] = $this->multi_api_helpers->markUpPrice($in->financial->priceCharged);
            $car['successful_fee'] = $this->multi_api_helpers->successfulFee($in->financial->priceCharged);
            $car['request_id'] = $in->quotationUuid;
            $car['processing_fee'] = '0.00';
            $cars[] = $car;
        }
        elseif(isset($in)){
            $out['error'] = $in->error_description;
            $out['status'] = false;
        }
        $out['cars'] = $cars;

        return $out;
    }

    private function putQuotations($a){
        if(stripos($a['passenger_email'], 'flit'))
            $instructions = 'This booking is for passenger '.$a['passenger_name'].' with phone number '.$a['passenger_area_code'].$a['passenger_phone'];
         else
            $instructions = $a['additional_info'];


        $p['custom_request'] = 'PUT';
        $p['url'] = $this->configs['server'].$this->configs['path'].'quotations/'.$a['request_id'].'?api_key='.$this->configs['server_key'];

        $p['payload'] = array('request'=>array('at'=>str_replace (' ', 'T',$a['pickup_time']),'airlineCode'=>$a['flight_code'],'flightNumber'=>$a['flight_info'],'instructions'=>$instructions,'tip'=>0));
        return $this->curl->request($p);
    }

    public function bookingPlacementRequest($a){
        $put_quotations = $this->putQuotations($a);
        if(isset($put_quotations) && $put_quotations){
            $this->tmp = json_decode($put_quotations);
        }

        if($this->tmp == null || isset($this->tmp->error)){
            return array(
                'payload'=> '',
                'url' => '',
                'method' => '',
                'class' => $this->class,
                'fctn'=>'bookingPlacementResponse',
                'name'=> $this->name,
                'request_type' => $a['request_type']
            );
        }

        $pass = explode(' ',trim($a['passenger_name']));

        if(!isset($pass[1]) || $pass[1] == '')
            $pass[1] = '';

        $cc = '';

        if($a['passenger_area_code'] == 1){
            if($this->country_code->isCanadianNumber(substr($a['passenger_phone'], 0,3)))
                $cc = 'CA';
            elseif($this->country_code->isUSNumber(substr($a['passenger_phone'], 0,3)))
                $cc = 'US';
        }else{
            $country = $this->country_code->getByDailingCode($a['passenger_area_code']);
            $cc = $country['countrycode'];
        }

        return array(
            'payload' => '{"firstName":"'.$pass[0].'","lastName":"'.$pass[1].'","email":"'.$a['passenger_email'].'","phoneNumber":{"country":"'.$cc.'","number":"'.$a['passenger_phone'].'"}}',
            'url' => $this->configs['server'].$this->configs['path'].'quotations/'.$a['request_id'].'/book?api_key='.$this->configs['server_key'],
            'method' => 'POST',
            'class' => $this->class,
            'fctn'=>'bookingPlacementResponse',
            'name'=> $this->name,
            'request_type' => $a['request_type']
        );
    }

    public function bookingPlacementResponse($in){
        $out = array();
        if(isset($in) && $in->status == 'REQUESTED'){
            $out['api_ride_id'] = $in->rideUuid;
            $out['sent_api_no'] = $in->rideUuid;
        }else{
            $out['api_ride_id'] = null;
            $out['sent_api_no'] = null;
        }

        return $out;
    }
}
