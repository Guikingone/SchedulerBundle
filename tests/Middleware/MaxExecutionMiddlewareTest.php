<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Middleware;

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use SchedulerBundle\Exception\MiddlewareException;
use SchedulerBundle\Middleware\MaxExecutionMiddleware;
use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Worker\WorkerInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;
use Symfony\Component\RateLimiter\Storage\StorageInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class MaxExecutionMiddlewareTest extends TestCase
{
    public function testMiddlewareCannotPreExecuteWithoutRateLimiter(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::never())->method('getMaxExecutions');

        $maxExecutionMiddleware = new MaxExecutionMiddleware();
        $maxExecutionMiddleware->preExecute($task);
    }

    public function testMiddlewareCannotPreExecuteWithoutMaxExecutionLimit(): void
    {
        $storage = $this->createMock(StorageInterface::class);

        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('getMaxExecutions')->willReturn(null);

        $maxExecutionMiddleware = new MaxExecutionMiddleware(new RateLimiterFactory([
            'id' => 'foo',
            'policy' => 'token_bucket',
        ], $storage));
        $maxExecutionMiddleware->preExecute($task);
    }

    public function testMiddlewareCannotPreExecuteWithoutReservationSupportingPolicy(): void
    {
        $storage = $this->createMock(StorageInterface::class);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('critical')->with(self::equalTo('A reservation cannot be created for task "foo", please ensure that the policy used supports it.'));

        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::exactly(2))->method('getName')->willReturn('foo');
        $task->expects(self::once())->method('getMaxExecutions')->willReturn(5);

        $maxExecutionMiddleware = new MaxExecutionMiddleware(new RateLimiterFactory([
            'id' => 'foo',
            'policy' => 'sliding_window',
            'limit' => 10,
            'interval' => '50',
        ], $storage), $logger);

        self::expectException(MiddlewareException::class);
        self::expectExceptionMessage('Reserving tokens is not supported by "Symfony\Component\RateLimiter\Policy\SlidingWindowLimiter');
        self::expectExceptionCode(0);
        $maxExecutionMiddleware->preExecute($task);
    }

    public function testMiddlewareCanPreExecuteWithReservationSupportingPolicy(): void
    {
        $storage = $this->createMock(StorageInterface::class);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('critical');

        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('getName')->willReturn('foo');
        $task->expects(self::once())->method('getMaxExecutions')->willReturn(5);

        $maxExecutionMiddleware = new MaxExecutionMiddleware(new RateLimiterFactory([
            'id' => 'foo',
            'policy' => 'token_bucket',
            'limit' => 10,
            'rate' => [
                'interval' => '5 seconds',
            ],
        ], $storage), $logger);

        $maxExecutionMiddleware->preExecute($task);
    }

    public function testMiddlewareCannotPostExecuteWithoutRateLimiter(): void
    {
        $worker = $this->createMock(WorkerInterface::class);

        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::never())->method('getMaxExecutions');

        $maxExecutionMiddleware = new MaxExecutionMiddleware();
        $maxExecutionMiddleware->postExecute($task, $worker);
    }

    public function testMiddlewareCannotPostExecuteWithoutMaxExecutionLimit(): void
    {
        $worker = $this->createMock(WorkerInterface::class);
        $storage = $this->createMock(StorageInterface::class);

        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('getMaxExecutions')->willReturn(null);

        $maxExecutionMiddleware = new MaxExecutionMiddleware(new RateLimiterFactory([
            'id' => 'foo',
            'policy' => 'token_bucket',
        ], $storage));
        $maxExecutionMiddleware->postExecute($task, $worker);
    }

    public function testMiddlewareCannotPostExecuteWithoutAcceptedTokenConsumption(): void
    {
        $worker = $this->createMock(WorkerInterface::class);

        $rateLimiterFactory = new RateLimiterFactory([
            'id' => 'foo',
            'policy' => 'token_bucket',
            'limit' => 1,
            'rate' => [
                'interval' => '5 seconds',
            ],
        ], new InMemoryStorage());
        $rateLimiterFactory->create('foo');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('critical')->with(self::equalTo('The execution limit for task "foo" has been exceeded'));

        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::exactly(3))->method('getName')->willReturn('foo');
        $task->expects(self::exactly(2))->method('getMaxExecutions')->willReturn(0);

        $maxExecutionMiddleware = new MaxExecutionMiddleware($rateLimiterFactory, $logger);

        $maxExecutionMiddleware->postExecute($task, $worker);

        self::expectException(MiddlewareException::class);
        self::expectExceptionMessage('Rate Limit Exceeded');
        self::expectExceptionCode(0);
        $maxExecutionMiddleware->postExecute($task, $worker);
    }
}
