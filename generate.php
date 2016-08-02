<?php

define('TEMPLATES_DIRECTORIES', __DIR__.'/templates/');

if(php_sapi_name() === 'cli') {
    if(count($argv) > 2) {
        switch($argv[1]) {
            case 'intents' :
                echo generateIntents($argv[2]);
            break;
            default:
                echo "Usupported parameters.\n";
        }
    }
    else {
        echo "Please, specify params:\n>".basename(__FILE__)." type SKILL_NAME\n";
    }
}
else {
    echo "Please, run script from command line.\n";
}

function generateIntents($skillName) {
    $config = require(__DIR__.'/base/config/main.php');
    $skillsDirectory = $config['directories']['skills'];
    $templatesDirectory = $config['directories']['templates'];
    if(!file_exists($skillsDirectory) || !is_dir($skillsDirectory)) {
        return "Templates directory not found.\n";
    }
    if(file_exists($skillsDirectory) && is_dir($skillsDirectory)) {
        $skillDirectory = $skillsDirectory.$skillName.'/';
        if(file_exists($skillDirectory) && is_dir($skillDirectory)) {
            if(!is_writable($skillDirectory)) {
                return "Skill directory is not writable.\n";
            }
            $intentsSchemaFilePath = $skillDirectory.'assets/IntentSchema.json';
            if(file_exists($intentsSchemaFilePath) && is_readable($intentsSchemaFilePath)) {
                $intents = file_get_contents($intentsSchemaFilePath);
                if(empty($intents)) {
                    return "Skill '{$skillName}' has no intents schema available.\n";
                }
                $intents = json_decode($intents, true);
                if(!is_array($intents) || !isset($intents['intents'])) {
                    return "Skill '{$skillName}' has no intents schema available.\n";
                }
                //launch
                $launchIntentFilePath = $templatesDirectory.'Launch.tpl.php';
                $_launchIntentFilePath = $skillDirectory.'Launch.php';
                if(!file_exists($_launchIntentFilePath)) {
                    echo "Creating launch intent...\n";
                    if(!copy($launchIntentFilePath, $_launchIntentFilePath)) {
                        echo "Launch intent file copy error.\n";
                    }
                }
                //session end
                $endSessionFilePath = $templatesDirectory.'EndSession.tpl.php';
                $_endSessionFilePath = $skillDirectory.'EndSession.php';
                if(!file_exists($_endSessionFilePath)) {
                    echo "Creating session end intent...\n";
                    if(!copy($endSessionFilePath, $_endSessionFilePath)) {
                        echo "Session end intent file copy error.\n";
                    }
                }
                echo "Creating intents...\n";
                $intentTemplate = file_get_contents($templatesDirectory.'Intent.tpl.php');
                foreach($intents['intents'] as $intent) {
                    $intentName = $intent['intent'];
                    if(!file_exists($skillDirectory.$intentName.'.php')) {
                        echo "Creating {$intentName}...\n";
                        $slots = array();
                        if(isset($intent['slots']) && is_array($intent['slots']) && (count($intent['slots']) > 0)) {
                            foreach($intent['slots'] as $slot) {
                                $slots[] = '//'.$slot['type'].' '.$slot['name'];
                            }
                        }
                        $intentFileContents = str_replace('//SLOTS', ((count($slots) > 0)? implode("\n", $slots):  "//NO SLOTS"), $intentTemplate);
                        $intentFileContents = str_replace('INTENT_NAME', $intentName, $intentFileContents);
                        file_put_contents($skillDirectory.$intentName.'.php', $intentFileContents);
                    }
                }
                echo "Done\n";
            }
            else {
                return "Skill '{$skillName}' has no intents schema available.\n";
            }
        }
        else {
            return "Skill '{$skillName}' was not found.\n";
        }
    }
    else {
        return "Skills directory was not found.\n";
    }
}