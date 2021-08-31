# Commands

This bundle provides a set of commands to interact with your tasks

- [Probe](#probe)

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

## Consuming the tasks

_Description: Consume due tasks_

```bash
$ bin/console scheduler:consume
```

### Options

This command allows using multiple options to filter consumed tasks (each one can be combined):

- `--limit`: Define the maximum amount of due tasks to consume.
- `--time-limit`: Define the maximum amount in seconds before the worker stop.
- `--failure-limit`: Define the maximum amount of tasks that can fails during consumation.
- `--wait`: Set the worker to an "infinite" wait loop where tasks are consumed then the worker wait until next minute.
- `--force`: Force the worker to wait for tasks even if no tasks are currently available.
- `--lazy`: Force the scheduler to retrieve the tasks using lazy-loading.
- `--strict`: Force the scheduler to check the date before retrieving the tasks.

### Extra informations

- The scheduler will only return tasks that haven't been executed since the last minute.

- The command filter tasks returned by the scheduler by checking if each task is not paused 
  (the worker will do this if the `--wait` option is set).

- The output of each executed task can be displayed if the `-vv` option is used.

- By default, when using `--wait`, the command will stop if no tasks can be found, thanks to `--force`,
  you can ask the worker to wait without stopping the command for the next minute and check again the tasks.

- When using `--lazy`, tasks are retrieve using a `LazyTaskList`, this can improve performances
  BUT keep in mind that using this approach can trigger edge cases when checking due tasks.

- When using `--strict`, keep in mind that `SchedulerInterface::getDueTasks()` is called twice,
  first, the command will check if tasks can be found using a strict comparison on the current date
  then the worker will retrieve the tasks using the same approach, if the worker didn't receive any tasks,
  it will start the sleeping phase until next minute.

## Executing tasks

_Introduced in `0.5`_

_Description: Execute a set of tasks (due or not) depending on filters_

```bash
$ bin/console scheduler:execute
```

### Options

This command allows using multiple options to filter consumed tasks (each one can be combined):

- `--due`: Execute due tasks.
- `--namme`: Allows to specify a set of tasks (depending on their name) that must be executed.
- `--expression`: Allows to specify a set of tasks (depending on their expression) that must be executed.
- `--tags`: Allows to specify a set of tasks (depending on their tags) that must be executed.

### Extra informations

- Depending on `--due` option, the scheduler will execute the due tasks or each tasks that match the submitted options.
- The worker is automatically stopped once each task has been consumed.

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

## Probe

### Displaying the current state of the probe

_Introduced in `0.5`_

_Description: Display the probe state along with (if defined) the external probe states_

```bash
$ bin/console scheduler:debug:probe
```

### Options

This command allows using additional options to display information:

- `--external`: Define if the external probes state must be displayed.

#### Example

```bash
$ bin/console scheduler:debug:probe

[INFO] The displayed probe state is the one found at 2021-05-17T17:24:56+00:00

+----------------+--------------+-----------------+
| Executed tasks | Failed tasks | Scheduled tasks |
+----------------+--------------+-----------------+
| 0              | 0            | 0               |
+----------------+--------------+-----------------+
```

- With external probes state

```bash
$ bin/console scheduler:debug:probe --external

  [INFO] The displayed probe state is the one found at 2021-05-17T17:24:56+00:00

+----------------+--------------+-----------------+
| Executed tasks | Failed tasks | Scheduled tasks |
+----------------+--------------+-----------------+
| 0              | 0            | 0               |
+----------------+--------------+-----------------+

  [INFO] Found 1 external probe

+------+-----------------+--------+-----------------------------------+-----------------+
| Name | Path            | State  | Last execution                    | Execution state |
+------+-----------------+--------+-----------------------------------+-----------------+
| foo  | /_external_path | paused | Tuesday, 18-May-2021 16:26:34 UTC | Not executed    |
+------+-----------------+--------+-----------------------------------+-----------------+
```

### Executing external probe

_Introduced in `0.5`_

_Description: Execute external probe_

```bash
$ bin/console scheduler:execute:external-probe
```

#### Example

// TODO

### Debugging middleware list

_Introduced in `0.6`_

_Description: Display the middleware list (both scheduler and worker)_

```bash
$ bin/console scheduler:debug:middleware
```

#### Example

```bash
$ bin/console scheduler:debug:middleware
                                                                                                                       
 [INFO] Found 2 middleware for the scheduling phase                                                                                                                                                     

+------------------------+---------------+----------------+----------+----------+
| Name                   | PreScheduling | PostScheduling | Priority | Required |
+------------------------+---------------+----------------+----------+----------+
| TaskCallbackMiddleware | Yes           | Yes            | 1        | No       |
| NotifierMiddleware     | Yes           | Yes            | 2        | No       |
+------------------------+---------------+----------------+----------+----------+
                                                                                                                        
 [INFO] Found 6 middleware for the execution phase                                                                      

+-------------------------+--------------+---------------+----------+----------+
| Name                    | PreExecution | PostExecution | Priority | Required |
+-------------------------+--------------+---------------+----------+----------+
| TaskCallbackMiddleware  | Yes          | Yes           | 1        | No       |
| NotifierMiddleware      | Yes          | Yes           | 2        | No       |
| ProbeTaskMiddleware     | Yes          | No            | No       | No       |
| TaskLockBagMiddleware   | No           | Yes           | 5        | No       |
| TaskUpdateMiddleware    | No           | Yes           | 10       | Yes      |
| SingleRunTaskMiddleware | No           | Yes           | 15       | Yes      |
+-------------------------+--------------+---------------+----------+----------+
```
