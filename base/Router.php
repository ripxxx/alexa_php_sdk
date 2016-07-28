<?php

class Router {
    
    protected $_config;
    
    protected function notFound() {
        header('HTTP/1.0 404 Not Found');
        echo 'Not found.';
    }
    
    protected function skillExists($skillName) {
        if ($skillName == '') return false;
        $skillDir = $this->_config['directories']['skills'].$skillName;        
        return (file_exists($skillDir) && is_dir($skillDir));
    }
    
    protected function getSkillConfig($skillName) {
        if($this->skillExists($skillName)) {
            $skillConfigFileName = $this->_config['directories']['skills'].$skillName.'/config.php';        
            if(file_exists($skillConfigFileName)) {
                return require($skillConfigFileName);
            }
            return array();
        }
    }
    
    protected function getSkillIntent($skillName, $intentName, User $user) {
        if($this->skillExists($skillName)) {
            $intentClassFileName = $this->_config['directories']['skills'].$skillName.'/'.$intentName.'.php';
            if(file_exists($intentClassFileName)) {
                require_once($intentClassFileName);
                return new $intentName($user);
            }
            return NULL;
        }
    }
    
    protected function createUser($data, $private = true) {
        $applicationId = $data['application']['applicationId'];
        $id = $data['user']['userId'];
        $sessionId = $data['sessionId'];
        $user = User::getInstance($id, $this->_config['directories']['users'], $applicationId, $sessionId, $private);
        foreach($data['attributes'] as $key=>$value) {
            $user[$key] = $value;
        }
        return $user;
    }

    public function __construct(array $config) {
        $this->_config = $config;
    }
    
    public function route($path) {        
        if($path) {            
            $postData = file_get_contents("php://input");            

            $pathParts = explode('/', ltrim($path, '\\/'));            

            //array_shift($pathParts); // remove first slash

            $skillName = array_shift($pathParts);
            $skillParams = $pathParts;            

            if($this->skillExists($skillName)) {                
                $config = $this->getSkillConfig($skillName);
                Skill::getInstance($skillName, array_merge_recursive($this->_config, $config));
                if(strlen($postData) > 1) {
                    $parsedPostData = ((strlen($postData) > 1)? json_decode($postData, true): array());
                    $user = $this->createUser($parsedPostData['session']);
                    if(is_array($parsedPostData['request'])) {
                        if(strtolower($parsedPostData['request']['type']) == 'launchrequest') {
                            $intentName = 'Launch';
                            $intent = $this->getSkillIntent($skillName, $intentName, $user);
                            if($intent) {
                                $response = $intent->run($params);
                                if(!$response) {
                                    $response = $intent->endSessionResponse();
                                }

                                echo $response->build();
                            }
                            else {
                                //add default intent
                            }
                        }
                        else if(strtolower($parsedPostData['request']['type']) == 'intentrequest') {
                            $intentName = ucfirst($parsedPostData['request']['intent']['name']).'Intent';
                            $intent = $this->getSkillIntent($skillName, $intentName, $user);
                            if($intent) {
                                $params = array();
                                foreach($parsedPostData['request']['intent']['slots'] as $key=>$value) {
                                    $params[strtolower($value['name'])] = $value['value'];
                                }
                                if($parsedPostData['session']['new']) {//ask
                                    $response = $intent->ask($params);
                                }
                                else {//run
                                    $response = $intent->run($params);
                                }
                                if(!$response) {
                                    $response = $intent->endSessionResponse();
                                }

                                echo $response->build();
                            }
                            else {

                            }
                        }
                        else if(strtolower($parsedPostData['request']['type']) == 'SessionEndedRequest') {
                            //log
                        }
                    }
                    return true;
                }
                else if(isset($skillParams[0])) {
                    if(isset($config['allowedContentTypes'])) {
                        if(preg_match("/.+?\\.(".$config['allowedContentTypes'].")/", $skillParams[0])) {
                            $fileName = $config['directories']['content'].'/'.$skillParams[0];
                            if(file_exists($fileName) && is_file($fileName) && is_writable($fileName)) {
                                readfile($fileName);
                                return true;
                            }
                            $this->notFound();
                            return false;
                        }
                    }
                }
            }
            else {
                $this->notFound();
                return false;
            }
        }
        else {
            $this->notFound();
            return false;
        }
    }
}

