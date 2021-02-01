# Worker

The worker is one of the main tools of this bundle, every "due" tasks is retrieved via the worker
and executed thanks to [runners](runners.md).

## Concepts

The worker uses a very simple approach and can act in two ways:

- As a daemon (using the `--wait` option in the consume command)
- As a loop (default behaviour)

## Daemon

The worker has been built to be able to "wait" due tasks, the approach is simple:

- Once launched, the worker asks the scheduler to return due tasks
- If there are no due tasks, the worker uses the current time and determines the "wait" period until the next minute.
- If there are due tasks, the worker will call the registered runners to execute each task. Once every task
has been executed, the worker will determine the "wait" period until the next minute and "wait".

## Loop

The worker can act as a simple "while" loop and wait until every due tasks are executed to stop,
that's the default behaviour if the `sleepUntilNextMinute` option is not passed in the `execute` method.
