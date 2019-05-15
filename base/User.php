<?php
/**
 * Created by Aleksandr Berdnikov.
 * Copyright 2016 Onix-Systems.
*/

namespace AlexaPHPSDK;

use ArrayAccess;
use Countable;
use Exception;


class User implements ArrayAccess, Countable {
    protected static $instance;
    
    protected $accessToken = NULL;
    protected $consentToken = NULL;
    protected $currentApplicationId;
    protected $currentSessionId;
    protected $data;
    protected $id;
    protected $fileName;
    protected $hash;
    protected $path;
    
    protected $reservedVariables = array('lastintent');//lower case
    
    protected function fileGetContents($fileName) {
        $contents = NULL;
        $fh = fopen($fileName, "r");
        if(flock($fh, LOCK_SH)) {
            $contents = fread($fh, filesize($fileName));
            flock($fh, LOCK_UN);
        }
        fclose($fh);
        return $contents;
    }
    
    protected function filePutContents($fileName, $contents) {
        $result = false;
        $fh = fopen($fileName, "w");
        if(flock($fh, LOCK_EX)) {
            fwrite($fh, $contents);
            fflush($fh);
            flock($fh, LOCK_UN);
            $result = true;
        }
        fclose($fh);
        return $result;
    }
    
    protected function readData($fileName, $hash, $currentSessionId) {
        $data = array();
        if(file_exists($fileName) && is_file($fileName)) {
            $serializedData = $this->fileGetContents($fileName);
            $data = unserialize($serializedData);
            if(!is_array($data)) {
                $data = array();
            }
            
            if(!isset($data[$currentSessionId])) {
                $data[$currentSessionId] = array();
            }
        }
        else {
            $data = [
                $hash => [],
                $currentSessionId => [],
            ];
        }
        return $data;
    }
    
    protected function saveData($fileName, array $data, $hash, $currentSessionId, $updateMainSession = true) {
        if($updateMainSession && isset($data[$currentSessionId]) && is_array($data[$currentSessionId])) {
            foreach($data[$currentSessionId] as $key=>$value) {
                if(!in_array(strtolower($key), $this->reservedVariables)) {
                    $data[$hash][$key] = $value;
                }
            }
        }
        $content = serialize($data);
        return $this->filePutContents($fileName, $content);
    }

    protected function __construct($id, $path, $applicationId, $sessionId, $consentToken) {
        $this->consentToken = $consentToken;
        $this->currentApplicationId = $applicationId;
        $this->currentSessionId = $sessionId;
        $this->id = $id;
        $hash = md5($id.$applicationId.'com.alexa.sdk');
        $this->path = $path;
        
        $fileName = $path.$hash;
        $this->data = $this->readData($fileName, $hash, $sessionId);
        $this->fileName = $fileName;
        $this->hash = $hash;
        
    }
    
    public function __destruct() {
        $this->saveData($this->fileName, $this->data, $this->hash, $this->currentSessionId);
    }
    
    public function __get($name) {
        if(($name == 'session') && isset($this->data[$this->currentSessionId])) {
            $data = $this->data[$this->currentSessionId];
            return $data;
        }
        else if($name == 'id') {
            return $this->id;
        }
        else if($name == 'token') {
            return $this->accessToken;
        }
        else if(in_array(strtolower($name), $this->reservedVariables)) {
            $_name = strtolower($name);
            if(isset($this->data[$this->currentSessionId][$_name])) {
                return $this->data[$this->currentSessionId][$_name];
            }
        }
        return NULL;
    }
    
    public function __set($name, $value) {
        if($name == 'token') {
            $this->accessToken = $value;
        }
        else if(in_array(strtolower($name), $this->reservedVariables)) {
            $this->data[$this->currentSessionId][strtolower($name)] = $value;
        }
    }

    public function offsetExists($offset) {
        return isset($this->data[$this->currentSessionId][$offset]) || (in_array(strtolower($offset), $this->reservedVariables) && isset($this->data[$this->currentSessionId][strtolower($offset)])) || isset($this->data[$this->hash][$offset]);
    }

    public function offsetGet($offset) {
        if(isset($this->data[$this->currentSessionId][$offset])) {
            return $this->data[$this->currentSessionId][$offset];
        }
        else if(in_array(strtolower($offset), $this->reservedVariables)) {
            $_offset = strtolower($offset);
            if(isset($this->data[$this->currentSessionId][$_offset])) {
                return $this->data[$this->currentSessionId][$_offset];
            }
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
        else if(in_array(strtolower($offset), $this->reservedVariables)) {
            $this->data[$this->currentSessionId][strtolower($offset)] = $value;
        }
        else {
            $this->data[$this->currentSessionId][$offset] = $value;
        }
    }

    public function offsetUnset($offset) {
        if(isset($this->data[$this->currentSessionId][$offset])) {
            unset($this->data[$this->currentSessionId][$offset]);
        }
        else if(in_array(strtolower($offset), $this->reservedVariables)) {
            $_offset = strtolower($offset);
            if(isset($this->data[$this->currentSessionId][$_offset])) {
                unset($this->data[$this->currentSessionId][$_offset]);
            }
        }
        
        if(isset($this->data[$this->hash][$offset])) {
            unset($this->data[$this->hash][$offset]);
        }
    }
    
    public function count() {
        $cnt = count($this->data[$this->currentSessionId])+count(array_diff($this->data[$this->hash], $this->data[$this->currentSessionId]));
        return $cnt;
    }
    
    public static function getInstance($id = NULL, $path = NULL, $applicationId = NULL, $sessionId = NULL, $consentToken = NULL) {
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
                self::$instance = new self($id, $path, $applicationId, $sessionId, $consentToken);
                return self::$instance;
            }
            else {
                return NULL;
            }
        }
    }
}

