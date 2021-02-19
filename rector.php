<?php

declare(strict_types=1);

use Rector\Core\Configuration\Option;
use Rector\Core\ValueObject\PhpVersion;
use Rector\Php71\Rector\FuncCall\CountOnNullRector;
use Rector\Set\ValueObject\SetList;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $parameters = $containerConfigurator->parameters();

    $parameters->set(Option::PHP_VERSION_FEATURES, PhpVersion::PHP_74);
    $parameters->set(Option::AUTO_IMPORT_NAMES, true);
    $parameters->set(Option::IMPORT_SHORT_CLASSES, true);
    $parameters->set(Option::IMPORT_DOC_BLOCKS, true);

    $parameters->set(Option::AUTOLOAD_PATHS, [
        __DIR__ . '/vendor/autoload.php',
    ]);

    $parameters->set(Option::PATHS, [
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ]);

    $parameters->set(Option::SKIP, [
        __DIR__ . '/src/Serializer',
        __DIR__ . '/vendor',
        CountOnNullRector::class
    ]);

    $parameters->set(Option::SETS, [
        SetList::CODE_QUALITY,
        SetList::DEAD_CODE,
        SetList::EARLY_RETURN,
        SetList::PHP_70,
        SetList::PHP_71,
        SetList::PHP_72,
        SetList::PHP_73,
        SetList::PHP_74,
        SetList::SYMFONY_50,
        SetList::SYMFONY_52,
    ]);

    $parameters->set(Option::ENABLE_CACHE, true);
};
