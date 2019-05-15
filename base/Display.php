<?php
/**
 * Created by Aleksandr Berdnikov.
*/

namespace AlexaPHPSDK;

class Display extends AlexaInterface {
    protected $markupVersion = 1.0;
    protected $templateVersion = 1.0;
    
    public static $token = "";
    
    public function __construct($markupVersion, $templateVersion) {
        parent::__construct();
        $this->markupVersion = floatval($markupVersion);
        $this->templateVersion = floatval($templateVersion);
    }
}