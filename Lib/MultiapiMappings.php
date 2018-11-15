<?php

class MultiapiMappings
{
    private $carTypes;
    private $providerStatus;

    public function __construct() {
    }

    public function setProviderStatus($ps){
        $this->providerStatus = $ps;
    }

    public function setCarTypes($ct){
        $this->carTypes = $ct;
    }

    public function fromProviderStatusIndex($index){
        $rv = null;
        foreach($this->providerStatus as $status){
            if($status['multi_api_provider_status']['job_status'] == $index){
                $rv = $status['multi_api_provider_status'];
                break;
            }
        }
        return $rv;
    }

    public function carTypeFlitwaysToProvider($id){
        $rv = null;
        foreach($this->carTypes as $row){
            if($row['flitways_fleet_id'] == $id){
                $rv = $row['provider_cartype'];
                break;
            }
        }
        return $rv;
    }

    public function carTypeProviderToFlitways($type){
        $rv = null;
        foreach($this->carTypes as $row){
            if($row['provider_cartype'] == $type){
                $rv = $row['flitways_fleet_id'];
                break;
            }
        }
        return $rv;
    }
}
