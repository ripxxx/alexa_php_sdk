<?php

namespace SKILL_NAMESPACE;

use AlexaPHPSDK\Intent;
use AlexaPHPSDK\Response;

//SLOTS

class INTENT_NAME extends Intent {
    
    public function ask($params = array()) {
        return $this->endSessionResponse('Goodbye.');
    }
    
    public function run($params = array()) {
        return $this->endSessionResponse('Goodbye.');
    }
    
}