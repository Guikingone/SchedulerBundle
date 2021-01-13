<?php

declare(strict_types=1);

use Rector\Core\Configuration\Option;
use Rector\Set\ValueObject\SetList;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $parameters = $containerConfigurator->parameters();

    $parameters->set(Option::PHP_VERSION_FEATURES, '7.2');

    $parameters->set(Option::AUTOLOAD_PATHS, [
        __DIR__ . '/vendor/autoload.php',
    ]);

    $parameters->set(Option::PATHS, [
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ]);

    $parameters->set(Option::EXCLUDE_PATHS, [
        __DIR__ . '/vendor',
    ]);

    $parameters->set(Option::SETS, [
        SetList::DEAD_CODE,
        SetList::PERFORMANCE,
        SetList::PHP_70,
        SetList::PHP_71,
        SetList::PHP_72,
    ]);

    $parameters->set(Option::ENABLE_CACHE, true);
};
