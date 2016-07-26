<?php

class LaunchRequest extends Intent {
    public function ask($params = array()) {
        return $this->run($params);
    }
}

