# Mercure

This bundle comes with a fully integrated [Mercure](https://www.mercure.rocks) support, 
this mean that you can listen and trigger actions depending on real-time update 
sent by the scheduler/worker/etc.

- [Configuration](#configuration)
- [List](#list)

## Configuration

```yaml
scheduler_bundle:
    mercure:
        enabled: true
        hub_url: 'https://www.foo.com/.well-know/mercure'
        update_url: 'https://www.bar.com/scheduler'
        jwt_token: '!ChangeMe'

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
| `worker.started`           | Contains the worker options                            |
| `worker.stopped`           | Contains the worker options and the last executed task |
| `worker.forked`            | Contains both old and forked worker options            |
| `worker.restarted`         | Contains the worker options and last executed task     |
