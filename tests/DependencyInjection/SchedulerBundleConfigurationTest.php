<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\DependencyInjection;

use PHPUnit\Framework\TestCase;
use SchedulerBundle\DependencyInjection\SchedulerBundleConfiguration;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class SchedulerBundleConfigurationTest extends TestCase
{
    public function testConfigurationCanBeEmpty(): void
    {
        $configuration = (new Processor())->processConfiguration(new SchedulerBundleConfiguration(), [
            'scheduler_bundle' => [],
        ]);

        self::assertArrayHasKey('path', $configuration);
        self::assertArrayHasKey('timezone', $configuration);
        self::assertArrayHasKey('tasks', $configuration);
        self::assertArrayHasKey('lock_store', $configuration);
    }

    public function testConfigurationCannotDefineTasksWithoutTransport(): void
    {
        self::expectException(InvalidConfigurationException::class);
        self::expectExceptionMessage('The transport must be configured to schedule tasks');
        self::expectExceptionCode(0);
        (new Processor())->processConfiguration(new SchedulerBundleConfiguration(), [
            'scheduler_bundle' => [
                'tasks' => [
                    'foo' => [
                        'type' => 'command',
                        'command' => 'cache:clear',
                        'expression' => '*/5 * * * *',
                        'description' => 'A simple cache clear task',
                        'options' => [
                            'env' => 'test',
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function testConfigurationCannotDefineChainedTaskWithoutTasks(): void
    {
        self::expectException(InvalidConfigurationException::class);
        self::expectExceptionMessage('The "chained" type requires that you provide tasks.');
        self::expectExceptionCode(0);
        (new Processor())->processConfiguration(new SchedulerBundleConfiguration(), [
            'scheduler_bundle' => [
                'transport' => [
                    'dsn' => 'cache://app',
                ],
                'tasks' => [
                    'foo' => [
                        'type' => 'chained',
                        'expression' => '*/5 * * * *',
                        'description' => 'A chained task',
                        'options' => [
                            'env' => 'test',
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function testConfigurationCannotDefineShellTaskWithArguments(): void
    {
        self::expectException(InvalidConfigurationException::class);
        self::expectExceptionMessage('The "arguments" option can only be defined for "command" task type.');
        self::expectExceptionCode(0);
        (new Processor())->processConfiguration(new SchedulerBundleConfiguration(), [
            'scheduler_bundle' => [
                'transport' => [
                    'dsn' => 'cache://app',
                ],
                'tasks' => [
                    'foo' => [
                        'type' => 'shell',
                        'arguments' => ['arg'],
                        'command' => ['ls', '-al'],
                        'expression' => '*/5 * * * *',
                        'description' => 'A shell task with args',
                        'options' => [
                            'env' => 'test',
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function testConfigurationCannotDefineShellTaskWithoutCommand(): void
    {
        self::expectException(InvalidConfigurationException::class);
        self::expectExceptionMessage('You must specify the "command" if you define "shell" task type.');
        self::expectExceptionCode(0);
        (new Processor())->processConfiguration(new SchedulerBundleConfiguration(), [
            'scheduler_bundle' => [
                'transport' => [
                    'dsn' => 'cache://app',
                ],
                'tasks' => [
                    'foo' => [
                        'type' => 'shell',
                        'arguments' => ['arg'],
                        'expression' => '*/5 * * * *',
                        'description' => 'A shell task with args',
                        'options' => [
                            'env' => 'test',
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function testConfigurationCannotDefinePriorityOutOfRange(): void
    {
        self::expectException(InvalidConfigurationException::class);
        self::expectExceptionMessage('The value -2000 is too small for path "scheduler_bundle.tasks.foo.priority". Should be greater than or equal to -1000');
        self::expectExceptionCode(0);
        (new Processor())->processConfiguration(new SchedulerBundleConfiguration(), [
            'scheduler_bundle' => [
                'transport' => [
                    'dsn' => 'cache://app',
                ],
                'tasks' => [
                    'foo' => [
                        'type' => 'shell',
                        'expression' => '*/5 * * * *',
                        'priority' => -2000,
                    ],
                ],
            ],
        ]);
    }

    public function testConfigurationCannotDefineCommandTaskWithoutCommand(): void
    {
        self::expectException(InvalidConfigurationException::class);
        self::expectExceptionMessage('You must specify the "command" if you define "command" task type.');
        self::expectExceptionCode(0);
        (new Processor())->processConfiguration(new SchedulerBundleConfiguration(), [
            'scheduler_bundle' => [
                'transport' => [
                    'dsn' => 'cache://app',
                ],
                'tasks' => [
                    'foo' => [
                        'type' => 'command',
                        'expression' => '*/5 * * * *',
                        'description' => 'A shell task with args',
                        'options' => [
                            'env' => 'test',
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function testConfigurationCannotDefineHttpTaskWithoutUrl(): void
    {
        self::expectException(InvalidConfigurationException::class);
        self::expectExceptionMessage('You must specify the "url" if you define "http" task type');
        self::expectExceptionCode(0);
        (new Processor())->processConfiguration(new SchedulerBundleConfiguration(), [
            'scheduler_bundle' => [
                'transport' => [
                    'dsn' => 'cache://app',
                ],
                'tasks' => [
                    'foo' => [
                        'type' => 'http',
                    ],
                ],
            ],
        ]);
    }

    public function testConfigurationCanDefineChainedTasks(): void
    {
        $configuration = (new Processor())->processConfiguration(new SchedulerBundleConfiguration(), [
            'scheduler_bundle' => [
                'transport' => [
                    'dsn' => 'cache://app',
                ],
                'tasks' => [
                    'random' => [
                        'type' => 'chained',
                        'tasks' => [
                            'foo' => [
                                'type' => 'shell',
                                'command' => ['ls', '-al'],
                                'expression' => '* * * * *',
                            ],
                            'bar' => [
                                'type' => 'command',
                                'command' => 'cache:clear',
                                'expression' => '* * * * *',
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        self::assertArrayHasKey('transport', $configuration);
        self::assertNotEmpty($configuration['transport']);
        self::assertArrayHasKey('dsn', $configuration['transport']);
        self::assertSame('cache://app', $configuration['transport']['dsn']);
        self::assertCount(1, $configuration['tasks']);
        self::assertArrayHasKey('random', $configuration['tasks']);
        self::assertCount(2, $configuration['tasks']['random']['tasks']);
        self::assertSame('chained', $configuration['tasks']['random']['type']);
        self::assertArrayHasKey('foo', $configuration['tasks']['random']['tasks']);
        self::assertSame('shell', $configuration['tasks']['random']['tasks']['foo']['type']);
        self::assertArrayHasKey('bar', $configuration['tasks']['random']['tasks']);
        self::assertSame('command', $configuration['tasks']['random']['tasks']['bar']['type']);
    }

    public function testConfigurationCanDefineCommandTask(): void
    {
        $configuration = (new Processor())->processConfiguration(new SchedulerBundleConfiguration(), [
            'scheduler_bundle' => [
                'transport' => [
                    'dsn' => 'cache://app',
                ],
                'tasks' => [
                    'foo' => [
                        'type' => 'command',
                        'description' => 'simple command task',
                        'expression' => '*/5 * * * *',
                        'command' => 'cache:clear',
                        'arguments' => ['arg1', 'arg2'],
                        'options' => ['env' =>'test', '--help', 'version'],
                    ],
                ],
            ],
        ]);

        self::assertCount(1, $configuration['tasks']);
        self::assertArrayHasKey('foo', $configuration['tasks']);
        self::assertSame('command', $configuration['tasks']['foo']['type']);
        self::assertSame('simple command task', $configuration['tasks']['foo']['description']);
        self::assertSame('*/5 * * * *', $configuration['tasks']['foo']['expression']);
        self::assertSame('cache:clear', $configuration['tasks']['foo']['command']);
        self::assertEquals([
            'arg1',
            'arg2',
        ], $configuration['tasks']['foo']['arguments']);
        self::assertEquals([
            "env" => "test",
            "--help",
            "version",
        ], $configuration['tasks']['foo']['options']);
    }

    public function testConfigurationCanDefineHttpTask(): void
    {
        $configuration = (new Processor())->processConfiguration(new SchedulerBundleConfiguration(), [
            'scheduler_bundle' => [
                'transport' => [
                    'dsn' => 'cache://app',
                ],
                'tasks' => [
                    'foo' => [
                        'type' => 'http',
                        'description' => 'simple http task',
                        'expression' => '*/5 * * * *',
                        'url' => 'https://symfony.com',
                        'method' => 'GET',
                        'client_options' => ['header' =>['User-Agent', 'My Fancy App']],
                    ],
                ],
            ],
        ]);

        self::assertCount(1, $configuration['tasks']);
        self::assertArrayHasKey('foo', $configuration['tasks']);
        self::assertSame('http', $configuration['tasks']['foo']['type']);
        self::assertSame('simple http task', $configuration['tasks']['foo']['description']);
        self::assertSame('*/5 * * * *', $configuration['tasks']['foo']['expression']);
        self::assertSame('GET', $configuration['tasks']['foo']['method']);
        self::assertEquals([
            'header' =>[
                'User-Agent',
                'My Fancy App',
            ],
        ], $configuration['tasks']['foo']['client_options']);
    }

    public function testConfigurationCanDefineNullTask(): void
    {
        $configuration = (new Processor())->processConfiguration(new SchedulerBundleConfiguration(), [
            'scheduler_bundle' => [
                'transport' => [
                    'dsn' => 'cache://app',
                ],
                'tasks' => [
                    'foo' => [
                        'type' => 'null',
                        'description' => 'simple null task',
                    ],
                ],
            ],
        ]);

        self::assertCount(1, $configuration['tasks']);
        self::assertArrayHasKey('foo', $configuration['tasks']);
        self::assertSame('null', $configuration['tasks']['foo']['type']);
        self::assertSame('simple null task', $configuration['tasks']['foo']['description']);
    }

    public function testConfigurationCanDefineShellTask(): void
    {
        $configuration = (new Processor())->processConfiguration(new SchedulerBundleConfiguration(), [
            'scheduler_bundle' => [
                'transport' => [
                    'dsn' => 'cache://app',
                ],
                'tasks' => [
                    'foo' => [
                        'type' => 'shell',
                        'description' => 'simple shell task',
                        'expression' => '*/5 * * * *',
                        'command' => ['ls', '-al'],
                        'cwd' =>'/tmp',
                        'environment_variables' => ['APP_ENV' => 'test'],
                    ],
                ],
            ],
        ]);

        self::assertCount(1, $configuration['tasks']);
        self::assertArrayHasKey('foo', $configuration['tasks']);
        self::assertSame('shell', $configuration['tasks']['foo']['type']);
        self::assertSame('simple shell task', $configuration['tasks']['foo']['description']);
        self::assertSame('*/5 * * * *', $configuration['tasks']['foo']['expression']);
        self::assertEquals(['ls', '-al'], $configuration['tasks']['foo']['command']);
        self::assertSame('/tmp', $configuration['tasks']['foo']['cwd']);
        self::assertEquals(['APP_ENV' => 'test'], $configuration['tasks']['foo']['environment_variables']);
    }

    public function testConfigurationCanDefineSpecificRateLimiter(): void
    {
        $configuration = (new Processor())->processConfiguration(new SchedulerBundleConfiguration(), [
            'scheduler_bundle' => [
                'rate_limiter' => 'foo',
            ],
        ]);

        self::assertArrayHasKey('rate_limiter', $configuration);
        self::assertNotNull($configuration['rate_limiter']);
        self::assertSame('foo', $configuration['rate_limiter']);
    }

    public function testConfigurationCanDefineCacheTransport(): void
    {
        $configuration = (new Processor())->processConfiguration(new SchedulerBundleConfiguration(), [
            'scheduler_bundle' => [
                'transport' => [
                    'dsn' => 'cache://app',
                ],
            ],
        ]);

        self::assertArrayHasKey('transport', $configuration);
        self::assertNotEmpty($configuration['transport']);
        self::assertArrayHasKey('dsn', $configuration['transport']);
        self::assertSame('cache://app', $configuration['transport']['dsn']);
    }
}
