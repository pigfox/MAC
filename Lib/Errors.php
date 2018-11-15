<?php

class Errors
{
    private $credit_codes = array();
    private $promo_code_codes = array();

    public function __construct($request = null, $response = null){
        $this->setCreditCodes();
        $this->setPromoCodeCodes();
    }

    private function setCreditCodes(){
        $this->credit_codes[] = array('code'=>'E000','msg'=>'Incorrect Input');
        $this->credit_codes[] = array('code'=>'E001','msg'=>'API Key is Required');
        $this->credit_codes[] = array('code'=>'E002','msg'=>'User ID is Required');
        $this->credit_codes[] = array('code'=>'E003','msg'=>'Passenger Credit Code ID is Required');
        $this->credit_codes[] = array('code'=>'E004','msg'=>'Fare Amount is Required');
        $this->credit_codes[] = array('code'=>'E005','msg'=>'No Match Found Between User ID & Passenger Credit Code ID');
        $this->credit_codes[] = array('code'=>'E006','msg'=>'Credit Balance Was Not Applied');
        $this->credit_codes[] = array('code'=>'E007','msg'=>'No Credits Found For User ID');
        $this->credit_codes[] = array('code'=>'E008','msg'=>'Credit Code is Required');
        $this->credit_codes[] = array('code'=>'E009','msg'=>'User Account is Not Found');
        $this->credit_codes[] = array('code'=>'E010','msg'=>'Credit Code not found');
        $this->credit_codes[] = array('code'=>'E011','msg'=>'Credit code already used');
        $this->credit_codes[] = array('code'=>'E012','msg'=>'Credit code cannot be added. Please try again');
        $this->credit_codes[] = array('code'=>'E013','msg'=>'Invalid API Key');
    }

    public function getCreditCode($i){
        return $this->credit_codes[$i];
    }

    private function setPromoCodeCodes(){
        $this->promo_code_codes[] = array('code'=>'E000','msg'=>'Incorrect Input');
        $this->promo_code_codes[] = array('code'=>'E001','msg'=>'API Key is Required');
        $this->promo_code_codes[] = array('code'=>'E002','msg'=>'Fare Price Requried');
        $this->promo_code_codes[] = array('code'=>'E003','msg'=>'Promo Code Requried');
        $this->promo_code_codes[] = array('code'=>'E004','msg'=>'Phone Requried');
        $this->promo_code_codes[] = array('code'=>'E005','msg'=>'Invalid API Key');
    }

    public function getPromoCodeCode($i){
        return $this->promo_code_codes[$i];
    }

}