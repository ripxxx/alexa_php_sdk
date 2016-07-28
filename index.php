<?php

require './base/config/autoload.php';
$mainConfig = require('./base/config/main.php');

$router = new Router($mainConfig);

$path = filter_input(INPUT_GET, 'path') ;
if(empty($path)) {
  $path = $_SERVER['REQUEST_URI'];  
}

$router->route($path);