<?php
/**
 * Created by Aleksandr Berdnikov.
*/

namespace AlexaPHPSDK;

class Reminder {
    protected $apiAccessToken;
    protected $apiEndPoint;
    protected $apiPath = '/v1/alerts/reminders';
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

    protected static $progressiveResponsesSentCount = PROGRESSIVE_RESPONSE_MAX_COUNT;
    
    public static $enabled = true;
    
    public function __construct($requestId, $apiEndPoint, $apiAccessToken) {
        $this->apiAccessToken = $apiAccessToken;
        $this->apiEndPoint = $apiEndPoint;
        $this->requestId = $requestId;
    }
    
    public function set($message = '', $timeout = 60) {
        if(!self::$enabled || !is_string($this->apiAccessToken) || !is_string($this->apiEndPoint)) {
            return false;
        }
        
        $request = '{"requestTime" : "'.date('Y-m-d\Th:i:s.000').'", "trigger" : {"type" : "SCHEDULED_RELATIVE", "offsetInSeconds" : "'.$timeout.'"}, "alertInfo" : {"spokenInfo" : {"content" : [{"locale" : "en-US", "text" : "'. addslashes($message).'"}]}}, "pushNotification" : {"status" : "ENABLED"}}';
        
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