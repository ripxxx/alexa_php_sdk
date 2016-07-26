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
    protected $private;

    protected function __construct($id, $path, $applicationId, $sessionId, $private) {
        $this->currentApplicationId = $applicationId;
        $this->currentSessionId = $sessionId;
        $this->id = $id;
        $this->hash = md5($id.'com.alexa.sdk');
        $this->path = $path;
        $this->private = $private;
        
        $this->fileName = $this->path.$this->hash;
        $this->data = array();
        if(file_exists($this->fileName)) {
            $serializedData = file_get_contents($this->fileName);
            $this->data = unserialize($serializedData);
            if(!isset($this->data[$this->currentApplicationId])) {
                $this->data[$this->currentApplicationId] = array();
            }
            
            if(!isset($this->data[$this->currentApplicationId][$this->currentSessionId])) {
                $this->data[$this->currentApplicationId][$this->currentSessionId] = array();
            }
            
            $this->data[$this->currentApplicationId][$this->currentSessionId]['private'] = $this->private;
        }
        else {
            $this->data = [
                $this->currentApplicationId => [
                    $this->currentSessionId => [
                        'private' => $this->private
                    ]
                ],
            ];
        }
    }
    
    public function __destruct() {
        $serializedData = serialize($this->data);
        file_put_contents($this->fileName, $serializedData);
    }
    
    public function __get($name) {
        if(($name == 'session') && isset($this->data[$this->currentApplicationId][$this->currentSessionId])) {
            $data = $this->data[$this->currentApplicationId][$this->currentSessionId];
            if(array_key_exists('private', $data)) {
                unset($data['private']);
            }
            return $data;
        }
        return NULL;
    }
    
    public function offsetExists($offset) {
        if(isset($this->data[$offset])) {
            if($offset == $this->currentApplicationId) {
                return true;
            }
            foreach($this->data[$offset] as $sessionId=>$data) {
                if(!$this->data[$offset][$sessionId]['private']) {
                    return true;
                }
            }
        }
        else if(isset($this->data[$this->currentApplicationId][$offset])) {
            if($offset == $this->currentSessionId) {
                return true;
            }
            //return !$this->data[$this->currentApplicationId][$offset]['private'];
            return isset($this->data[$this->currentApplicationId][$offset]);
        }
        
        return isset($this->data[$this->currentApplicationId][$this->currentSessionId][$offset]);
    }

    public function offsetGet($offset) {
        if(isset($this->data[$offset])) {
            if($offset == $this->currentApplicationId) {
                return $this->data[$offset];
            }
            $data = array();
            foreach($this->data[$offset] as $sessionId=>$data) {
                if(!$this->data[$offset][$sessionId]['private']) {
                    $data[$sessionId] = $data;
                }
            }
            if(count($data) > 0) {
               return $data; 
            }
            return NULL;
        }
        else if(isset($this->data[$this->currentApplicationId][$offset])) {
            if($offset == $this->currentSessionId) {
                return $this->data[$this->currentApplicationId][$offset];
            }
            //return ((!$this->data[$this->currentApplicationId][$offset]['private'])? $this->data[$this->currentApplicationId][$offset]: NULL);
            return ((isset($this->data[$this->currentApplicationId][$offset]))? $this->data[$this->currentApplicationId][$offset]: NULL);
        }
        
        return ((isset($this->data[$this->currentApplicationId][$this->currentSessionId][$offset]))? $this->data[$this->currentApplicationId][$this->currentSessionId][$offset]: NULL);
    }

    public function offsetSet($offset, $value) {
        if(!isset($this->data[$offset]) && !isset($this->data[$this->currentApplicationId][$offset])) {
            if (is_null($offset)) {
                $this->data[$this->currentApplicationId][$this->currentSessionId][] = $value;
            }
            else {
                $this->data[$this->currentApplicationId][$this->currentSessionId][$offset] = $value;
            }
        }
    }

    public function offsetUnset($offset) {
        if(!isset($this->data[$offset]) && !isset($this->data[$this->currentApplicationId][$offset]) && isset($this->data[$this->currentApplicationId][$this->currentSessionId][$offset])) {
            unset($this->data[$this->currentApplicationId][$this->currentSessionId][$offset]);
        }
    }
    
    public function count() {
        return count($this->data);
    }
    
    public static function getInstance($id = NULL, $path = NULL, $applicationId = NULL, $sessionId = NULL, $private = true) {
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
                self::$instance = new self($id, $path, $applicationId, $sessionId, $private);
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

