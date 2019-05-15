<?php
/**
 * Created by Aleksandr Berdnikov.
*/

namespace AlexaPHPSDK;

class AudioPlayer extends AlexaInterface {
    const CLEAR_ALL = 'CLEAR_ALL';//Clears the entire playback queue and stops the currently playing stream (if applicable).
    const CLEAR_ENQUEUED = 'CLEAR_ENQUEUED';//Clears the queue and continues to play the currently playing stream.
    const ENQUEUE = 'ENQUEUE';//Add the specified stream to the end of the current queue. This does not impact the currently playing stream.
    const REPLACE_ALL = 'REPLACE_ALL';//Immediately begin playback of the specified stream, and replace current and enqueued streams.
    const REPLACE_ENQUEUED = 'REPLACE_ENQUEUED';//Replace all streams in the queue. This does not impact the currently playing stream.


    public static $activity = "IDLE";
    public static $offset = 0;
    public static $token = "";
    
    public function __construct() {
        parent::__construct();
    }
    
    public function play($streamURL, $playBehavior = self::ENQUEUE, $offset = 0, $expectedPreviousToken = '', $title = NULL, $subtitle = NULL, array $art = NULL, array $backgroundImage = NULL) {
        $token = md5($this->getId().strtotime('now'));
        
        $directive = array(
            'type' => 'AudioPlayer.Play',
            'playBehavior' => $playBehavior,
            'audioItem' => array(
                'stream' => array(
                    'url' => $streamURL,
                    'token' => $token,
                    'offsetInMilliseconds' => intval($offset)
                )
            )
        );
        
        if($playBehavior == self::ENQUEUE) {
            $directive['audioItem']['stream']['expectedPreviousToken'] = ((empty($expectedPreviousToken))? $this->id: $expectedPreviousToken);
        }
        
        if(!is_null($title) || !is_null($subtitle) || !is_null($art) || !is_null($backgroundImage)) {
            $directive['audioItem']['metadata'] = array();
            !is_null($title) && $directive['audioItem']['metadata']['title'] = $title;
            !is_null($subtitle) && $directive['audioItem']['metadata']['subtitle'] = $subtitle;
            
            if(false) {
                $directive['audioItem']['metadata']['art'] = array(
                    'sources' => array(
                        array('url' => '')
                    )
                );
            }

            if(false) {
                $directive['audioItem']['metadata']['backgroundImage'] = array(
                    'sources' => array(
                        array('url' => '')
                    )
                );
            }
        }
        
        $this->directive = $directive;
        
        return $token;
    }
    
    public function reset($all = true) {
        $this->directive = array(
            'type' => 'AudioPlayer.ClearQueue',
            'clearBehavior' => (($all)? self::CLEAR_ALL: self::CLEAR_ENQUEUED)
        );
    }
    
    public function stop() {
        $this->directive = array(
            'type' => 'AudioPlayer.Stop'
        );
    }
}