<?php
/**
 * Created by Aleksandr Berdnikov.
 * Copyright 2016 Onix-Systems.
*/

namespace AlexaPHPSDK;

//DEBUGS
//define('REQUEST_TIMESTAMP_DEBUG', true);
//define('REQUEST_SIGNATURE_DEBUG', true);

define('VALID_SIGNATURE_CERTIFICATE_HOST_NAME', 's3.amazonaws.com');
define('VALID_SIGNATURE_CERTIFICATE_URL_PATH', '/echo.api/');
define('SIGNATURE_CERTIFICATE_SAN', 'echo-api.amazon.com');

class Router {
    
    protected $_config;
    
    protected function checkSignature($sha1, $time) {
        if(defined('REQUEST_SIGNATURE_DEBUG')) {
            return ((REQUEST_SIGNATURE_DEBUG === true)? true: false);
        }
        $headers = getallheaders();
        foreach($headers as $key=>$value) {//found that somtimes receiving Signaturecertchainurl instead of SignatureCertChainUrl
           $headers[strtolower($key)] = $value;
        }
        if(is_array($headers) && isset($headers['signature']) && isset($headers['signaturecertchainurl'])) {
            $signature = $headers['signature'];
            $signatureCertChainUrl = $headers['signaturecertchainurl'];       
            if((strlen($signature) > 1) && (strlen($signatureCertChainUrl) > 1)) {
                $signature = base64_decode($signature);
                $apiCert = $this->getSignatureCertificate($signatureCertChainUrl);
                if(($signature !== false) && !is_null($apiCert)) {
                    $_sha1 = NULL;
                    $publicKey = openssl_get_publickey($apiCert);
                    $forcedToDowload = false;
                    if(!openssl_public_decrypt($signature, $_sha1, $publicKey)) {
                        $apiCert = $this->getSignatureCertificate($signatureCertChainUrl, true);
                        $forcedToDowload = true;
                        if(!is_null($apiCert)) {
                            $publicKey = openssl_get_publickey($apiCert);
                            if(!openssl_public_decrypt($signature, $_sha1, $publicKey)) {
                                return false;
                            }
                        }
                    }
                    
                    if(!empty($_sha1)) {
                        if(strpos($_sha1, $sha1) !== false) {
                            return $this->validateTimestamp($time);
                        }
                        else if(!$forcedToDowload) {
                            $apiCert = $this->getSignatureCertificate($signatureCertChainUrl, true);
                            $forcedToDowload = true;
                            if(!is_null($apiCert)) {
                                $publicKey = openssl_get_publickey($apiCert);
                                if(!openssl_public_decrypt($signature, $_sha1, $publicKey)) {
                                    if(strpos($_sha1, $sha1) !== false) {
                                        return $this->validateTimestamp($time);
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
        return false;
    }
    
    protected function createContext(array $requestData) {
        $data = $requestData['context'];
        
        //AudioPlayer state
        if(isset($data['AudioPlayer']) && is_array($data['AudioPlayer'])) {
            isset($data['AudioPlayer']['playerActivity']) && AudioPlayer::$activity = strtoupper($data['AudioPlayer']['playerActivity']);
            isset($data['AudioPlayer']['token']) && AudioPlayer::$token = $data['AudioPlayer']['token'];
            isset($data['AudioPlayer']['offsetInMilliseconds']) && AudioPlayer::$offset = intval($data['AudioPlayer']['offsetInMilliseconds']);
        }
        //Display state
        if(isset($data['Display']) && is_array($data['Display'])) {
            isset($data['Display']['token']) && Display::$token = $data['Display']['token'];
        }
        
        //Setting up API access
        $apiAccessToken = null;
        $apiEndpoint = null;
        if(isset($data['System']['apiAccessToken']) && isset($data['System']['apiEndpoint'])) {
            $apiAccessToken = $data['System']['apiAccessToken'];
            $apiEndpoint = $data['System']['apiEndpoint'];
        }
        
        $supportedInterfaces = [];
        if(isset($data['System']['device']['supportedInterfaces']) && is_array($data['System']['device']['supportedInterfaces'])) {
            $supportedInterfaces = $data['System']['device']['supportedInterfaces'];
        }
        
        return Context::getInstance($data['System']['application']['applicationId'], $data['System']['device']['deviceId'], $data['System']['user']['userId'], $apiAccessToken, $apiEndpoint, $supportedInterfaces);
    }
    
    protected function createUser(array $requestData) {
        $data = $requestData['session'];
        $applicationId = $data['application']['applicationId'];
        $id = $data['user']['userId'];
        $sessionId = $data['sessionId'];
        $consentToken = ((isset($data['user']['permissions']) && isset($data['user']['permissions']['consentToken']))? $data['user']['permissions']['consentToken'] : NULL);
        $user = User::getInstance($id, $this->_config['directories']['users'], $applicationId, $sessionId, $consentToken);
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
    
    protected function getClassName($skillName, $name) {
        if($this->skillExists($skillName)) {
            $classFileName = $this->_config['directories']['skills'].$skillName.'/'.$name.'.php';
            if(file_exists($classFileName)) {
                spl_autoload_register(function($class) {
                    $skillName = Skill::getInstance()->name;
                    $prefix = $skillName;
                    
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
                require_once($classFileName);
                $classFullName = $skillName.'\\'.$name;
                return $classFullName;
            }
        }
        return NULL;
    }
    
    protected function getSignatureCertificate($url, $forcedCertificateFileDownload = false) {
        $apiCert = NULL;
        if($this->validateSignatureCertificateUrl($url)) {
            $certFilePath = $this->_config['directories']['users'].'echo-api-cert.pem';
            if($forcedCertificateFileDownload) {
                $apiCert = file_get_contents($url);
                file_put_contents($certFilePath, $apiCert);
            }
            else {
                if(file_exists($certFilePath)) {
                    $apiCert = file_get_contents($certFilePath);
                }
                else {
                    return $this->getSignatureCertificate($url, true);
                }
                
            }
            if(!(strlen($apiCert) > 1) || !$this->validateSignatureCertificate($apiCert)) {
                $apiCert = NULL;
            }
        }
        return $apiCert;
    }
    
    protected function getSkillAudioPlayerController($skillName) {
        $className = $this->getClassName($skillName, 'AudioPlayerController');
        if(!is_null($className)) {
            return new $className();
        }
        return NULL;
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
    
    protected function getSkillIntent($skillName, $intentName, User $user, $applicationId, $requestId, $deviceId, $locale, $timestamp) {
        $className = $this->getClassName($skillName, $intentName);
        if(!is_null($className)) {
            return new $className($user, $applicationId, $requestId, $deviceId, $locale, $timestamp);
        }
        return NULL;
    }
    
    protected function getSkillMessageHandler($skillName) {
        $className = $this->getClassName($skillName, 'MessageHandler');
        if(!is_null($className)) {
            return new $className();
        }
        return NULL;
    }
    
    protected function getSkillRouteHandler($skillName, $handlerName, array $config) {
        if($this->skillExists($skillName)) {
            $handlerClassFileName = $this->_config['directories']['skills'].$skillName.'/'.((preg_match('/\\.php/', $handlerName))? ltrim($handlerName, '/'): $handlerName.'.php');
            if(file_exists($handlerClassFileName)) {
                spl_autoload_register(function($class) {
                    $skillName = Skill::getInstance()->name;
                    $prefix = $skillName;
                    
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
    
    protected function processEndSessionRequest(Skill $skill, Context $context, User $user, $requestId, $locale, $timestamp) {
        $errorMessage = 'Unable to run session end intent.';
        if(!is_null($user)) {
            Skill::log($user->id.' session end.');
            $intent = $this->getSkillIntent($skill->name, 'EndSession', $user, $context->applicationId, $requestId, $context->deviceId, strtoupper($locale), $timestamp);
            if($intent) {
                $response = $intent->run();
                $user->lastIntent = $intent->name;
                if(!$response) {
                    $response = $intent->endSessionResponse();
                    Skill::log($errorMessage);
                }
                return $response;
            }
        }
        $response = new Response(true);
        $response->addText($errorMessage);
        return $response;
    }
    
    protected function processIntentRequest(Skill $skill, Context $context, User $user, $intentName, $slots, $isNew, $requestId, $locale, $timestamp) {
        $errorMessage = 'Unable to run intent, please try again later.';
        if(!is_null($user) && ($intentName != '')) {
            $forceAuthorization = $skill->needAuthorization && is_null($user->token);
            if(strpos($intentName, 'AMAZON.') === 0) {
                $intentName = ucfirst(str_replace('AMAZON.', '', $intentName));
            }
            else {
                $intentName = ucfirst($intentName).'Intent';
            }
            $intent = $this->getSkillIntent($skill->name, $intentName, $user, $context->applicationId, $requestId, $context->deviceId, strtoupper($locale), $timestamp);
            //$defaultIntent = new DefaultIntent($user, $context->applicationId, $requestId, $context->deviceId, strtoupper($locale), $timestamp);
            if($intent) {
                ProgressiveResponse::$enabled = !empty($context->apiAccessToken);
                $params = array();
                if(isset($slots) && is_array($slots)) {
                    foreach($slots as $key=>$value) {
                        if(is_array($value) && array_key_exists('name', $value) && array_key_exists('value', $value)) {
                            $params[strtolower($value['name'])] = $value['value'];
                        }
                    }
                }
                if($isNew) {//ask
                    Skill::log($user->id.' ask for intent "'.$intentName.'".');
                    $response = $intent->ask($params);
                }
                else {//run
                    Skill::log($user->id.' run intent "'.$intentName.'".');
                    $response = $intent->run($params);
                }
                $user->lastIntent = $intent->name;
                if(!$response) {
                    $response = $intent->endSessionResponse($errorMessage);
                    Skill::log($errorMessage);
                }
                else if($forceAuthorization) {
                    $response->forceAcccountLinking(((isset($skill['authorizationRequestMessage']))? $skill['authorizationRequestMessage']: ''));
                }
                return $response;
            }
        }
        $response = new Response(true);
        $response->addText($errorMessage);
        return $response;
    }
    
    protected function processLaunchRequest(Skill $skill, Context $context, User $user, $requestId, $locale, $timestamp) {
        $errorMessage = 'Unable to launch the skill.';
        if(!is_null($user)) {
            $forceAuthorization = $skill->needAuthorization && is_null($user->token);
            Skill::log($user->id.' launch the skill.');
            $intent = $this->getSkillIntent($skill->name, 'Launch', $user, $context->applicationId, $requestId, $context->deviceId, strtoupper($locale), $timestamp);
            if($intent) {
                ProgressiveResponse::$enabled = !empty($context->apiAccessToken);
                $response = $intent->run();
                $user->lastIntent = $intent->name;
                if(!$response) {
                    $response = $intent->endSessionResponse($errorMessage);
                    Skill::log($errorMessage);
                }
                else if($forceAuthorization) {
                    $response->forceAcccountLinking(((isset($skill['authorizationRequestMessage']))? $skill['authorizationRequestMessage']: ''));
                }
                return $response;
            }
        }
        $response = new Response(true);
        $response->addText($errorMessage);
        return $response;
    }
    
    protected function readFile($filePath) {
        if(file_exists($filePath) && is_file($filePath) && is_writable($filePath)) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $contentType = finfo_file($finfo, $filePath);
            finfo_close($finfo);
            header("Content-Type: ".$contentType);
            header('Access-Control-Allow-Origin: https://ask-ifr-download.s3.amazonaws.com');
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
    
    protected function validateRequest(array $requestData, array $fields) {
        $checkedPaths = array();
        $result = true;
        foreach($fields as $key=>$type) {
            $data = $requestData;
            if(!in_array($key, $checkedPaths)) {
                $pathParts = explode('.', $key);
                $path = $key;
                foreach($pathParts as $i=>$_key) {
                    $path = implode('.', array_slice($pathParts, 0, ($i+1)));
                    if(!in_array($path, $checkedPaths)) {
                        if(isset($data[$_key])) {
                            $data = &$data[$_key];
                            if($i < (count($pathParts)-1)) {
                                if(is_array($data)) {
                                    $checkedPaths[] = $path;
                                }
                            }
                        }
                    }
                    else {
                        $data = &$data[$_key];
                    }
                }
                if(($type == 'array') && !is_array($data)) {
                    $result = false;
                    break;
                }
                else if(($type == 'string') && !is_string($data)) {
                    $result = false;
                    break;
                }
                else if(empty($data)) {
                    $result = false;
                    break;
                }
                else {
                    $checkedPaths[] = $path;
                }
            }
        }
        return $result;
    }
    
    protected function validateContextData(array $requestData) {
        $mandatoryKeys = array(
            'context.System.application.applicationId' => 'string',
            'context.System.device.deviceId' => 'string',
            'context.System.user.userId' => 'string'
        );
        $result = $this->validateRequest($requestData, $mandatoryKeys);
        return $result;
    }
    
    protected function validateContextUserData(array $requestData) {
        $mandatoryKeys = array(
            'context.System.user.userId' => 'string'
        );
        $result = $this->validateRequest($requestData, $mandatoryKeys);
        return $result;
    }
    
    protected function validateRequestData(array $requestData) {
        $mandatoryKeys = array(
            'request.type' => 'string',
            'request.requestId' => 'string',
            'request.timestamp' => 'string'
        );
        $result = $this->validateRequest($requestData, $mandatoryKeys);
        return $result;
    }
    
    protected function validateUserData(array $requestData) {
        $mandatoryKeys = array(
            'session.sessionId' => 'string',
            'session.application.applicationId' => 'string',
            'session.user.userId' => 'string'
        );
        $result = $this->validateRequest($requestData, $mandatoryKeys);
        return $result;
    }
    
    protected function validateSignatureCertificateUrl($url) {
        if((strlen($url) > 1) && (filter_var($url, FILTER_VALIDATE_URL) !== false)) {
            $pathParts = parse_url($url);
            if(($pathParts['scheme'] == 'https') && (!isset($pathParts['port']) || ($pathParts['port'] == 443))) {//checking for HTTPS
                if(($pathParts['host'] == VALID_SIGNATURE_CERTIFICATE_HOST_NAME) && preg_match('/'.str_replace('/', '\/', VALID_SIGNATURE_CERTIFICATE_URL_PATH).'/i', $pathParts['path'])) {
                    return true;
                }
            }
        }
        return false;
    }
    
    protected function validateSignatureCertificate($certData) {
        if(strlen($certData) > 1) {
            $parsedCertData = openssl_x509_parse($certData);
            if(is_array($parsedCertData) && isset($parsedCertData['subject']) && is_array($parsedCertData['subject']) && isset($parsedCertData['subject']['CN']) && ($parsedCertData['subject']['CN'] == SIGNATURE_CERTIFICATE_SAN)) {
                $validFrom = intval($parsedCertData['validFrom_time_t']);
                $validTo = intval($parsedCertData['validTo_time_t']);
                $now = strtotime('now');
                if(($now >= $validFrom) && ($now <= $validTo)) {
                    return true; 
                }
            }
        }
        return false;
    }
    
    protected function validateTimestamp($time) {
        if(defined('REQUEST_TIMESTAMP_DEBUG')) {
            return ((REQUEST_TIMESTAMP_DEBUG === true)? true: false);
        }
        if(is_string($time) && strlen($time) > 1) {
            $_time = strtotime($time);
            if(($_time > 1) && ((strtotime('now')-$_time) < 150)) {
                return true;
            }
        }
        return false;
    }

    public function __construct(array $config) {
        $this->_config = $config;
    }
    
    public function route($path) {        
        if($path) {    
            $postData = file_get_contents("php://input");
            $parsedPostData = NULL;
            $context = NULL;
            $user = NULL;
            $sha1 = NULL;
            if(strlen($postData) > 1) {
                $sha1 = sha1($postData, true);
                $parsedPostData = ((strlen($postData) > 1)? json_decode($postData, true): NULL);
                if(is_array($parsedPostData)) {
                    if($this->validateContextData($parsedPostData)) {
                        $context = $this->createContext($parsedPostData);
                    }
                    if($this->validateUserData($parsedPostData)) {
                        $user = $this->createUser($parsedPostData);
                    }
                    else if($this->validateContextUserData($parsedPostData)) {
                        
                    }
                }
            }

            $pathParts = explode('/', ltrim($path, '\\/'));
            $skillName = array_shift($pathParts);
            $skillParams = $pathParts;            
            
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
                            $inputData = ((is_array($parsedPostData))? $parsedPostData: ((empty($postData))? NULL: $postData));
                            return $routeHandler->run($uriParams, ($skillParams+array('input' => $inputData)));
                        }
                    } 
                }//*/

                if(!is_null($parsedPostData) && $this->validateRequestData($parsedPostData)) {
                    if(!$this->checkSignature($sha1, $parsedPostData['request']['timestamp'])) {
                        $this->badRequest();
                        return false;
                    }
                    
                    $requestId = $parsedPostData['request']['requestId'];
                    $requestLocale = ((isset($parsedPostData['request']['locale']))? $parsedPostData['request']['locale']: NULL);
                    $requestTimestamp = $parsedPostData['request']['timestamp'];
                    $requestType = strtolower($parsedPostData['request']['type']);
                    
                    if(!is_null($context)) {
                        $response = NULL;
                        if($requestType == 'launchrequest') {
                            $response = $this->processLaunchRequest($skill, $context, $user, $requestId, $requestLocale, $requestTimestamp);
                        }
                        else if($requestType == 'intentrequest') {
                            $isNew = false;
                            $requestName = '';
                            $requestSlots = [];
                            if(isset($parsedPostData['request']['intent'])) {
                                isset($parsedPostData['request']['intent']['name']) && $requestName = $parsedPostData['request']['intent']['name'];
                                isset($parsedPostData['request']['intent']['slots']) && $requestSlots = $parsedPostData['request']['intent']['slots'];
                            }
                            isset($parsedPostData['session']['new']) && $isNew = $parsedPostData['session']['new'];
                            $response = $this->processIntentRequest($skill, $context, $user, $requestName, $requestSlots, $isNew, $requestId, $requestLocale, $requestTimestamp);
                        }
                        else if($requestType == 'sessionendedrequest') {
                            $response = $this->processEndSessionRequest($skill, $context, $user, $requestId, $requestLocale, $requestTimestamp);
                        }
                        else if(strpos($requestType, '.') > -1) {
                            list($requestType, $requestSubType) = explode('.', $requestType);
                            $errorMessage = "Unable to process {$requestType}.{$requestSubType}.";
                            if($requestType == 'audioplayer') {
                                $audioPlayerController = $this->getSkillAudioPlayerController($skillName);
                                if(!is_null($audioPlayerController) && ($audioPlayerController instanceof PlaybackController)) {
                                    if($requestSubType == 'playbackfailed') {
                                        $response = $audioPlayerController->failed(Context::getInstance()->audioPlayer);
                                    }
                                    else if($requestSubType == 'playbackfinished') {
                                        $response = $audioPlayerController->finished(Context::getInstance()->audioPlayer);
                                    }
                                    else if($requestSubType == 'playbacknearlyfinished') {
                                        $response = $audioPlayerController->nearlyFinished(Context::getInstance()->audioPlayer);
                                    }
                                    else if($requestSubType == 'playbackstopped') {
                                        $response = $audioPlayerController->stopped(Context::getInstance()->audioPlayer);
                                    }
                                    else if($requestSubType == 'playbackstarted') {
                                        $response = $audioPlayerController->started(Context::getInstance()->audioPlayer);
                                    }
                                }
                            }
                            else if($requestType == 'display') {

                            }
                            else if($requestType == 'gameengine') {

                            }
                            else if($requestType == 'messaging') {
                                $messageHandler = $this->getSkillMessageHandler($skillName);
                                if(!is_null($messageHandler) && ($messageHandler instanceof Messaging)) {
                                    if($requestSubType == 'messagereceived') {
                                    
                                    }
                                }
                            }
                            else if($requestType == 'playbackcontroller') {
                                $audioPlayerController = $this->getSkillAudioPlayerController($skillName);
                                if(!is_null($audioPlayerController) && ($audioPlayerController instanceof PlaybackController)) {
                                    if($requestSubType == 'nextcommandissued') {
                                        $response = $audioPlayerController->next(Context::getInstance()->audioPlayer);
                                    }
                                    else if($requestSubType == 'pausecommandissued') {
                                        $response = $audioPlayerController->pause(Context::getInstance()->audioPlayer);
                                    }
                                    else if($requestSubType == 'playcommandissued') {
                                        $response = $audioPlayerController->play(Context::getInstance()->audioPlayer);
                                    }
                                    else if($requestSubType == 'previouscommandissued') {
                                        $response = $audioPlayerController->previous(Context::getInstance()->audioPlayer);
                                    }
                                }
                            }
                            else if($requestType == 'systems') {

                            }
                        }
                        if(is_null($response)) {
                            $response = new Response(NULL);
                        }
                        echo $response->build();
                        return true;
                    }
                    else {
                        
                    }
                }
                else if((count($skillParams) > 0) && isset($skillParams[0])) {
                    if(isset($config['allowedContentTypes'])) {
                        if(preg_match("/.+?\\.(".$config['allowedContentTypes'].")/", $skillParams[0])) {
                            $filePath = $config['directories']['content'].'/'.$skillParams[0];
                            Skill::log('Request for '.$skillParams[0].'.');
                            return $this->readFile($filePath);
                        }
                    }
                    Skill::log('Request for not allowed content: '.$skillParams[0].'.');
                    $this->notFound();
                    return false;
                }
                else {
                    Skill::log('Failed to parse request.');
                    //Skill::log('Requested path was not found: '.implode('/', $skillParams).'.');
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
    
    public function badRequest($message = '<h1>Bad Request.</h1>') {
        header('HTTP/1.0 400 Bad Request');
        echo $message;
    }
    
    public function notFound($message = '<h1>Not found.</h1>') {
        header('HTTP/1.0 404 Not Found');
        echo $message;
    }
    
    public function unauthorized($message = '<h1>Unauthorized.</h1>') {
        header('HTTP/1.0 401 Unauthorized');
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

