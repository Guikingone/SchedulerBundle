<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\SchedulerBundle\Transport;

use PHPUnit\Framework\TestCase;
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

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class FilesystemTransportTest extends TestCase
{
    private $filesystem;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        $this->filesystem = new Filesystem();
        $this->filesystem->remove(__DIR__.'/assets/**/*.json');
    }

    /**
     * {@inheritdoc}
     */
    protected function tearDown(): void
    {
        $this->filesystem->remove(__DIR__.'/assets/_symfony_scheduler_/bar.json');
        $this->filesystem->remove(__DIR__.'/assets/_symfony_scheduler_/foo.json');
    }

    public function testTaskListCanBeRetrieved(): void
    {
        $objectNormalizer = new ObjectNormalizer(null, null, null, new PropertyInfoExtractor([], [new PhpDocExtractor(), new ReflectionExtractor()]));

        $serializer = new Serializer([new TaskNormalizer(new DateTimeNormalizer(), new DateTimeZoneNormalizer(), new DateIntervalNormalizer(), $objectNormalizer), new DateTimeNormalizer(), new DateIntervalNormalizer(), new JsonSerializableNormalizer(), $objectNormalizer], [new JsonEncoder()]);
        $objectNormalizer->setSerializer($serializer);

        $transport = new FilesystemTransport(__DIR__.'/assets', [], $serializer);

        $task = new NullTask('bar');
        $task->setScheduledAt(new \DateTimeImmutable());

        $transport->create($task);
        static::assertTrue($this->filesystem->exists(__DIR__.'/assets/_symfony_scheduler_/bar.json'));

        $list = $transport->list();
        static::assertNotEmpty($list);
        static::assertInstanceOf(NullTask::class, $list->get('bar'));
    }

    public function testTaskListCanBeRetrievedAndSorted(): void
    {
        $objectNormalizer = new ObjectNormalizer(null, null, null, new PropertyInfoExtractor([], [new PhpDocExtractor(), new ReflectionExtractor()]));

        $serializer = new Serializer([new TaskNormalizer(new DateTimeNormalizer(), new DateTimeZoneNormalizer(), new DateIntervalNormalizer(), $objectNormalizer), new DateTimeNormalizer(), new DateIntervalNormalizer(), new JsonSerializableNormalizer(), $objectNormalizer], [new JsonEncoder()]);
        $objectNormalizer->setSerializer($serializer);

        $task = new NullTask('bar');

        $schedulePolicyOrchestrator = $this->createMock(SchedulePolicyOrchestratorInterface::class);
        $schedulePolicyOrchestrator->expects(self::once())->method('sort')->willReturn([$task]);

        $transport = new FilesystemTransport(__DIR__.'/assets', [
            'execution_mode' => 'first_in_first_out',
        ], $serializer, $schedulePolicyOrchestrator);

        $transport->create($task);
        static::assertTrue($this->filesystem->exists(__DIR__.'/assets/_symfony_scheduler_/bar.json'));

        $list = $transport->list();
        static::assertNotEmpty($list);
        static::assertInstanceOf(NullTask::class, $list->get('bar'));
    }

    public function testTaskCannotBeRetrievedWithUndefinedTask(): void
    {
        $objectNormalizer = new ObjectNormalizer(null, null, null, new PropertyInfoExtractor([], [new PhpDocExtractor(), new ReflectionExtractor()]));

        $serializer = new Serializer([new TaskNormalizer(new DateTimeNormalizer(), new DateTimeZoneNormalizer(), new DateIntervalNormalizer(), $objectNormalizer), $objectNormalizer], [new JsonEncoder()]);
        $objectNormalizer->setSerializer($serializer);

        $transport = new FilesystemTransport(__DIR__.'/assets', [], $serializer);

        static::expectException(InvalidArgumentException::class);
        static::expectExceptionMessage('The "bar" task does not exist');
        $transport->get('bar');
    }

    public function testTaskCanBeRetrieved(): void
    {
        $objectNormalizer = new ObjectNormalizer(null, null, null, new PropertyInfoExtractor([], [new PhpDocExtractor(), new ReflectionExtractor()]));

        $serializer = new Serializer([new TaskNormalizer(new DateTimeNormalizer(), new DateTimeZoneNormalizer(), new DateIntervalNormalizer(), $objectNormalizer), $objectNormalizer], [new JsonEncoder()]);
        $objectNormalizer->setSerializer($serializer);

        $transport = new FilesystemTransport(__DIR__.'/assets', [], $serializer);

        $transport->create(new NullTask('foo'));
        static::assertTrue($this->filesystem->exists(__DIR__.'/assets/_symfony_scheduler_/foo.json'));

        $task = $transport->get('foo');
        static::assertInstanceOf(NullTask::class, $task);
        static::assertSame('foo', $task->getName());
        static::assertSame('* * * * *', $task->getExpression());
    }

    public function testTaskCanBeCreated(): void
    {
        $objectNormalizer = new ObjectNormalizer(null, null, null, new PropertyInfoExtractor([], [new PhpDocExtractor(), new ReflectionExtractor()]));

        $serializer = new Serializer([new TaskNormalizer(new DateTimeNormalizer(), new DateTimeZoneNormalizer(), new DateIntervalNormalizer(), $objectNormalizer), $objectNormalizer], [new JsonEncoder()]);
        $objectNormalizer->setSerializer($serializer);

        $transport = new FilesystemTransport(__DIR__.'/assets', [], $serializer);

        $transport->create(new NullTask('foo'));
        static::assertTrue($this->filesystem->exists(__DIR__.'/assets/_symfony_scheduler_/foo.json'));
    }

    public function testTaskCannotBeUpdatedWhenUndefinedButShouldBeCreated(): void
    {
        $objectNormalizer = new ObjectNormalizer(null, null, null, new PropertyInfoExtractor([], [new PhpDocExtractor(), new ReflectionExtractor()]));

        $serializer = new Serializer([new TaskNormalizer(new DateTimeNormalizer(), new DateTimeZoneNormalizer(), new DateIntervalNormalizer(), $objectNormalizer), $objectNormalizer], [new JsonEncoder()]);
        $objectNormalizer->setSerializer($serializer);

        $transport = new FilesystemTransport(__DIR__.'/assets', [], $serializer);

        $transport->update('foo', new NullTask('foo'));

        $task = $transport->get('foo');
        static::assertInstanceOf(NullTask::class, $task);
        static::assertSame('foo', $task->getName());
        static::assertSame('* * * * *', $task->getExpression());
    }

    public function testTaskCanBeUpdated(): void
    {
        $objectNormalizer = new ObjectNormalizer(null, null, null, new PropertyInfoExtractor([], [new PhpDocExtractor(), new ReflectionExtractor()]));

        $serializer = new Serializer([new TaskNormalizer(new DateTimeNormalizer(), new DateTimeZoneNormalizer(), new DateIntervalNormalizer(), $objectNormalizer), $objectNormalizer], [new JsonEncoder()]);
        $objectNormalizer->setSerializer($serializer);

        $transport = new FilesystemTransport(__DIR__.'/assets', [], $serializer);

        $transport->create(new ShellTask('foo', ['echo', 'Symfony']));
        static::assertTrue($this->filesystem->exists(__DIR__.'/assets/_symfony_scheduler_/foo.json'));

        $task = $transport->get('foo');
        $task->setExpression('0 * * * *');
        static::assertSame('0 * * * *', $task->getExpression());

        $transport->update('foo', $task);
        $updatedTask = $transport->get('foo');

        static::assertSame('0 * * * *', $updatedTask->getExpression());
        static::assertTrue($this->filesystem->exists(__DIR__.'/assets/_symfony_scheduler_/foo.json'));
    }

    public function testTaskCanBeDeleted(): void
    {
        $objectNormalizer = new ObjectNormalizer(null, null, null, new PropertyInfoExtractor([], [new PhpDocExtractor(), new ReflectionExtractor()]));

        $serializer = new Serializer([new TaskNormalizer(new DateTimeNormalizer(), new DateTimeZoneNormalizer(), new DateIntervalNormalizer(), $objectNormalizer), $objectNormalizer], [new JsonEncoder()]);
        $objectNormalizer->setSerializer($serializer);

        $transport = new FilesystemTransport(__DIR__.'/assets', [], $serializer);

        $transport->create(new NullTask('foo'));
        static::assertTrue($this->filesystem->exists(__DIR__.'/assets/_symfony_scheduler_/foo.json'));

        $transport->delete('foo');
        static::assertFalse($this->filesystem->exists(__DIR__.'/assets/_symfony_scheduler_/foo.json'));
    }

    public function testTaskCannotBePausedTwice(): void
    {
        $objectNormalizer = new ObjectNormalizer(null, null, null, new PropertyInfoExtractor([], [new PhpDocExtractor(), new ReflectionExtractor()]));

        $serializer = new Serializer([new TaskNormalizer(new DateTimeNormalizer(), new DateTimeZoneNormalizer(), new DateIntervalNormalizer(), $objectNormalizer), $objectNormalizer], [new JsonEncoder()]);
        $objectNormalizer->setSerializer($serializer);

        $transport = new FilesystemTransport(__DIR__.'/assets', [], $serializer);

        $transport->create(new NullTask('foo'));
        static::assertTrue($this->filesystem->exists(__DIR__.'/assets/_symfony_scheduler_/foo.json'));

        $transport->pause('foo');

        $task = $transport->get('foo');
        static::assertSame(TaskInterface::PAUSED, $task->getState());

        static::expectException(LogicException::class);
        static::expectExceptionMessage('The task "foo" is already paused');
        $transport->pause('foo');
    }

    public function testTaskCanBePaused(): void
    {
        $objectNormalizer = new ObjectNormalizer(null, null, null, new PropertyInfoExtractor([], [new PhpDocExtractor(), new ReflectionExtractor()]));

        $serializer = new Serializer([new TaskNormalizer(new DateTimeNormalizer(), new DateTimeZoneNormalizer(), new DateIntervalNormalizer(), $objectNormalizer), $objectNormalizer], [new JsonEncoder()]);
        $objectNormalizer->setSerializer($serializer);

        $transport = new FilesystemTransport(__DIR__.'/assets', [], $serializer);

        $transport->create(new NullTask('foo'));
        static::assertTrue($this->filesystem->exists(__DIR__.'/assets/_symfony_scheduler_/foo.json'));

        $transport->pause('foo');

        $task = $transport->get('foo');
        static::assertSame(TaskInterface::PAUSED, $task->getState());
    }

    public function testTaskCannotBeResumedTwice(): void
    {
        $objectNormalizer = new ObjectNormalizer(null, null, null, new PropertyInfoExtractor([], [new PhpDocExtractor(), new ReflectionExtractor()]));

        $serializer = new Serializer([new TaskNormalizer(new DateTimeNormalizer(), new DateTimeZoneNormalizer(), new DateIntervalNormalizer(), $objectNormalizer), $objectNormalizer], [new JsonEncoder()]);
        $objectNormalizer->setSerializer($serializer);

        $transport = new FilesystemTransport(__DIR__.'/assets', [], $serializer);

        $transport->create(new NullTask('foo'));
        static::assertTrue($this->filesystem->exists(__DIR__.'/assets/_symfony_scheduler_/foo.json'));

        $transport->pause('foo');

        $task = $transport->get('foo');
        static::assertSame(TaskInterface::PAUSED, $task->getState());

        $transport->resume('foo');
        $task = $transport->get('foo');
        static::assertSame(TaskInterface::ENABLED, $task->getState());

        static::expectException(LogicException::class);
        static::expectExceptionMessage('The task "foo" is already enabled');
        $transport->resume('foo');
    }

    public function testTaskCanBeResumed(): void
    {
        $objectNormalizer = new ObjectNormalizer(null, null, null, new PropertyInfoExtractor([], [new PhpDocExtractor(), new ReflectionExtractor()]));

        $serializer = new Serializer([new TaskNormalizer(new DateTimeNormalizer(), new DateTimeZoneNormalizer(), new DateIntervalNormalizer(), $objectNormalizer), $objectNormalizer], [new JsonEncoder()]);
        $objectNormalizer->setSerializer($serializer);

        $transport = new FilesystemTransport(__DIR__.'/assets', [], $serializer);

        $transport->create(new NullTask('foo'));
        static::assertTrue($this->filesystem->exists(__DIR__.'/assets/_symfony_scheduler_/foo.json'));

        $transport->pause('foo');

        $task = $transport->get('foo');
        static::assertSame(TaskInterface::PAUSED, $task->getState());

        $transport->resume('foo');

        $task = $transport->get('foo');
        static::assertSame(TaskInterface::ENABLED, $task->getState());
    }

    public function testTaskCanBeCleared(): void
    {
        $objectNormalizer = new ObjectNormalizer(null, null, null, new PropertyInfoExtractor([], [new PhpDocExtractor(), new ReflectionExtractor()]));

        $serializer = new Serializer([new TaskNormalizer(new DateTimeNormalizer(), new DateTimeZoneNormalizer(), new DateIntervalNormalizer(), $objectNormalizer), $objectNormalizer], [new JsonEncoder()]);
        $objectNormalizer->setSerializer($serializer);

        $transport = new FilesystemTransport(__DIR__.'/assets', [], $serializer);

        $transport->create(new NullTask('foo'));
        $transport->create(new NullTask('bar'));
        static::assertTrue($this->filesystem->exists(__DIR__.'/assets/_symfony_scheduler_/foo.json'));
        static::assertTrue($this->filesystem->exists(__DIR__.'/assets/_symfony_scheduler_/bar.json'));

        $transport->clear();
        static::assertFalse($this->filesystem->exists(__DIR__.'/assets/_symfony_scheduler_/foo.json'));
        static::assertFalse($this->filesystem->exists(__DIR__.'/assets/_symfony_scheduler_/bar.json'));
    }
}
