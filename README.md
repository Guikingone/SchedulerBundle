# Scheduler Bundle

A Symfony bundle built to schedule/consume tasks.

## Main features

- External transports (Doctrine, Redis, etc)
- Retry / Remove tasks if failed
- Can wait until tasks are dues
- Messenger integration

## Installation

Make sure Composer is installed globally, as explained in the
[installation chapter](https://getcomposer.org/doc/00-intro.md)
of the Composer documentation.

```bash
$ composer require guikingone/scheduler-bundle
```

## Quick start

Once installed, time to update the `config/bundles.php`:

```php
// config/bundles.php

return [
    // ...
    SchedulerBundle\SchedulerBundle::class => ['all' => true],
];
```

Once done, just add a `config/packages/scheduler.yaml`:

```yaml
# config/packages/scheduler.yaml
scheduler_bundle:
    transport:
        dsn: 'filesystem://first_in_first_out'
```

Once transport is configured, time to create a simple task:

```yaml
# config/packages/scheduler.yaml
scheduler_bundle:
    transport:
        dsn: 'memory://first_in_first_out'
    tasks:
        foo:
            type: 'command'
            command: 'cache:clear'
            expression: '*/5 * * * *'
            description: 'A simple cache clear task'
            options:
              env: test
```

Once a task is configured, time to execute it, two approaches can be used:

- Adding a cron entry `* * * * * cd /path-to-your-project && php bin/console scheduler:consume >> /dev/null 2>&1`
- Launching the command `scheduler:consume --wait` in a background command

## Documentation

For a full breakdown of each feature, please head to the [documentation](doc)
