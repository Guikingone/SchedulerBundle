<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Bridge\ApiPlatform;

use ApiPlatform\Core\DataProvider\RestrictedDataProviderInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use SchedulerBundle\Bridge\ApiPlatform\TaskListDataProvider;
use SchedulerBundle\Bridge\ApiPlatform\Filter\SearchFilter;
use SchedulerBundle\Exception\RuntimeException;
use SchedulerBundle\SchedulePolicy\FirstInFirstOutPolicy;
use SchedulerBundle\SchedulePolicy\SchedulePolicyOrchestrator;
use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Transport\InMemoryTransport;
use SchedulerBundle\Transport\TransportInterface;
use stdClass;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class TaskListDataProviderTest extends TestCase
{
    public function testProviderSupport(): void
    {
        $provider = new TaskListDataProvider(new SearchFilter(), new InMemoryTransport([], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ])));

        self::assertInstanceOf(RestrictedDataProviderInterface::class, $provider);
        self::assertFalse($provider->supports(stdClass::class));
        self::assertTrue($provider->supports(TaskInterface::class));
    }

    public function testProviderCannotReturnListWithError(): void
    {
        $transport = $this->createMock(TransportInterface::class);
        $transport->expects(self::once())->method('list')
            ->willThrowException(new RuntimeException('Random error'))
        ;

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('critical')
            ->with(
                self::equalTo('The list cannot be retrieved'),
                self::equalTo([
                    'error' => 'Random error',
                ])
            )
        ;

        $provider = new TaskListDataProvider(new SearchFilter(), $transport, $logger);

        self::expectException(RuntimeException::class);
        self::expectExceptionMessage('Random error');
        self::expectExceptionCode(0);
        $provider->getCollection(TaskInterface::class);
    }

    public function testProviderCanReturnTaskList(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('critical');

        $provider = new TaskListDataProvider(new SearchFilter(), new InMemoryTransport([], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ])), $logger);

        self::assertCount(0, $provider->getCollection(TaskInterface::class));
    }

    public function testProviderCannotReturnFilteredTaskListWithoutFilters(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('critical');

        $provider = new TaskListDataProvider(new SearchFilter(), new InMemoryTransport([], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ])), $logger);
        $provider->getCollection(TaskInterface::class, 'GET', [
            'filters' => [],
        ]);
    }

    public function testProviderCanReturnFilteredTaskList(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('critical');

        $provider = new TaskListDataProvider(new SearchFilter(), new InMemoryTransport([], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ])), $logger);
        $provider->getCollection(TaskInterface::class, 'GET', [
            'filters' => [
                'expression' => '* * * * *',
            ],
        ]);
    }
}
