<?php
/**
 * Created by Aleksandr Berdnikov.
*/

namespace AlexaPHPSDK;

class AlexaInterface {
    protected $directive = NULL;
    protected $id = '---';
    protected $response = NULL;
    
    public function __construct() {
        $this->id = md5(get_called_class().strtotime('now'));
    }
    
    public function __get($name) {
        if($name == 'needRemoveShouldEndSession') {
            return false;
        }
        return NULL;
    }

    public function getDirective(Response $response = NULL) {
        return $this->directive;
    }
    
    public function getId() {
        return $this->id;
    }
}