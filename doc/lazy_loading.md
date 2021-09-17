# Lazy loading

> Lazy loading (also known as asynchronous loading) 
> is a design pattern commonly used in computer programming and mostly in web design 
> and development to defer initialization of an object until the point at which it is needed. 
> It can contribute to efficiency in the program's operation if properly and appropriately used. 
> This makes it ideal in use cases where network content is accessed and initialization times are to be kept at a minimum, 
> such as in the case of web pages. 
> For example, deferring loading of images on a web page until they are needed can make the initial display of the web page faster. 
> The opposite of lazy loading is eager loading.

[Source](https://en.wikipedia.org/wiki/Lazy_loading)

This bundle provides a small integration of this pattern via multiple usages:

- [LazyTaskList](#lazytasklist)
- [LazyTask](#lazytask)
- [LazyScheduler](#lazyscheduler)
- [LazyTransport](#lazytransport)

## LazyTaskList

The [LazyTaskList](../src/Task/LazyTaskList.php) is built 
around the default [TaskList](../src/Task/TaskList.php), 
the idea is to provide the same API but allows fetching tasks (or information about tasks) later.

The state of the list can be fetched using `isInitialized()`.

```php
<?php

declare(strict_types=1);

use SchedulerBundle\Task\LazyTaskList;
use SchedulerBundle\Task\NullTask;
use SchedulerBundle\Task\TaskList;

$lazyList = new LazyTaskList(new TaskList([
    new NullTask('foo'),
]));
$lazyList->isInitialized(); // Will return false, by default, the list is not initialized so the task will not be returned or stored in the list for now.

$task = $lazyList->get('foo'); // This call will trigger the call on the task list and return the task (or null if not found)
$lazyList->isInitialized(); // Will return true as we trigger the initialization via `get()`

$task = $lazyList->get('foo', true); // Thanks to LazyTask, the call will return a LazyTask which contains the actual task (or null)
```

## LazyTask

The [LazyTask](../src/Task/LazyTask.php) act as a wrapper around 
any task that implement [TaskInterface](../src/Task/TaskInterface.php),
the idea is to delay to the extreme end the usage of the desired task.

**By default, the bundle does not use this type of task internally to prevent BC breaks or extra delay.**

Let's imagine that we use the transport to access a specific task:

```php
<?php

declare(strict_types=1);

use SchedulerBundle\Transport\TransportInterface;
use Symfony\Component\HttpFoundation\Response;

final class FooController
{
    public function __invoke(TransportInterface $transport): Response
    {
        $lazyTask = $transport->get('foo', true); // Will return a LazyTask which contains the task callable to fetch the actual task

        $task = $lazyTask->getTask(); // Will trigger the callable used to fetch the task and return the task

        echo $task->getName(); // Will return 'foo'

        // ...
    }
}
```

The important part here is that the `LazyTask` is not stored via the transport but used to fetch the task,
if an exception / error occurs, it will be thrown as usual.

## LazyScheduler

The [LazyScheduler](../src/LazyScheduler.php) is used to handle the task lifecycle in an "asynchronous" approach.

**The API still exactly the same as the default scheduler.**

The LazyScheduler can be enabled via the configuration:

```yaml
# config/packages/scheduler_bundle.yaml

scheduler_bundle:
    scheduler:
        mode: 'lazy'

# ...
```

In the end controller, the `SchedulerInterface` still valid to receive the `LazyScheduler`:

```php
<?php

declare(strict_types=1);

use SchedulerBundle\SchedulerInterface;
use Symfony\Component\HttpFoundation\Response;

final class FooController
{
    public function __invoke(SchedulerInterface $scheduler): Response
    {
        $tasks = $scheduler->getTasks(); // Will return the whole tasks list.

        // ...
    }
}
```

## LazyTransport

The [LazyTransport](../src/Transport/LazyTransport.php) is used to handle the transport lifecycle
using an "asynchronous" approach.

**The API still exactly the same as the default transport.**

The `LazyTransport` can be enabled via the configuration:

```yaml
# config/packages/scheduler_bundle.yaml

scheduler_bundle:
    transport:
        dsn: 'lazy://(memory://batch)'

# ...
```
