# Worker

The worker is one of the main tools of this bundle, every "due" tasks is retrieved via the worker
and executed thanks to [runners](runners.md).

- [API](#api)
- [Concepts](#concepts)
- [Daemon](#daemon)
- [Loop](#loop)
- [Forking a worker](#forking-a-worker)
- [Extending](#extending)

## API

Even if the worker is only responsible for executing tasks thanks to runners,
some methods can be used to improve its usage:

- `execute`: Allows executing tasks depending on the given options,
  if an empty set of tasks is submitted,
  the worker will use the scheduler to retrieve the currently due tasks.

- `fork`: This method allows to retrieve a **cloned** worker,
  this can be useful when executing tasks outside the main worker process.

- `stop`: This method indicate to the worker that it should be stopped.

- `restart`: This method allows to reset the internal worker state and dispatch an event related to this reset.

- `isRunning`: Returns the state of the worker regarding the current execution.

- `getFailedTasks`: Return a [TaskList](../src/Task/TaskList.php)
  that contains the failed tasks during the current execution.

- `getLastExecutedTask`: Return the last executed task or null if none.

- `getTaskLockRegistry`: Return the task lock registry which contains the lock for each task to execute.

- `getRunners`: Return the injected runners.

- `getConfiguration`: Return the current configuration of the worker (in the case of a forked one, the forked one).

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

The worker can act as a simple `while` loop and wait until every due tasks are executed to stop,
that's the default behaviour if the `sleepUntilNextMinute` option is not passed in the `execute` method.

## Forking a worker

You may face situations where the default worker must be **cloned** or **forked** 
to  execute properly a task and/or update an option 
without compromising the default worker behavior.

To do so, the worker has a method called `fork()`,
this method allows to retrieve a new worker (based on the options passed via the first one)
and execute tasks if needed.

If you need to interact with both workers right after the fork succeed, 
a [WorkerForkedEvent](../src/Event/WorkerForkedEvent.php) is dispatched.

**PS: The default worker can be retrieved via `$forkedWorker->getOptions()['forkedFrom']`.**

**PS II: You can determine if the current worker is a fork via the option `isForked`.**

## Extending

The worker can easily be extended thanks to [WorkerInterface](../src/Worker/WorkerInterface.php) but
it forces you to implement your own logic and respect the contract. 

A better alternative could be to extend [AbstractWorker](../src/Worker/AbstractWorker.php)
and use the `run` method inside the `execute` one, this way, you can easily define your own process
and access the core logic of the worker (task handling, errors handling, etc). 
