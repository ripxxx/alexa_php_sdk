<?php
/**
 * Created by Aleksandr Berdnikov.
 * Copyright 2016 Onix-Systems.
*/

namespace AlexaPHPSDK;

define('MAX_AUDIO_FILES', 5);

class Response {
    protected $audioCounter = 0;
    protected $description = '';
    protected $imageUrl = '';
    protected $needAccountLinking = false;
    protected $repromprtMessage = '';
    protected $shouldEndSession;
    protected $smallImageUrl = '';
    protected $speach = array();
    protected $title = '';
    
    public function __construct($shouldEndSession = false) {
        $this->shouldEndSession = $shouldEndSession;
    }

    public function addAudio($url) {
        if(($url != '') && (filter_var($url, FILTER_VALIDATE_URL) !== false) && ($this->audioCounter < MAX_AUDIO_FILES)) {
            $this->audioCounter++;
            
            $this->speach[] = array(
                'content' => $url,
                'type' => 'audio'
            );
            
            return true;
        }
        return false;
    }
    
    public function forceAcccountLinking($text = '', $needAccountLinking = true) {
        $this->speach = array(
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
    
    public function setRepromprtMessage($text) {
        if($text != '') {
            $this->repromprtMessage = $text;
            return true;
        }
        return false;
    } 
    
    public function addText($text) {
        if($text != '') {
            $this->speach[] = array(
                'content' => $text,
                'type' => 'text'
            );
            return true;
        }
        return false;
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
        $ssml = '';
        $skill = Skill::getInstance();
        $user = User::getInstance();
        if(count($this->speach) > 0) {
            $ssml = '<speak>';
            foreach($this->speach as $speachPart) {
                switch($speachPart['type']) {
                    case 'audio':
                        $ssml.= '<audio src=\\"'.$speachPart['content'].'\\" />';
                    break;
                    default:
                        $ssml.= $speachPart['content'];
                }
            }
            //$ssml.= ((strlen($repromprtMessage) == 0)? '': $repromprtMessage);
            $ssml.= '</speak>';
        }
        else {
            $ssml = '<speak>Sorry, I have no answer for your question.</speak>';
        }
        
        if($this->needAccountLinking) {
            $type = 'LinkAccount';
            $card = '"card" : {"type" : "'.$type.'"}';
        }
        else if(!empty($this->description) || !empty($this->imageUrl) || !empty($this->smallImageUrl)) {
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
        
        $session = $user->session;
        
        $response = '{"version" : "1.0", "response" : {"outputSpeech" : {"type" : "SSML", "ssml" : "'.$ssml.'"}, '.((strlen($repromprtMessage) == 0)? '': '"reprompt": {"outputSpeech": {"type" : "PlainText", "text" : "'.$repromprtMessage.'"}}, ').((strlen($card) > 0)? $card.', ': '').'"shouldEndSession" : '.(($shouldEndSession)? 'true': 'false').'}, "sessionAttributes" : '.json_encode($session, JSON_FORCE_OBJECT).'}';
     
        return $response;
    }
}

