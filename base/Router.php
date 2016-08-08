<?php
/**
 * Created by Aleksandr Berdnikov.
 * Copyright 2016 Onix-Systems.
*/

namespace AlexaPHPSDK;

class Router {
    
    protected $_config;
    
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
    
    protected function getSkillRouteHandler($skillName, $handlerName, array $config) {
        if($this->skillExists($skillName)) {
            $handlerClassFileName = $this->_config['directories']['skills'].$skillName.'/'.((preg_match('/\\.php/', $handlerName))? ltrim($handlerName, '/'): $handlerName.'.php');
            if(file_exists($handlerClassFileName)) {
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
                require_once($handlerClassFileName);
                $classFullName = $skillName.'\\'.str_replace('.php', '', basename($handlerClassFileName));
                return new $classFullName($config, $this);
            }
            return NULL;
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
        if(isset($data['user']['accessToken'])) {
            $user->token = $data['user']['accessToken'];
        }
        if(isset($data['attributes']) && is_array($data['attributes']) && (count($data['attributes']) > 0)) {
            foreach($data['attributes'] as $key=>$value) {
                $user[$key] = $value;
            }
        }
        return $user;
    }
    
    protected function getUriParams() {
        $requestUri = $_SERVER['REQUEST_URI'];
        $requestUriQuery = parse_url($requestUri, PHP_URL_QUERY);
        $params = array();
        if(strlen($requestUriQuery) > 0) {
            $requestUriQueryArray = explode('&', $requestUriQuery);
            foreach($requestUriQueryArray as $param) {
                list($key, $value) = explode('=', $param);
                $params[$key] = $value;
            }
        }
        return $params;
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
                $parsedPostData = ((strlen($postData) > 1)? json_decode($postData, true): NULL);
                is_array($parsedPostData) && isset($parsedPostData['session']) && $user = $this->createUser($parsedPostData['session']);
            }

            $pathParts = explode('/', ltrim($path, '\\/'));
            $skillName = array_shift($pathParts);
            $skillParams = $pathParts;            

            $defaultSkill = NULL;
            !is_null($user) && $defaultSkill = new DefaultIntent($user);
            
            if($this->skillExists($skillName)) { 
                $config = $this->getSkillConfig($skillName);
                $skill = Skill::getInstance($skillName, array_merge_recursive($this->_config, $config));

                if(count($skillParams) > 0) {
                    $routeName= array_shift($skillParams);
                    $uriParams = $this->getUriParams();
                    
                    if($routeName == 'content') {
                        //content request will be processed below
                    }
                    else if($routeName == 'authorize') {
                        if(!isset($skill['authorization']) || !is_array($skill['authorization'])) {
                            Skill::log('No authorization config was found for skill "'.$skillName.'".');
                            $this->notFound();
                            return false;
                        }
                        $authorizationHandlerName = 'Authorize';
                        if(isset($skill['routes']) && isset($skill['routes']['authorize']) && ($skill['routes']['authorize'] != '')) {
                            $authorizationHandlerName = $skill['routes']['authorize'];
                        }
                        $authorizationHandler = $this->getSkillRouteHandler($skillName, $authorizationHandlerName, $skill['authorization']);
                        if(is_null($authorizationHandler)) {
                            Skill::log('Authorization handler for skill "'.$skillName.'" was not found.');
                            $this->notFound();
                            return false;
                        }
                        return $authorizationHandler->run($uriParams, $skillParams);
                    }
                    else {
                        $config = array();
                        if(isset($skill[$routeName]) && is_array($skill[$routeName])) {
                            $config = $skill[$routeName];
                        }
                        $_routeName = $routeName;
                        if(isset($skill['routes']) && isset($skill['routes'][$routeName]) && ($skill['routes'][$routeName] != '')) {
                            $_routeName = $skill['routes'][$routeName];
                        }
                        $routeHandler = $this->getSkillRouteHandler($skillName, $_routeName, $config);
                        if(is_null($routeHandler)) {
                            if(is_null($parsedPostData)) {
                                Skill::log('Route handler "'.$routeName.'" for skill "'.$skillName.'" was not found.');
                                $this->notFound();
                                return false;
                            }
                            array_unshift($skillParams, $routeName);
                        }
                        else {
                            return $routeHandler->run($uriParams, $skillParams);
                        }
                    } 
                }//*/

                if(!is_null($parsedPostData)) {
                    if(is_array($parsedPostData['request'])) {
                        $forceAuthorization = $skill->needAuthorization && is_null($user->token);
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
                                else if($forceAuthorization) {
                                    $response->forceAcccountLinking(((isset($skill['authorizationRequestMessage']))? $skill['authorizationRequestMessage']: ''));
                                }
                                echo $response->build();
                            }
                            else {
                                $response = $defaultSkill->endSessionResponse($errorMessage);
                                echo $response->build();
                            }
                        }
                        else if(strtolower($parsedPostData['request']['type']) == 'intentrequest') {
                            $errorMessage = 'Unable to run intent, please try again later.';
                            $intentName = ucfirst($parsedPostData['request']['intent']['name']).'Intent';
                            $intent = $this->getSkillIntent($skillName, $intentName, $user);
                            if($intent) {
                                $params = array();
                                if(isset($parsedPostData['request']['intent']['slots']) && is_array($parsedPostData['request']['intent']['slots'])) {
                                    foreach($parsedPostData['request']['intent']['slots'] as $key=>$value) {
                                        $params[strtolower($value['name'])] = $value['value'];
                                    }
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
                                else if($forceAuthorization) {
                                    $response->forceAcccountLinking(((isset($skill['authorizationRequestMessage']))? $skill['authorizationRequestMessage']: ''));
                                }
                                echo $response->build();
                            }
                            else {
                                $response = $defaultSkill->endSessionResponse($errorMessage);
                                echo $response->build();
                            }
                        }
                        else if(strtolower($parsedPostData['request']['type']) == 'sessionendedrequest') {
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
                                $response = $defaultSkill->endSessionResponse();
                                echo $response->build();
                            }
                            Skill::log($user->id.' session end.');
                        }
                    }
                    return true;
                }
                else if((count($skillParams) > 0) && isset($skillParams[0])) {
                    if(isset($config['allowedContentTypes'])) {
                        if(preg_match("/.+?\\.(".$config['allowedContentTypes'].")/", $skillParams[0])) {
                            $filePath = $config['directories']['content'].'/'.$skillParams[0];
                            Skill::log($user->id.' request for '.$skillParams[0].'.');
                            return $this->readFile($filePath);
                        }
                    }
                    Skill::log($user->id.' request for not allowed content: '.$skillParams[0].'.');
                    $this->notFound();
                    return false;
                }
                else {
                    Skill::log($user->id.' requested path was not found: '.implode('/', $skillParams).'.');
                    $this->notFound();
                    return false;
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
    
    public function notFound($message = '<h1>Not found.</h1>') {
        header('HTTP/1.0 404 Not Found');
        echo $message;
    }
    
    public function redirect($url) {
        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        }
        header('Location: '.$url);
        return true;
    }
}

