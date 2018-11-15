<?php
APP::import('Model', 'Utility');
class Util
{
    private $time_zone = 'UTC';

    public function convertTimeStamp($google_server_time, $time_stamp){
        $date = new DateTime($time_stamp, new DateTimeZone($this->time_zone));
        $date->setTimezone(new DateTimeZone($google_server_time['timeZoneId']));
        return $date->format('Y-m-d H:i:s');
    }

    function getUserIP(){
        $client  = @$_SERVER['HTTP_CLIENT_IP'];
        $forward = @$_SERVER['HTTP_X_FORWARDED_FOR'];
        $remote  = $_SERVER['REMOTE_ADDR'];

        if(filter_var($client, FILTER_VALIDATE_IP)){
            $ip = $client;
        }
        elseif(filter_var($forward, FILTER_VALIDATE_IP)){
            $ip = $forward;
        }
        else{
            $ip = $remote;
        }

        return $ip;
    }

    public function timeDiff($start){
        return microtime(true) - $start;
    }

    function setCurrency(){
        $this->Utility = new Utility();
        $this->Utility->setSource('ip2country');
        $country = $this->Utility->getCountryCurrencyByIp($this->getUserIP());

        if(3 == strlen($country[0]['cc']['currency']))
            CakeSession::write('currency', $country[0]['cc']['currency']);
        else
            CakeSession::write('currency', 'USD');
    }

    public function getPercentages(){
        for($i = 5; $i <= 100; $i = $i+5)
            $a[] = $i;

        return $a;
    }

    public function getAmounts(){
        for($i = 5; $i <= 50; $i = $i+5)
            $a[] = $i;

        return $a;
    }

    public function getDays(){
        for($i = 1; $i <= 31; $i++)
            $a[] = $i;

        return $a;
    }

    public function getMonths(){
        return array(1=>'Jan',2=>'Feb',3=>'Mar',4=>'Apr',5=>'May',6=>'Jun',7=>'Jul',8=>'Aug',9=>'Sep',10=>'Oct',11=>'Nov',12=>'Dec');
    }

    public function getYears(){
        for($i = 2015; $i <= 2030; $i++)
            $a[] = $i;

        return $a;
    }

    public function generateUpperCaseLetters($length){
        $key = '';
        $charset = "ABCDEFGHIJKLMNOPQRSTUVWXYZ";

        for($i=0; $i<$length; $i++)
            $key .= $charset[(mt_rand(0,(strlen($charset)-1)))];

        return $key;
    }

    function isValidUuid( $uuid ) {

        if (!is_string($uuid) || (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $uuid) !== 1)) {
            return false;
        }
        return true;
    }

    function randomString($length = 6) {
        $str = "";
        $characters = array_merge(range('A','Z'), range('a','z'), range('0','9'));
        $max = count($characters) - 1;
        for ($i = 0; $i < $length; $i++) {
            $rand = mt_rand(0, $max);
            $str .= $characters[$rand];
        }
        return $str;
    }

    function getGUID(){
        if (function_exists('com_create_guid')){
            return com_create_guid();
        }
        else {
            mt_srand((double)microtime()*10000);
            $charid = strtoupper(md5(uniqid(rand(), true)));
            $hyphen = chr(45);// "-"
            $uuid = chr(123)// "{"
                .substr($charid, 0, 8).$hyphen
                .substr($charid, 8, 4).$hyphen
                .substr($charid,12, 4).$hyphen
                .substr($charid,16, 4).$hyphen
                .substr($charid,20,12)
                .chr(125);// "}"
            return $uuid;
        }
    }
}
