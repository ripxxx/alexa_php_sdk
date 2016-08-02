<?php

namespace SKILL_NAMESPACE;

use AlexaPHPSDK\LaunchRequest;
use AlexaPHPSDK\Response;

class Launch extends LaunchRequest {
    
    public function run($params = array()) {
        $response = new Response();
        $response->addText('Hello!');
        return $response;
    }
    
}

