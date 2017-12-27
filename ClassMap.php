<?php



return [
    'namespaces' => [
        'FastRoute'=>__DIR__.DS.'Plugins'.DS.'nikic'.DS.'FastRoute'.DS.'src',
        'Psr\Log'=>__DIR__.DS.'Plugins'.DS.'php-fig'.DS.'log'.DS.'Psr'.DS.'Log',
        'Fig\Http\Message'=>__DIR__.DS.'Plugins'.DS.'php-fig'.DS.'http-message-util'.DS.'src',
        'Psr\Http\Message'=>__DIR__.DS.'Plugins'.DS.'php-fig'.DS.'http-message'.DS.'src',
        'Fig\Link'=>__DIR__.DS.'Plugins'.DS.'php-fig'.DS.'link-util'.DS.'src',
        'Psr\Container'=>__DIR__.DS.'Plugins'.DS.'php-fig'.DS.'container'.DS.'src',
        'Fig\Cache'=>__DIR__.DS.'Plugins'.DS.'php-fig'.DS.'cache-util'.DS.'src',
        'Psr\Link'=>__DIR__.DS.'Plugins'.DS.'php-fig'.DS.'link'.DS.'src',
        'Psr\Cache'=>__DIR__.DS.'Plugins'.DS.'php-fig'.DS.'cache'.DS.'src',
        'Monolog'=>__DIR__.DS.'Plugins'.DS.'Seldaek'.DS.'monolog'.DS.'src'.DS.'Monolog',
        'Noodlehaus'=>__DIR__.DS.'Plugins'.DS.'hassankhan'.DS.'config'.DS.'src',
        'MongoDB'=>__DIR__.DS.'Plugins'.DS.'mongodb'.DS.'mongo-php-library'.DS.'src',
        'Curl'=>__DIR__.DS.'Plugins'.DS.'php-curl-class'.DS.'php-curl-class'.DS.'src'.DS.'Curl',
        'GuzzleHttp\Ring'=>__DIR__.DS.'Plugins'.DS.'guzzle'.DS.'RingPHP'.DS.'src',
        'GuzzleHttp\Stream'=>__DIR__.DS.'Plugins'.DS.'guzzle'.DS.'streams'.DS.'src',
        'React\Promise'=>__DIR__.DS.'Plugins'.DS.'reactphp'.DS.'promise'.DS.'src',
        'Elasticsearch'=>__DIR__.DS.'Plugins'.DS.'elastic'.DS.'elasticsearch-php'.DS.'src'.DS.'Elasticsearch',
    ],
    'files' => [
        __DIR__.DS.'Plugins'.DS.'nikic'.DS.'FastRoute'.DS.'src'.DS.'functions.php',
        __DIR__.DS.'Plugins'.DS.'mongodb'.DS.'mongo-php-library'.DS.'src'.DS.'functions.php',
        __DIR__.DS.'Plugins'.DS.'reactphp'.DS.'promise'.DS.'src'.DS.'functions_include.php',
    ],
];
