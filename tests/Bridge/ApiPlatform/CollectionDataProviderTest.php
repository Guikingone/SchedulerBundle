<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Bridge\ApiPlatform;

use ApiPlatform\Core\DataProvider\RestrictedDataProviderInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use SchedulerBundle\Bridge\ApiPlatform\CollectionDataProvider;
use SchedulerBundle\Bridge\ApiPlatform\Filter\SearchFilter;
use SchedulerBundle\Exception\RuntimeException;
use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Task\TaskListInterface;
use SchedulerBundle\Transport\TransportInterface;
use stdClass;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class CollectionDataProviderTest extends TestCase
{
    public function testProviderSupport(): void
    {
        $transport = $this->createMock(TransportInterface::class);

        $provider = new CollectionDataProvider(new SearchFilter(), $transport);

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

        $provider = new CollectionDataProvider(new SearchFilter(), $transport, $logger);

        self::expectException(RuntimeException::class);
        self::expectExceptionMessage('Random error');
        self::expectExceptionCode(0);
        $provider->getCollection(TaskInterface::class);
    }

    public function testProviderCanReturnTaskList(): void
    {
        $list = $this->createMock(TaskListInterface::class);
        $list->expects(self::never())->method('filter');

        $transport = $this->createMock(TransportInterface::class);
        $transport->expects(self::once())->method('list')->willReturn($list);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('critical');

        $provider = new CollectionDataProvider(new SearchFilter(), $transport, $logger);

        self::assertSame($list, $provider->getCollection(TaskInterface::class));
    }

    public function testProviderCannotReturnFilteredTaskListWithoutFilters(): void
    {
        $list = $this->createMock(TaskListInterface::class);
        $list->expects(self::never())->method('filter')->willReturnSelf();

        $transport = $this->createMock(TransportInterface::class);
        $transport->expects(self::once())->method('list')->willReturn($list);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('critical');

        $provider = new CollectionDataProvider(new SearchFilter(), $transport, $logger);
        $provider->getCollection(TaskInterface::class, 'GET', [
            'filters' => [],
        ]);
    }

    public function testProviderCanReturnFilteredTaskList(): void
    {
        $list = $this->createMock(TaskListInterface::class);
        $list->expects(self::once())->method('filter')->willReturnSelf();

        $transport = $this->createMock(TransportInterface::class);
        $transport->expects(self::once())->method('list')->willReturn($list);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('critical');

        $provider = new CollectionDataProvider(new SearchFilter(), $transport, $logger);
        $provider->getCollection(TaskInterface::class, 'GET', [
            'filters' => [
                'expression' => '* * * * *',
            ],
        ]);
    }
}
