<?php
/**
 * Created by Aleksandr Berdnikov.
*/

namespace AlexaPHPSDK;

class Context {
    protected static $instance;
    
    protected $apiAccessToken = NULL;
    protected $apiEndpoint = NULL;
    
    protected $applicationId = NULL;
    protected $deviceId = NULL;
    protected $interfaces = [];
    protected $userId = NULL;
    
    protected function __construct($applicationId, $deviceId, $userId, $apiAccessToken, $apiEndpoint, array $supportedInterfaces) {
        $this->apiAccessToken = $apiAccessToken;
        $this->apiEndpoint = $apiEndpoint;
        
        $this->applicationId = $applicationId;
        $this->deviceId = $deviceId;
        $this->userId = $userId;
        
        //Creating AudioPlayer
        if(isset($supportedInterfaces['AudioPlayer']) && (is_array($supportedInterfaces['AudioPlayer']))) {
            $this->interfaces['audioPlayer'] = new AudioPlayer();
        }
        
        if(isset($supportedInterfaces['Display']) && (is_array($supportedInterfaces['Display']))) {
            $markupVersion = ((isset($supportedInterfaces['Display']['markupVersion']))? $supportedInterfaces['Display']['markupVersion']: 1.0);
            $templateVersion = ((isset($supportedInterfaces['Display']['templateVersion']))? $supportedInterfaces['Display']['templateVersion']: 1.0);
            $this->interfaces['display'] = new Display($markupVersion, $templateVersion);
        }
        
        if(isset($supportedInterfaces['VideoApp']) && (is_array($supportedInterfaces['VideoApp']))) {
            $this->interfaces['videoApp'] = new VideoApp();
        }
    }
    
    public function __get($name) {
        if($name == 'applicationId') {
            return $this->applicationId;
        }
        else if($name == 'deviceId') {
            return $this->deviceId;
        }
        else if($name == 'userId') {
            return $this->userId;
        }
        else if($name == 'apiAccessToken') {
            return $this->apiAccessToken;
        }
        else if($name == 'apiEndpoint') {
            return $this->apiEndpoint;
        }
        else if(isset($this->interfaces[$name])) {
            return $this->interfaces[$name];
        }
        return NULL;
    }
    
    public static function getInstance($applicationId = NULL, $deviceId = NULL, $userId = NULL, $apiAccessToken = NULL, $apiEndpoint = NULL, array $supportedInterfaces = []) {
        if(self::$instance) {
            return self::$instance;
        }
        else {
            if((strlen($applicationId) > 0) && (strlen($deviceId) > 0) && (strlen($userId) > 0)) {
                self::$instance = new self($applicationId, $deviceId, $userId, $apiAccessToken, $apiEndpoint, $supportedInterfaces);
                return self::$instance;
            }
            else {
                return NULL;
            }
        }
    }
}