<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Bridge\ApiPlatform;

use ApiPlatform\Core\DataProvider\RestrictedDataProviderInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use SchedulerBundle\Bridge\ApiPlatform\ItemDataProvider;
use SchedulerBundle\Exception\InvalidArgumentException;
use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Transport\TransportInterface;
use stdClass;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class ItemDataProviderTest extends TestCase
{
    public function testProviderSupport(): void
    {
        $transport = $this->createMock(TransportInterface::class);

        $provider = new ItemDataProvider($transport);

        self::assertInstanceOf(RestrictedDataProviderInterface::class, $provider);
        self::assertFalse($provider->supports(stdClass::class));
        self::assertTrue($provider->supports(TaskInterface::class));
    }

    public function testProviderCannotReturnUndefinedTask(): void
    {
        $transport = $this->createMock(TransportInterface::class);
        $transport->expects(self::once())->method('get')->with(self::equalTo('foo'))
            ->willThrowException(new InvalidArgumentException('The task "foo" does not exist'))
        ;

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('critical')
            ->with(self::equalTo('The task "foo" cannot be found'), self::equalTo([
                'error' => 'The task "foo" does not exist',
            ]))
        ;

        $provider = new ItemDataProvider($transport, $logger);

        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage('The task "foo" does not exist');
        self::expectExceptionCode(0);
        $provider->getItem(TaskInterface::class, 'foo');
    }

    public function testProviderCanReturnTask(): void
    {
        $task = $this->createMock(TaskInterface::class);

        $transport = $this->createMock(TransportInterface::class);
        $transport->expects(self::once())->method('get')->with(self::equalTo('foo'))->willReturn($task);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('critical');

        $provider = new ItemDataProvider($transport, $logger);

        self::assertSame($task, $provider->getItem(TaskInterface::class, 'foo'));
    }
}
