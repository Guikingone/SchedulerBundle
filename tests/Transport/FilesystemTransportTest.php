<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Transport;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use SchedulerBundle\SchedulePolicy\FirstInFirstOutPolicy;
use SchedulerBundle\SchedulePolicy\SchedulePolicyOrchestrator;
use SchedulerBundle\Serializer\NotificationTaskBagNormalizer;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;
use SchedulerBundle\Exception\InvalidArgumentException;
use SchedulerBundle\Exception\LogicException;
use SchedulerBundle\SchedulePolicy\SchedulePolicyOrchestratorInterface;
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

    public function testTaskListCanBeRetrieved(): void
    {
        $objectNormalizer = new ObjectNormalizer(null, null, null, new PropertyInfoExtractor([], [new PhpDocExtractor(), new ReflectionExtractor()]));

        $serializer = new Serializer([new TaskNormalizer(new DateTimeNormalizer(), new DateTimeZoneNormalizer(), new DateIntervalNormalizer(), $objectNormalizer, new NotificationTaskBagNormalizer($objectNormalizer)), new DateTimeNormalizer(), new DateIntervalNormalizer(), new JsonSerializableNormalizer(), $objectNormalizer], [new JsonEncoder()]);
        $objectNormalizer->setSerializer($serializer);

        $transport = new FilesystemTransport(getcwd().'/assets', [], $serializer, new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ]));

        $task = new NullTask('bar');
        $task->setScheduledAt(new DateTimeImmutable());

        $transport->create($task);
        self::assertTrue($this->filesystem->exists(getcwd().'/assets/_symfony_scheduler_/bar.json'));

        $list = $transport->list();
        self::assertNotEmpty($list);
        self::assertInstanceOf(NullTask::class, $list->get('bar'));
    }

    public function testTaskListCanBeRetrievedAndSorted(): void
    {
        $objectNormalizer = new ObjectNormalizer(null, null, null, new PropertyInfoExtractor([], [new PhpDocExtractor(), new ReflectionExtractor()]));

        $serializer = new Serializer([new TaskNormalizer(new DateTimeNormalizer(), new DateTimeZoneNormalizer(), new DateIntervalNormalizer(), $objectNormalizer, new NotificationTaskBagNormalizer($objectNormalizer)), new DateTimeNormalizer(), new DateIntervalNormalizer(), new JsonSerializableNormalizer(), $objectNormalizer], [new JsonEncoder()]);
        $objectNormalizer->setSerializer($serializer);

        $task = new NullTask('bar');

        $schedulePolicyOrchestrator = $this->createMock(SchedulePolicyOrchestratorInterface::class);
        $schedulePolicyOrchestrator->expects(self::once())->method('sort')->willReturn([$task]);

        $transport = new FilesystemTransport(getcwd().'/assets', [
            'execution_mode' => 'first_in_first_out',
        ], $serializer, $schedulePolicyOrchestrator);

        $transport->create($task);
        self::assertTrue($this->filesystem->exists(getcwd().'/assets/_symfony_scheduler_/bar.json'));

        $list = $transport->list();
        self::assertNotEmpty($list);
        self::assertInstanceOf(NullTask::class, $list->get('bar'));
    }

    public function testTaskCannotBeRetrievedWithUndefinedTask(): void
    {
        $objectNormalizer = new ObjectNormalizer(null, null, null, new PropertyInfoExtractor([], [new PhpDocExtractor(), new ReflectionExtractor()]));

        $serializer = new Serializer([new TaskNormalizer(new DateTimeNormalizer(), new DateTimeZoneNormalizer(), new DateIntervalNormalizer(), $objectNormalizer, new NotificationTaskBagNormalizer($objectNormalizer)), $objectNormalizer], [new JsonEncoder()]);
        $objectNormalizer->setSerializer($serializer);

        $transport = new FilesystemTransport(getcwd().'/assets', [], $serializer, new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ]));

        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage('The "bar" task does not exist');
        $transport->get('bar');
    }

    public function testTaskCanBeRetrieved(): void
    {
        $objectNormalizer = new ObjectNormalizer(null, null, null, new PropertyInfoExtractor([], [new PhpDocExtractor(), new ReflectionExtractor()]));

        $serializer = new Serializer([new TaskNormalizer(new DateTimeNormalizer(), new DateTimeZoneNormalizer(), new DateIntervalNormalizer(), $objectNormalizer, new NotificationTaskBagNormalizer($objectNormalizer)), $objectNormalizer], [new JsonEncoder()]);
        $objectNormalizer->setSerializer($serializer);

        $transport = new FilesystemTransport(getcwd().'/assets', [], $serializer, new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ]));

        $transport->create(new NullTask('foo'));
        self::assertTrue($this->filesystem->exists(getcwd().'/assets/_symfony_scheduler_/foo.json'));

        $task = $transport->get('foo');
        self::assertInstanceOf(NullTask::class, $task);
        self::assertSame('foo', $task->getName());
        self::assertSame('* * * * *', $task->getExpression());
    }

    public function testTaskCanBeCreated(): void
    {
        $objectNormalizer = new ObjectNormalizer(null, null, null, new PropertyInfoExtractor([], [new PhpDocExtractor(), new ReflectionExtractor()]));

        $serializer = new Serializer([new TaskNormalizer(new DateTimeNormalizer(), new DateTimeZoneNormalizer(), new DateIntervalNormalizer(), $objectNormalizer, new NotificationTaskBagNormalizer($objectNormalizer)), $objectNormalizer], [new JsonEncoder()]);
        $objectNormalizer->setSerializer($serializer);

        $transport = new FilesystemTransport(getcwd().'/assets', [], $serializer, new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ]));

        $transport->create(new NullTask('foo'));
        self::assertTrue($this->filesystem->exists(getcwd().'/assets/_symfony_scheduler_/foo.json'));
    }

    public function testTaskCannotBeUpdatedWhenUndefinedButShouldBeCreated(): void
    {
        $objectNormalizer = new ObjectNormalizer(null, null, null, new PropertyInfoExtractor([], [new PhpDocExtractor(), new ReflectionExtractor()]));

        $serializer = new Serializer([new TaskNormalizer(new DateTimeNormalizer(), new DateTimeZoneNormalizer(), new DateIntervalNormalizer(), $objectNormalizer, new NotificationTaskBagNormalizer($objectNormalizer)), $objectNormalizer], [new JsonEncoder()]);
        $objectNormalizer->setSerializer($serializer);

        $transport = new FilesystemTransport(getcwd().'/assets', [], $serializer, new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ]));

        $transport->update('foo', new NullTask('foo'));

        $task = $transport->get('foo');
        self::assertInstanceOf(NullTask::class, $task);
        self::assertSame('foo', $task->getName());
        self::assertSame('* * * * *', $task->getExpression());
    }

    public function testTaskCanBeUpdated(): void
    {
        $objectNormalizer = new ObjectNormalizer(null, null, null, new PropertyInfoExtractor([], [new PhpDocExtractor(), new ReflectionExtractor()]));

        $serializer = new Serializer([new TaskNormalizer(new DateTimeNormalizer(), new DateTimeZoneNormalizer(), new DateIntervalNormalizer(), $objectNormalizer, new NotificationTaskBagNormalizer($objectNormalizer)), $objectNormalizer], [new JsonEncoder()]);
        $objectNormalizer->setSerializer($serializer);

        $transport = new FilesystemTransport(getcwd().'/assets', [], $serializer, new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ]));

        $transport->create(new ShellTask('foo', ['echo', 'Symfony']));
        self::assertTrue($this->filesystem->exists(getcwd().'/assets/_symfony_scheduler_/foo.json'));

        $task = $transport->get('foo');
        $task->setExpression('0 * * * *');
        self::assertSame('0 * * * *', $task->getExpression());

        $transport->update('foo', $task);
        $updatedTask = $transport->get('foo');

        self::assertSame('0 * * * *', $updatedTask->getExpression());
        self::assertTrue($this->filesystem->exists(getcwd().'/assets/_symfony_scheduler_/foo.json'));
    }

    public function testTaskCanBeDeleted(): void
    {
        $objectNormalizer = new ObjectNormalizer(null, null, null, new PropertyInfoExtractor([], [new PhpDocExtractor(), new ReflectionExtractor()]));

        $serializer = new Serializer([new TaskNormalizer(new DateTimeNormalizer(), new DateTimeZoneNormalizer(), new DateIntervalNormalizer(), $objectNormalizer, new NotificationTaskBagNormalizer($objectNormalizer)), $objectNormalizer], [new JsonEncoder()]);
        $objectNormalizer->setSerializer($serializer);

        $transport = new FilesystemTransport(getcwd().'/assets', [], $serializer, new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ]));

        $transport->create(new NullTask('foo'));
        self::assertTrue($this->filesystem->exists(getcwd().'/assets/_symfony_scheduler_/foo.json'));

        $transport->delete('foo');
        self::assertFalse($this->filesystem->exists(getcwd().'/assets/_symfony_scheduler_/foo.json'));
    }

    public function testTaskCannotBePausedTwice(): void
    {
        $objectNormalizer = new ObjectNormalizer(null, null, null, new PropertyInfoExtractor([], [new PhpDocExtractor(), new ReflectionExtractor()]));

        $serializer = new Serializer([new TaskNormalizer(new DateTimeNormalizer(), new DateTimeZoneNormalizer(), new DateIntervalNormalizer(), $objectNormalizer, new NotificationTaskBagNormalizer($objectNormalizer)), $objectNormalizer], [new JsonEncoder()]);
        $objectNormalizer->setSerializer($serializer);

        $transport = new FilesystemTransport(getcwd().'/assets', [], $serializer, new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ]));

        $transport->create(new NullTask('foo'));
        self::assertTrue($this->filesystem->exists(getcwd().'/assets/_symfony_scheduler_/foo.json'));

        $transport->pause('foo');

        $task = $transport->get('foo');
        self::assertSame(TaskInterface::PAUSED, $task->getState());

        self::expectException(LogicException::class);
        self::expectExceptionMessage('The task "foo" is already paused');
        $transport->pause('foo');
    }

    public function testTaskCanBePaused(): void
    {
        $objectNormalizer = new ObjectNormalizer(null, null, null, new PropertyInfoExtractor([], [new PhpDocExtractor(), new ReflectionExtractor()]));

        $serializer = new Serializer([new TaskNormalizer(new DateTimeNormalizer(), new DateTimeZoneNormalizer(), new DateIntervalNormalizer(), $objectNormalizer, new NotificationTaskBagNormalizer($objectNormalizer)), $objectNormalizer], [new JsonEncoder()]);
        $objectNormalizer->setSerializer($serializer);

        $transport = new FilesystemTransport(getcwd().'/assets', [], $serializer, new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ]));

        $transport->create(new NullTask('foo'));
        self::assertTrue($this->filesystem->exists(getcwd().'/assets/_symfony_scheduler_/foo.json'));

        $transport->pause('foo');

        $task = $transport->get('foo');
        self::assertSame(TaskInterface::PAUSED, $task->getState());
    }

    public function testTaskCannotBeResumedTwice(): void
    {
        $objectNormalizer = new ObjectNormalizer(null, null, null, new PropertyInfoExtractor([], [new PhpDocExtractor(), new ReflectionExtractor()]));

        $serializer = new Serializer([new TaskNormalizer(new DateTimeNormalizer(), new DateTimeZoneNormalizer(), new DateIntervalNormalizer(), $objectNormalizer, new NotificationTaskBagNormalizer($objectNormalizer)), $objectNormalizer], [new JsonEncoder()]);
        $objectNormalizer->setSerializer($serializer);

        $transport = new FilesystemTransport(getcwd().'/assets', [], $serializer, new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ]));

        $transport->create(new NullTask('foo'));
        self::assertTrue($this->filesystem->exists(getcwd().'/assets/_symfony_scheduler_/foo.json'));

        $transport->pause('foo');

        $task = $transport->get('foo');
        self::assertSame(TaskInterface::PAUSED, $task->getState());

        $transport->resume('foo');
        $task = $transport->get('foo');
        self::assertSame(TaskInterface::ENABLED, $task->getState());

        self::expectException(LogicException::class);
        self::expectExceptionMessage('The task "foo" is already enabled');
        $transport->resume('foo');
    }

    public function testTaskCanBeResumed(): void
    {
        $objectNormalizer = new ObjectNormalizer(null, null, null, new PropertyInfoExtractor([], [new PhpDocExtractor(), new ReflectionExtractor()]));

        $serializer = new Serializer([new TaskNormalizer(new DateTimeNormalizer(), new DateTimeZoneNormalizer(), new DateIntervalNormalizer(), $objectNormalizer, new NotificationTaskBagNormalizer($objectNormalizer)), $objectNormalizer], [new JsonEncoder()]);
        $objectNormalizer->setSerializer($serializer);

        $transport = new FilesystemTransport(getcwd().'/assets', [], $serializer, new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ]));

        $transport->create(new NullTask('foo'));
        self::assertTrue($this->filesystem->exists(getcwd().'/assets/_symfony_scheduler_/foo.json'));

        $transport->pause('foo');

        $task = $transport->get('foo');
        self::assertSame(TaskInterface::PAUSED, $task->getState());

        $transport->resume('foo');

        $task = $transport->get('foo');
        self::assertSame(TaskInterface::ENABLED, $task->getState());
    }

    public function testTaskCanBeCleared(): void
    {
        $objectNormalizer = new ObjectNormalizer(null, null, null, new PropertyInfoExtractor([], [new PhpDocExtractor(), new ReflectionExtractor()]));

        $serializer = new Serializer([new TaskNormalizer(new DateTimeNormalizer(), new DateTimeZoneNormalizer(), new DateIntervalNormalizer(), $objectNormalizer, new NotificationTaskBagNormalizer($objectNormalizer)), $objectNormalizer], [new JsonEncoder()]);
        $objectNormalizer->setSerializer($serializer);

        $transport = new FilesystemTransport(getcwd().'/assets', [], $serializer, new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ]));

        $transport->create(new NullTask('foo'));
        $transport->create(new NullTask('bar'));
        self::assertTrue($this->filesystem->exists(getcwd().'/assets/_symfony_scheduler_/foo.json'));
        self::assertTrue($this->filesystem->exists(getcwd().'/assets/_symfony_scheduler_/bar.json'));

        $transport->clear();
        self::assertFalse($this->filesystem->exists(getcwd().'/assets/_symfony_scheduler_/foo.json'));
        self::assertFalse($this->filesystem->exists(getcwd().'/assets/_symfony_scheduler_/bar.json'));
    }
}
