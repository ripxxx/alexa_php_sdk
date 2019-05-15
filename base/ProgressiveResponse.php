<?php
/**
 * Created by Aleksandr Berdnikov.
*/

namespace AlexaPHPSDK;

define('PROGRESSIVE_RESPONSE_MAX_AUDIO_FILES', 1);
define('PROGRESSIVE_RESPONSE_MAX_COUNT', 5);

class ProgressiveResponse {
    protected $apiAccessToken;
    protected $apiEndPoint;
    protected $apiPath = '/v1/directives';
    protected $audioCounter = 0;
    protected $curlOptions = array(
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => false,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_ENCODING => "",
        CURLOPT_USERAGENT => "alexa_skill",
        CURLOPT_AUTOREFERER => true,
        CURLOPT_CONNECTTIMEOUT => 4,
        CURLOPT_TIMEOUT => 8,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_HTTPHEADER => array()
    );
    protected $requestId;
    protected $speech = array();

    protected static $progressiveResponsesSentCount = PROGRESSIVE_RESPONSE_MAX_COUNT;
    
    public static $enabled = false;
    
    public function __construct($requestId, $apiEndPoint, $apiAccessToken) {
        $this->apiAccessToken = $apiAccessToken;
        $this->apiEndPoint = $apiEndPoint;
        $this->requestId = $requestId;
    }
    
     public function addAudio($url) {
        if(($url != '') && (filter_var($url, FILTER_VALIDATE_URL) !== false) && ($this->audioCounter < RESPONSE_MAX_AUDIO_FILES)) {
            $this->audioCounter++;
            
            $this->speech[] = array(
                'content' => $url,
                'type' => 'audio'
            );
            
            return true;
        }
        return false;
    }
    
    public function addText($text) {
        if($text != '') {
            $this->speech[] = array(
                'content' => $text,
                'type' => 'text'
            );
            return true;
        }
        return false;
    }
    
    public function clear() {
        $this->speech = array();
    }
    
    public function send($message = '') {
        if(!self::$enabled || !is_string($this->apiAccessToken) || !is_string($this->apiEndPoint) || !is_string($this->requestId)) {
            return false;
        }
        
        if(self::$progressiveResponsesSentCount < 1) {
            return false;
        }
        
        --self::$progressiveResponsesSentCount;
        
        $ssml = '';
        if(count($this->speech) > 0) {
            $this->addText($message);
            $ssml = '<speak>';
            foreach($this->speech as $speechPart) {
                switch($speechPart['type']) {
                    case 'audio':
                        $ssml.= '<audio src=\\"'.$speechPart['content'].'\\" />';
                    break;
                    default:
                        $ssml.= $speechPart['content'].' ';
                }
            }
            //$ssml.= ((strlen($repromprtMessage) == 0)? '': $repromprtMessage);
            $ssml.= '</speak>';
        }
        else {
            $ssml = $message;
        }
        
        $request = '{"header" : {"requestId" : "'.$this->requestId.'"}, "directive" : {"type" : "VoicePlayer.Speak", "speech" : "'.$ssml.'"}}';
        
        $options = $this->curlOptions;
        array_push($options[CURLOPT_HTTPHEADER], 'Authorization: Bearer '.$this->apiAccessToken);
        array_push($options[CURLOPT_HTTPHEADER], 'Content-Type: application/json');
        $options[CURLOPT_POST] = 1;
        $options[CURLOPT_POSTFIELDS] = $request;
        
        $requestUrl = $this->apiEndPoint.'/'.trim($this->apiPath, '/');

        $curlHandler = curl_init($requestUrl);
        curl_setopt_array($curlHandler, $options);
        $responseBody = curl_exec($curlHandler);
        $errorno = curl_errno($curlHandler);
        $errmsg = curl_error($curlHandler);
        $headers = curl_getinfo($curlHandler);
        curl_close($curlHandler);
        
        if($errorno == 0) {
            return true;
        }
        return false;
    }
}