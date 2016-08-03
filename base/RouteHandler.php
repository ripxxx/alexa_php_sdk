<?php
/**
 * Created by Aleksandr Berdnikov.
 * Copyright 2016 Onix-Systems.
*/

namespace AlexaPHPSDK;

class RouteHandler {
    protected $config;
    protected $router;


    public function __construct(array $config, Router $router) {
        $this->config = $config;
        $this->router = $router;
    }
    
    public function run(array $params, array $skillParams) {
        $this->router->notFound();
        
        return false;
    }
}

