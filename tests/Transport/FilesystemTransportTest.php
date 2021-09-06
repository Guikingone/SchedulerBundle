<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Transport;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use SchedulerBundle\SchedulePolicy\FirstInFirstOutPolicy;
use SchedulerBundle\SchedulePolicy\SchedulePolicyOrchestrator;
use SchedulerBundle\Serializer\AccessLockBagNormalizer;
use SchedulerBundle\Serializer\NotificationTaskBagNormalizer;
use SchedulerBundle\Task\LazyTask;
use SchedulerBundle\Task\LazyTaskList;
use SchedulerBundle\Task\TaskList;
use SchedulerBundle\Transport\TransportInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;
use Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;
use SchedulerBundle\Exception\InvalidArgumentException;
use SchedulerBundle\Exception\LogicException;
use SchedulerBundle\Serializer\TaskNormalizer;
use SchedulerBundle\Task\NullTask;
use SchedulerBundle\Task\ShellTask;
use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Transport\FilesystemTransport;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\DateIntervalNormalizer;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Serializer\Normalizer\DateTimeZoneNormalizer;
use Symfony\Component\Serializer\Normalizer\JsonSerializableNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\SerializerInterface;
use Throwable;
use function getcwd;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class FilesystemTransportTest extends TestCase
{
    private Filesystem $filesystem;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        $this->filesystem = new Filesystem();
        $this->filesystem->remove(getcwd().'/assets/**/*.json');
    }

    /**
     * {@inheritdoc}
     */
    protected function tearDown(): void
    {
        $this->filesystem->remove(getcwd().'/assets/_symfony_scheduler_/bar.json');
        $this->filesystem->remove(getcwd().'/assets/_symfony_scheduler_/foo.json');
    }

    public function testTransportCannotBeConfiguredWithInvalidPath(): void
    {
        $serializer = $this->createMock(SerializerInterface::class);

        self::expectException(InvalidOptionsException::class);
        self::expectExceptionMessage('The option "path" with value 135 is expected to be of type "string", but is of type "int".');
        self::expectExceptionCode(0);
        new FilesystemTransport(null, [
            'path' => 135,
        ], $serializer, new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ]));
    }

    public function testTransportCanBeConfigured(): void
    {
        $serializer = $this->createMock(SerializerInterface::class);

        $filesystemTransport = new FilesystemTransport(getcwd().'/assets', [], $serializer, new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ]));

        self::assertArrayHasKey('path', $filesystemTransport->getOptions());
        self::assertSame(getcwd().'/assets', $filesystemTransport->getOptions()['path']);
        self::assertArrayHasKey('execution_mode', $filesystemTransport->getOptions());
        self::assertSame('first_in_first_out', $filesystemTransport->getOptions()['execution_mode']);
        self::assertArrayHasKey('filename_mask', $filesystemTransport->getOptions());
        self::assertSame('%s/_symfony_scheduler_/%s.json', $filesystemTransport->getOptions()['filename_mask']);
    }

    /**
     * @throws Throwable {@see TransportInterface::list()}
     */
    public function testTaskListCanBeRetrieved(): void
    {
        $objectNormalizer = new ObjectNormalizer(null, null, null, new PropertyInfoExtractor([], [new PhpDocExtractor(), new ReflectionExtractor()]));
        $lockTaskBagNormalizer = new AccessLockBagNormalizer($objectNormalizer);

        $serializer = new Serializer([
            new TaskNormalizer(
                new DateTimeNormalizer(),
                new DateTimeZoneNormalizer(),
                new DateIntervalNormalizer(),
                $objectNormalizer,
                new NotificationTaskBagNormalizer($objectNormalizer),
                $lockTaskBagNormalizer
            ),
            new DateTimeNormalizer(),
            new DateIntervalNormalizer(),
            new JsonSerializableNormalizer(),
            $objectNormalizer,
        ], [new JsonEncoder()]);
        $objectNormalizer->setSerializer($serializer);

        $filesystemTransport = new FilesystemTransport(getcwd().'/assets', [], $serializer, new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ]));

        $nullTask = new NullTask('foo', [
            'scheduled_at' => new DateTimeImmutable(),
        ]);
        $secondNullTask = new NullTask('bar', [
            'scheduled_at' => new DateTimeImmutable(),
        ]);

        $filesystemTransport->create($nullTask);
        $filesystemTransport->create($secondNullTask);
        self::assertTrue($this->filesystem->exists(getcwd().'/assets/_symfony_scheduler_/foo.json'));
        self::assertTrue($this->filesystem->exists(getcwd().'/assets/_symfony_scheduler_/bar.json'));

        $list = $filesystemTransport->list();
        self::assertInstanceOf(TaskList::class, $list);
        self::assertCount(2, $list);
        self::assertSame('foo', $list->toArray(false)[0]->getName());
        self::assertSame('bar', $list->toArray(false)[1]->getName());
        self::assertInstanceOf(NullTask::class, $list->get('foo'));
        self::assertInstanceOf(NullTask::class, $list->get('bar'));

        $lazyList = $filesystemTransport->list(true);
        self::assertInstanceOf(LazyTaskList::class, $lazyList);
        self::assertCount(2, $lazyList);
        self::assertSame('foo', $lazyList->toArray(false)[0]->getName());
        self::assertSame('bar', $lazyList->toArray(false)[1]->getName());
        self::assertInstanceOf(NullTask::class, $lazyList->get('foo'));
        self::assertInstanceOf(NullTask::class, $lazyList->get('bar'));
    }

    /**
     * @throws Throwable {@see TransportInterface::list()}
     */
    public function testTaskListCanBeRetrievedAndSorted(): void
    {
        $objectNormalizer = new ObjectNormalizer(null, null, null, new PropertyInfoExtractor([], [new PhpDocExtractor(), new ReflectionExtractor()]));
        $lockTaskBagNormalizer = new AccessLockBagNormalizer($objectNormalizer);

        $serializer = new Serializer([
            new TaskNormalizer(
                new DateTimeNormalizer(),
                new DateTimeZoneNormalizer(),
                new DateIntervalNormalizer(),
                $objectNormalizer,
                new NotificationTaskBagNormalizer($objectNormalizer),
                $lockTaskBagNormalizer
            ),
            new DateTimeNormalizer(),
            new DateIntervalNormalizer(),
            new JsonSerializableNormalizer(),
            $objectNormalizer,
        ], [new JsonEncoder()]);
        $objectNormalizer->setSerializer($serializer);

        $nullTask = new NullTask('bar');

        $filesystemTransport = new FilesystemTransport(getcwd().'/assets', [
            'execution_mode' => 'first_in_first_out',
        ], $serializer, new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ]));

        $filesystemTransport->create($nullTask);
        self::assertTrue($this->filesystem->exists(getcwd().'/assets/_symfony_scheduler_/bar.json'));

        $list = $filesystemTransport->list();
        self::assertNotEmpty($list);
        self::assertInstanceOf(NullTask::class, $list->get('bar'));

        $lazyList = $filesystemTransport->list(true);
        self::assertNotEmpty($lazyList);
        self::assertInstanceOf(NullTask::class, $lazyList->get('bar'));
    }

    public function testTaskCannotBeRetrievedWithUndefinedTask(): void
    {
        $objectNormalizer = new ObjectNormalizer(null, null, null, new PropertyInfoExtractor([], [new PhpDocExtractor(), new ReflectionExtractor()]));
        $lockTaskBagNormalizer = new AccessLockBagNormalizer($objectNormalizer);

        $serializer = new Serializer([
            new TaskNormalizer(
                new DateTimeNormalizer(),
                new DateTimeZoneNormalizer(),
                new DateIntervalNormalizer(),
                $objectNormalizer,
                new NotificationTaskBagNormalizer($objectNormalizer),
                $lockTaskBagNormalizer
            ),
            $objectNormalizer,
        ], [new JsonEncoder()]);
        $objectNormalizer->setSerializer($serializer);

        $filesystemTransport = new FilesystemTransport(getcwd().'/assets', [], $serializer, new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ]));

        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage('The "bar" task does not exist');
        $filesystemTransport->get('bar');
    }

    public function testTaskCanBeRetrieved(): void
    {
        $objectNormalizer = new ObjectNormalizer(null, null, null, new PropertyInfoExtractor([], [new PhpDocExtractor(), new ReflectionExtractor()]));
        $lockTaskBagNormalizer = new AccessLockBagNormalizer($objectNormalizer);

        $serializer = new Serializer([
            new TaskNormalizer(
                new DateTimeNormalizer(),
                new DateTimeZoneNormalizer(),
                new DateIntervalNormalizer(),
                $objectNormalizer,
                new NotificationTaskBagNormalizer($objectNormalizer),
                $lockTaskBagNormalizer
            ),
            $objectNormalizer,
        ], [new JsonEncoder()]);
        $objectNormalizer->setSerializer($serializer);

        $filesystemTransport = new FilesystemTransport(getcwd().'/assets', [], $serializer, new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ]));

        $filesystemTransport->create(new NullTask('foo'));
        self::assertTrue($this->filesystem->exists(getcwd().'/assets/_symfony_scheduler_/foo.json'));

        $task = $filesystemTransport->get('foo');
        self::assertInstanceOf(NullTask::class, $task);
        self::assertSame('foo', $task->getName());
        self::assertSame('* * * * *', $task->getExpression());
    }

    public function testTaskCanBeRetrievedLazily(): void
    {
        $objectNormalizer = new ObjectNormalizer(null, null, null, new PropertyInfoExtractor([], [new PhpDocExtractor(), new ReflectionExtractor()]));
        $lockTaskBagNormalizer = new AccessLockBagNormalizer($objectNormalizer);

        $serializer = new Serializer([
            new TaskNormalizer(
                new DateTimeNormalizer(),
                new DateTimeZoneNormalizer(),
                new DateIntervalNormalizer(),
                $objectNormalizer,
                new NotificationTaskBagNormalizer($objectNormalizer),
                $lockTaskBagNormalizer
            ),
            $objectNormalizer,
        ], [new JsonEncoder()]);
        $objectNormalizer->setSerializer($serializer);

        $filesystemTransport = new FilesystemTransport(getcwd().'/assets', [], $serializer, new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ]));

        $filesystemTransport->create(new NullTask('foo'));
        self::assertTrue($this->filesystem->exists(getcwd().'/assets/_symfony_scheduler_/foo.json'));

        $lazyTask = $filesystemTransport->get('foo', true);
        self::assertInstanceOf(LazyTask::class, $lazyTask);
        self::assertFalse($lazyTask->isInitialized());

        $task = $lazyTask->getTask();
        self::assertTrue($lazyTask->isInitialized());
        self::assertInstanceOf(NullTask::class, $task);
        self::assertSame('foo', $task->getName());
        self::assertSame('* * * * *', $task->getExpression());
    }

    public function testTaskCanBeCreated(): void
    {
        $objectNormalizer = new ObjectNormalizer(null, null, null, new PropertyInfoExtractor([], [new PhpDocExtractor(), new ReflectionExtractor()]));
        $lockTaskBagNormalizer = new AccessLockBagNormalizer($objectNormalizer);

        $serializer = new Serializer([
            new TaskNormalizer(
                new DateTimeNormalizer(),
                new DateTimeZoneNormalizer(),
                new DateIntervalNormalizer(),
                $objectNormalizer,
                new NotificationTaskBagNormalizer($objectNormalizer),
                $lockTaskBagNormalizer
            ), $objectNormalizer,
        ], [new JsonEncoder()]);
        $objectNormalizer->setSerializer($serializer);

        $filesystemTransport = new FilesystemTransport(getcwd().'/assets', [], $serializer, new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ]));

        $filesystemTransport->create(new NullTask('foo'));
        self::assertTrue($this->filesystem->exists(getcwd().'/assets/_symfony_scheduler_/foo.json'));
    }

    public function testTaskCannotBeUpdatedWhenUndefinedButShouldBeCreated(): void
    {
        $objectNormalizer = new ObjectNormalizer(null, null, null, new PropertyInfoExtractor([], [new PhpDocExtractor(), new ReflectionExtractor()]));
        $lockTaskBagNormalizer = new AccessLockBagNormalizer($objectNormalizer);

        $serializer = new Serializer([
            new TaskNormalizer(
                new DateTimeNormalizer(),
                new DateTimeZoneNormalizer(),
                new DateIntervalNormalizer(),
                $objectNormalizer,
                new NotificationTaskBagNormalizer($objectNormalizer),
                $lockTaskBagNormalizer
            ),
            $objectNormalizer,
        ], [new JsonEncoder()]);
        $objectNormalizer->setSerializer($serializer);

        $filesystemTransport = new FilesystemTransport(getcwd().'/assets', [], $serializer, new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ]));

        $filesystemTransport->update('foo', new NullTask('foo'));

        $task = $filesystemTransport->get('foo');
        self::assertInstanceOf(NullTask::class, $task);
        self::assertSame('foo', $task->getName());
        self::assertSame('* * * * *', $task->getExpression());
    }

    public function testTaskCanBeUpdated(): void
    {
        $objectNormalizer = new ObjectNormalizer(null, null, null, new PropertyInfoExtractor([], [new PhpDocExtractor(), new ReflectionExtractor()]));
        $lockTaskBagNormalizer = new AccessLockBagNormalizer($objectNormalizer);

        $serializer = new Serializer([
            new TaskNormalizer(
                new DateTimeNormalizer(),
                new DateTimeZoneNormalizer(),
                new DateIntervalNormalizer(),
                $objectNormalizer,
                new NotificationTaskBagNormalizer($objectNormalizer),
                $lockTaskBagNormalizer
            ),
            $objectNormalizer,
        ], [new JsonEncoder()]);
        $objectNormalizer->setSerializer($serializer);

        $filesystemTransport = new FilesystemTransport(getcwd().'/assets', [], $serializer, new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ]));

        $filesystemTransport->create(new ShellTask('foo', ['echo', 'Symfony']));
        self::assertTrue($this->filesystem->exists(getcwd().'/assets/_symfony_scheduler_/foo.json'));

        $task = $filesystemTransport->get('foo');
        $task->setExpression('0 * * * *');
        self::assertSame('0 * * * *', $task->getExpression());

        $filesystemTransport->update('foo', $task);
        $updatedTask = $filesystemTransport->get('foo');

        self::assertSame('0 * * * *', $updatedTask->getExpression());
        self::assertTrue($this->filesystem->exists(getcwd().'/assets/_symfony_scheduler_/foo.json'));
    }

    public function testTaskCanBeDeleted(): void
    {
        $objectNormalizer = new ObjectNormalizer(null, null, null, new PropertyInfoExtractor([], [new PhpDocExtractor(), new ReflectionExtractor()]));
        $lockTaskBagNormalizer = new AccessLockBagNormalizer($objectNormalizer);

        $serializer = new Serializer([
            new TaskNormalizer(
                new DateTimeNormalizer(),
                new DateTimeZoneNormalizer(),
                new DateIntervalNormalizer(),
                $objectNormalizer,
                new NotificationTaskBagNormalizer($objectNormalizer),
                $lockTaskBagNormalizer
            ),
            $objectNormalizer,
        ], [new JsonEncoder()]);
        $objectNormalizer->setSerializer($serializer);

        $filesystemTransport = new FilesystemTransport(getcwd().'/assets', [], $serializer, new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ]));

        $filesystemTransport->create(new NullTask('foo'));
        self::assertTrue($this->filesystem->exists(getcwd().'/assets/_symfony_scheduler_/foo.json'));

        $filesystemTransport->delete('foo');
        self::assertFalse($this->filesystem->exists(getcwd().'/assets/_symfony_scheduler_/foo.json'));
    }

    public function testTaskCannotBePausedTwice(): void
    {
        $objectNormalizer = new ObjectNormalizer(null, null, null, new PropertyInfoExtractor([], [new PhpDocExtractor(), new ReflectionExtractor()]));
        $lockTaskBagNormalizer = new AccessLockBagNormalizer($objectNormalizer);

        $serializer = new Serializer([
            new TaskNormalizer(
                new DateTimeNormalizer(),
                new DateTimeZoneNormalizer(),
                new DateIntervalNormalizer(),
                $objectNormalizer,
                new NotificationTaskBagNormalizer($objectNormalizer),
                $lockTaskBagNormalizer
            ),
            $objectNormalizer,
        ], [new JsonEncoder()]);
        $objectNormalizer->setSerializer($serializer);

        $filesystemTransport = new FilesystemTransport(getcwd().'/assets', [], $serializer, new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ]));

        $filesystemTransport->create(new NullTask('foo'));
        self::assertTrue($this->filesystem->exists(getcwd().'/assets/_symfony_scheduler_/foo.json'));

        $filesystemTransport->pause('foo');

        $task = $filesystemTransport->get('foo');
        self::assertSame(TaskInterface::PAUSED, $task->getState());

        self::expectException(LogicException::class);
        self::expectExceptionMessage('The task "foo" is already paused');
        $filesystemTransport->pause('foo');
    }

    public function testTaskCanBePaused(): void
    {
        $objectNormalizer = new ObjectNormalizer(null, null, null, new PropertyInfoExtractor([], [new PhpDocExtractor(), new ReflectionExtractor()]));
        $lockTaskBagNormalizer = new AccessLockBagNormalizer($objectNormalizer);

        $serializer = new Serializer([
            new TaskNormalizer(
                new DateTimeNormalizer(),
                new DateTimeZoneNormalizer(),
                new DateIntervalNormalizer(),
                $objectNormalizer,
                new NotificationTaskBagNormalizer($objectNormalizer),
                $lockTaskBagNormalizer
            ),
            $objectNormalizer,
        ], [new JsonEncoder()]);
        $objectNormalizer->setSerializer($serializer);

        $filesystemTransport = new FilesystemTransport(getcwd().'/assets', [], $serializer, new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ]));

        $filesystemTransport->create(new NullTask('foo'));
        self::assertTrue($this->filesystem->exists(getcwd().'/assets/_symfony_scheduler_/foo.json'));

        $filesystemTransport->pause('foo');

        $task = $filesystemTransport->get('foo');
        self::assertSame(TaskInterface::PAUSED, $task->getState());
    }

    public function testTaskCannotBeResumedTwice(): void
    {
        $objectNormalizer = new ObjectNormalizer(null, null, null, new PropertyInfoExtractor([], [new PhpDocExtractor(), new ReflectionExtractor()]));
        $lockTaskBagNormalizer = new AccessLockBagNormalizer($objectNormalizer);

        $serializer = new Serializer([
            new TaskNormalizer(
                new DateTimeNormalizer(),
                new DateTimeZoneNormalizer(),
                new DateIntervalNormalizer(),
                $objectNormalizer,
                new NotificationTaskBagNormalizer($objectNormalizer),
                $lockTaskBagNormalizer
            ),
            $objectNormalizer,
        ], [new JsonEncoder()]);
        $objectNormalizer->setSerializer($serializer);

        $filesystemTransport = new FilesystemTransport(getcwd().'/assets', [], $serializer, new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ]));

        $filesystemTransport->create(new NullTask('foo'));
        self::assertTrue($this->filesystem->exists(getcwd().'/assets/_symfony_scheduler_/foo.json'));

        $filesystemTransport->pause('foo');

        $task = $filesystemTransport->get('foo');
        self::assertSame(TaskInterface::PAUSED, $task->getState());

        $filesystemTransport->resume('foo');
        $task = $filesystemTransport->get('foo');
        self::assertSame(TaskInterface::ENABLED, $task->getState());

        self::expectException(LogicException::class);
        self::expectExceptionMessage('The task "foo" is already enabled');
        $filesystemTransport->resume('foo');
    }

    public function testTaskCanBeResumed(): void
    {
        $objectNormalizer = new ObjectNormalizer(null, null, null, new PropertyInfoExtractor([], [new PhpDocExtractor(), new ReflectionExtractor()]));
        $lockTaskBagNormalizer = new AccessLockBagNormalizer($objectNormalizer);

        $serializer = new Serializer([
            new TaskNormalizer(
                new DateTimeNormalizer(),
                new DateTimeZoneNormalizer(),
                new DateIntervalNormalizer(),
                $objectNormalizer,
                new NotificationTaskBagNormalizer($objectNormalizer),
                $lockTaskBagNormalizer
            ), $objectNormalizer, ], [new JsonEncoder()]);
        $objectNormalizer->setSerializer($serializer);

        $filesystemTransport = new FilesystemTransport(getcwd().'/assets', [], $serializer, new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ]));

        $filesystemTransport->create(new NullTask('foo'));
        self::assertTrue($this->filesystem->exists(getcwd().'/assets/_symfony_scheduler_/foo.json'));

        $filesystemTransport->pause('foo');

        $task = $filesystemTransport->get('foo');
        self::assertSame(TaskInterface::PAUSED, $task->getState());

        $filesystemTransport->resume('foo');

        $task = $filesystemTransport->get('foo');
        self::assertSame(TaskInterface::ENABLED, $task->getState());
    }

    public function testTaskCanBeCleared(): void
    {
        $objectNormalizer = new ObjectNormalizer(null, null, null, new PropertyInfoExtractor([], [new PhpDocExtractor(), new ReflectionExtractor()]));
        $lockTaskBagNormalizer = new AccessLockBagNormalizer($objectNormalizer);

        $serializer = new Serializer([
            new TaskNormalizer(
                new DateTimeNormalizer(),
                new DateTimeZoneNormalizer(),
                new DateIntervalNormalizer(),
                $objectNormalizer,
                new NotificationTaskBagNormalizer($objectNormalizer),
                $lockTaskBagNormalizer
            ),
            $objectNormalizer,
        ], [new JsonEncoder()]);
        $objectNormalizer->setSerializer($serializer);

        $filesystemTransport = new FilesystemTransport(getcwd().'/assets', [], $serializer, new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ]));

        $filesystemTransport->create(new NullTask('foo'));
        $filesystemTransport->create(new NullTask('bar'));
        self::assertTrue($this->filesystem->exists(getcwd().'/assets/_symfony_scheduler_/foo.json'));
        self::assertTrue($this->filesystem->exists(getcwd().'/assets/_symfony_scheduler_/bar.json'));

        $filesystemTransport->clear();
        self::assertFalse($this->filesystem->exists(getcwd().'/assets/_symfony_scheduler_/foo.json'));
        self::assertFalse($this->filesystem->exists(getcwd().'/assets/_symfony_scheduler_/bar.json'));
    }
}
