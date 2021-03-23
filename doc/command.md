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

## Yielding a task

_Introduced in `0.3`_

_Description: Yield a task_

**More information**: The behaviour behind "yielding" a task is to un-schedule it 
then immediately re-schedule it.

```bash
$ bin/console scheduler:yield **task**
```

### Example

Using the `--force` option:

```bash
$ bin/console scheduler:retry foo --force

[OK] The task "foo" has been yielded
```

Using the question:

```bash
$ bin/console scheduler:retry foo --force

Do you want to yield this task? (yes/no) [no]:
> yes

[OK] The task "foo" has been yielded
```

Using the `--force` option and the `--async` one:

```bash
$ bin/console scheduler:retry foo --async --force

[OK] The task "foo" has been yielded
```
**PS: Using the `async` option forces the scheduler to call the message bus, this approach requires
that you call the related command from it to consume messages**
