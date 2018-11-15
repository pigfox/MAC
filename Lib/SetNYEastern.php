<?php
App::uses('CakeSession', 'Model/Datasource');
APP::import('Model', 'Booking');
APP::import('Model', 'Nyeasterncancellationlog');
App::uses('MultiapiMappings', 'Lib');
App::uses('MultiapiHelpers', 'Lib');
APP::import('Model', 'Multipaicartypes');
App::uses('Multiapi', 'Model');
App::uses('NYEastern', 'Model');
class SetNYEastern
{
	private $class = 'SetNYEastern';
	private $name = 'NYEastern';
    private $booking;
    private $nyEasternCancellationLog;
    private $mappings;
    private $request_type;
    private $generic_error = 'E007';
    private $generic_message = 'Sorry, your booking can\'t be processed. Please start a new search to try again';
    private $multi_api_helpers;
    private $multi_api_model;
    private $configs;
    private $zip_codes_nj_ny_states;
    private $pickup_target_radius;

	public function __construct() {
        $this->multi_api_model = new Multiapi();
        $this->configs = CakeSession::read('set_nyeastern');
        if(!isset($this->configs) || $this->configs == null){
            $this->configs = $this->multi_api_model->getClassSetters('set_nyeastern');
            CakeSession::write('set_nyeastern', $this->configs);
        }

        $this->zip_codes_nj_ny_states = CakeSession::read('zip_codes_nj_ny_states');
        if(!isset($this->zip_codes_nj_ny_states) || $this->zip_codes_nj_ny_states == null){
            $ny_eastern_model = new NYEastern();
            $this->zip_codes_nj_ny_states = $ny_eastern_model->getLatLngNjNyStates();
            CakeSession::write('zip_codes_nj_ny_states', $this->zip_codes_nj_ny_states);
        }

        $this->booking = new Booking();
        $this->multiApiCarTypes = new Multipaicartypes();
        $this->mappings = new MultiapiMappings();
        $this->mappings->setCarTypes($this->multiApiCarTypes->getAll($this->configs['multi_api_providers_id']));

        $this->nyEasternCancellationLog = new Nyeasterncancellationlog();
        $this->multi_api_helpers = new MultiapiHelpers();
   }

    public function run($provider, $input){
        $this->pickup_target_radius = $provider['pickup_target_radius'];

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

    private function filterBookingCode($in){
        $out = $this->generic_error;

        if(isset($in['code']))
            return $in['code'];
        elseif(isset($in['status']) && $in['status'] == true)
            return 200;

        if(isset($in['cars']) && 0 < count($in['cars']))
            $out = 200;

        return $out;
    }

    private function filterBookingStatus($in){
        $out = $this->generic_error;

        if($this->getRequestType() == 'booking_search_request'){
            if(isset($in['cars']) && 0 < count($in['cars']))
                $out = true;
        }

        if($this->getRequestType() == 'booking_placement_request')
            if(isset($in['api_ride_id']) && is_numeric($in['api_ride_id']) && isset($in['sent_api_no']) && is_numeric($in['sent_api_no']))
                $out = true;
            else
                $out = $in['status'];

        return $out;
    }

    public function getResponseCode($in){
        $out = $this->generic_error;

        if($this->getRequestType() == 'booking_search_request'){
            $out = $this->filterBookingCode($in);
        }

        if($this->getRequestType() == 'booking_placement_request'){
            $out = $this->filterBookingCode($in);
        }

        return $out;
    }

    public function getResponseStatus($in){
        $out = $this->generic_error;

        if($this->getRequestType() == 'booking_search_request'){
            $out = $this->filterBookingStatus($in);
        }

        if($this->getRequestType() == 'booking_placement_request'){
            $out = $this->filterBookingStatus($in);
        }

        return $out;
    }

    public function formatResponseData($in){
        $out = $this->generic_error;

        if($this->getRequestType() == 'booking_search_request'){
            return $in;
        }

        if($this->getRequestType() == 'booking_placement_request'){
            if($in['status']){
                $out = array('api_ride_id'=>$in['api_ride_id'],'sent_api_no'=>$in['sent_api_no']);
            }
            else{
                $out = array('message'=>$in['reason']);
            }
        }

        return $out;
    }

    public function runBookingSearchRequest($name, $input){
        if($name != $this->name){
            return false;
        }

        if($input['asap'] == 1){
            return false;
        }

        if(!$this->isLocal($input)){
            return false;
        }

        return true;
    }

   private function isLocal($input){
        $rv = false;
        foreach($this->zip_codes_nj_ny_states as $location){
            $theta = (double) ($input['long_from'] - $location['lng']);
            $lat_to = (double) $location['lat'];
            $dist = sin(deg2rad($input['lat_from'])) * sin(deg2rad(($lat_to))) +  cos(deg2rad($input['lat_from'])) * cos(deg2rad($lat_to)) * cos(deg2rad($theta));
            $dist = acos($dist);
            $dist = rad2deg($dist);
            $miles = $dist * 60 * 1.1515;

            if($miles <= $this->pickup_target_radius){
                $rv = true;
                break;
            }
        }
        return $rv;
   }

   public function getParamters(){
	    return array('server'=>$this->configs['server'],'key'=>$this->configs['server_key']);
   }

   public function bookingSearchRequest($a){
       return array(
           'payload'=>'{"key":"'.$this->configs['server_key'].'","method":"quote","test":'.$this->configs['test'].',"pickup":{"address":"'.$a['pickup_address'].'","latlng":"'.$a['lat_from'].','.$a['long_from'].'"},"destination":{"address":"'.$a['destination_address'].'","latlng":"'.$a['lat_to'].','.$a['long_to'].'"},"all_inclusive":true,"car_type":"all","meet_greet":false}',
           'url' => $this->configs['server'],
           'method' => 'POST',
           'class' => $this->class,
           'fctn'=>'bookingSearchResponse',
           'name'=> $this->name,
           'request_type' => $a['request_type']
       );
   }

    public function bookingSearchResponse($in){
        if($in->status == true){
            $out = array('cars'=>'');
            $cars = array();

            foreach($in->quote->economy as $key => $item){
                $car = array();
                $car['rate_vehicle_id'] = $this->mappings->carTypeProviderToFlitways($key);
                $car['rate_meet_and_greet'] = $item->pricing->meet_greet;
                $car['rate_total'] = $this->multi_api_helpers->markUpPrice($item->pricing->total);
                $car['successful_fee'] = $this->multi_api_helpers->successfulFee($item->pricing->total);
                $car['request_id'] = $item->quote_id;
                $car['processing_fee'] = '0.00';
                $cars[] = $car;
            }

            $out['cars'] = $cars;
            return $out;
        }else{
            return null;
        }
    }

    public function bookingPlacementRequest($a){
        return array('payload'=>'{"key":"'.$this->configs['server_key'].'","quote_id":"'.$a['request_id'].'","reservation":"'.$a['pickup_time'].'","passengers":'.$a['no_of_passengers'].',"special_request":"'.$a['additional_info'].'","flight_no":"'.$a['flight_info'].'","car_type":"'.$this->mappings->carTypeFlitwaysToProvider($a['vehicle_fleet_id']).'","name":"'.$a['passenger_name'].'","phone":"'.$a['passenger_phone'].'","email":"'.$a['passenger_email'].'","test":'.$this->configs['test'].',"method":"booking","pricing":true,"service_type":"economy","all_inclusive":true,"tip_rate":false,"meet_greet":true,"reference":"'.$a['passenger_name'].'"}',
            'url' => $this->configs['server'],
            'method' => 'POST',
            'class' => $this->class,
            'fctn'=>'bookingPlacementResponse',
            'name'=> $this->name,
            'request_type' => $a['request_type']
        );
    }

    public function bookingPlacementResponse($in){
        if($in->status == true){
            return array('status'=>true,'api_ride_id'=>$in->job_no,'sent_api_no'=>$in->job_no);
        }else{
            return array('status'=>false,'code'=>$in->error->code,'reason'=>$in->error->reason,'method'=>$in->method);
        }
    }
}
