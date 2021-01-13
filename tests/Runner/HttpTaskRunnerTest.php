<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Runner;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use SchedulerBundle\Runner\HttpTaskRunner;
use SchedulerBundle\Task\HttpTask;
use SchedulerBundle\Task\NullTask;
use SchedulerBundle\Task\TaskInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class HttpTaskRunnerTest extends TestCase
{
    public function testRunnerSupport(): void
    {
        $runner = new HttpTaskRunner();

        self::assertFalse($runner->support(new NullTask('foo')));
        self::assertTrue($runner->support(new HttpTask('foo', 'https://symfony.com', 'GET')));
    }

    public function testRunnerCanGenerateErrorOutput(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse(\json_encode([
                'error' => 404,
                'message' => 'Resource not found',
            ]), ['http_code' => 404]),
        ]);

        $runner = new HttpTaskRunner($httpClient);
        $output = $runner->run(new HttpTask('foo', 'https://symfony.com', 'GET'));

        self::assertSame('HTTP 404 returned for "https://symfony.com/".', $output->getOutput());
        self::assertSame(TaskInterface::ERRORED, $output->getTask()->getExecutionState());
    }

    public function testRunnerCanGenerateSuccessOutput(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse(\json_encode([
                'body' => 'test',
            ]), ['http_code' => 200]),
        ]);

        $runner = new HttpTaskRunner($httpClient);
        $output = $runner->run(new HttpTask('foo', 'https://symfony.com', 'GET'));

        self::assertSame('{"body":"test"}', $output->getOutput());
        self::assertSame(TaskInterface::SUCCEED, $output->getTask()->getExecutionState());
    }
}
