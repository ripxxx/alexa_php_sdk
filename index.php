<?php

require './base/config/autoload.php';
$mainConfig = require('./base/config/main.php');

$router = new Router($mainConfig);

$path = $_SERVER['REQUEST_URI']; 
// $path = filter_input(INPUT_GET, 'path') ;

$router->route($path);