<?php

class User implements ArrayAccess, Countable {
    protected static $instance;
    
    protected $currentApplicationId;
    protected $currentSessionId;
    protected $data;
    protected $id;
    protected $fileName;
    protected $hash;
    protected $path;

    protected function __construct($id, $path, $applicationId, $sessionId) {
        $this->currentApplicationId = $applicationId;
        $this->currentSessionId = $sessionId;
        $this->id = $id;
        $this->hash = md5($id.$applicationId.'com.alexa.sdk');
        $this->path = $path;
        
        $this->fileName = $this->path.$this->hash;
        $this->data = array();
        if(file_exists($this->fileName)) {
            $serializedData = file_get_contents($this->fileName);
            $this->data = unserialize($serializedData);
            if(!is_array($this->data)) {
                $this->data = array();
            }
            
            if(!isset($this->data[$this->currentSessionId])) {
                $this->data[$this->currentSessionId] = array();
            }
        }
        else {
            $this->data = [
                $this->hash => [],
                $this->currentSessionId => [],
            ];
        }
    }
    
    public function __destruct() {
        foreach($this->data[$this->currentSessionId] as $key=>$value) {
           $this->data[$this->hash][$key] = $value;
        }
        $serializedData = serialize($this->data);
        file_put_contents($this->fileName, $serializedData);
    }
    
    public function __get($name) {
        if(($name == 'session') && isset($this->data[$this->currentSessionId])) {
            $data = $this->data[$this->currentSessionId];
            return $data;
        }
        return NULL;
    }
    
    public function offsetExists($offset) {
        return isset($this->data[$this->currentSessionId][$offset]) || isset($this->data[$this->hash][$offset]);
    }

    public function offsetGet($offset) {
        if(isset($this->data[$this->currentSessionId][$offset])) {
            return $this->data[$this->currentSessionId][$offset];
        }
        else if(isset($this->data[$this->hash][$offset])) {
            return $this->data[$this->hash][$offset];
        }
        return NULL;
    }

    public function offsetSet($offset, $value) {
        if (is_null($offset)) {
            $this->data[$this->currentSessionId][] = $value;
        }
        else {
            $this->data[$this->currentSessionId][$offset] = $value;
        }
    }

    public function offsetUnset($offset) {
        if(isset($this->data[$this->currentSessionId][$offset])) {
            unset($this->data[$this->currentSessionId][$offset]);
        }
        
        if(isset($this->data[$this->hash][$offset])) {
            unset($this->data[$this->hash][$offset]);
        }
    }
    
    public function count() {
        $cnt = count($this->data[$this->currentSessionId])+count(array_diff($this->data[$this->hash], $this->data[$this->currentSessionId]));
        return $cnt;
    }
    
    public static function getInstance($id = NULL, $path = NULL, $applicationId = NULL, $sessionId = NULL) {
        if(self::$instance) {
            return self::$instance;
        }
        else {
            if((strlen($id) > 0) && (strlen($path) > 0)) {
                if(!file_exists($path) || !is_dir($path)) {
                    $error = 'Directory not found: "'.path.'".';
                    throw new Exception($error);
                }
                else if(!is_writable($path)) {
                    $error = 'Directory is not writable: "'.path.'".';
                    throw new Exception($error);
                }
                self::$instance = new self($id, $path, $applicationId, $sessionId);
                return self::$instance;
            }
            else if(strlen($id) == 0) {
                $error = 'Empty user id.';
                throw new Exception($error);
            }
            else {
                $error = 'Empty path to users directory.';
                throw new Exception($error);
            }
        }
    }
}

