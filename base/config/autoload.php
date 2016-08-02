<?php
/**
 * Created by Aleksandr Berdnikov.
 * Copyright 2016 Onix-Systems.
*/
namespace AlexaPHPSDK;

spl_autoload_register(function($class) {
    $prefix = __NAMESPACE__;
    
    $baseDirectory = __DIR__.'/..';

    $length = strlen($prefix);
    if(strncmp($prefix, $class, $length) !== 0) {
        return;
    }

    $realativeClass = substr($class, $length);
    $filePath = $baseDirectory.str_replace('\\', '/', $realativeClass).'.php';
    if (file_exists($filePath)) {
        require $filePath;
    }
});

