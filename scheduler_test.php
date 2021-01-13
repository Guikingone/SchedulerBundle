<?php

require __DIR__.'/vendor/autoload.php';

use Psr\Log\LogLevel;
use Symfony\Component\Console\Application;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpKernel\Log\Logger;
use Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;
use SchedulerBundle\Command\ConsumeTasksCommand;
use SchedulerBundle\Command\ListTasksCommand;
use SchedulerBundle\Command\RebootSchedulerCommand;
use SchedulerBundle\EventListener\TaskExecutionSubscriber;
use SchedulerBundle\Runner\ShellTaskRunner;
use SchedulerBundle\Scheduler;
use SchedulerBundle\Task\ShellTask;
use Symfony\Component\Serializer\Normalizer\JsonSerializableNormalizer;
use Symfony\Component\Stopwatch\Stopwatch;
use Symfony\Component\Messenger\Handler\HandlersLocator;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\Middleware\HandleMessageMiddleware;
use SchedulerBundle\Messenger\TaskMessage;
use SchedulerBundle\Messenger\TaskMessageHandler;
use SchedulerBundle\Serializer\TaskNormalizer;
use SchedulerBundle\Task\TaskExecutionTracker;
use SchedulerBundle\Transport\FilesystemTransport;
use SchedulerBundle\Worker\Worker;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\DateIntervalNormalizer;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Serializer\Normalizer\DateTimeZoneNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

$objectNormalizer = new ObjectNormalizer(null, null, null, new PropertyInfoExtractor([], [new PhpDocExtractor(), new ReflectionExtractor()]));
$serializer = new Serializer([new TaskNormalizer(new DateTimeNormalizer(), new DateTimeZoneNormalizer(), new DateIntervalNormalizer(), $objectNormalizer), new DateTimeNormalizer(), new DateTimeZoneNormalizer(), new DateIntervalNormalizer(), new JsonSerializableNormalizer(), $objectNormalizer], [new JsonEncoder()]);
$objectNormalizer->setSerializer($serializer);

$transport = new FilesystemTransport(__DIR__.'/tmp/_sf_scheduler', [], $serializer);
$scheduler = new Scheduler('Europe/Paris', $transport, null, new MessageBus());

$logger = new Logger(LogLevel::DEBUG, 'php://stderr');

$dispatcher = new EventDispatcher();
$dispatcher->addSubscriber(new TaskExecutionSubscriber($scheduler));

$worker = new Worker(
    $scheduler,
    [
        new ShellTaskRunner(),
    ],
    new TaskExecutionTracker(new Stopwatch()),
    $dispatcher,
    $logger
);

$bus = new MessageBus([
    new HandleMessageMiddleware(new HandlersLocator([
        TaskMessage::class => [new TaskMessageHandler($worker)],
    ])),
]);

$task = new ShellTask('app.test', ['echo', 'Symfony']);
$task->setExpression('*/5 * * * *');
$task->setOutput(true);
$task->setTags(['app', 'test']);
// $scheduler->schedule($task);

$task = new ShellTask('me.test', ['echo', 'Me']);
$task->setExpression('* * * * *');
$task->setOutput(true);
// $scheduler->schedule($task);

$app = new Application();
$app->add(new ConsumeTasksCommand($scheduler, $worker, $dispatcher));
$app->add(new ListTasksCommand($scheduler));
$app->add(new RebootSchedulerCommand($scheduler, $worker, $dispatcher, $logger));
$app->run();
