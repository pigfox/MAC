<?php
APP::import('Model', 'CurlLibDebug');
class Curl
{
    public function request($params){

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL,$params['url']);

        if(isset($params['payload']) || isset($params['client_id']) || isset($params['client_secret'])){
            curl_setopt($ch, CURLOPT_POST, 1);
        }

        if(isset($params['client_id']) && isset($params['client_secret'])){
            curl_setopt($ch,CURLOPT_USERPWD, $params['client_id'].":".$params['client_secret']);
        }

        if(isset($params['urlencode'])){
            $encoded = '';

            foreach($params['urlencode'] as $k => $v)
                $encoded .= urlencode($k).'='.urlencode($v).'&';

            $encoded = substr($encoded, 0, strlen($encoded)-1);
            curl_setopt($ch, CURLOPT_POSTFIELDS,  $encoded);
        }

        if(isset($params['payload'])){
            if(is_array($params['payload']))
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params['payload']));
             else
                 curl_setopt($ch, CURLOPT_POSTFIELDS, $params['payload']);
        }

        if(isset($params['custom_request']) && ($params['custom_request'] == 'PUT' || $params['custom_request'] == 'DELETE' || $params['custom_request'] == 'POST')){
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $params['custom_request']);
        }

        $headers = array();

        if(isset($params['payload'])){
            $headers[] = 'Accept:application/json';
            $headers[] = 'Content-Type:application/json';

            if(is_array($params['payload'])){
                if(0 < strlen(json_encode($params['payload'])))
                    $headers[] = 'Content-Length:'.strlen(json_encode($params['payload']));
            }else{
                if(0 < strlen($params['payload']))
                    $headers[] = 'Content-Length:'.strlen($params['payload']);
            }

            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }
        elseif(isset($params['urlencode'])){
            $headers[] = 'Content-Length:'.strlen($encoded);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }

        if(isset($params['authorization'])){
            $headers[] = 'Authorization:Bearer '.$params['authorization'];
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        try {
            $rv = curl_exec($ch);
            $response_headers = curl_getinfo($ch);
        } catch (Exception $e) {
            $rv = $e;
        }
        curl_close ($ch);

        if(isset($params['payload']))
            $this->recordRequestCycle($params['payload'], $headers, $rv, $response_headers);
        else
            $this->recordRequestCycle('', $headers, $rv, $response_headers);

        return $rv;
    }

    private function recordRequestCycle($p, $h, $r, $rh){
        $cld = new CurlLibDebug();

        if(is_object($p) || is_array($p))
            $a['params'] = json_encode($p);
        elseif(is_string($p))
            $a['params'] = $p;
        else
            $a['params'] = '';

        if(is_object($h) || is_array($h))
            $a['request_headers'] = json_encode($h);
        elseif (is_string($h))
            $a['request_headers'] = $h;
        else
            $a['request_headers'] = '';

        if(is_object($r) || is_array($r))
            $a['response'] = json_encode($r);
        elseif(is_string($r))
            $a['response'] = $r;
        else
            $a['response'] = '';

        if(is_object($rh) || is_array($rh))
            $a['response_headers'] = json_encode($rh);
        elseif(is_string($rh))
            $a['response_headers'] = $rh;
        else
            $a['response_headers'] = '';

        $cld->recordRequestCycle($a);
    }
}
