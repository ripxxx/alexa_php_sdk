<?php

namespace hello;

use AlexaPHPSDK\EndSessionRequest;
use AlexaPHPSDK\Response;

class EndSession extends EndSessionRequest {
    
    public function run($params = array()) {
        $response = $this->endSessionResponse();
        return $response;
    }
    
}