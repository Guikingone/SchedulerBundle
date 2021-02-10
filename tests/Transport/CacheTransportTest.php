<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Transport;

use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemPoolInterface;
use SchedulerBundle\Exception\InvalidArgumentException;
use SchedulerBundle\Exception\RuntimeException;
use SchedulerBundle\SchedulePolicy\FirstInFirstOutPolicy;
use SchedulerBundle\SchedulePolicy\SchedulePolicyOrchestrator;
use SchedulerBundle\Serializer\NotificationTaskBagNormalizer;
use SchedulerBundle\Serializer\TaskNormalizer;
use SchedulerBundle\Task\NullTask;
use SchedulerBundle\Task\ShellTask;
use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Transport\CacheTransport;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\DateIntervalNormalizer;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Serializer\Normalizer\DateTimeZoneNormalizer;
use Symfony\Component\Serializer\Normalizer\JsonSerializableNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class CacheTransportTest extends TestCase
{
    public function testTransportCanBeConfigured(): void
    {
        $serializer = $this->createMock(SerializerInterface::class);

        $transport = new CacheTransport([
            'execution_mode' => 'nice',
        ], new ArrayAdapter(), $serializer, new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ]));

        self::assertSame('nice', $transport->getExecutionMode());
    }

    public function testTransportCannotReturnUndefinedTask(): void
    {
        $objectNormalizer = new ObjectNormalizer(null, null, null, new PropertyInfoExtractor([], [new PhpDocExtractor(), new ReflectionExtractor()]));

        $serializer = new Serializer([
            new TaskNormalizer(
                new DateTimeNormalizer(),
                new DateTimeZoneNormalizer(),
                new DateIntervalNormalizer(),
                $objectNormalizer,
                new NotificationTaskBagNormalizer($objectNormalizer)
            ),
            new DateTimeNormalizer(),
            new DateIntervalNormalizer(),
            new JsonSerializableNormalizer(),
            $objectNormalizer
        ], [new JsonEncoder()]);
        $objectNormalizer->setSerializer($serializer);

        $transport = new CacheTransport([], new ArrayAdapter(), $serializer, new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ]));

        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage('The task "foo" does not exist');
        self::expectExceptionCode(0);
        $transport->get('foo');
    }

    public function testTransportCannotReturnInternalTaskList(): void
    {
        $objectNormalizer = new ObjectNormalizer(null, null, null, new PropertyInfoExtractor([], [new PhpDocExtractor(), new ReflectionExtractor()]));

        $serializer = new Serializer([
            new TaskNormalizer(
                new DateTimeNormalizer(),
                new DateTimeZoneNormalizer(),
                new DateIntervalNormalizer(),
                $objectNormalizer,
                new NotificationTaskBagNormalizer($objectNormalizer)
            ),
            new DateTimeNormalizer(),
            new DateIntervalNormalizer(),
            new JsonSerializableNormalizer(),
            $objectNormalizer
        ], [new JsonEncoder()]);
        $objectNormalizer->setSerializer($serializer);
        $transport = new CacheTransport([], new ArrayAdapter(), $serializer, new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ]));

        self::expectException(RuntimeException::class);
        self::expectExceptionMessage('This key is internal and cannot be accessed');
        self::expectExceptionCode(0);
        $transport->get('_scheduler_task_list');
    }

    public function testTransportCanReturnTask(): void
    {
        $objectNormalizer = new ObjectNormalizer(null, null, null, new PropertyInfoExtractor([], [new PhpDocExtractor(), new ReflectionExtractor()]));

        $serializer = new Serializer([
            new TaskNormalizer(
                new DateTimeNormalizer(),
                new DateTimeZoneNormalizer(),
                new DateIntervalNormalizer(),
                $objectNormalizer,
                new NotificationTaskBagNormalizer($objectNormalizer)
            ),
            new DateTimeNormalizer(),
            new DateIntervalNormalizer(),
            new JsonSerializableNormalizer(),
            $objectNormalizer
        ], [new JsonEncoder()]);
        $objectNormalizer->setSerializer($serializer);

        $transport = new CacheTransport([], new ArrayAdapter(), $serializer, new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ]));
        $transport->create(new NullTask('foo'));

        $task = $transport->get('foo');
        self::assertSame('foo', $task->getName());
        self::assertSame('* * * * *', $task->getExpression());
    }

    public function testTransportCanListTasks(): void
    {
        $objectNormalizer = new ObjectNormalizer(null, null, null, new PropertyInfoExtractor([], [new PhpDocExtractor(), new ReflectionExtractor()]));

        $serializer = new Serializer([
            new TaskNormalizer(
                new DateTimeNormalizer(),
                new DateTimeZoneNormalizer(),
                new DateIntervalNormalizer(),
                $objectNormalizer,
                new NotificationTaskBagNormalizer($objectNormalizer)
            ),
            new DateTimeNormalizer(),
            new DateIntervalNormalizer(),
            new JsonSerializableNormalizer(),
            $objectNormalizer
        ], [new JsonEncoder()]);
        $objectNormalizer->setSerializer($serializer);

        $transport = new CacheTransport([], new ArrayAdapter(), $serializer, new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ]));
        $transport->create(new NullTask('foo'));

        $list = $transport->list();

        self::assertNotEmpty($list);
        self::assertSame('foo', $list->get('foo')->getName());
    }

    public function testTransportCannotCreateExistingTask(): void
    {
        $objectNormalizer = new ObjectNormalizer(null, null, null, new PropertyInfoExtractor([], [new PhpDocExtractor(), new ReflectionExtractor()]));

        $serializer = new Serializer([
            new TaskNormalizer(
                new DateTimeNormalizer(),
                new DateTimeZoneNormalizer(),
                new DateIntervalNormalizer(),
                $objectNormalizer,
                new NotificationTaskBagNormalizer($objectNormalizer)
            ),
            new DateTimeNormalizer(),
            new DateIntervalNormalizer(),
            new JsonSerializableNormalizer(),
            $objectNormalizer
        ], [new JsonEncoder()]);
        $objectNormalizer->setSerializer($serializer);

        $pool = $this->createMock(CacheItemPoolInterface::class);
        $pool->expects(self::exactly(3))->method('hasItem')
            ->withConsecutive([self::equalTo('_scheduler_task_list')], [self::equalTo('foo')])
            ->willReturnOnConsecutiveCalls([true], [false], [true])
        ;

        $transport = new CacheTransport([], $pool, $serializer, new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ]));

        $transport->create(new NullTask('foo'));
        $transport->create(new NullTask('foo'));
    }

    public function testTransportCannotUpdateUndefinedTask(): void
    {
        $objectNormalizer = new ObjectNormalizer(null, null, null, new PropertyInfoExtractor([], [new PhpDocExtractor(), new ReflectionExtractor()]));

        $serializer = new Serializer([
            new TaskNormalizer(
                new DateTimeNormalizer(),
                new DateTimeZoneNormalizer(),
                new DateIntervalNormalizer(),
                $objectNormalizer,
                new NotificationTaskBagNormalizer($objectNormalizer)
            ),
            new DateTimeNormalizer(),
            new DateIntervalNormalizer(),
            new JsonSerializableNormalizer(),
            $objectNormalizer
        ], [new JsonEncoder()]);
        $objectNormalizer->setSerializer($serializer);

        $transport = new CacheTransport([], new ArrayAdapter(), $serializer, new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ]));

        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage('The task "foo" does not exist');
        self::expectExceptionCode(0);
        $transport->update('foo', new NullTask('foo'));
    }

    public function testTransportCanUpdateTask(): void
    {
        $objectNormalizer = new ObjectNormalizer(null, null, null, new PropertyInfoExtractor([], [new PhpDocExtractor(), new ReflectionExtractor()]));

        $serializer = new Serializer([
            new TaskNormalizer(
                new DateTimeNormalizer(),
                new DateTimeZoneNormalizer(),
                new DateIntervalNormalizer(),
                $objectNormalizer,
                new NotificationTaskBagNormalizer($objectNormalizer)
            ),
            new DateTimeNormalizer(),
            new DateIntervalNormalizer(),
            new JsonSerializableNormalizer(),
            $objectNormalizer
        ], [new JsonEncoder()]);
        $objectNormalizer->setSerializer($serializer);

        $transport = new CacheTransport([], new ArrayAdapter(), $serializer, new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ]));
        $transport->create(new NullTask('foo'));

        $transport->update('foo', new ShellTask('foo', []));

        self::assertInstanceOf(ShellTask::class, $transport->get('foo'));
    }

    public function testTransportCannotPauseAlreadyPausedTask(): void
    {
        $objectNormalizer = new ObjectNormalizer(null, null, null, new PropertyInfoExtractor([], [new PhpDocExtractor(), new ReflectionExtractor()]));

        $serializer = new Serializer([
            new TaskNormalizer(
                new DateTimeNormalizer(),
                new DateTimeZoneNormalizer(),
                new DateIntervalNormalizer(),
                $objectNormalizer,
                new NotificationTaskBagNormalizer($objectNormalizer)
            ),
            new DateTimeNormalizer(),
            new DateIntervalNormalizer(),
            new JsonSerializableNormalizer(),
            $objectNormalizer
        ], [new JsonEncoder()]);
        $objectNormalizer->setSerializer($serializer);

        $transport = new CacheTransport([], new ArrayAdapter(), $serializer, new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ]));
        $transport->create(new NullTask('foo'));

        $transport->pause('foo');

        self::expectException(RuntimeException::class);
        self::expectExceptionMessage('The task "foo" is already paused');
        self::expectExceptionCode(0);
        $transport->pause('foo');
    }

    public function testTransportCanPauseTask(): void
    {
        $objectNormalizer = new ObjectNormalizer(null, null, null, new PropertyInfoExtractor([], [new PhpDocExtractor(), new ReflectionExtractor()]));

        $serializer = new Serializer([
            new TaskNormalizer(
                new DateTimeNormalizer(),
                new DateTimeZoneNormalizer(),
                new DateIntervalNormalizer(),
                $objectNormalizer,
                new NotificationTaskBagNormalizer($objectNormalizer)
            ),
            new DateTimeNormalizer(),
            new DateIntervalNormalizer(),
            new JsonSerializableNormalizer(),
            $objectNormalizer
        ], [new JsonEncoder()]);
        $objectNormalizer->setSerializer($serializer);

        $transport = new CacheTransport([], new ArrayAdapter(), $serializer, new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ]));
        $transport->create(new NullTask('foo'));
        $transport->pause('foo');

        self::assertSame(TaskInterface::PAUSED, $transport->get('foo')->getState());
    }

    public function testTransportCannotResumeAlreadyResumedTask(): void
    {
        $objectNormalizer = new ObjectNormalizer(null, null, null, new PropertyInfoExtractor([], [new PhpDocExtractor(), new ReflectionExtractor()]));

        $serializer = new Serializer([
            new TaskNormalizer(
                new DateTimeNormalizer(),
                new DateTimeZoneNormalizer(),
                new DateIntervalNormalizer(),
                $objectNormalizer,
                new NotificationTaskBagNormalizer($objectNormalizer)
            ),
            new DateTimeNormalizer(),
            new DateIntervalNormalizer(),
            new JsonSerializableNormalizer(),
            $objectNormalizer
        ], [new JsonEncoder()]);
        $objectNormalizer->setSerializer($serializer);

        $transport = new CacheTransport([], new ArrayAdapter(), $serializer, new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ]));
        $transport->create(new NullTask('foo'));

        self::expectException(RuntimeException::class);
        self::expectExceptionMessage('The task "foo" is already enabled');
        self::expectExceptionCode(0);
        $transport->resume('foo');
    }

    public function testTransportCanResumePausedTask(): void
    {
        $objectNormalizer = new ObjectNormalizer(null, null, null, new PropertyInfoExtractor([], [new PhpDocExtractor(), new ReflectionExtractor()]));

        $serializer = new Serializer([
            new TaskNormalizer(
                new DateTimeNormalizer(),
                new DateTimeZoneNormalizer(),
                new DateIntervalNormalizer(),
                $objectNormalizer,
                new NotificationTaskBagNormalizer($objectNormalizer)
            ),
            new DateTimeNormalizer(),
            new DateIntervalNormalizer(),
            new JsonSerializableNormalizer(),
            $objectNormalizer
        ], [new JsonEncoder()]);
        $objectNormalizer->setSerializer($serializer);

        $transport = new CacheTransport([], new ArrayAdapter(), $serializer, new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ]));
        $transport->create(new NullTask('foo'));
        $transport->pause('foo');
        self::assertSame(TaskInterface::PAUSED, $transport->get('foo')->getState());

        $transport->resume('foo');
        self::assertSame(TaskInterface::ENABLED, $transport->get('foo')->getState());
    }

    public function testTransportCannotDeleteUndefinedTask(): void
    {
        $objectNormalizer = new ObjectNormalizer(null, null, null, new PropertyInfoExtractor([], [new PhpDocExtractor(), new ReflectionExtractor()]));

        $serializer = new Serializer([
            new TaskNormalizer(
                new DateTimeNormalizer(),
                new DateTimeZoneNormalizer(),
                new DateIntervalNormalizer(),
                $objectNormalizer,
                new NotificationTaskBagNormalizer($objectNormalizer)
            ),
            new DateTimeNormalizer(),
            new DateIntervalNormalizer(),
            new JsonSerializableNormalizer(),
            $objectNormalizer
        ], [new JsonEncoder()]);
        $objectNormalizer->setSerializer($serializer);
        $transport = new CacheTransport([], new ArrayAdapter(), $serializer, new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ]));

        $transport->create(new NullTask('foo'));
        self::assertNotEmpty($transport->list());

        $transport->delete('bar');
        self::assertNotEmpty($transport->list());
    }

    public function testTransportCanDeleteTask(): void
    {
        $objectNormalizer = new ObjectNormalizer(null, null, null, new PropertyInfoExtractor([], [new PhpDocExtractor(), new ReflectionExtractor()]));

        $serializer = new Serializer([
            new TaskNormalizer(
                new DateTimeNormalizer(),
                new DateTimeZoneNormalizer(),
                new DateIntervalNormalizer(),
                $objectNormalizer,
                new NotificationTaskBagNormalizer($objectNormalizer)
            ),
            new DateTimeNormalizer(),
            new DateIntervalNormalizer(),
            new JsonSerializableNormalizer(),
            $objectNormalizer
        ], [new JsonEncoder()]);
        $objectNormalizer->setSerializer($serializer);
        $transport = new CacheTransport([], new ArrayAdapter(), $serializer, new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ]));

        $transport->create(new NullTask('foo'));
        self::assertNotEmpty($transport->list());

        $transport->delete('foo');
        self::assertEmpty($transport->list());
    }

    public function testTransportCanClear(): void
    {
        $objectNormalizer = new ObjectNormalizer(null, null, null, new PropertyInfoExtractor([], [new PhpDocExtractor(), new ReflectionExtractor()]));

        $serializer = new Serializer([
            new TaskNormalizer(
                new DateTimeNormalizer(),
                new DateTimeZoneNormalizer(),
                new DateIntervalNormalizer(),
                $objectNormalizer,
                new NotificationTaskBagNormalizer($objectNormalizer)
            ),
            new DateTimeNormalizer(),
            new DateIntervalNormalizer(),
            new JsonSerializableNormalizer(),
            $objectNormalizer
        ], [new JsonEncoder()]);
        $objectNormalizer->setSerializer($serializer);
        $transport = new CacheTransport([], new ArrayAdapter(), $serializer, new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ]));
        $transport->create(new NullTask('foo'));

        $transport->clear();
        self::assertEmpty($transport->list());
    }
}
