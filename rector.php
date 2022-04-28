<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Core\ValueObject\PhpVersion;
use Rector\Doctrine\Set\DoctrineSetList;
use Rector\PHPUnit\Set\PHPUnitSetList;
use Rector\Set\ValueObject\SetList;
use Rector\Symfony\Set\SymfonySetList;

return static function (RectorConfig $config): void {
    $config->phpVersion(phpVersion: PhpVersion::PHP_80);
    $config->importShortClasses();
    $config->parallel();
    $config->importNames();

    $config->autoloadPaths(autoloadPaths: [
        __DIR__ . '/vendor/autoload.php',
    ]);

    $config->paths(paths: [
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ]);

    '8.1' !== PHP_VERSION ? $config->skip(criteria: [
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
    ]) : $config->skip(criteria: [
        __DIR__ . '/vendor',
        __DIR__ . '/src/DependencyInjection/SchedulerBundleExtension.php',
        __DIR__ . '/tests/Serializer/TaskNormalizerTest.php',
    ]);

    $config->rule(rectorClass: DoctrineSetList::DOCTRINE_25);
    $config->rule(rectorClass: DoctrineSetList::DOCTRINE_DBAL_211);
    $config->rule(rectorClass: DoctrineSetList::DOCTRINE_DBAL_30);

    $config->rule(rectorClass: PHPUnitSetList::PHPUNIT_91);
    $config->rule(rectorClass: PHPUnitSetList::PHPUNIT_EXCEPTION);
    $config->rule(rectorClass: PHPUnitSetList::PHPUNIT_MOCK);
    $config->rule(rectorClass: PHPUnitSetList::PHPUNIT_SPECIFIC_METHOD);
    $config->rule(rectorClass: PHPUnitSetList::PHPUNIT_YIELD_DATA_PROVIDER);

    $config->rule(rectorClass: SetList::CODE_QUALITY);
    $config->rule(rectorClass: SetList::DEAD_CODE);
    $config->rule(rectorClass: SetList::EARLY_RETURN);
    $config->rule(rectorClass: SetList::PHP_70);
    $config->rule(rectorClass: SetList::PHP_71);
    $config->rule(rectorClass: SetList::PHP_72);
    $config->rule(rectorClass: SetList::PHP_72);
    $config->rule(rectorClass: SetList::PHP_73);
    $config->rule(rectorClass: SetList::PHP_74);
    $config->rule(rectorClass: SetList::UNWRAP_COMPAT);

    $config->rule(rectorClass: SymfonySetList::SYMFONY_50);
    $config->rule(rectorClass: SymfonySetList::SYMFONY_51);
    $config->rule(rectorClass: SymfonySetList::SYMFONY_52);
    $config->rule(rectorClass: SymfonySetList::SYMFONY_53);
    $config->rule(rectorClass: SymfonySetList::SYMFONY_54);
    $config->rule(rectorClass: SymfonySetList::SYMFONY_50_TYPES);
    $config->rule(rectorClass: SymfonySetList::SYMFONY_CONSTRUCTOR_INJECTION);

    $config->phpstanConfig(filePath: __DIR__.'/phpstan.neon.8.0.dist');
};
