<?php
/**
 * Created by Aleksandr Berdnikov.
 * Copyright 2016 Onix-Systems.
*/

namespace AlexaPHPSDK;

class EndSessionRequest extends Intent {
    public function ask($params = array()) {
        return $this->run($params);
    }
}

