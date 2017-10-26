<?php
/**
 * Created by Aleksandr Berdnikov.
 * Copyright 2016 Onix-Systems.
*/

namespace AlexaPHPSDK;

require './functions.php';

//require './base/config/autoload.php';
require './vendor/autoload.php';
$mainConfig = require('./base/config/main.php');

$router = new Router($mainConfig);

$path = trim(filter_input(INPUT_GET, 'path'), '/');
if(empty($path)) {
  $path = $_SERVER['REQUEST_URI'];
}

$router->route($path);