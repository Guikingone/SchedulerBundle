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
        self::assertArrayNotHasKey('probe', $configuration);
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
        self::assertSame('foo', $configuration['tasks']['random']['tasks'][0]['name']);
        self::assertSame('shell', $configuration['tasks']['random']['tasks'][0]['type']);
        self::assertSame('bar', $configuration['tasks']['random']['tasks'][1]['name']);
        self::assertSame('command', $configuration['tasks']['random']['tasks'][1]['type']);
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

    public function testConfigurationCanDefineProbe(): void
    {
        $configuration = (new Processor())->processConfiguration(new SchedulerBundleConfiguration(), [
            'scheduler_bundle' => [
                'probe' => [
                    'enabled' => true,
                    'path' => '/_foo',
                ],
            ],
        ]);

        self::assertArrayHasKey('probe', $configuration);
        self::assertTrue($configuration['probe']['enabled']);
        self::assertSame('/_foo', $configuration['probe']['path']);
        self::assertArrayHasKey('clients', $configuration['probe']);
        self::assertEmpty($configuration['probe']['clients']);

        self::assertCount(0, $configuration['tasks']);
    }

    public function testConfigurationCanEnableProbeWithTasks(): void
    {
        $configuration = (new Processor())->processConfiguration(new SchedulerBundleConfiguration(), [
            'scheduler_bundle' => [
                'probe' => [
                    'enabled' => true,
                    'path' => '/_foo',
                ],
                'transport' => [
                    'dsn' => 'memory://first_in_first_out',
                ],
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

        self::assertArrayHasKey('probe', $configuration);
        self::assertTrue($configuration['probe']['enabled']);
        self::assertSame('/_foo', $configuration['probe']['path']);
        self::assertArrayHasKey('clients', $configuration['probe']);
        self::assertEmpty($configuration['probe']['clients']);

        self::assertCount(1, $configuration['tasks']);
    }

    public function testConfigurationCanDefineProbeClients(): void
    {
        $configuration = (new Processor())->processConfiguration(new SchedulerBundleConfiguration(), [
            'scheduler_bundle' => [
                'probe' => [
                    'enabled' => true,
                    'path' => '/_foo',
                    'clients' => [
                        'bar' => [
                            'externalProbePath' => '/_external_probe',
                            'errorOnFailedTasks' => true,
                            'delay' => 2000,
                        ],
                        'foo' => [
                            'externalProbePath' => '/_external_probe',
                            'errorOnFailedTasks' => true,
                            'delay' => 1000,
                        ],
                    ],
                ],
            ],
        ]);

        self::assertArrayHasKey('probe', $configuration);
        self::assertTrue($configuration['probe']['enabled']);
        self::assertSame('/_foo', $configuration['probe']['path']);
        self::assertArrayHasKey('clients', $configuration['probe']);
        self::assertNotEmpty($configuration['probe']['clients']);
        self::assertCount(2, $configuration['probe']['clients']);
        self::assertArrayHasKey('bar', $configuration['probe']['clients']);
        self::assertSame('/_external_probe', $configuration['probe']['clients']['bar']['externalProbePath']);
        self::assertTrue($configuration['probe']['clients']['bar']['errorOnFailedTasks']);
        self::assertSame(2000, $configuration['probe']['clients']['bar']['delay']);
        self::assertArrayHasKey('foo', $configuration['probe']['clients']);
        self::assertSame('/_external_probe', $configuration['probe']['clients']['foo']['externalProbePath']);
        self::assertTrue($configuration['probe']['clients']['foo']['errorOnFailedTasks']);
        self::assertSame(1000, $configuration['probe']['clients']['foo']['delay']);

        self::assertCount(2, $configuration['tasks']);
        self::assertArrayHasKey('foo', $configuration['tasks']);
        self::assertSame('probe', $configuration['tasks']['foo']['type']);
        self::assertSame('* * * * *', $configuration['tasks']['foo']['expression']);
        self::assertArrayHasKey('bar', $configuration['tasks']);
        self::assertSame('probe', $configuration['tasks']['bar']['type']);
        self::assertSame('* * * * *', $configuration['tasks']['bar']['expression']);
    }
}
