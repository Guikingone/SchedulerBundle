# Transport

This bundle provides multiple transports to store and handle tasks.

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

## Filesystem

The [FilesystemTransport](../src/Transport/FilesystemTransport.php) stores every task in files,
the default path is `%kernel.project_dir%/var/tasks`.

### Usage

```yaml
scheduler_bundle:
    transport:
        dsn: 'filesystem://first_in_first_out' # OR 'fs://first_in_first_out' OR 'file://first_in_first_out'
```

### Extra configuration

This transport can configure the following keys:

- `path`: Define the path where tasks are stored.
- `filename_mask`: The filename mask is used to define the stored file name (default to `%s/_symfony_scheduler_/%s.json`).

```yaml
scheduler_bundle:
    transport:
        dsn: 'memory://first_in_first_out?filename_mask=%s/_foo_scheduler/%s.json'
        options:
            path: '%kernel.project_dir%/_foo'
```

_Note: Keep in mind that this directory could be versioned if required_

## Failover

The [FailoverTransport](../src/Transport/FailoverTransport.php) allows to use multiple transports,
this transport is useful if you use a transport that can fail during an operation (like network-related, etc).

### Usage

```yaml
scheduler_bundle:
    transport:
        dsn: 'failover://(memory://first_in_first_out || memory://last_in_first_out)' # Or 'fo://(memory://first_in_first_out || memory://last_in_first_out)'
```

## LongTail

The [LongTail](../src/Transport/LongTailTransport.php) allows to use multiple transports,
the main idea behind is to use the transport which contains fewer tasks than the other(s):

### Usage

```yaml
scheduler_bundle:
    transport:
        dsn: 'longtail://(memory://first_in_first_out <> memory://last_in_first_out)' # Or 'lt://(memory://first_in_first_out <> memory://last_in_first_out)'
```

## RoundRobin

The [RoundRobin](../src/Transport/RoundRobinTransport.php) allows to use multiple transports,
this transport is useful if you use a transport that can fail during an operation (like network-related, etc).

### Usage

```yaml
scheduler_bundle:
    transport:
        dsn: 'roundrobin://(memory://first_in_first_out && memory://last_in_first_out)' # Or 'rr://(memory://first_in_first_out && memory://last_in_first_out)'
```

## Redis

The [RedisTransport](../src/Bridge/Redis/Transport/RedisTransport.php) allows to use Redis as tasks storage.
This transport is useful if you need to share tasks between multiple projects/instances.

### Usage

```yaml
scheduler_bundle:
    transport:
        dsn: 'redis://first_in_first_out'
```

### Extra configuration

This transport can configure multiple options:

- `execution_mode`: Relates to default configuration keys.
- `list`: Define the name of the list that store tasks.

```yaml
scheduler_bundle:
    transport:
        dsn: 'redis://default?execution_mode=first_in_first_out&table_name=foo&auto_setup=false'
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

### Extra configuration

This transport can configure multiple options:

- `execution_mode`: Relates to default configuration keys.
- `auto_setup`:
- `table_name`: Define the name of the table used to store tasks.

```yaml
scheduler_bundle:
    transport:
        dsn: 'doctrine://default?execution_mode=first_in_first_out&table_name=foo&auto_setup=false'
```
