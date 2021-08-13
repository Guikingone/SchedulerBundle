# Runners

Runners are the foundations of the [Worker](../src/Worker/Worker.php), 
every task is executed via a runner.

- [Retrieving the runners](#retrieving)
- [Existing runners](#existing-runners)
- [Defining a new runner](#defining-a-new-runner)

## Retrieving

Runners are stored in the [RunnerRegistry](../src/Runner/RunnerRegistry.php), you can easily find a runner regarding
the task that you want to execute using `find()` or even filter the runners regarding a specific condition using
`filter()`.

## Existing runners

| Runner                                                               | Description                                                                                  |
| ---------------------------------------------------------------------| ---------------------------------------------------------------------------------------------|
| [`CallbackTaskRunner`](../src/Runner/CallbackTaskRunner.php)         | Execute every `CallbackTask` thanks to `call_user_func_array`                                |
| [`ChainedTaskRunner`](../src/Runner/ChainedTaskRunner.php)           | Execute every `ChainedTask`, the whole "sub-tasks" list is executed                          |
| [`CommandTaskRunner`](../src/Runner/CommandTaskRunner.php)           | Execute every `CommandTask`, the `Application` is used                                       |
| [`HttpTaskRunner`](../src/Runner/HttpTaskRunner.php)                 | Execute every `HttpTask`, if the http client is not available, the task is not executed      |
| [`MessengerTaskRunner`](../src/Runner/MessengerTaskRunner.php)       | Execute every `MessengerTask`, if the bus is not available, the task is not executed         |
| [`NotificationTaskRunner`](../src/Runner/NotificationTaskRunner.php) | Execute every `NotificationTask`, if the notifier is not available, the task is not executed |
| [`NullTaskRunner`](../src/Runner/NullTaskRunner.php)                 | Execute every `NullTask`, return an output without any further action                        |
| [`ShellTaskRunner`](../src/Runner/ShellTaskRunner.php)               | Execute every `ShellTask` using `Process`                                                    |

## Defining a new runner

Defining a new runner is as simple as it sounds,
just implement [RunnerInterface](../src/Runner/RunnerInterface.php) and define implementations for each method, 
once this is done, the runner will be tagged and injected in the [RunnerRegistry](../src/Runner/RunnerRegistry.php).
