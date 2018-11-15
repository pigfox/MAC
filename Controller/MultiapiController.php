<?php
APP::import('Model', 'Multiapirequestlog');
App::uses('AppController', 'Controller');
App::uses('SetNYEastern', 'Lib');
App::uses('SetUber', 'Lib');
App::uses('SetWingz', 'Lib');
App::uses('SetLyft', 'Lib');
App::uses('Auth', 'Lib');
App::uses('Util', 'Lib');
App::uses('MultiapiHelpers', 'Lib');
class MultiapiController extends AppController {

    private $targets = array();
    private $input = array();
    private $enabled_apis;
    private $classes = array();
    private $util;
    private $time_limit = 7; //secs
    private $multi_api_helpers;
    private $multiapirequestlog;
    private $request_cycle_id = null;

    public function __construct($request = null, $response = null){
        parent::__construct($request, $response);
        $this->response->disableCache();
        $json = file_get_contents('php://input');
        $this->input = json_decode($json, TRUE);
        $config = Configure::read('multi_api_key');
        if(isset($config['multi_api_key']) && isset($this->input['multi_api_key'])){
            $auth = new Auth();
            if(!$auth->enforceKey($config['multi_api_key'], $this->input['multi_api_key']))
                exit('Invalid Access Key');
        }
        else{
            exit('Key(s) not set');
        }

        $this->multi_api_helpers = new MultiapiHelpers($response);
        $this->setResources();
        $this->logRequest($this->input);
    }

    private function logRequest($in){
        unset($in['multi_api_key']);
        $log = json_encode($in);
        if($in['request_type'] == 'booking_search_request'){
            $this->multiapirequestlog->multi_api_partner_api_booking_search_request_log($this->request_cycle_id, $log);
        }elseif($in['request_type'] == 'booking_placement_request'){
            $this->multiapirequestlog->multi_api_partner_api_booking_placement_request_log($this->request_cycle_id, $log);
        }
    }

    private function setResources(){
        $this->util = new Util();
        $this->request_cycle_id = $this->util->getGUID();
        $this->multiapirequestlog = new Multiapirequestlog();
        $this->Multiapi->setSource('multi_api_providers');
        $this->enabled_apis = $this->Multiapi->getEnabledApis();
    }

    public function request(){
        $this->autoRender = false;

        foreach($this->enabled_apis as $v){
            $this->classes[$v['multi_api_providers']['class']] = new $v['multi_api_providers']['class'];
            $this->targets[] = $this->classes[$v['multi_api_providers']['class']]->run($v['multi_api_providers'], $this->input);
        }

        if(0 < count($this->targets)){
            $this->execute($this->targets);
        }else{
            $response['status'] = false;
            $response['code'] = 'E002';
            $response['message'] = 'There are no vehicles available for your '.str_replace('_', ' ', $this->input['request_type']);
            $this->multi_api_helpers->output($response);
            exit();
        }
    }//end function request

    private function execute($apis){
        $mh = curl_multi_init();
        $ch = array();
        foreach($apis as $i => $api){
            $ch[$i] = curl_init($api['url']);
            curl_setopt($ch[$i], CURLOPT_VERBOSE, 1);
            curl_setopt($ch[$i], CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch[$i], CURLINFO_HEADER_OUT, 1);

            if($api['method'] == 'GET')
                curl_setopt($ch[$i], CURLOPT_HTTPGET, 1);

            if(isset($api['payload']) && 0 < strlen($api['payload'])){
                curl_setopt($ch[$i], CURLOPT_POST, 1);
                curl_setopt($ch[$i], CURLOPT_POSTFIELDS, $api['payload']);
            }

            if($api['method'] == 'POST'){
                curl_setopt($ch[$i], CURLOPT_POST, 1);
            }

            $headers = array('Content-Type:application/json');

            if(isset($api['api-key']) && isset($api['api-key-name']))
                $headers[] = $api['api-key-name'].':'.$api['api-key'];

            if(isset($api['authorization']))
                $headers[] = 'Authorization:Bearer '.$api['authorization'];

            if(isset($api['token']))
                $headers[] = 'token:'.$api['token'];

            if(isset($api['payload']) && 0 < strlen($api['payload']))
                $headers[] = 'Content-Length:'.strlen($api['payload']);

            curl_setopt($ch[$i], CURLOPT_HTTPHEADER, $headers);

            $ch['start'][$i] = microtime(true);
            curl_multi_add_handle($mh, $ch[$i]);

            if($api['request_type'] == 'booking_search_request')
                $this->multiapirequestlog->multi_api_provider_booking_search_request_log($this->request_cycle_id, json_encode($headers), json_encode($api));
            elseif($api['request_type'] == 'booking_placement_request')
                $this->multiapirequestlog->multi_api_provider_booking_placement_request_log($this->request_cycle_id, json_encode($headers), json_encode($api));
        }

        $running = null;
        $start_networking = microtime(true);

        do {
            curl_multi_exec($mh, $running);

            if($this->time_limit <= $this->util->timeDiff($start_networking))
                $running = false;

        } while ($running);

        $response = array();
        $response_codes = array(200,201);

        foreach($apis as $i => $api){
            try{
                if(isset($ch[$i]) && in_array(curl_getinfo($ch[$i], CURLINFO_HTTP_CODE), $response_codes)){
                    $data = $this->classes[$api['class']]->{$api['fctn']}(json_decode(curl_multi_getcontent($ch[$i])));
                    if($data != null){
                        $response['code'] = $this->classes[$api['class']]->getResponseCode($data);
                        $response['success'] = $this->classes[$api['class']]->getResponseStatus($data);
                        $response['response_type'][$api['request_type']][] = $this->classes[$api['class']]->formatResponseData($data);
                    }
                }else{
                    $request_type = $api['request_type'];
                }

                if(isset($api['request_type']) && $api['request_type'] == 'booking_search_request'){
                    $this->multiapirequestlog->multi_api_provider_booking_search_response_log($this->request_cycle_id, json_encode(curl_getinfo($ch[$i])), curl_multi_getcontent($ch[$i]));
                }

                if(isset($api['request_type']) && $api['request_type'] == 'booking_placement_request'){
                    $this->multiapirequestlog->multi_api_provider_booking_placement_response_log($this->request_cycle_id, json_encode(curl_getinfo($ch[$i])), curl_multi_getcontent($ch[$i]));
                }

            }catch(Exception $e){
                $response['exception'][$api['name'].$api['fctn']] = $e->getMessage();
            }
        }

        if(isset($response['response_type']) && array_key_exists('booking_search_request',$response['response_type'])){
            $this->multiapirequestlog->multi_api_partner_api_booking_search_response_log($this->request_cycle_id, json_encode($response));
            $response = $this->multi_api_helpers->filterBookingSearchResponse($response);
        }

        if(isset($response['response_type']) && array_key_exists('booking_placement_request',$response['response_type'])){
            $this->multiapirequestlog->multi_api_partner_api_booking_placement_response_log($this->request_cycle_id, json_encode($response));
            $response = $this->multi_api_helpers->filterBookingPlacementResponse($response);
        }

        if(0 == count($response)){
            $response['status'] = false;
            $response['code'] = 'E001';
            if(isset($request_type))
                $response['message'] = 'There are no results available for your '.str_replace('_', ' ', $request_type).'.';
            else
                $response['message'] = 'There are no results available';
        }

        curl_multi_remove_handle($mh, $ch[$i]);
        $this->request_cycle_id = null;
        $this->multi_api_helpers->output($response);
    }//end function execute()
}
?>
