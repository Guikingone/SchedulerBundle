![SchedulerBundleHero](assets/SchedulerBundle.png)

<div align="center">
    <img src="https://github.com/Guikingone/SchedulerBundle/workflows/SchedulerBundle%20-%20Daily/badge.svg" alt="CI" />
    <img src="https://insight.symfony.com/projects/825be328-29f8-44f7-a750-f82818ae9111/mini.svg"  alt="hero"/>
</div>

<h4 align="center">A Symfony bundle built to schedule/consume repetitive tasks</h4>

## Main features

- External transports (Doctrine, Redis, etc)
- External configuration storage (Doctrine, Redis, etc)
- Retry / Remove / Sort policies
- Background worker
- [Symfony/Messenger](https://symfony.com/doc/current/messenger.html) integration

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
        dsn: 'filesystem://first_in_first_out'
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
