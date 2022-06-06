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

    $rectorConfig->autoloadPaths(autoloadPaths: [
        __DIR__ . '/vendor/autoload.php',
    ]);

    $rectorConfig->paths(paths: [
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ]);

    '8.1' !== PHP_VERSION ? $rectorConfig->skip(criteria: [
        __DIR__ . '/vendor',
        __DIR__ . '/src/DependencyInjection/SchedulerBundleExtension.php',
        __DIR__ . '/src/Fiber/AbstractFiberHandler.php',
        __DIR__ . '/src/Middleware/FiberAwareWorkerMiddlewareStack.php',
        __DIR__ . '/src/Transport/Configuration/FiberConfiguration.php',
        __DIR__ . '/src/Transport/FiberTransport.php',
        __DIR__ . '/src/Worker/ExecutionPolicy/FiberPolicy.php',
        __DIR__ . '/src/Worker/FiberWorker.php',
        __DIR__ . '/src/FiberScheduler.php',
        __DIR__ . '/tests/Serializer/TaskNormalizerTest.php',
        __DIR__ . '/tests/FiberSchedulerTest.php',
        __DIR__ . '/tests/Middleware/FiberAwareWorkerMiddlewareStackTest.php',
        __DIR__ . '/tests/Transport/Configuration/FiberConfigurationTest.php',
        __DIR__ . '/tests/Transport/FiberTransportTest.php',
        __DIR__ . '/tests/Worker/ExecutionPolicy/FiberPolicyTest.php',
        __DIR__ . '/tests/Worker/FiberWorkerTest.php',
    ]) : $rectorConfig->skip(criteria: [
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
        SetList::UNWRAP_COMPAT,
    ]);

    $rectorConfig->sets(sets: [
        SymfonySetList::SYMFONY_50,
        SymfonySetList::SYMFONY_50_TYPES,
        SymfonySetList::SYMFONY_52,
        SymfonySetList::SYMFONY_CONSTRUCTOR_INJECTION,
    ]);

    '8.1' !== PHP_VERSION
        ? $rectorConfig->phpstanConfig(filePath: __DIR__ . '/phpstan.neon.8.0.dist')
        : $rectorConfig->phpstanConfig(filePath: __DIR__ . '/phpstan.neon.8.1.dist')
    ;
};
