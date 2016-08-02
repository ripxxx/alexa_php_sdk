<?php
/**
 * Created by Aleksandr Berdnikov.
 * Copyright 2016 Onix-Systems.
*/

namespace AlexaPHPSDK;

class Router {
    
    protected $_config;
    
    protected function notFound() {
        header('HTTP/1.0 404 Not Found');
        echo 'Not found.';
    }
    
    protected function readFile($filePath) {
        if(file_exists($filePath) && is_file($filePath) && is_writable($filePath)) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $contentType = finfo_file($finfo, $filePath);
            finfo_close($finfo);
            header("Content-Type: ".$contentType);
            header('Access-Control-Allow-Origin: http://ask-ifr-download.s3.amazonaws.com');
            readfile($filePath);
            return true;
        }
        $this->notFound();
        return false;
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
                spl_autoload_register(function($class) {
                    $skillName = Skill::getInstance()->name;
                    $prefix = ucfirst($skillName);
                    
                    $baseDirectory = $this->_config['directories']['skills'].$skillName;
                    
                    $length = strlen($prefix);
                    if(strncmp($prefix, $class, $length) !== 0) {
                        return;
                    }

                    $realativeClass = substr($class, $length);
                    $filePath = $baseDirectory.str_replace('\\', '/', $realativeClass).'.php';

                    if (file_exists($filePath)) {
                        require $filePath;
                    }
                });
                require_once($intentClassFileName);
                $classFullName = $skillName.'\\'.$intentName;
                return new $classFullName($user);
            }
            return NULL;
        }
    }
    
    protected function createUser($data) {
        $applicationId = $data['application']['applicationId'];
        $id = $data['user']['userId'];
        $sessionId = $data['sessionId'];
        $user = User::getInstance($id, $this->_config['directories']['users'], $applicationId, $sessionId);
        if(isset($data['attributes']) && is_array($data['attributes']) && (count($data['attributes']) > 0)) {
            foreach($data['attributes'] as $key=>$value) {
                $user[$key] = $value;
            }
        }
        return $user;
    }

    public function __construct(array $config) {
        $this->_config = $config;
    }
    
    public function route($path) {        
        if($path) {    
            $postData = file_get_contents("php://input");
            $parsedPostData = NULL;
            $user = NULL;
            if(strlen($postData) > 1) {
                $parsedPostData = ((strlen($postData) > 1)? json_decode($postData, true): array());
                $user = $this->createUser($parsedPostData['session']);
            }

            $pathParts = explode('/', ltrim($path, '\\/'));

            $skillName = array_shift($pathParts);
            $skillParams = $pathParts;            

            $defaultSkill = NULL;
            !is_null($user) && $defaultSkill = new DefaultIntent($user);
            
            if($this->skillExists($skillName)) { 
                $config = $this->getSkillConfig($skillName);
                Skill::getInstance($skillName, array_merge_recursive($this->_config, $config));
                if(!is_null($parsedPostData)) {
                    if(is_array($parsedPostData['request'])) {
                        if(strtolower($parsedPostData['request']['type']) == 'launchrequest') {
                            $errorMessage = 'Unable to launch the skill.';
                            $intentName = 'Launch';
                            Skill::log($user->id.' launch the skill.');
                            $intent = $this->getSkillIntent($skillName, $intentName, $user);
                            if($intent) {
                                $response = $intent->run();
                                if(!$response) {
                                    $response = $intent->endSessionResponse($errorMessage);
                                    Skill::log($errorMessage);
                                }
                                echo $response->build();
                            }
                            else {
                                $defaultSkill->endSessionResponse($errorMessage);
                                echo $response->build();
                            }
                        }
                        else if(strtolower($parsedPostData['request']['type']) == 'intentrequest') {
                            $errorMessage = 'Unable to run intent, please try again later.';
                            $intentName = ucfirst($parsedPostData['request']['intent']['name']).'Intent';
                            $intent = $this->getSkillIntent($skillName, $intentName, $user);
                            if($intent) {
                                $params = array();
                                foreach($parsedPostData['request']['intent']['slots'] as $key=>$value) {
                                    $params[strtolower($value['name'])] = $value['value'];
                                }
                                if($parsedPostData['session']['new']) {//ask
                                    Skill::log($user->id.' ask for intent "'.$intentName.'".');
                                    $response = $intent->ask($params);
                                }
                                else {//run
                                    Skill::log($user->id.' run intent "'.$intentName.'".');
                                    $response = $intent->run($params);
                                }
                                if(!$response) {
                                    $response = $intent->endSessionResponse($errorMessage);
                                    Skill::log($errorMessage);
                                }
                                echo $response->build();
                            }
                            else {
                                $defaultSkill->endSessionResponse($errorMessage);
                                echo $response->build();
                            }
                        }
                        else if(strtolower($parsedPostData['request']['type']) == 'SessionEndedRequest') {
                            $errorMessage = 'Unable to run session end intent.';
                            $intentName = 'EndSession';
                            $intent = $this->getSkillIntent($skillName, $intentName, $user);
                            if($intent) {
                                $response = $intent->run();
                                if(!$response) {
                                    $response = $intent->endSessionResponse();
                                    Skill::log($errorMessage);
                                }
                                echo $response->build();
                            }
                            else {
                                $defaultSkill->endSessionResponse();
                                echo $response->build();
                            }
                            Skill::log($user->id.' session end.');
                        }
                    }
                    return true;
                }
                else if(isset($skillParams[0])) {
                    if(isset($config['allowedContentTypes'])) {
                        if(preg_match("/.+?\\.(".$config['allowedContentTypes'].")/", $skillParams[0])) {
                            $filePath = $config['directories']['content'].'/'.$skillParams[0];
                            Skill::log($user->id.' request for '.$skillParams[0].'.');
                            return $this->readFile($filePath);
                        }
                    }
                    Skill::log($user->id.' request for not allowed content: '.$skillParams[0].'.');
                }
            }
            else {
                Skill::getInstance($skillName, $this->_config);
                Skill::log('Skill "'.$skillName.'" was not found.');
                $this->notFound();
                return false;
            }
        }
        else {
            Skill::getInstance('----EMPTY_PATH-----', $this->_config);
            Skill::log('Empty path.');
            $this->notFound();
            return false;
        }
    }
}

