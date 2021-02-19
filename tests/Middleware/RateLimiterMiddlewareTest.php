<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Middleware;

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use SchedulerBundle\Exception\MiddlewareException;
use SchedulerBundle\Middleware\RateLimiterMiddleware;
use SchedulerBundle\Task\TaskInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;
use Symfony\Component\RateLimiter\Storage\StorageInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class RateLimiterMiddlewareTest extends TestCase
{
    public function testMiddlewareCannotPreExecuteWithoutRateLimiter(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::never())->method('getMaxExecution');

        $middleware = new RateLimiterMiddleware();
        $middleware->preExecute($task);
    }

    public function testMiddlewareCannotPreExecuteWithoutMaxExecutionLimit(): void
    {
        $storage = $this->createMock(StorageInterface::class);

        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('getMaxExecution')->willReturn(null);

        $middleware = new RateLimiterMiddleware(new RateLimiterFactory([
            'id' => 'foo',
            'policy' => 'token_bucket',
        ], $storage));
        $middleware->preExecute($task);
    }

    public function testMiddlewareCannotPreExecuteWithoutReservationSupportingPolicy(): void
    {
        $storage = $this->createMock(StorageInterface::class);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('critical')->with(self::equalTo('A reservation cannot be created for task "foo", please ensure that the policy used supports it.'));

        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::exactly(2))->method('getName')->willReturn('foo');
        $task->expects(self::exactly(2))->method('getMaxExecution')->willReturn(5);

        $middleware = new RateLimiterMiddleware(new RateLimiterFactory([
            'id' => 'foo',
            'policy' => 'sliding_window',
            'limit' => 10,
            'interval' => '50',
        ], $storage), $logger);

        self::expectException(MiddlewareException::class);
        self::expectExceptionMessage('Reserving tokens is not supported by "Symfony\Component\RateLimiter\Policy\SlidingWindowLimiter');
        self::expectExceptionCode(0);
        $middleware->preExecute($task);
    }

    public function testMiddlewareCanPreExecuteWithReservationSupportingPolicy(): void
    {
        $storage = $this->createMock(StorageInterface::class);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('critical');

        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('getName')->willReturn('foo');
        $task->expects(self::exactly(2))->method('getMaxExecution')->willReturn(5);

        $middleware = new RateLimiterMiddleware(new RateLimiterFactory([
            'id' => 'foo',
            'policy' => 'token_bucket',
            'limit' => 10,
            'rate' => [
                'interval' => '5 seconds',
            ],
        ], $storage), $logger);

        $middleware->preExecute($task);
    }

    public function testMiddlewareCannotPostExecuteWithoutRateLimiter(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::never())->method('getMaxExecution');

        $middleware = new RateLimiterMiddleware();
        $middleware->postExecute($task);
    }

    public function testMiddlewareCannotPostExecuteWithoutMaxExecutionLimit(): void
    {
        $storage = $this->createMock(StorageInterface::class);

        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('getMaxExecution')->willReturn(null);

        $middleware = new RateLimiterMiddleware(new RateLimiterFactory([
            'id' => 'foo',
            'policy' => 'token_bucket',
        ], $storage));
        $middleware->postExecute($task);
    }

    public function testMiddlewareCannotPostExecuteWithoutAcceptedTokenConsumption(): void
    {
        $factory = new RateLimiterFactory([
            'id' => 'foo',
            'policy' => 'token_bucket',
            'limit' => 1,
            'rate' => [
                'interval' => '5 seconds',
            ],
        ], new InMemoryStorage());
        $factory->create('foo');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('critical')->with(self::equalTo('The execution limit for task "foo" has been exceeded'));

        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::exactly(3))->method('getName')->willReturn('foo');
        $task->expects(self::exactly(2))->method('getMaxExecution')->willReturn(0);

        $middleware = new RateLimiterMiddleware($factory, $logger);

        $middleware->postExecute($task);

        self::expectException(MiddlewareException::class);
        self::expectExceptionMessage('Rate Limit Exceeded');
        self::expectExceptionCode(0);
        $middleware->postExecute($task);
    }
}
