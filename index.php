<?php
require './base/config/autoload.php';
$mainConfig = require('./base/config/main.php');

$router = new Router($mainConfig);

$path = filter_input(INPUT_GET, 'path');

$router->route($path);