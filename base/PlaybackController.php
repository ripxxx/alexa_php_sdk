<?php
/**
 * Created by Aleksandr Berdnikov.
*/

namespace AlexaPHPSDK;

class PlaybackController {
    protected function createResponse(AudioPlayer $audioPlayer = NULL, $shouldEndSession = NULL) {
        $response = new Response($shouldEndSession);
        if(!is_null($audioPlayer)) {
            $response->add($audioPlayer);
        }
        return $response;
    }
    
    public function failed(AudioPlayer $audioPlayer) {
        return $this->createResponse();
    }
    
    public function finished(AudioPlayer $audioPlayer) {
        return $this->createResponse();
    }
    
    public function nearlyFinished(AudioPlayer $audioPlayer) {
        return $this->createResponse();
    }
    
    public function next(AudioPlayer $audioPlayer) {
        return $this->createResponse();
    }
    
    public function pause(AudioPlayer $audioPlayer) {
        return $this->createResponse();
    }
    
    public function play(AudioPlayer $audioPlayer) {
        return $this->createResponse();
    }
    
    public function previous(AudioPlayer $audioPlayer) {
        return $this->createResponse();
    }
    
    public function stopped(AudioPlayer $audioPlayer) {
        return $this->createResponse();
    }
    
    public function started(AudioPlayer $audioPlayer) {
        return $this->createResponse();
    }
}