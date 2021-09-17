# Transport

Transports are the foundations behind the storage of tasks: once tasks are defined in the configuration
or scheduled via the [Scheduler](../src/Scheduler.php), every task is stored via transports.

Think of transports like the transports used by the following components in Symfony:

- [Mailer](https://symfony.com/doc/current/mailer.html#transport-setup)
- [Messenger](https://symfony.com/doc/current/messenger.html#transport-configuration)
- [Notifier](https://symfony.com/doc/current/notifier.html)

This bundle defines a set of transports, each transport has its own configuration and can be overridden if required.

## List of available transports

- [InMemory](#inmemory)
- [Filesystem](#filesystem)
- [Cache](#cache)
- [FailOver](#failover)
- [RoundRobin](#roundrobin)
- [Lazy](#lazy)
- [Redis](#redis)
- [Doctrine](#doctrine)

## Informations

Once created, the transport is injected into the `Scheduler`, 
you will probably never need to interact with it without using the Scheduler, otherwise, 
the transport is injected using the [TransportInterface](../src/Transport/TransportInterface.php) 
and the `scheduler.transport` identifier.

```php
<?php

declare(strict_types=1);

use SchedulerBundle\Transport\TransportInterface;
use Symfony\Component\HttpFoundation\Request;

final class FooController
{
    public function __invoke(Request $request, TransportInterface $transport)
    {
        // ...
    }
}
```

_Note: Using the transport without the scheduler can lead to edge issues as the scheduler is synchronized with it._

## Configuration

Every transport has its own configuration keys (thanks to query parameters), here's the default keys:

- `execution_mode`: Define the schedule policy used by the transports (more info on [Policies](policies.md)).
- `path`: Define the path used by the [FilesystemTransport](../src/Transport/FilesystemTransport.php).

## InMemory

The [InMemoryTransport](../src/Transport/InMemoryTransport.php) stores every task in memory,
this transport is perfect for `test` environnement or/and for POC applications.

### Usage

```yaml
scheduler_bundle:
    transport:
        dsn: 'memory://first_in_first_out'
```

**Configuration**: This transport requires that you provide an `execution_mode` as the first parameter,
this value is used to sort every new task and improve performances/resources consumption.

## Filesystem

The [FilesystemTransport](../src/Transport/FilesystemTransport.php) stores every task in files,
the default path is `%kernel.project_dir%/var/tasks`.

### Usage

```yaml
scheduler_bundle:
    transport:
        dsn: 'filesystem://first_in_first_out' # OR 'fs://first_in_first_out' OR 'file://first_in_first_out'
```

**Configuration**:

- This transport requires that you provide an `execution_mode` as the first parameter,
this value is used to sort every new task and improve performances/resources consumption.
  
- The second and optional key is the `path` where every task is stored (using `json` format):

```yaml
scheduler_bundle:
    transport:
        dsn: 'filesystem://first_in_first_out?path=/srv/app'
```

- The third and optional key is the `filename_mask` which define the "mask" of every task file (default to `%s/_symfony_scheduler_/%s.json`):

```yaml
scheduler_bundle:
    transport:
        dsn: 'filesystem://first_in_first_out?filename_mask=%s/_foo/%s.json'
```

**Important:** This configuration key must use the `json` file extension, 
the mask is used in combination with the `path` configuration key to store every task.

_Note: Container parameters cannot be passed here as the container is not involved in the transport configuration._

_Note: Keep in mind that this directory could be versioned if required_

## Cache

_Introduced in `0.4`_

The [CacheTransport](../src/Transport/CacheTransport.php) stores every task in a PSR compliant
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
    transport:
        dsn: 'cache://app'
```

**Configuration**: This transport requires that you provide the name of the pool to use, the `execution_mode` option is allowed.

## FailOver

The [FailOverTransport](../src/Transport/FailOverTransport.php) allows to use multiple transports and "prevent" errors
when trying to schedule a task, this approach is also known as ["High-Availability"](https://en.wikipedia.org/wiki/High_availability),
it is particularly useful if you use a transport that can fail during an operation (like network-related, etc).

### Usage

```yaml
scheduler_bundle:
    transport:
        dsn: 'failover://(memory://first_in_first_out || memory://last_in_first_out)' # Or 'fo://(memory://first_in_first_out || memory://last_in_first_out)'
```

**configuration**: This transport requires at least 2 transports to be used (each one can be configured as usual).

## LongTail

The [LongTail](../src/Transport/LongTailTransport.php) allows to use multiple transports. It's specifically designed
to maximize the transport usage by always trying to use the transport with the lowest amount of tasks. 
This approach can help when you're scheduling tasks in a [high-stress environment](https://en.wikipedia.org/wiki/Long_tail).

### Usage

```yaml
scheduler_bundle:
    transport:
        dsn: 'longtail://(memory://first_in_first_out <> memory://last_in_first_out)' # Or 'lt://(memory://first_in_first_out <> memory://last_in_first_out)'
```

**configuration**: This transport requires at least 2 transports to be used (each one can be configured as usual).

## RoundRobin

The [RoundRobin](../src/Transport/RoundRobinTransport.php) allows using multiple transports,
the main advantages of this transport is to distribute the work load around multiple transports, 
think of it as a ["load-balancer"](https://en.wikipedia.org/wiki/Load_balancing_(computing)) transport.

### Usage

```yaml
scheduler_bundle:
    transport:
        dsn: 'roundrobin://(memory://first_in_first_out && memory://last_in_first_out)' # Or 'rr://(memory://first_in_first_out && memory://last_in_first_out)'
```

### Configuration:

- This transport requires at least 2 transports to be used (each one can be configured as usual).
- A `quantum` can be defined to determine the maximum duration (expressed in seconds) 
  within a transport MUST perform the action, the default value is `2`.

**Note**: By default, the execution duration limit is based around the formula: `count(transports) * quantum`.

## Lazy

The [Lazy](../src/Transport/LazyTransport.php) allows to perform actions using an asynchronous approach.

### Usage

```yaml
scheduler_bundle:
    transport:
        dsn: 'lazy://(memory://batch)'
```

## Redis

The [RedisTransport](../src/Bridge/Redis/Transport/RedisTransport.php) allows to use Redis 
as tasks storage, this transport is useful if you need to share tasks between multiple projects/instances.

**Requirements**: The `Redis` transport requires at least redis >= 4.3.

### Usage

```yaml
scheduler_bundle:
    transport:
        dsn: 'redis://user:password@host:port'
```

**configuration**: This transport requires multiple options:

- The `user` of the Redis instance
- The related `password`
- The `host` of the Redis instance
- The optional `port` of the Redis instance

### Extra configuration

This transport can be configured using multiple options:

- `execution_mode`: Relates to default configuration keys.
- `list`: Define the name of the list that store tasks.

```yaml
scheduler_bundle:
    transport:
        dsn: 'redis://user:password@127.0.0.1:6543?execution_mode=first_in_first_out&list=foo'
```

## Doctrine

The [DoctrineTransport](../src/Bridge/Doctrine/Transport/DoctrineTransport.php) allows to use Doctrine connections
as tasks storage.
This transport is useful if you need to share tasks between multiple projects/instances.

### Usage

```yaml
scheduler_bundle:
    transport:
        dsn: 'doctrine://default?execution_mode=first_in_first_out' # Or 'dbal://default?execution_mode=first_in_first_out'
```

**Configuration**: This transports requires the following configuration keys:

- The `connection` name as the "host"
- The optional `table_name` where the tasks must be stored (default to `_symfony_scheduler_tasks`)
- The optional `auto_setup` to define if the connection should configure the table if it does not exist

### Extra configuration

This transport can configure multiple options:

- `execution_mode`: Relates to default configuration keys.

```yaml
scheduler_bundle:
    transport:
        dsn: 'doctrine://default?execution_mode=first_in_first_out&table_name=foo&auto_setup=false'
```

### Extra integration

The Doctrine bride integrate deeply with the [Doctrine bundle](https://packagist.org/packages/doctrine/doctrine-bundle),
the first integration is done via the connection which is retrieved via the `doctrine` service,
the second integration is done via [DoctrineMigrationsBundle](https://packagist.org/packages/doctrine/doctrine-migrations-bundle),
thanks to this one and when using `bin/console doctrine:migrations:diff`, the schema related to the task list can be generated.

_**Note**_: An important thing to keep in mind is that if you don't use the DoctrineMigrationsBundle bundle 
and the `auto_setup` option is set to `true`, the schema is generated when scheduling the tasks.
