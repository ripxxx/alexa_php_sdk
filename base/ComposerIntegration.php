<?php
/**
 * Created by Aleksandr Berdnikov.
*/

namespace AlexaPHPSDK;

use Composer\Script\Event;
use Composer\Installer\PackageEvent;

class ComposerIntegration  {
    const COMMAND_ATTEMPTS_COUNT = 3;

    protected static $configFilePath = __DIR__.'/config/main.php';
    protected static $defaultConfigFilePath = __DIR__.'/config/main.php.default';
    
    protected static function absolutePath($path) {
        $absolutePath = str_replace('\\', '/', $path);
        $pathParts = explode('/', $absolutePath);
        $additionalPathParts = array();
        $normalizedPathParts = array();
        foreach($pathParts as $pathPart) {
            if(!empty($normalizedPathParts)) {
                if(empty($pathPart)) {
                    continue;
                }
                else if($pathPart == '..') {
                    array_pop($normalizedPathParts);
                    continue;
                }
            }
            else if($pathPart == '..') {
                $additionalPathParts[] = '..';
                continue;
            }

            array_push($normalizedPathParts, $pathPart);
        }

        return implode(((PHP_OS == 'WINNT')? '\\': '/'), array_merge($additionalPathParts, $normalizedPathParts));
    }
    
    protected static function askForDirectoryPath($description, $prompt, $defaultPath, $attempts = 1) {
        echo "\n".$description." Default: ".$defaultPath.", press enter to use.\n";
        echo "Relative path to ".getcwd()." is allowed.\n";
        $s = ((PHP_OS == 'WINNT')? '\\': '/');
        while($attempts > 0) {
            $directoryPath = self::readline($prompt);
            
            if(empty($directoryPath)) {
                $directoryPath = $defaultPath;
            }
            
            if(file_exists($directoryPath) && is_dir($directoryPath)) {
                return realpath($directoryPath).$s;
            }
            else {
                $yn = self::readline("Do you want to create '".$directoryPath."'? [y/n, default y]: ");
                if(empty($yn) || in_array(strtolower($yn), array('y', 'yes'))) {
                    if(@mkdir($directoryPath, 755, true)) {
                        return realpath($directoryPath).$s;
                    }
                    else {
                        echo 'Failed to create directory: '.$directoryPath."\n";
                    }
                }
            }
            
            --$attempts;
        }
        return false;
    }
    
    protected static function askForURL($description, $prompt, $defaultUrl, $attempts = 1) {
        echo "\n".$description." Default: ".$defaultUrl.", press enter to use.\n";
        
        $attemptsMade = 0;
        while($attemptsMade < $attempts) {
            $url = self::readline((($attemptsMade > 0)? "!!!Invalid URL.\n": '').$prompt);
            
            if(empty($url)) {
                $url = $defaultUrl;
            }

            if(strpos($url, ':/') === false) {
                $url = 'https://'.$url;
            }

            if(filter_var($url, FILTER_VALIDATE_URL) !== false) {
                $url = 'https://'.preg_replace('/http:\/\/|https:\/\//', '', trim($url, '/'));
                return $url;
            }

            ++$attemptsMade;
        }
        return false;
    }

    protected static function assert($assertion, $description) {
        if(!$assertion) {
            print($description);
        }
        return $assertion;
    }
    
    protected static function configureDirectories(array &$config, array &$replaces) {
        $s = ((PHP_OS == 'WINNT')? '\\': '/');
        
        (!array_key_exists('base',  $config['directories']) || !file_exists($config['directories']['base'])) && $config['directories']['base'] = __DIR__;
        (!array_key_exists('config',  $config['directories']) || !file_exists($config['directories']['config'])) && $config['directories']['config'] = __DIR__.$s.'config';
        
        //PATH TO LOGS
        $logsDirectoryPath = self::askForDirectoryPath('***Path to logs directory.', 'Please, enter path to logs directory: ', self::absolutePath($config['directories']['log']), self::COMMAND_ATTEMPTS_COUNT); 
        if(!self::assert(($logsDirectoryPath !== false), '!!!Unable to set logs directory.')) {
            return false;
        }
        //PATH TO SKILLS
        $skillsDirectoryPath = self::askForDirectoryPath('***Path to skills directory.', 'Please, enter path to skills directory: ', self::absolutePath($config['directories']['skills']), self::COMMAND_ATTEMPTS_COUNT); 
        if(!self::assert(($skillsDirectoryPath !== false), '!!!Unable to set skills directory.')) {
            return false;
        }
        //PATH TO USERS
        $usersDirectoryPath = self::askForDirectoryPath('***Path to users sessions directory.', 'Please, enter path to users sessions directory: ', self::absolutePath($config['directories']['users']), self::COMMAND_ATTEMPTS_COUNT); 
        if(!self::assert(($usersDirectoryPath !== false), '!!!Unable to set users sessions directory.')) {
            return false;
        }

        $config['directories']['base'] = self::absolutePath($config['directories']['base']).$s;
        $config['directories']['config'] = self::absolutePath($config['directories']['config']).$s;
        $config['directories']['log'] = $logsDirectoryPath;
        $config['directories']['skills'] = $skillsDirectoryPath;
        $config['directories']['users'] = $usersDirectoryPath;
        
        $yn = self::readline('Do you want to replace paths with paths relative to "'.$config['directories']['config'].'"? [y/n, default y]: ');
        if(empty($yn) || in_array(strtolower($yn), array('y', 'yes'))) {
            foreach($config['directories'] as $key=>$path) {
                $config['directories'][$key] = self::relativePath($path, $config['directories']['config']).$s;
                $replaces[var_export($config['directories']['config'], true)] = '__DIR__';
                $replaces[rtrim(var_export($config['directories']['config'], true), '\'')] = '__DIR__.\''.trim(var_export($s, true), '\'');
            }
        }
        return true;
    }
    
    protected static function configureOther(array &$config, array &$replaces) {
        echo "\n";
        //URL
        $url = ((array_key_exists('url', $config))? $config['url']: 'localhost');
        $url = self::askForURL('***URL of your site with path to framework.', 'Please enter URL: ', $url, self::COMMAND_ATTEMPTS_COUNT);
        if(!self::assert(($url !== false), '!!!Unable to set URL.')) {
            return false;
        }
        $config['url'] = $url;
        return true;
    }
    
    protected static function getConfig() {
        $defaultConfig = array(
            'directories' => array(
                'base' => __DIR__,
                'config' => __DIR__.'/config/',
                'log' => __DIR__.'/../../log/',
                'skills' => __DIR__.'/../../skills/',
                'templates' => __DIR__.'/../../templates/',
                'users' => '/../../../users/',
            )
        );

        if(file_exists(self::$configFilePath)) {    
            $config = require(self::$configFilePath);
        }
        else if(file_exists(self::$defaultConfigFilePath)) {
            $config = require(self::$defaultConfigFilePath);
        }
        else {
            $config = $defaultConfig;
        }

        if(!array_key_exists('directories', $config)) {
            $config['directories'] = $defaultConfig['directories'];
        }
        
        return $config;
    }
    
    protected static function readline($prompt = '') {
        $line = '';
        if(PHP_OS == 'WINNT') {
            print($prompt);
            $line = stream_get_line(STDIN, 1024, PHP_EOL);
        }
        else {
            $line = readline($prompt);
        }
        return $line;
    }
    
    protected static function relativePath($path, $relativeToPath) {
        $path = self::absolutePath($path);
        $relativeToPath = self::absolutePath($relativeToPath);

        $separator = ((PHP_OS == 'WINNT')? '\\': '/');

        $pathParts = explode($separator, $path);
        $relativeToPathParts = explode($separator, $relativeToPath);

        $pathPartsCount = count($pathParts);
        $relativeToPathPartsCount = count($relativeToPathParts);

        $additionalPathParts = array();
        $basePathParts = array();
        $relativePathParts = array();
        for($i = 0; $i < $relativeToPathPartsCount; $i++) {
            $basePathParts[] = $relativeToPathParts[$i];
            if($i < $pathPartsCount) {
                if($pathParts[$i] != $relativeToPathParts[$i]) {
                    $relativePathParts[] = '..';
                    $additionalPathParts[] = $pathParts[$i];
                }
            }
            else {
                $relativePathParts[] = '..';
            }
        }
        if(($pathPartsCount - $relativeToPathPartsCount) > 0) {
            for($i = $relativeToPathPartsCount; $i < $pathPartsCount; $i++) {
                $additionalPathParts[] = $pathParts[$i];
            }
        }

        return implode(((PHP_OS == 'WINNT')? '\\': '/'), array_merge($basePathParts, $relativePathParts, $additionalPathParts));
    }
    
    protected static function saveConfigurationFile(array $configuration, $configurationFilePath, array $replaces = array()) {
        $content = "<?php\n";
        $content.= "/**\n * Created by ".get_current_user()." on ".date("m/d/Y H:i:s")."\n*/\n\n";
        
        $config = var_export($configuration, true);
        $config = str_replace("=> \n", "=> ", $config);
        $config = preg_replace("/=>\s+array/", "=> array", $config);
        if(count($replaces) > 0) {
            foreach($replaces as $key=>$value) {
                $config = str_replace($key, $value, $config);
            }
        }
        
        $content.= 'return '.$config.';';
        
        return (file_put_contents($configurationFilePath, $content) !== false);
    }
    
    public static function configure(Event $event) {
        $config = self::getConfig();
        $replaces = array();
        
        echo "\nPath to configuration file: ".realpath(self::$configFilePath)."\n";
        
        //CONFIGURING DIRECTORIES
        if(!self::assert(self::configureDirectories($config, $replaces), "\nUnable to create configuration file. Please try again, run composer configure.\n")) {
            return;
        }
        
        //CONFIGURING OTHER
        if(!self::assert(self::configureOther($config, $replaces), "\nUnable to create configuration file. Please try again, run composer configure.\n")) {
            return;
        }

        //SAVING CONFIGURATION FILE
        if(self::saveConfigurationFile($config, self::$configFilePath, $replaces)) {
            echo "\nConfiguration file was successfully created.\n";
        }
        else {
            echo "\nUnable to create configuration file. Please try again, run composer configure.\n";
        }
    }
    
    public static function createSkill(Event $event) {
        $arguments = $event->getArguments();
        
        $s = ((PHP_OS == 'WINNT')? '\\': '/');
        
        $skillName = '';
        if(count($arguments) == 0) {
            $skillName = self::readline('Please, enter skill name: ');
        }
        else {
            $skillName = $arguments[0];
        }

        $config = self::getConfig();
        $url = ((array_key_exists('url', $config))? $config['url']: 'localhost');
        $skillsDirectory = $config['directories']['skills'];
        if(!self::assert((file_exists($skillsDirectory) && is_dir($skillsDirectory) && is_writable($skillsDirectory)), "Skills directory not found or has no write permission.\n")) {
            return;
        }
        $skillDirectory = $skillsDirectory.$skillName.$s;
        if(!self::assert(!file_exists($skillDirectory), "Skill directory already exists.\n")) {
            return;
        }
        echo "Creating skill directory...\n";
        if(@mkdir($skillDirectory)) {
            echo "Creating assets directory...\n";
            $assetsDirectory = $skillDirectory.'assets'.$s;
            if(@mkdir($assetsDirectory)) {
                //file_put_contents($assetsDirectory.'.info', 'Put your intents schema here.');
                $customSlotTypesDirectory = $assetsDirectory.$s.'CustomSlotTypes';
                if(@mkdir($customSlotTypesDirectory)) {
                    file_put_contents($customSlotTypesDirectory.$s.'.info', 'Put your custom slot types here.');
                }
                $intentSchemaTemplate = $config['directories']['base'].'..'.$s.'templates'.$s.'IntentSchema.json';
                if(file_exists($intentSchemaTemplate) && is_readable($intentSchemaTemplate)) {
                    copy($intentSchemaTemplate, $assetsDirectory.'IntentSchema.json');
                }
                else {
                    file_put_contents($assetsDirectory.'IntentSchema.json', '');
                }
                file_put_contents($assetsDirectory.'SampleUtterances.txt', '');
            }
            echo "Creating content directory...\n";
            $contentDirectory = $skillDirectory.'content';
            if(@mkdir($contentDirectory)) {
                file_put_contents($contentDirectory.$s.'.info', 'Put your content here.');
            }
            echo "Creating private directory...\n";
            $privateDirectory = $skillDirectory.'private';
            if(@mkdir($privateDirectory)) {
                file_put_contents($privateDirectory.$s.'.info', 'Put your private files here.');
            }
            //SAVING CONFIGURATION FILE
            echo "Creating config file...\n";
            $config = array(
                'directories' => array(
                    'content' => $skillDirectory.'content',
                ),
                'skillHttpsUrl' => $url.'/'.$skillName,
                'allowedContentTypes' => 'jpg|jpeg|gif|mp3'
            );
            $replaces = array(
                rtrim(var_export($skillDirectory, true), '\'') => '__DIR__.\''.trim(var_export($s, true), '\'')
            );
            self::saveConfigurationFile($config, $skillDirectory.'/config.php', $replaces);
            if($url == 'localhost') {
                echo "\n!!!Please, change 'skillHttpsUrl' to real URL to your skill.\n\n";
            }
            //ADDING GIT FILES
            $yn = self::readline('Do you want to create git files? [y/n, default y]: ');
            if(empty($yn) || in_array(strtolower($yn), array('y', 'yes'))) {
                echo "Creating gitignore file...\n";
                file_put_contents($skillDirectory.'/.gitignore', "/private/*\n!/private/.info");
                echo "Creating readme file...\n";
                file_put_contents($skillDirectory.'/README.md', "# ".strtolower($skillName)."_skill".ucfirst($skillName)." skill");
            }
            //GENERATING INTENTS
            $yn = self::readline('Do you want to generate intents? [y/n, default y]: ');
            if(empty($yn) || in_array(strtolower($yn), array('y', 'yes'))) {
                self::generateIntents($event);
            }
        }
    }
    
    public static function generateIntents(Event $event) {
        $arguments = $event->getArguments();
        
        $s = ((PHP_OS == 'WINNT')? '\\': '/');
        
        $skillName = '';
        if(count($arguments) == 0) {
            $skillName = self::readline('Please, enter skill name: ');
        }
        else {
            $skillName = $arguments[0];
        }
        
        $generateLaunchIntent = true;
        $generateEndSessionIntent = true;
        $generateIntentsWithSchema = true;
        
        $config = self::getConfig();
        $templatesDirectory = $config['directories']['base'].'..'.$s.'templates'.$s;
        if(!self::assert((file_exists($templatesDirectory) && is_dir($templatesDirectory)), "Templates directory({$templatesDirectory}) was not found. Please try again, run composer generate-intents {$skillName}.\n")) {
            return;
        }
        
        $skillsDirectory = $config['directories']['skills'];
        
        if(self::assert((file_exists($skillsDirectory) && is_dir($skillsDirectory)), "Skills directory was not found.\n")) {
            $skillDirectory = $skillsDirectory.$skillName.$s;
            if(self::assert((file_exists($skillDirectory) && is_dir($skillDirectory)), "Skill '{$skillName}' was not found.\n")) {
                if(!self::assert(is_writable($skillDirectory), "Skill directory is not writable.\n")) {
                    return;
                }
                if($generateLaunchIntent) {//launch
                    $launchIntentFilePath = $templatesDirectory.'Launch.tpl.php';
                    $_launchIntentFilePath = $skillDirectory.'Launch.php';
                    if(!file_exists($_launchIntentFilePath)) {
                        echo "Creating launch intent...\n";
                        $intentFileContents = file_get_contents($launchIntentFilePath);
                        $intentFileContents = str_replace('SKILL_NAMESPACE', $skillName, $intentFileContents);
                        file_put_contents($_launchIntentFilePath, $intentFileContents);
                    }
                }
                if($generateEndSessionIntent) {//session end
                    $endSessionFilePath = $templatesDirectory.'EndSession.tpl.php';
                    $_endSessionFilePath = $skillDirectory.'EndSession.php';
                    if(!file_exists($_endSessionFilePath)) {
                        echo "Creating session end intent...\n";
                        $intentFileContents = file_get_contents($endSessionFilePath);
                        $intentFileContents = str_replace('SKILL_NAMESPACE', $skillName, $intentFileContents);
                        file_put_contents($_endSessionFilePath, $intentFileContents);
                    }
                }
                if($generateIntentsWithSchema) {
                    $intentsSchemaFilePath = $skillDirectory.'assets/IntentSchema.json';
                    if(file_exists($intentsSchemaFilePath) && is_readable($intentsSchemaFilePath)) {
                        $intents = file_get_contents($intentsSchemaFilePath);
                        if(!self::assert(!empty($intents), "Skill '{$skillName}' has no intents schema available.\n")) {
                            return;
                        }
                        $intents = json_decode($intents, true);
                        if(!self::assert((is_array($intents) && array_key_exists('intents', $intents)), "Skill '{$skillName}' has no intents schema available.\n")) {
                            return;
                        }

                        echo "Creating intents...\n";
                        $intentTemplate = file_get_contents($templatesDirectory.'Intent.tpl.php');
                        foreach($intents['intents'] as $intent) {
                            $intentName = $intent['intent'];
                            if(strpos($intentName, 'AMAZON.') === 0) {
                                $intentName = ucfirst(str_replace('AMAZON.', '', $intentName));
                            }
                            else {
                                $intentName = ucfirst($intentName).'Intent';
                            }
                            
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
                }
            }
        }
    }

    public static function postInstall(Event $event) {
        self::configure($event);
    }

    public static function postUpdate(Event $event) {
        self::configure($event);
    }
}