<?php

class SetUber
{
    public function __construct($a) {
        $this->server = $a['server'];
    }

    public function runProvider($name, $input){
        if($name != $this->name){
            return false;
        }

        return true;
    }

    public function setRideEstimate($a){
        return array(
            'payload'=>'{"start_latitude":"'.$a['uber_start_latitude'].'","start_longitude":"'.$a['uber_start_longitude'].'","end_latitude":"'.$a['uber_end_latitude'].'","end_longitude":"'.$a['uber_end_longitude'].'","seat_count:"'.isset($a['uber_seat_count']).'"}',
            'url'=>$this->server.'/v1/estimates/price',
            'method'=>'POST'
        );
    }
}
