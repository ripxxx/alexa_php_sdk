<?php

class Skill implements ArrayAccess, Countable {
    private static $instance;
    protected $config;
    protected $tmpConfig;
    protected $name;
    
    protected function __construct($name, $config) {
        $this->name = $name;
        $this->config = $config;
    }
    
    public function __get($name) {
        if($name == 'name') {
            return $this->name;
        }
        return NULL;
    }


    public function offsetExists($offset) {
        return (isset($this->config[$offset]) || isset($this->tmpConfig[$offset]));
    }

    public function offsetGet($offset) {
        if(isset($this->tmpConfig[$offset])) {
            return $this->tmpConfig[$offset];
            
        }
        else if(isset($this->config[$offset])) {
            return $this->config[$offset];
        }
        return NULL;
    }

    public function offsetSet($offset, $value) {
        $this->tmpConfig[$offset] = $value;
    }

    public function offsetUnset($offset) {
        if(isset($this->tmpConfig[$offset])) {
            unset($this->tmpConfig[$offset]);
        }
    }
    
    public function count() {
        $cnt = count($this->config);
        foreach($this->tmpConfig as $key=>$value) {
            if(!array_key_exists($key, $this->config)) {
                ++$cnt;
            }
        }
        return $cnt;
    }
    
    public static function getInstance($name = NULL, $config = NULL) {
        if(self::$instance) {
            return self::$instance;
        }
        else {
            if(is_null($name) || (strlen($name) == 0)) {
                $error = 'Empty name.';
                throw new Exception($error);
            }
            else if(is_null($config) || !is_array($config) || (count($config) == 0)){
                $error = 'Empty config.';
                throw new Exception($error);
            }
            else {
                self::$instance = new self($name, $config);
                return self::$instance;
            }
        }
    }
}

