<?php
/**
 * Created by Aleksandr Berdnikov.
 * Copyright 2016 Onix-Systems.
*/

namespace AlexaPHPSDK;

class Intent {
    protected $applicationId;
    protected $deviceId;
    protected $id;
    protected $locale;
    protected $timestamp;
    protected $user;
    
    public function __construct(User $user, $applicationId, $requestId, $deviceId, $locale, $timestamp) {
        $this->applicationId = $applicationId;
        $this->deviceId = $deviceId;
        $this->id = $requestId;
        $this->locale = $locale;
        $this->timestamp = strtotime($timestamp);
        $this->user = $user;
    }
    
    public function __get($name) {
        if($name == 'applicationId') {
            return $this->applicationId;
        }
        else if($name == 'deviceId') {
            return $this->deviceId;
        }
        else if($name == 'id') {
            return $this->id;
        }
        else if($name == 'locale') {
            return $this->locale;
        }
        else if($name == 'progressiveResponse') {
            if(ProgressiveResponse::$enabled) {
                return new ProgressiveResponse($this->id, Context::getInstance()->apiEndpoint, Context::getInstance()->apiAccessToken);
            }
        }
        else if($name == 'reminder') {
            if(Reminder::$enabled) {
                return new Reminder($this->id, Context::getInstance()->apiEndpoint, Context::getInstance()->apiAccessToken);
            }
        }
        else if($name == 'response') {
            return new Response(false);
        }
        else if($name == 'timestamp') {
            return $this->timestamp;
        }
        else if($name == 'user') {
            return $this->user;
        }
        else if($name == 'name') {
            $classNameParts = explode('\\', get_class($this));
            return str_replace('Intent', '', array_pop($classNameParts));
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

