# Transport configuration

- [Introduction](#introduction)
- [Informations](#informations)
- [Extending the transport configuration](#extending-the-transport-configuration)

## Introduction

As explained [here](transport.md), transports are the foundations behind the storage of tasks but 
what about the configuration of each transport?

Since the beginning of the project, the configuration of the transports has been stored in memory via attributes and/or arrays.
Problem is, this approach can trigger errors and impact performances.

Since `0.9`, transports configuration are now stored via dedicated storages.

This bundle defines a set of configuration storage:

- [InMemory](#inmemory)
- [Cache](#cache)
- [FailOver](#failover)
- [LongTail](#longtail) // TODO
- [Lazy](#lazy)
- [Fiber](#fiber)
- [Redis](#redis) // TODO
- [Doctrine](#doctrine) // TODO

## Informations

Once created, the transport is injected into the current transport,
you will probably never need to interact with it without using the transport, otherwise,
the transport is injected using the [ConfigurationInterface](../src/Transport/Configuration/ConfigurationInterface.php)
and the `scheduler.configuration` identifier.

```php
<?php

declare(strict_types=1);

use SchedulerBundle\Transport\Configuration\ConfigurationInterface;
use Symfony\Component\HttpFoundation\Request;

final class FooController
{
    public function __invoke(Request $request, ConfigurationInterface $configuration)
    {
        // ...
    }
}
```

_**Note**: Using the transport without the scheduler can lead to edge issues as the transport is synchronized with it._

## InMemory

The [InMemoryConfiguration](../src/Transport/Configuration/InMemoryConfiguration.php) stores every key / value in memory,
this configuration is perfect for `test` environnement or/and for POC applications.

### Usage

```yaml
scheduler_bundle:
    # ...

    configuration:
        dsn: 'configuration://memory' # Or 'configuration://array'
```

**Note**: This configuration is the default one if you don't define the `configuration.dsn` key.

## Cache

The [CacheConfiguration](../src/Transport/Configuration/CacheConfiguration.php) stores every task in a PSR compliant
`CacheItemPoolInterface`, every cache adapter that implement this interface can be used.

By default, this bundle use the ones defined in the `cache` configuration key of the `framework`:

```yaml
framework:
    cache:
        app: cache.adapter.filesystem
```

### Usage

```yaml
scheduler_bundle:
    # ...

    configuration:
        dsn: 'configuration://cache'
```

## FailOver

The [FailOverConfiguration](../src/Transport/Configuration/FailOverConfiguration.php) allows to use multiple configuration and "prevent" errors
when trying to access / set keys, this approach is also known as ["High-Availability"](https://en.wikipedia.org/wiki/High_availability),
it is particularly useful if you use a configuration that can fail during an operation (like network-related, etc).

### Usage

```yaml
scheduler_bundle:
    # ...

    configuration:
        dsn: 'configuration://failover(memory://first_in_first_out || memory://last_in_first_out)' # Or 'configuration://fo(memory://first_in_first_out || memory://last_in_first_out)'
```

**configuration**: This configuration requires at least 2 configuration to be used (each one can be configured as usual).

## Lazy

The [LazyConfiguration](../src/Transport/Configuration/LazyConfiguration.php) act as a wrapper around a configuration,
each action is performed in a lazy way, keep in mind that it can trigger some edge cases depending on the current environment.

### Usage

```yaml
scheduler_bundle:
    # ...

    configuration:
        dsn: 'configuration://lazy(configuration://memory)'
```

## Fiber

_Requires PHP `>=8.1`_

The [FiberConfiguration](../src/Transport/Configuration/FiberConfiguration.php) act as a wrapper around a configuration,
it uses [Fibers](https://www.php.net/manual/en/language.fibers.php) in a way that each action is performed using a separated fiber.

### Usage

```yaml
scheduler_bundle:
    # ...

    configuration:
        dsn: 'configuration://fiber(configuration://memory)'
```

## Extending the transport configuration

If desired, you can create your own configuration storage thanks to [ConfigurationInterface](../src/Transport/Configuration/ConfigurationInterface.php),
it requires that you create your own configuration factory via [ConfigurationFactoryInterface](../src/Transport/Configuration/ConfigurationFactoryInterface.php).

Once created, both classes will be autoconfigured and autowired into the related classes.
