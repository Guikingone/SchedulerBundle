<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Bridge\ApiPlatform;

use ApiPlatform\Core\DataProvider\RestrictedDataProviderInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use SchedulerBundle\Bridge\ApiPlatform\TaskDataProvider;
use SchedulerBundle\Exception\InvalidArgumentException;
use SchedulerBundle\SchedulePolicy\SchedulePolicyOrchestrator;
use SchedulerBundle\Task\NullTask;
use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Transport\InMemoryTransport;
use stdClass;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class TaskDataProviderTest extends TestCase
{
    public function testProviderSupport(): void
    {
        $provider = new TaskDataProvider(new InMemoryTransport([], new SchedulePolicyOrchestrator([])));

        self::assertInstanceOf(RestrictedDataProviderInterface::class, $provider);
        self::assertFalse($provider->supports(stdClass::class));
        self::assertTrue($provider->supports(TaskInterface::class));
    }

    public function testProviderCannotReturnUndefinedTask(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('critical')
            ->with(self::equalTo('The task "foo" cannot be found'), self::equalTo([
                'error' => 'The task "foo" does not exist or is invalid',
            ]))
        ;

        $provider = new TaskDataProvider(new InMemoryTransport([], new SchedulePolicyOrchestrator([])), $logger);

        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage('The task "foo" does not exist or is invalid');
        self::expectExceptionCode(0);
        $provider->getItem(TaskInterface::class, 'foo');
    }

    public function testProviderCanReturnTask(): void
    {
        $task = new NullTask('foo');

        $transport = new InMemoryTransport([], new SchedulePolicyOrchestrator([]));
        $transport->create($task);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('critical');

        $provider = new TaskDataProvider($transport, $logger);

        self::assertSame($task, $provider->getItem(TaskInterface::class, 'foo'));
    }
}
