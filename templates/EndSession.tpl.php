<?php

namespace SKILL_NAMESPACE;

use AlexaPHPSDK\EndSessionRequest;
use AlexaPHPSDK\Response;

class EndSession extends EndSessionRequest {
    
    public function run($params = array()) {
        $response = $this->endSessionResponse();
        return $response;
    }
    
}