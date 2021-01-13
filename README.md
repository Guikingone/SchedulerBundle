Scheduler Component
===================

The Scheduler component provides an API to schedule and run repetitive tasks.

**This Component is experimental**.
[Experimental features](https://symfony.com/doc/current/contributing/code/experimental.html)
are not covered by Symfony's
[Backward Compatibility Promise](https://symfony.com/doc/current/contributing/code/bc.html).

Resources
---------

  * [Documentation](https://symfony.com/doc/current/components/scheduler.html)
  * [Contributing](https://symfony.com/doc/current/contributing/index.html)
  * [Report issues](https://github.com/symfony/symfony/issues) and
    [send Pull Requests](https://github.com/symfony/symfony/pulls)
    in the [main Symfony repository](https://github.com/symfony/symfony)

Getting Started
---------------

```
$ composer require symfony/scheduler
```

```php
<?php

use Symfony\Component\EventDispatcher\EventDispatcher;
use SchedulerBundle\Scheduler;
use SchedulerBundle\Task\ShellTask;
use SchedulerBundle\Task\TaskExecutionTracker;
use SchedulerBundle\Transport\InMemoryTransport;
use SchedulerBundle\Worker\Worker;
use Symfony\Component\Stopwatch\Stopwatch;

$eventDispatcher = new EventDispatcher();

$transport = new InMemoryTransport();
$scheduler = new Scheduler('UTC', $transport, $eventDispatcher);
$scheduler->schedule(new ShellTask('app.foo', ['ls', '-al']));

$worker = new Worker($scheduler, [], new TaskExecutionTracker(new Stopwatch()), $eventDispatcher);
$worker->execute();
```

Resources
---------

  * [Contributing](https://symfony.com/doc/current/contributing/index.html)
  * [Report issues](https://github.com/symfony/symfony/issues) and
    [send Pull Requests](https://github.com/symfony/symfony/pulls)
    in the [main Symfony repository](https://github.com/symfony/symfony)
