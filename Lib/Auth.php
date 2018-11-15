<?php
class Auth
{
    public function enforceServerIP($request_ip, $allowed_ips){
        if(!in_array($request_ip, $allowed_ips))
            die('Access denied, invalid ip');
    }

    public function enforceKey($required, $supplied){
        if($required != $supplied)
            return false;
        else
            return true;
    }
}
