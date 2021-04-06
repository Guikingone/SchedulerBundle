<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Runner;

use PHPUnit\Framework\TestCase;
use SchedulerBundle\Runner\ProbeTaskRunner;
use SchedulerBundle\Task\Output;
use SchedulerBundle\Task\ProbeTask;
use SchedulerBundle\Task\ShellTask;
use SchedulerBundle\Task\TaskInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class ProbeTaskRunnerTest extends TestCase
{
    public function testRunnerSupport(): void
    {
        $runner = new ProbeTaskRunner();

        self::assertFalse($runner->support(new ShellTask('foo', [])));
        self::assertTrue($runner->support(new ProbeTask('foo', '/_probe')));
    }

    public function testRunnerCannotRunInvalidTask(): void
    {
        $runner = new ProbeTaskRunner();

        $output = $runner->run(new ShellTask('foo', []));

        self::assertSame(Output::ERROR, $output->getType());
        self::assertNull($output->getOutput());
        self::assertInstanceOf(ShellTask::class, $output->getTask());
    }

    public function testRunnerCanRunTaskWithEmptyProbeState(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->expects(self::once())->method('toArray')->with(self::equalTo(true))->willReturn([]);

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->expects(self::once())->method('request')
            ->with(self::equalTo('GET'), self::equalTo('/_probe'))
            ->willReturn($response)
        ;

        $runner = new ProbeTaskRunner($httpClient);
        $output = $runner->run(new ProbeTask('foo', '/_probe'));

        self::assertSame(Output::ERROR, $output->getType());
        self::assertSame('The probe state is invalid', $output->getOutput());
        self::assertInstanceOf(ProbeTask::class, $output->getTask());
        self::assertSame(TaskInterface::ERRORED, $output->getTask()->getExecutionState());
    }

    public function testRunnerCanRunTaskWithInvalidProbeStateAndTaskFailureEnabled(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->expects(self::once())->method('toArray')->with(self::equalTo(true))->willReturn([
            'failedTasks' => 1,
        ]);

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->expects(self::once())->method('request')
            ->with(self::equalTo('GET'), self::equalTo('/_probe'))
            ->willReturn($response)
        ;

        $runner = new ProbeTaskRunner($httpClient);
        $output = $runner->run(new ProbeTask('foo', '/_probe', true));

        self::assertSame(Output::ERROR, $output->getType());
        self::assertSame('The probe state is invalid', $output->getOutput());
        self::assertInstanceOf(ProbeTask::class, $output->getTask());
        self::assertSame(TaskInterface::ERRORED, $output->getTask()->getExecutionState());
    }

    public function testRunnerCanRunTaskWithInvalidProbeStateAndTaskFailureDisabled(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->expects(self::once())->method('toArray')->with(self::equalTo(true))->willReturn([
            'failedTasks' => 1,
        ]);

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->expects(self::once())->method('request')
            ->with(self::equalTo('GET'), self::equalTo('/_probe'))
            ->willReturn($response)
        ;

        $runner = new ProbeTaskRunner($httpClient);
        $output = $runner->run(new ProbeTask('foo', '/_probe'));

        self::assertSame(Output::SUCCESS, $output->getType());
        self::assertSame('The probe succeed', $output->getOutput());
        self::assertInstanceOf(ProbeTask::class, $output->getTask());
        self::assertSame(TaskInterface::SUCCEED, $output->getTask()->getExecutionState());
    }

    public function testRunnerCanRunTaskWithValidProbeStateAndErrorOnFailedTasksEnabled(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->expects(self::once())->method('toArray')->with(self::equalTo(true))->willReturn([
            'failedTasks' => 0,
        ]);

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->expects(self::once())->method('request')
            ->with(self::equalTo('GET'), self::equalTo('/_probe'))
            ->willReturn($response)
        ;

        $runner = new ProbeTaskRunner($httpClient);
        $output = $runner->run(new ProbeTask('foo', '/_probe', true));

        self::assertSame(Output::SUCCESS, $output->getType());
        self::assertSame('The probe succeed', $output->getOutput());
        self::assertInstanceOf(ProbeTask::class, $output->getTask());
        self::assertSame(TaskInterface::SUCCEED, $output->getTask()->getExecutionState());
    }
}
