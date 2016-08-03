<?php
/**
 * Created by Aleksandr Berdnikov.
 * Copyright 2016 Onix-Systems.
*/

namespace AlexaPHPSDK;

class Authorize extends RouteHandler {
    
    protected function redirect($url) {
        if($this->router->redirect($url)) {
            return true;
        }
        return false;
    }
    
}

