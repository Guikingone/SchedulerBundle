<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Core\ValueObject\PhpVersion;
use Rector\Doctrine\Set\DoctrineSetList;
use Rector\PHPUnit\Set\PHPUnitSetList;
use Rector\Set\ValueObject\SetList;
use Rector\Symfony\Set\SymfonySetList;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->phpVersion(phpVersion: PhpVersion::PHP_80);
    $rectorConfig->importNames();
    $rectorConfig->importShortClasses();
    $rectorConfig->parallel(seconds: 600, maxNumberOfProcess: 32);

    $rectorConfig->autoloadPaths(autoloadPaths: [
        __DIR__ . '/vendor/autoload.php',
    ]);

    $rectorConfig->paths(paths: [
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ]);

    $rectorConfig->skip(criteria: [
        __DIR__ . '/vendor',
        __DIR__ . '/src/DependencyInjection/SchedulerBundleExtension.php',
        __DIR__ . '/tests/Serializer/TaskNormalizerTest.php',
    ]);

    $rectorConfig->sets(sets: [
        DoctrineSetList::DOCTRINE_25,
        DoctrineSetList::DOCTRINE_DBAL_211,
        DoctrineSetList::DOCTRINE_DBAL_30,
    ]);

    $rectorConfig->sets(sets: [
        PHPUnitSetList::PHPUNIT_91,
        PHPUnitSetList::PHPUNIT_EXCEPTION,
        PHPUnitSetList::REMOVE_MOCKS,
        PHPUnitSetList::PHPUNIT_SPECIFIC_METHOD,
        PHPUnitSetList::PHPUNIT_YIELD_DATA_PROVIDER,
    ]);

    $rectorConfig->sets(sets: [
        SetList::CODE_QUALITY,
        SetList::DEAD_CODE,
        SetList::EARLY_RETURN,
        SetList::PHP_70,
        SetList::PHP_71,
        SetList::PHP_72,
        SetList::PHP_73,
        SetList::PHP_74,
        SetList::PHP_80,
        SetList::PSR_4,
    ]);

    $rectorConfig->sets(sets: [
        SymfonySetList::SYMFONY_50,
        SymfonySetList::SYMFONY_50_TYPES,
        SymfonySetList::SYMFONY_51,
        SymfonySetList::SYMFONY_52,
        SymfonySetList::SYMFONY_CONSTRUCTOR_INJECTION,
        SymfonySetList::SYMFONY_52_VALIDATOR_ATTRIBUTES,
        SymfonySetList::SYMFONY_53,
        SymfonySetList::SYMFONY_54,
    ]);

    '8.1' !== PHP_VERSION
        ? $rectorConfig->phpstanConfig(filePath: __DIR__ . '/phpstan.neon.8.0.dist')
        : $rectorConfig->phpstanConfig(filePath: __DIR__ . '/phpstan.neon.8.1.dist')
    ;
};
