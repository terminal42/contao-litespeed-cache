<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Terminal42\ContaoLiteSpeedCache\Controller\InvalidateController;
use Terminal42\ContaoLiteSpeedCache\Cron\InvalidateCron;
use Terminal42\ContaoLiteSpeedCache\LiteSpeedProxy;
use Terminal42\ContaoLiteSpeedCache\ProxyHandler;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();
    $services->defaults()->autoconfigure();

    $services->set(ProxyHandler::class)
        ->args([
            service('database_connection'),
            service('http_client'),
            service('contao.framework'),
            service('router'),
            service('contao.routing.content_url_generator'),
            service('uri_signer'),
            service('monolog.logger.contao.error'),
        ])
    ;

    $services->set(LiteSpeedProxy::class)
        ->args([
            service(ProxyHandler::class),
        ])
    ;

    $services->set(InvalidateCron::class)
        ->args([
            service(ProxyHandler::class),
        ])
    ;

    $services->set(InvalidateController::class)
        ->args([
            service(ProxyHandler::class),
        ])
        ->public()
    ;
};
