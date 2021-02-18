# Middleware

- [Scheduling](#Scheduling)
- [Execution](#Execution)
- [Order](#Order)

This bundle defines middleware related to execution and scheduling phases.

Middlewares are "man in the middle" that allows you to interact with the task
that is about to be scheduled/executed or even after the scheduling/execution.

There's two type of middleware:

- Pre_*Action*_Middleware
- Post_*Action*_Middleware

Both are called by [SchedulerMiddlewareStack](../src/Middleware/SchedulerMiddlewareStack.php) and/or
[WorkerMiddlewareStack](../src/Middleware/WorkerMiddlewareStack.php).

## Scheduling

The [SchedulerMiddlewareStack](../src/Middleware/SchedulerMiddlewareStack.php) allows to interact
during the scheduling process, some points to keep in mind:

- If an error/exception occurs/is thrown during the `preScheduling` process, the scheduling process is stopped.
- Same thing goes for `postScheduling`.

Defining a "scheduling middleware" is pretty straight-forward:

```php
<?php

declare(strict_types=1);

namespace App\Middleware;

use SchedulerBundle\Middleware\PostSchedulingMiddlewareInterface;
use SchedulerBundle\Middleware\PreSchedulingMiddlewareInterface;
use SchedulerBundle\SchedulerInterface;
use SchedulerBundle\Task\TaskInterface;

final class FooMiddleware implements PreSchedulingMiddlewareInterface, PostSchedulingMiddlewareInterface
{
    public function preScheduling(TaskInterface $task, SchedulerInterface $scheduler) : void
    {
    }

    public function postScheduling(TaskInterface $task, SchedulerInterface $scheduler) : void
    {
    }
}
```

Both method receives the current task (before scheduling it and sending it through transport) along with the scheduler.

## Execution

The [WorkerMiddlewareStack](../src/Middleware/WorkerMiddlewareStack.php) allows to interact
during the execution process, some points to keep in mind:

- If an error/exception occurs/is thrown during the `preExecute` process, 
  the execution process is stopped then the task is stored in the failed task list.
- Same thing goes for `postExecute`.

Defining an "execution middleware" is pretty straight-forward:

```php
<?php

declare(strict_types=1);

namespace App\Middleware;

use SchedulerBundle\Middleware\PostExecutionMiddlewareInterface;
use SchedulerBundle\Middleware\PreExecutionMiddlewareInterface;
use SchedulerBundle\Task\TaskInterface;

final class FooMiddleware implements PreExecutionMiddlewareInterface, PostExecutionMiddlewareInterface
{
    public function preExecute(TaskInterface $task, array $extraOptions = []): void
    {
    }

    public function postExecute(TaskInterface $task) : void
    {
    }
}
```

Both method receives the current task.

### Extra informations

- Implementing both interfaces for each middleware is not required, your middleware can be focused on a single one.
- A middleware can interact during both process by implementing the desired interfaces, 
  each stack sort related middleware before interacting with it.

## Order

// TODO
