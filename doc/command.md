# Commands

This bundle provides a set of commands to interact with your tasks

## Listing the tasks

_Description: List every tasks scheduled_

```bash
$ bin/console scheduler:list
```

## Listing the failed tasks

_Description: List every task that have failed during execution_

```bash
$ bin/console scheduler:list:failed
```

## Rebooting the scheduler

_Description: Remove every task (except the ones using `@reboot` expression) and reboot the scheduler_

```bash
$ bin/console scheduler:reboot
```

## Removing failed task

_Description: Remove a task that has failed during execution_

```bash
$ bin/console scheduler:remove:failed **taskname**
```

## Retrying a failed task

_Description: Retry a task that has failed during execution_

```bash
$ bin/console scheduler:retry:failed **taskname**
```
