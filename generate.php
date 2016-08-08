<?php
/**
 * Created by Aleksandr Berdnikov.
 * Copyright 2016 Onix-Systems.
*/

define('TEMPLATES_DIRECTORIES', __DIR__.'/templates/');

if(php_sapi_name() === 'cli') {
    if(count($argv) > 2) {
        switch($argv[1]) {
            case 'intents' :
                echo generateIntents($argv[2]);
            break;
            case 'skill' :
                echo generateSkill($argv[2]);
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

function generateIntents($skillName, $generateLaunchIntent = true, $generateEndSessionIntent = true, $generateIntentsWithSchema = true) {
    $config = require(__DIR__.'/base/config/main.php');
    $skillsDirectory = $config['directories']['skills'];
    $templatesDirectory = $config['directories']['templates'];
    if(!file_exists($templatesDirectory) || !is_dir($templatesDirectory)) {
        return "Templates directory not found.\n";
    }
    if(file_exists($skillsDirectory) && is_dir($skillsDirectory)) {
        $skillDirectory = $skillsDirectory.$skillName.'/';
        if(file_exists($skillDirectory) && is_dir($skillDirectory)) {
            if(!is_writable($skillDirectory)) {
                return "Skill directory is not writable.\n";
            }
            if($generateLaunchIntent) {//launch
                $launchIntentFilePath = $templatesDirectory.'Launch.tpl.php';
                $_launchIntentFilePath = $skillDirectory.'Launch.php';
                if(!file_exists($_launchIntentFilePath)) {
                    echo "Creating launch intent...\n";
                    $intentFileContents = file_get_contents($launchIntentFilePath);
                    $intentFileContents = str_replace('SKILL_NAMESPACE', $skillName, $intentFileContents);
                    if(!file_put_contents($_launchIntentFilePath, $intentFileContents)) {
                        echo "Launch intent file copy error.\n";
                    }
                }
            }
            if($generateEndSessionIntent) {//session end
                $endSessionFilePath = $templatesDirectory.'EndSession.tpl.php';
                $_endSessionFilePath = $skillDirectory.'EndSession.php';
                if(!file_exists($_endSessionFilePath)) {
                    echo "Creating session end intent...\n";
                    $intentFileContents = file_get_contents($endSessionFilePath);
                    $intentFileContents = str_replace('SKILL_NAMESPACE', $skillName, $intentFileContents);
                    if(!file_put_contents($_endSessionFilePath, $intentFileContents)) {
                        echo "Session end intent file copy error.\n";
                    }
                }
            }
            if($generateIntentsWithSchema) {
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
                
                    echo "Creating intents...\n";
                    $intentTemplate = file_get_contents($templatesDirectory.'Intent.tpl.php');
                    foreach($intents['intents'] as $intent) {
                        $intentName = $intent['intent'].'Intent';
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
                            $intentFileContents = str_replace('SKILL_NAMESPACE', $skillName, $intentFileContents);
                            file_put_contents($skillDirectory.$intentName.'.php', $intentFileContents);
                        }
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

function generateSkill($skillName) {
    $config = require(__DIR__.'/base/config/main.php');
    $skillsDirectory = $config['directories']['skills'];
    if(!file_exists($skillsDirectory) || !is_dir($skillsDirectory) || !is_writable($skillsDirectory)) {
        return "Skills directory not found or has no write permission.\n";
    }
    $skillDirectory = $skillsDirectory.$skillName.'/';
    if(file_exists($skillDirectory) && is_dir($skillDirectory)) {
        return "Skill directory already exists.\n";
    }
    echo "Creating skill directory...\n";
    mkdir($skillDirectory);
    if(file_exists($skillDirectory) && is_dir($skillDirectory)) {
        if(!is_writable($skillDirectory)) {
            return "Skill directory is not writable.\n";
        }
        echo "Creating assets directory...\n";
        $assetsDirectory = $skillDirectory.'/assets/';
        mkdir($assetsDirectory);
        if(file_exists($assetsDirectory) && is_dir($assetsDirectory)) {
            if(!is_writable($assetsDirectory)) {
                return "Assets directory is not writable.\n";
            }
        }
        file_put_contents($assetsDirectory.'.info', "Put your intents schema here.");
        echo "Creating content directory...\n";
        $contentDirectory = $skillDirectory.'/content/';
        mkdir($contentDirectory);
        if(file_exists($contentDirectory) && is_dir($contentDirectory)) {
            if(!is_writable($contentDirectory)) {
                return "Content directory is not writable.\n";
            }
        }
        file_put_contents($contentDirectory.'.info', "Put your content here.");
        echo "Creating private directory...\n";
        $privateDirectory = $skillDirectory.'/private/';
        mkdir($privateDirectory);
        if(file_exists($privateDirectory) && is_dir($privateDirectory)) {
            if(!is_writable($privateDirectory)) {
                return "Private directory is not writable.\n";
            }
        }
        file_put_contents($privateDirectory.'.info', "private files");
        echo "Creating gitignore file...\n";
        file_put_contents('.gitignore', "/private/*\n!/private/.info");
        echo "Creating config file...\n";
        file_put_contents($skillDirectory.'/config.php', "<?php
return [
    'directories' => [
        'content' => __DIR__.'/content',
    ],
    'skillHttpsUrl' => 'https://www.yoursite.com/'.$skillName,
    'allowedContentTypes' => 'jpg|jpeg|gif|mp3'
];");
        echo "Creating readme file...\n";
        file_put_contents($skillDirectory.'/README.md', "# ".strtolower($skillName)."_skill
".ucfirst($skillName)." skill");
        return generateIntents($skillName, true, true, false);
    }
}