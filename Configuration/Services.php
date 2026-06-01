<?php

declare(strict_types=1);

use AdGrafik\FalFtp\FTPClient\Filter\FilterInterface;
use AdGrafik\FalFtp\FTPClient\FilterRegistry;
use AdGrafik\FalFtp\FTPClient\FTP;
use AdGrafik\FalFtp\FTPClient\Parser\ParserInterface;
use AdGrafik\FalFtp\FTPClient\ParserRegistry;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

return static function (ContainerConfigurator $containerConfigurator, ContainerBuilder $containerBuilder): void {
    $services = $containerConfigurator->services();
    $services->defaults()
        ->private()
        ->autowire()
        ->autoconfigure()
    ;

    $services->instanceof(FilterInterface::class)
        ->tag('falftp.filter')
    ;

    $services->instanceof(ParserInterface::class)
        ->tag('falftp.parser')
    ;

    $services->load('AdGrafik\\FalFtp\\', __DIR__ . '/../Classes/')->exclude([
        __DIR__ . '/../Classes/Driver',
        __DIR__ . '/../Classes/FTPClient/Exception',
        __DIR__ . '/../Classes/FTPClient/AbstractFTP.php',
        __DIR__ . '/../Classes/FTPClient/Exception.php',
    ]);

    $services->set(FTP::class)->public();

    $services->set(FilterRegistry::class)
        ->arg('$filters', tagged_iterator('falftp.filter', defaultPriorityMethod: 'getPriority'))
    ;

    $services->set(ParserRegistry::class)
        ->arg('$parsers', tagged_iterator('falftp.parser', defaultPriorityMethod: 'getPriority'))
    ;
};
