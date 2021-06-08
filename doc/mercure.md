# Mercure

This bundle comes with a fully integrated [Mercure](https://www.mercure.rocks) support, 
this mean that you can listen and trigger actions depending on real-time update 
sent by the scheduler/worker/etc.

- [Introduction](#introduction)
- [Configuration](#configuration)
- [List](#list)

## Introduction

Listening to tasks / worker events can be hard and sometimes, you face the situation
where you need to receive information about these events outside the application
or even in a separate application.

Thanks to [Mercure](https://www.mercure.rocks), this bundle provides a complete lifecycle handling for
tasks and worker events.

_**To prevent any errors or issues if you use the [Symfony/MercureBundle](https://packagist.org/packages/symfony/mercure-bundle),
the [Hub](https://github.com/symfony/mercure/blob/main/src/Hub.php) is configured and registered via a dedicated approach.**_

## Configuration

```yaml
scheduler_bundle:
    mercure:
        enabled: true
        hub_url: 'https://www.foo.com/.well-know/mercure'
        update_url: 'https://www.bar.com/scheduler'
        jwt_token: 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJtZXJjdXUYIjp7InB1Ymxpc2giOlsiKiJdfX0.obDjwCgqtPuIvwBlTxUEmibbBf0zypKCNzNKP7Op4UT' # Invalid token, to change

# ...
```

## List

Here's the full list of update dispatched:

### Task

| Task                       | Body                                                   |
| ---------------------------| -------------------------------------------------------|
| `task.scheduled`           | Contains the task body as json                         |
| `task.unscheduled`         | Contains the name of the task                          |
| `task.executed`            | Contains the task body and the output                  |
| `task.failed`              | Contains the task that failed, the reason and the date |

### Worker

| Task                       | Body                                                   |
| ---------------------------| -------------------------------------------------------|
| `worker.paused`            | Contains the worker options                            |
| `worker.started`           | Contains the worker options                            |
| `worker.stopped`           | Contains the worker options and the last executed task |
| `worker.forked`            | Contains both old and forked worker options            |
| `worker.restarted`         | Contains the worker options and last executed task     |
