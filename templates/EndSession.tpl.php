<?php

class EndSession extends EndSessionRequest {
    
    public function run($params = array()) {
        $response = $this->endSessionResponse();
        return $response;
    }
    
}