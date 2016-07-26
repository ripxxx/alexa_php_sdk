<?php

class EndSessionRequest extends Intent {
    public function ask($params = array()) {
        return $this->run($params);
    }
}

