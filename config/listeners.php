<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Terminal42\ContaoLiteSpeedCache\EventListener\DoctrineSchemaListener;
use Terminal42\ContaoLiteSpeedCache\EventListener\ResponseListener;
use Terminal42\ContaoLiteSpeedCache\ProxyHandler;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();
    $services->defaults()->autoconfigure();

    $services->set(DoctrineSchemaListener::class)
        ->tag('doctrine.event_listener', ['event' => 'postGenerateSchema'])
    ;

    $services->set(ResponseListener::class)
        ->arg('$proxyHandler', service(ProxyHandler::class))
    ;
};
