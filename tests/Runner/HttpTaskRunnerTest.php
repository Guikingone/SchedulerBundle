<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Runner;

use PHPUnit\Framework\TestCase;
use SchedulerBundle\Task\Output;
use SchedulerBundle\Task\ShellTask;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use SchedulerBundle\Runner\HttpTaskRunner;
use SchedulerBundle\Task\HttpTask;
use SchedulerBundle\Task\NullTask;
use SchedulerBundle\Task\TaskInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use function json_encode;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class HttpTaskRunnerTest extends TestCase
{
    public function testRunnerSupport(): void
    {
        $httpTaskRunner = new HttpTaskRunner();

        self::assertFalse($httpTaskRunner->support(new NullTask('foo')));
        self::assertTrue($httpTaskRunner->support(new HttpTask('foo', 'https://symfony.com', 'GET')));
    }

    public function testRunnerCannotSupportInvalidTask(): void
    {
        $httpClient = $this->createMock(HttpClientInterface::class);

        $shellTask = new ShellTask('foo', ['echo', 'Symfony']);

        $httpTaskRunner = new HttpTaskRunner($httpClient);
        $output = $httpTaskRunner->run($shellTask);

        self::assertSame(TaskInterface::ERRORED, $shellTask->getExecutionState());
        self::assertSame(Output::ERROR, $output->getType());
        self::assertNull($output->getOutput());
        self::assertSame($shellTask, $output->getTask());
    }

    public function testRunnerCanGenerateErrorOutput(): void
    {
        $mockHttpClient = new MockHttpClient([
            new MockResponse(json_encode([
                'error' => 404,
                'message' => 'Resource not found',
            ], JSON_THROW_ON_ERROR), ['http_code' => 404]),
        ]);

        $httpTaskRunner = new HttpTaskRunner($mockHttpClient);
        $output = $httpTaskRunner->run(new HttpTask('foo', 'https://symfony.com', 'GET'));

        self::assertSame('HTTP 404 returned for "https://symfony.com/".', $output->getOutput());
        self::assertSame(TaskInterface::ERRORED, $output->getTask()->getExecutionState());
    }

    public function testRunnerCanGenerateSuccessOutput(): void
    {
        $mockHttpClient = new MockHttpClient([
            new MockResponse(json_encode([
                'body' => 'test',
            ], JSON_THROW_ON_ERROR), ['http_code' => 200]),
        ]);

        $httpTaskRunner = new HttpTaskRunner($mockHttpClient);
        $output = $httpTaskRunner->run(new HttpTask('foo', 'https://symfony.com', 'GET'));

        self::assertSame('{"body":"test"}', $output->getOutput());
        self::assertSame(TaskInterface::SUCCEED, $output->getTask()->getExecutionState());
    }
}
