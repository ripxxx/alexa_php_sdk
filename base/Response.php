<?php
/**
 * Created by Aleksandr Berdnikov.
 * Copyright 2016 Onix-Systems.
*/

namespace AlexaPHPSDK;

define('RESPONSE_MAX_AUDIO_FILES', 5);

class Response {
    protected $audioCounter = 0;
    protected $directives = array();
    protected $description = '';
    protected $imageUrl = '';
    protected $needAccountLinking = false;
    protected $requestedPermissions = [];
    protected $needRemoveShouldEndSessionDirectivesCount = 0;
    protected $noCard = false;
    protected $repromprtMessage = '';
    protected $shouldEndSession;
    protected $smallImageUrl = '';
    protected $speech = array();
    protected $title = '';
    
    public function __construct($shouldEndSession = false) {
        $this->shouldEndSession = $shouldEndSession;
    }
    
    public function add(AlexaInterface $alexaInterface) {
        $id = $alexaInterface->getId();
        $directive = $alexaInterface->getDirective($this);
        $alexaInterface->needRemoveShouldEndSession && ++$this->needRemoveShouldEndSessionDirectivesCount;
        if(!is_null($directive)) {
            $this->directives[$id]= $directive;
            return true;
        }
        return false;
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
    
    public function addVideoStream($title, $subTitle, $url) {
        $directive = array(
            'type' => 'VideoApp.Launch',
            'videoItem' => array(
                'source' => $url,
                'metadata' => array(
                    'title' => $title,
                    'subtitle' => $subTitle
                )
            )
        );
        $this->directives[]= $directive;
    }
    
    public function forceAcccountLinking($text = '', $needAccountLinking = true) {
        $this->speech = array(
            array(
                'content' => ((empty($text))? 'Please use Alexa app to link your Amazon account with Skill service.': $text),
                'type' => 'text'
            )
        );
        $this->shouldEndSession = true;
        $this->needAccountLinking = $needAccountLinking;
    }
    
    public function forceSessionEnd($shouldEndSession = true) {
        $this->shouldEndSession = $shouldEndSession;
    }
    
    public function requestPermissions(array $requestedPermissions = [], $text = '') {
        $this->speech = array(
            array(
                'content' => ((empty($text))? 'Please use Alexa app to update your permissions settings.': $text),
                'type' => 'text'
            )
        );
        $this->shouldEndSession = true;
        $this->requestedPermissions = $requestedPermissions;
    }
    
    public function setDescription($text, $title = NULL) {
        if($text != '') {
            $this->description = $text;
            !is_null($title) && $this->title = $title;
            return true;
        }
        return false;
    }
    
    public function setImage($url, $smallImage = false) {
        if($url != '' && (filter_var($url, FILTER_VALIDATE_URL) !== false)) {
            if($smallImage) {
                $this->smallImageUrl = $url;
            }
            else {
                $this->imageUrl = $url;
            }
            return true;
        }
        return false;
    }
    
    public function setRepromptMessage($text) {
        if($text != '') {
            $this->repromprtMessage = $text;
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
    
    public function removeCard($remove = true) {
        $this->noCard = $remove;
    }
    
    public function setTitle($text) {
        if($text != '') {
            $this->title = $text;
            return true;
        }
        return false;
    }
    
    public function build($repromprtMessage = '', $shouldEndSession = NULL) {
        is_null($shouldEndSession) && $shouldEndSession = $this->shouldEndSession;
        (strlen($repromprtMessage) == 0) && $repromprtMessage = $this->repromprtMessage;
        
        $card = '';
        $skill = Skill::getInstance();
        $session = NULL;
        $ssml = '';
        $user = User::getInstance();
        if(count($this->speech) > 0) {
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
        /*else {
            $ssml = '<speak>Sorry, I have no answer for your question.</speak>';
        }//*/
        
        if($this->needAccountLinking) {
            $type = 'LinkAccount';
            $card = '"card" : {"type" : "'.$type.'"}';
        }
        else if (count($this->requestedPermissions) > 0) {
            $type = 'AskForPermissionsConsent';
            $card = '"card" : {"type" : "'.$type.'", "permissions" : ["'.implode('" ,"', $this->requestedPermissions).'"]}';
        }
        else if(!$this->noCard && (!empty($this->description) || !empty($this->imageUrl) || !empty($this->smallImageUrl))) {
            $image = '';
            $type = 'Simple';
            if(!empty($this->imageUrl) || !empty($this->smallImageUrl)) {
               $type = 'Standard';
               $imageUrl = $this->imageUrl;
               empty($imageUrl) && $imageUrl = $this->smallImageUrl;
               $smallImageUrl = $this->smallImageUrl;
               empty($smallImageUrl) && $smallImageUrl = $imageUrl;
               $image = '"image" : {"smallImageUrl" : "'.$smallImageUrl.'", "largeImageUrl" : "'.$imageUrl.'"}';
           }
           $description = $this->description;
           $title = $this->title;
           empty($title) && $title = $skill->name;
           empty($description) && $description = $title;
           $card = '"card" : {"type" : "'.$type.'", "title" : "'.$title.'", '.(($type == 'Simple')? '"content"': '"text"').' : "'.$description.'"'.((strlen($image) > 0)? ', '.$image: '').'}';//need escape for title & description
        }
        
        if(!is_null($user)) {
            $session = $user->session;
        }
        
        $params = [];
        //text
        if(strlen($ssml) > 0) {
            $params[] = '"outputSpeech" : {"type" : "SSML", "ssml" : "'.$ssml.'"}';
        }
        //reprompt
        if(strlen($repromprtMessage) > 0) {
            $params[] = '"reprompt": {"outputSpeech": {"type" : "PlainText", "text" : "'.$repromprtMessage.'"}}';
        }
        //card
        if(strlen($card) > 0) {
            $params[] = $card;
        }
        //directives
        if(count($this->directives) > 0) {
            $params[] = '"directives": '. json_encode(array_values($this->directives), JSON_UNESCAPED_SLASHES);
        }
        //shouldEndSession
        if(($this->needRemoveShouldEndSessionDirectivesCount <= 0) && !is_null($shouldEndSession)) {
            $params[] = '"shouldEndSession" : '.(($shouldEndSession)? 'true': 'false');
        }
        
        $_response = array(
            '"version" : "1.0"',
            '"response" : {'.implode(', ', $params).'}'
        );
        
        if(!is_null($session)) {
            $_response[] = '"sessionAttributes" : '.json_encode($session, JSON_FORCE_OBJECT);
        }
        
        $response = '{'.implode(', ', $_response).'}';
     
        return $response;
    }
}

