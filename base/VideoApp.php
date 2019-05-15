<?php
/**
 * Created by Aleksandr Berdnikov.
*/

namespace AlexaPHPSDK;

class VideoApp extends AlexaInterface {
    
    public function __construct() {
        parent::__construct();
    }
    
    public function __get($name) {
        if($name == 'needRemoveShouldEndSession') {
            return true;
        }
        return parent::__get($name);
    }
}