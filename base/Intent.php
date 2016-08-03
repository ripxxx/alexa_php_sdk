<?php
/**
 * Created by Aleksandr Berdnikov.
 * Copyright 2016 Onix-Systems.
*/

namespace AlexaPHPSDK;

class Intent {
    private $user;
    
    public function __construct(User $user) {
        $this->user = $user;
    }
    
    public function __get($name) {
        if($name == 'user') {
            return $this->user;
        }
        return NULL;
    }
    
    public function endSessionResponse($message = 'Goodbye') {
        $response = new Response(true);
        $response->addText($message);
        return $response;
    }
    
    public function ask($params = array()) {
        $response = new Response(true);
        
        return $response;
    }
    
    public function run($params = array()) {
        $response = new Response(false);
        
        return $response;
    }
}

