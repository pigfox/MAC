<?php
class MultiapiHelpers
{
    private $response;
    private $percentage_rate_increase = 0;
    private $flat_rate_increase = 0;

    public function __construct($response=null){
        $this->response = $response;
    }

    public function setPercentageRateIncrease($pri){
        $this->percentage_rate_increase = $pri;
    }

    public function setFlatRateIncrease($fli){
        $this->flat_rate_increase = $fli;
    }

    public function markUpPrice($price){
        $rv = null;
        if(0 < $this->percentage_rate_increase){
            $rv = $price * (1 + $this->percentage_rate_increase/100);
        }elseif(0 < $this->flat_rate_increase)
            $rv = $price + $this->flat_rate_increase;
        else
            $rv = $price;

        return round($rv, 2);
    }

    public function successfulFee($price){
        $rv = null;
        if(0 < $this->percentage_rate_increase){
            $rv = $price * $this->percentage_rate_increase/100;
        }elseif(0 < $this->flat_rate_increase)
            $rv = $this->flat_rate_increase;
        else
            $rv = $price;

        return round($rv, 2);

    }

    public function filterBookingSearchResponse($response){
        $cars = array();
        foreach($response['response_type']['booking_search_request'] as $items){
            if(isset($items['cars'])){
                foreach($items['cars'] as $item){
                    $cars[] = $item;
                }
            }
        }

        $response['response_type']['booking_search_request'] = '';
        $response['response_type']['booking_search_request'][]['cars'] = $cars;

        return $response;
    }

    public function filterBookingPlacementResponse($response){
        $ride = array();
        foreach($response['response_type']['booking_placement_request'] as $item){
            if(isset($item['status']) && $item['status'] == true){
                $ride = $item;
                break;
            }else{
                $ride = $item;
            }
        }

        $response['response_type']['booking_placement_request'] = '';
        $response['response_type']['booking_placement_request'][] = $ride;

        return $response;
    }

    public function output($data){
        $this->response->type('json');
        $json = json_encode($data);
        $this->response->body($json);
    }
}
