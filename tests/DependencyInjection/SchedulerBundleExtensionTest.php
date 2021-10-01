<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\DependencyInjection;

use Generator;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use SchedulerBundle\Bridge\Doctrine\SchemaListener\SchedulerTransportDoctrineSchemaSubscriber;
use SchedulerBundle\Bridge\Doctrine\Transport\DoctrineTransportFactory;
use SchedulerBundle\Bridge\Redis\Transport\RedisTransportFactory;
use SchedulerBundle\Command\ConsumeTasksCommand;
use SchedulerBundle\Command\DebugMiddlewareCommand;
use SchedulerBundle\Command\DebugProbeCommand;
use SchedulerBundle\Command\ExecuteExternalProbeCommand;
use SchedulerBundle\Command\ExecuteTaskCommand;
use SchedulerBundle\Command\ListFailedTasksCommand;
use SchedulerBundle\Command\ListTasksCommand;
use SchedulerBundle\Command\RebootSchedulerCommand;
use SchedulerBundle\Command\RemoveFailedTaskCommand;
use SchedulerBundle\Command\RetryFailedTaskCommand;
use SchedulerBundle\Command\YieldTaskCommand;
use SchedulerBundle\DataCollector\SchedulerDataCollector;
use SchedulerBundle\DependencyInjection\SchedulerBundleConfiguration;
use SchedulerBundle\DependencyInjection\SchedulerBundleExtension;
use SchedulerBundle\EventListener\MercureEventSubscriber;
use SchedulerBundle\EventListener\ProbeStateSubscriber;
use SchedulerBundle\EventListener\StopWorkerOnSignalSubscriber;
use SchedulerBundle\EventListener\TaskLifecycleSubscriber;
use SchedulerBundle\EventListener\TaskLoggerSubscriber;
use SchedulerBundle\EventListener\TaskSubscriber;
use SchedulerBundle\EventListener\WorkerLifecycleSubscriber;
use SchedulerBundle\Expression\BuilderInterface;
use SchedulerBundle\Expression\ComputedExpressionBuilder;
use SchedulerBundle\Expression\CronExpressionBuilder;
use SchedulerBundle\Expression\Expression;
use SchedulerBundle\Expression\ExpressionBuilder;
use SchedulerBundle\Expression\ExpressionBuilderInterface;
use SchedulerBundle\Expression\FluentExpressionBuilder;
use SchedulerBundle\LazyScheduler;
use SchedulerBundle\Messenger\TaskToExecuteMessageHandler;
use SchedulerBundle\Messenger\TaskToPauseMessageHandler;
use SchedulerBundle\Messenger\TaskToYieldMessageHandler;
use SchedulerBundle\Middleware\MiddlewareStackInterface;
use SchedulerBundle\Middleware\NotifierMiddleware;
use SchedulerBundle\Middleware\PostExecutionMiddlewareInterface;
use SchedulerBundle\Middleware\PostSchedulingMiddlewareInterface;
use SchedulerBundle\Middleware\PreExecutionMiddlewareInterface;
use SchedulerBundle\Middleware\PreSchedulingMiddlewareInterface;
use SchedulerBundle\Middleware\MaxExecutionMiddleware;
use SchedulerBundle\Middleware\ProbeTaskMiddleware;
use SchedulerBundle\Middleware\SchedulerMiddlewareStack;
use SchedulerBundle\Middleware\SingleRunTaskMiddleware;
use SchedulerBundle\Middleware\TaskCallbackMiddleware;
use SchedulerBundle\Middleware\TaskExecutionMiddleware;
use SchedulerBundle\Middleware\TaskLockBagMiddleware;
use SchedulerBundle\Middleware\TaskUpdateMiddleware;
use SchedulerBundle\Middleware\WorkerMiddlewareStack;
use SchedulerBundle\Probe\Probe;
use SchedulerBundle\Probe\ProbeInterface;
use SchedulerBundle\Runner\CallbackTaskRunner;
use SchedulerBundle\Runner\ChainedTaskRunner;
use SchedulerBundle\Runner\CommandTaskRunner;
use SchedulerBundle\Runner\HttpTaskRunner;
use SchedulerBundle\Runner\MessengerTaskRunner;
use SchedulerBundle\Runner\NotificationTaskRunner;
use SchedulerBundle\Runner\NullTaskRunner;
use SchedulerBundle\Runner\ProbeTaskRunner;
use SchedulerBundle\Runner\RunnerInterface;
use SchedulerBundle\Runner\RunnerRegistry;
use SchedulerBundle\Runner\RunnerRegistryInterface;
use SchedulerBundle\Runner\ShellTaskRunner;
use SchedulerBundle\SchedulePolicy\BatchPolicy;
use SchedulerBundle\SchedulePolicy\DeadlinePolicy;
use SchedulerBundle\SchedulePolicy\ExecutionDurationPolicy;
use SchedulerBundle\SchedulePolicy\FirstInFirstOutPolicy;
use SchedulerBundle\SchedulePolicy\FirstInLastOutPolicy;
use SchedulerBundle\SchedulePolicy\IdlePolicy;
use SchedulerBundle\SchedulePolicy\MemoryUsagePolicy;
use SchedulerBundle\SchedulePolicy\NicePolicy;
use SchedulerBundle\SchedulePolicy\PolicyInterface;
use SchedulerBundle\SchedulePolicy\RoundRobinPolicy;
use SchedulerBundle\SchedulePolicy\SchedulePolicyOrchestrator;
use SchedulerBundle\SchedulePolicy\SchedulePolicyOrchestratorInterface;
use SchedulerBundle\Scheduler;
use SchedulerBundle\SchedulerInterface;
use SchedulerBundle\Serializer\AccessLockBagNormalizer;
use SchedulerBundle\Serializer\NotificationTaskBagNormalizer;
use SchedulerBundle\Serializer\TaskNormalizer;
use SchedulerBundle\Task\Builder\AbstractTaskBuilder;
use SchedulerBundle\Task\Builder\ChainedBuilder;
use SchedulerBundle\Task\Builder\CommandBuilder;
use SchedulerBundle\Task\Builder\HttpBuilder;
use SchedulerBundle\Task\Builder\NullBuilder;
use SchedulerBundle\Task\Builder\ProbeTaskBuilder;
use SchedulerBundle\Task\Builder\ShellBuilder;
use SchedulerBundle\Task\TaskBuilder;
use SchedulerBundle\Task\TaskBuilderInterface;
use SchedulerBundle\Task\TaskExecutionTracker;
use SchedulerBundle\Task\TaskExecutionTrackerInterface;
use SchedulerBundle\TaskBag\TaskBagInterface;
use SchedulerBundle\Transport\CacheTransportFactory;
use SchedulerBundle\Transport\Configuration\ConfigurationFactory;
use SchedulerBundle\Transport\Configuration\ConfigurationFactoryInterface;
use SchedulerBundle\Transport\Configuration\ConfigurationInterface;
use SchedulerBundle\Transport\Configuration\InMemoryConfigurationFactory;
use SchedulerBundle\Transport\Configuration\LazyConfigurationFactory;
use SchedulerBundle\Transport\FailOverTransportFactory;
use SchedulerBundle\Transport\FilesystemTransportFactory;
use SchedulerBundle\Transport\InMemoryTransportFactory;
use SchedulerBundle\Transport\LazyTransportFactory;
use SchedulerBundle\Transport\LongTailTransportFactory;
use SchedulerBundle\Transport\RoundRobinTransportFactory;
use SchedulerBundle\Transport\TransportFactory;
use SchedulerBundle\Transport\TransportFactoryInterface;
use SchedulerBundle\Transport\TransportInterface;
use SchedulerBundle\Worker\Worker;
use SchedulerBundle\Worker\WorkerInterface;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\Compiler\ResolveChildDefinitionsPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Mercure\Hub;
use Symfony\Component\Mercure\Jwt\StaticTokenProvider;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\PersistingStoreInterface;
use Symfony\Component\Lock\Store\StoreFactory;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Notifier\NotifierInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class SchedulerBundleExtensionTest extends TestCase
{
    public function testExtensionCannotBeConfiguredWithoutTransport(): void
    {
        $container = $this->getContainer([
            'path' => '/_foo',
            'timezone' => 'Europe/Paris',
        ]);

        self::assertFalse($container->hasParameter('scheduler.timezone'));
        self::assertFalse($container->hasParameter('scheduler.trigger_path'));
    }

    public function testParametersAreRegistered(): void
    {
        $container = $this->getContainer([
            'path' => '/_foo',
            'timezone' => 'Europe/Paris',
            'transport' => [
                'dsn' => 'memory://first_in_first_out',
            ],
        ]);

        self::assertTrue($container->hasParameter('scheduler.timezone'));
        self::assertSame('Europe/Paris', $container->getParameter('scheduler.timezone'));
        self::assertTrue($container->hasParameter('scheduler.trigger_path'));
        self::assertSame('/_foo', $container->getParameter('scheduler.trigger_path'));
        self::assertTrue($container->hasParameter('scheduler.scheduler_mode'));
        self::assertSame('default', $container->getParameter('scheduler.scheduler_mode'));
        self::assertTrue($container->hasParameter('scheduler.probe_enabled'));
        self::assertFalse($container->getParameter('scheduler.probe_enabled'));
        self::assertFalse($container->getParameter('scheduler.pool_support'));
    }

    public function testInterfacesForAutoconfigureAreRegistered(): void
    {
        $container = $this->getContainer([
            'path' => '/_foo',
            'timezone' => 'Europe/Paris',
            'transport' => [
                'dsn' => 'memory://first_in_first_out',
            ],
        ]);

        $autoconfigurationInterfaces = $container->getAutoconfiguredInstanceof();

        self::assertArrayHasKey(RunnerInterface::class, $autoconfigurationInterfaces);
        self::assertTrue($autoconfigurationInterfaces[RunnerInterface::class]->hasTag('scheduler.runner'));
        self::assertArrayHasKey(TransportInterface::class, $autoconfigurationInterfaces);
        self::assertTrue($autoconfigurationInterfaces[TransportInterface::class]->hasTag('scheduler.transport'));
        self::assertArrayHasKey(TransportFactoryInterface::class, $autoconfigurationInterfaces);
        self::assertTrue($autoconfigurationInterfaces[TransportFactoryInterface::class]->hasTag('scheduler.transport_factory'));
        self::assertArrayHasKey(ConfigurationInterface::class, $autoconfigurationInterfaces);
        self::assertTrue($autoconfigurationInterfaces[ConfigurationInterface::class]->hasTag('scheduler.configuration'));
        self::assertArrayHasKey(ConfigurationFactoryInterface::class, $autoconfigurationInterfaces);
        self::assertTrue($autoconfigurationInterfaces[ConfigurationFactoryInterface::class]->hasTag('scheduler.configuration_factory'));
        self::assertArrayHasKey(PolicyInterface::class, $autoconfigurationInterfaces);
        self::assertTrue($autoconfigurationInterfaces[PolicyInterface::class]->hasTag('scheduler.schedule_policy'));
        self::assertArrayHasKey(WorkerInterface::class, $autoconfigurationInterfaces);
        self::assertTrue($autoconfigurationInterfaces[WorkerInterface::class]->hasTag('scheduler.worker'));
        self::assertArrayHasKey(MiddlewareStackInterface::class, $autoconfigurationInterfaces);
        self::assertTrue($autoconfigurationInterfaces[MiddlewareStackInterface::class]->hasTag('scheduler.middleware_hub'));
        self::assertArrayHasKey(PreSchedulingMiddlewareInterface::class, $autoconfigurationInterfaces);
        self::assertTrue($autoconfigurationInterfaces[PreSchedulingMiddlewareInterface::class]->hasTag('scheduler.scheduler_middleware'));
        self::assertArrayHasKey(PostSchedulingMiddlewareInterface::class, $autoconfigurationInterfaces);
        self::assertTrue($autoconfigurationInterfaces[PostSchedulingMiddlewareInterface::class]->hasTag('scheduler.scheduler_middleware'));
        self::assertArrayHasKey(PreExecutionMiddlewareInterface::class, $autoconfigurationInterfaces);
        self::assertTrue($autoconfigurationInterfaces[PreExecutionMiddlewareInterface::class]->hasTag('scheduler.worker_middleware'));
        self::assertArrayHasKey(PostExecutionMiddlewareInterface::class, $autoconfigurationInterfaces);
        self::assertTrue($autoconfigurationInterfaces[PostExecutionMiddlewareInterface::class]->hasTag('scheduler.worker_middleware'));
        self::assertArrayHasKey(ExpressionBuilderInterface::class, $autoconfigurationInterfaces);
        self::assertTrue($autoconfigurationInterfaces[ExpressionBuilderInterface::class]->hasTag('scheduler.expression_builder'));
        self::assertArrayHasKey(BuilderInterface::class, $autoconfigurationInterfaces);
        self::assertTrue($autoconfigurationInterfaces[BuilderInterface::class]->hasTag('scheduler.task_builder'));
        self::assertArrayHasKey(ProbeInterface::class, $autoconfigurationInterfaces);
        self::assertTrue($autoconfigurationInterfaces[ProbeInterface::class]->hasTag('scheduler.probe'));
        self::assertArrayHasKey(TaskBagInterface::class, $autoconfigurationInterfaces);
        self::assertTrue($autoconfigurationInterfaces[TaskBagInterface::class]->hasTag('scheduler.task_bag'));
    }

    public function testConfigurationFactoriesAreRegistered(): void
    {
        $container = $this->getContainer([
            'path' => '/_foo',
            'timezone' => 'Europe/Paris',
            'transport' => [
                'dsn' => 'memory://first_in_first_out',
            ],
            'configuration' => [
                'dsn' => 'configuration://memory',
            ],
            'tasks' => [],
            'lock_store' => null,
        ]);

        self::assertTrue($container->hasDefinition(ConfigurationFactory::class));
        self::assertCount(1, $container->getDefinition(ConfigurationFactory::class)->getArguments());
        self::assertInstanceOf(TaggedIteratorArgument::class, $container->getDefinition(ConfigurationFactory::class)->getArgument(0));
        self::assertFalse($container->getDefinition(ConfigurationFactory::class)->isPublic());
        self::assertTrue($container->getDefinition(ConfigurationFactory::class)->hasTag('container.preload'));
        self::assertSame(ConfigurationFactory::class, $container->getDefinition(ConfigurationFactory::class)->getTag('container.preload')[0]['class']);

        self::assertTrue($container->hasDefinition(InMemoryConfigurationFactory::class));
        self::assertCount(0, $container->getDefinition(InMemoryConfigurationFactory::class)->getArguments());
        self::assertFalse($container->getDefinition(InMemoryConfigurationFactory::class)->isPublic());
        self::assertTrue($container->getDefinition(InMemoryConfigurationFactory::class)->hasTag('scheduler.configuration_factory'));
        self::assertTrue($container->getDefinition(InMemoryConfigurationFactory::class)->hasTag('container.preload'));
        self::assertSame(InMemoryConfigurationFactory::class, $container->getDefinition(InMemoryConfigurationFactory::class)->getTag('container.preload')[0]['class']);

        self::assertTrue($container->hasDefinition(LazyConfigurationFactory::class));
        self::assertCount(1, $container->getDefinition(LazyConfigurationFactory::class)->getArguments());
        self::assertInstanceOf(TaggedIteratorArgument::class, $container->getDefinition(LazyConfigurationFactory::class)->getArgument(0));
        self::assertFalse($container->getDefinition(LazyConfigurationFactory::class)->isPublic());
        self::assertTrue($container->getDefinition(LazyConfigurationFactory::class)->hasTag('scheduler.configuration_factory'));
        self::assertTrue($container->getDefinition(LazyConfigurationFactory::class)->hasTag('container.preload'));
        self::assertSame(LazyConfigurationFactory::class, $container->getDefinition(LazyConfigurationFactory::class)->getTag('container.preload')[0]['class']);
    }

    public function testConfigurationCanBeConfigured(): void
    {
        $container = $this->getContainer([
            'configuration' => [
                'dsn' => 'configuration://memory',
            ],
            'transport' => [
                'dsn' => 'memory://first_in_first_out',
            ],
            'tasks' => [],
        ]);

        self::assertTrue($container->hasDefinition('scheduler.configuration'));
        self::assertTrue($container->hasAlias(ConfigurationInterface::class));
        self::assertSame('scheduler.configuration', (string) $container->getAlias(ConfigurationInterface::class));
        self::assertCount(2, $container->getDefinition('scheduler.configuration')->getArguments());
        self::assertSame('configuration://memory', $container->getDefinition('scheduler.configuration')->getArgument(0));
        self::assertInstanceOf(Reference::class, $container->getDefinition('scheduler.configuration')->getArgument(1));
        self::assertSame(ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $container->getDefinition('scheduler.configuration')->getArgument(1)->getInvalidBehavior());
        self::assertInstanceOf(Reference::class, $container->getDefinition('scheduler.configuration')->getFactory()[0]);
        self::assertSame('build', $container->getDefinition('scheduler.configuration')->getFactory()[1]);
        self::assertFalse($container->getDefinition('scheduler.configuration')->isPublic());
        self::assertTrue($container->getDefinition('scheduler.configuration')->hasTag('scheduler.configuration'));
        self::assertTrue($container->getDefinition('scheduler.configuration')->hasTag('container.preload'));
        self::assertSame(ConfigurationInterface::class, $container->getDefinition('scheduler.configuration')->getTag('container.preload')[0]['class']);
    }

    public function testTransportFactoriesAreRegistered(): void
    {
        $container = $this->getContainer([
            'path' => '/_foo',
            'timezone' => 'Europe/Paris',
            'transport' => [
                'dsn' => 'memory://first_in_first_out',
            ],
            'tasks' => [],
            'lock_store' => null,
        ]);

        self::assertTrue($container->hasDefinition(TransportFactory::class));
        self::assertCount(1, $container->getDefinition(TransportFactory::class)->getArguments());
        self::assertInstanceOf(TaggedIteratorArgument::class, $container->getDefinition(TransportFactory::class)->getArgument(0));
        self::assertTrue($container->getDefinition(TransportFactory::class)->hasTag('container.preload'));
        self::assertSame(TransportFactory::class, $container->getDefinition(TransportFactory::class)->getTag('container.preload')[0]['class']);
        self::assertTrue($container->hasAlias(TransportFactoryInterface::class));

        self::assertTrue($container->hasDefinition(InMemoryTransportFactory::class));
        self::assertFalse($container->getDefinition(InMemoryTransportFactory::class)->isPublic());
        self::assertTrue($container->getDefinition(InMemoryTransportFactory::class)->hasTag('scheduler.transport_factory'));
        self::assertTrue($container->getDefinition(InMemoryTransportFactory::class)->hasTag('container.preload'));
        self::assertSame(InMemoryTransportFactory::class, $container->getDefinition(InMemoryTransportFactory::class)->getTag('container.preload')[0]['class']);

        self::assertTrue($container->hasDefinition(FilesystemTransportFactory::class));
        self::assertFalse($container->getDefinition(FilesystemTransportFactory::class)->isPublic());
        self::assertTrue($container->getDefinition(FilesystemTransportFactory::class)->hasTag('scheduler.transport_factory'));
        self::assertTrue($container->getDefinition(FilesystemTransportFactory::class)->hasTag('container.preload'));
        self::assertSame(FilesystemTransportFactory::class, $container->getDefinition(FilesystemTransportFactory::class)->getTag('container.preload')[0]['class']);

        self::assertTrue($container->hasDefinition(FailOverTransportFactory::class));
        self::assertFalse($container->getDefinition(FailOverTransportFactory::class)->isPublic());
        self::assertCount(1, $container->getDefinition(FailOverTransportFactory::class)->getArguments());
        self::assertInstanceOf(TaggedIteratorArgument::class, $container->getDefinition(FailOverTransportFactory::class)->getArgument(0));
        self::assertTrue($container->getDefinition(FailOverTransportFactory::class)->hasTag('scheduler.transport_factory'));
        self::assertTrue($container->getDefinition(FailOverTransportFactory::class)->hasTag('container.preload'));
        self::assertSame(FailOverTransportFactory::class, $container->getDefinition(FailOverTransportFactory::class)->getTag('container.preload')[0]['class']);

        self::assertTrue($container->hasDefinition(LongTailTransportFactory::class));
        self::assertFalse($container->getDefinition(LongTailTransportFactory::class)->isPublic());
        self::assertCount(1, $container->getDefinition(LongTailTransportFactory::class)->getArguments());
        self::assertInstanceOf(TaggedIteratorArgument::class, $container->getDefinition(LongTailTransportFactory::class)->getArgument(0));
        self::assertTrue($container->getDefinition(LongTailTransportFactory::class)->hasTag('scheduler.transport_factory'));
        self::assertTrue($container->getDefinition(LongTailTransportFactory::class)->hasTag('container.preload'));
        self::assertSame(LongTailTransportFactory::class, $container->getDefinition(LongTailTransportFactory::class)->getTag('container.preload')[0]['class']);

        self::assertTrue($container->hasDefinition(RoundRobinTransportFactory::class));
        self::assertFalse($container->getDefinition(RoundRobinTransportFactory::class)->isPublic());
        self::assertCount(1, $container->getDefinition(RoundRobinTransportFactory::class)->getArguments());
        self::assertInstanceOf(TaggedIteratorArgument::class, $container->getDefinition(RoundRobinTransportFactory::class)->getArgument(0));
        self::assertTrue($container->getDefinition(RoundRobinTransportFactory::class)->hasTag('scheduler.transport_factory'));
        self::assertTrue($container->getDefinition(RoundRobinTransportFactory::class)->hasTag('container.preload'));
        self::assertSame(RoundRobinTransportFactory::class, $container->getDefinition(RoundRobinTransportFactory::class)->getTag('container.preload')[0]['class']);

        self::assertTrue($container->hasDefinition(LazyTransportFactory::class));
        self::assertFalse($container->getDefinition(LazyTransportFactory::class)->isPublic());
        self::assertCount(1, $container->getDefinition(LazyTransportFactory::class)->getArguments());
        self::assertInstanceOf(TaggedIteratorArgument::class, $container->getDefinition(LazyTransportFactory::class)->getArgument(0));
        self::assertTrue($container->getDefinition(LazyTransportFactory::class)->hasTag('scheduler.transport_factory'));
        self::assertTrue($container->getDefinition(LazyTransportFactory::class)->hasTag('container.preload'));
        self::assertSame(LazyTransportFactory::class, $container->getDefinition(LazyTransportFactory::class)->getTag('container.preload')[0]['class']);

        self::assertTrue($container->hasDefinition(RedisTransportFactory::class));
        self::assertFalse($container->getDefinition(RedisTransportFactory::class)->isPublic());
        self::assertTrue($container->getDefinition(RedisTransportFactory::class)->hasTag('scheduler.transport_factory'));
        self::assertTrue($container->getDefinition(RedisTransportFactory::class)->hasTag('container.preload'));
        self::assertSame(RedisTransportFactory::class, $container->getDefinition(RedisTransportFactory::class)->getTag('container.preload')[0]['class']);

        self::assertFalse($container->hasDefinition(CacheTransportFactory::class));
    }

    public function testCacheTransportFactoryIsRegistered(): void
    {
        $container = $this->getContainer([
            'path' => '/_foo',
            'timezone' => 'Europe/Paris',
            'transport' => [
                'dsn' => 'cache://app',
            ],
            'tasks' => [],
            'lock_store' => null,
        ]);

        self::assertTrue($container->hasDefinition(CacheTransportFactory::class));
        self::assertFalse($container->getDefinition(CacheTransportFactory::class)->isPublic());
        self::assertCount(1, $container->getDefinition(CacheTransportFactory::class)->getArguments());
        self::assertInstanceOf(Reference::class, $container->getDefinition(CacheTransportFactory::class)->getArgument(0));
        self::assertSame('cache.app', (string) $container->getDefinition(CacheTransportFactory::class)->getArgument(0));
        self::assertTrue($container->getDefinition(CacheTransportFactory::class)->hasTag('scheduler.transport_factory'));
        self::assertTrue($container->getDefinition(CacheTransportFactory::class)->hasTag('container.preload'));
        self::assertSame(CacheTransportFactory::class, $container->getDefinition(CacheTransportFactory::class)->getTag('container.preload')[0]['class']);
    }

    public function testTransportIsRegistered(): void
    {
        $schedulerBundleExtension = new SchedulerBundleExtension();

        $containerBuilder = new ContainerBuilder();
        $containerBuilder->register(SerializerInterface::class, SerializerInterface::class);

        $schedulerBundleExtension->load([
            'scheduler_bundle' => [
                'path' => '/_foo',
                'timezone' => 'Europe/Paris',
                'transport' => [
                    'dsn' => 'memory://first_in_first_out',
                ],
                'tasks' => [],
                'lock_store' => null,
            ],
        ], $containerBuilder);

        self::assertTrue($containerBuilder->hasDefinition('scheduler.transport'));
        self::assertTrue($containerBuilder->hasAlias(TransportInterface::class));
        self::assertCount(4, $containerBuilder->getDefinition('scheduler.transport')->getArguments());

        $factory = $containerBuilder->getDefinition('scheduler.transport')->getFactory();
        self::assertIsArray($factory);
        self::assertArrayHasKey(0, $factory);
        self::assertArrayHasKey(1, $factory);
        self::assertInstanceOf(Reference::class, $factory[0]);
        self::assertSame('createTransport', $factory[1]);

        self::assertSame('memory://first_in_first_out', $containerBuilder->getDefinition('scheduler.transport')->getArgument(0));
        self::assertSame([
            'execution_mode' => 'first_in_first_out',
            'path' => '%kernel.project_dir%/var/tasks',
        ], $containerBuilder->getDefinition('scheduler.transport')->getArgument(1));
        self::assertInstanceOf(Reference::class, $containerBuilder->getDefinition('scheduler.transport')->getArgument(2));
        self::assertInstanceOf(Reference::class, $containerBuilder->getDefinition('scheduler.transport')->getArgument(3));
        self::assertTrue($containerBuilder->getDefinition('scheduler.transport')->isShared());
        self::assertFalse($containerBuilder->getDefinition('scheduler.transport')->isPublic());
        self::assertTrue($containerBuilder->getDefinition('scheduler.transport')->hasTag('container.preload'));
        self::assertSame(TransportInterface::class, $containerBuilder->getDefinition('scheduler.transport')->getTag('container.preload')[0]['class']);
    }

    public function testSchedulerIsRegistered(): void
    {
        $container = $this->getContainer([
            'path' => '/_foo',
            'timezone' => 'Europe/Paris',
            'transport' => [
                'dsn' => 'memory://first_in_first_out',
            ],
            'tasks' => [],
            'lock_store' => null,
        ]);

        self::assertTrue($container->hasDefinition(Scheduler::class));
        self::assertTrue($container->hasAlias(SchedulerInterface::class));
        self::assertCount(5, $container->getDefinition(Scheduler::class)->getArguments());
        self::assertSame('Europe/Paris', $container->getDefinition(Scheduler::class)->getArgument(0));
        self::assertInstanceOf(Reference::class, $container->getDefinition(Scheduler::class)->getArgument(1));
        self::assertSame(TransportInterface::class, (string) $container->getDefinition(Scheduler::class)->getArgument(1));
        self::assertSame(ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $container->getDefinition(Scheduler::class)->getArgument(1)->getInvalidBehavior());
        self::assertInstanceOf(Reference::class, $container->getDefinition(Scheduler::class)->getArgument(2));
        self::assertSame(SchedulerMiddlewareStack::class, (string) $container->getDefinition(Scheduler::class)->getArgument(2));
        self::assertSame(ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $container->getDefinition(Scheduler::class)->getArgument(2)->getInvalidBehavior());
        self::assertInstanceOf(Reference::class, $container->getDefinition(Scheduler::class)->getArgument(3));
        self::assertSame(EventDispatcherInterface::class, (string) $container->getDefinition(Scheduler::class)->getArgument(3));
        self::assertSame(ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $container->getDefinition(Scheduler::class)->getArgument(3)->getInvalidBehavior());
        self::assertInstanceOf(Reference::class, $container->getDefinition(Scheduler::class)->getArgument(4));
        self::assertSame(MessageBusInterface::class, (string) $container->getDefinition(Scheduler::class)->getArgument(4));
        self::assertSame(ContainerInterface::NULL_ON_INVALID_REFERENCE, $container->getDefinition(Scheduler::class)->getArgument(4)->getInvalidBehavior());
        self::assertFalse($container->getDefinition(Scheduler::class)->isPublic());
        self::assertTrue($container->getDefinition(Scheduler::class)->hasTag('monolog.logger'));
        self::assertSame('scheduler', $container->getDefinition(Scheduler::class)->getTag('monolog.logger')[0]['channel']);
        self::assertTrue($container->getDefinition(Scheduler::class)->hasTag('container.preload'));
        self::assertSame(Scheduler::class, $container->getDefinition(Scheduler::class)->getTag('container.preload')[0]['class']);
    }

    public function testLazySchedulerIsRegistered(): void
    {
        $container = $this->getContainer([
            'scheduler' => [
                'mode' => 'lazy',
            ],
            'path' => '/_foo',
            'timezone' => 'Europe/Paris',
            'transport' => [
                'dsn' => 'memory://first_in_first_out',
            ],
            'tasks' => [],
            'lock_store' => null,
        ]);

        self::assertTrue($container->hasParameter('scheduler.scheduler_mode'));
        self::assertSame('lazy', $container->getParameter('scheduler.scheduler_mode'));

        self::assertTrue($container->hasDefinition(Scheduler::class));
        self::assertTrue($container->hasAlias(SchedulerInterface::class));
        self::assertCount(5, $container->getDefinition(Scheduler::class)->getArguments());
        self::assertSame('Europe/Paris', $container->getDefinition(Scheduler::class)->getArgument(0));
        self::assertInstanceOf(Reference::class, $container->getDefinition(Scheduler::class)->getArgument(1));
        self::assertSame(TransportInterface::class, (string) $container->getDefinition(Scheduler::class)->getArgument(1));
        self::assertSame(ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $container->getDefinition(Scheduler::class)->getArgument(1)->getInvalidBehavior());
        self::assertInstanceOf(Reference::class, $container->getDefinition(Scheduler::class)->getArgument(2));
        self::assertSame(SchedulerMiddlewareStack::class, (string) $container->getDefinition(Scheduler::class)->getArgument(2));
        self::assertSame(ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $container->getDefinition(Scheduler::class)->getArgument(2)->getInvalidBehavior());
        self::assertInstanceOf(Reference::class, $container->getDefinition(Scheduler::class)->getArgument(3));
        self::assertSame(EventDispatcherInterface::class, (string) $container->getDefinition(Scheduler::class)->getArgument(3));
        self::assertSame(ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $container->getDefinition(Scheduler::class)->getArgument(3)->getInvalidBehavior());
        self::assertInstanceOf(Reference::class, $container->getDefinition(Scheduler::class)->getArgument(4));
        self::assertSame(MessageBusInterface::class, (string) $container->getDefinition(Scheduler::class)->getArgument(4));
        self::assertSame(ContainerInterface::NULL_ON_INVALID_REFERENCE, $container->getDefinition(Scheduler::class)->getArgument(4)->getInvalidBehavior());
        self::assertFalse($container->getDefinition(Scheduler::class)->isPublic());
        self::assertTrue($container->getDefinition(Scheduler::class)->hasTag('monolog.logger'));
        self::assertSame('scheduler', $container->getDefinition(Scheduler::class)->getTag('monolog.logger')[0]['channel']);
        self::assertTrue($container->getDefinition(Scheduler::class)->hasTag('container.preload'));
        self::assertSame(Scheduler::class, $container->getDefinition(Scheduler::class)->getTag('container.preload')[0]['class']);

        self::assertTrue($container->hasDefinition(LazyScheduler::class));
        self::assertTrue($container->hasAlias(SchedulerInterface::class));
        self::assertSame(Scheduler::class, (string) $container->getAlias(SchedulerInterface::class));

        $decoratedService = $container->getDefinition(LazyScheduler::class)->getDecoratedService();
        self::assertIsArray($decoratedService);
        self::assertArrayHasKey(0, $decoratedService);
        self::assertArrayHasKey(1, $decoratedService);
        self::assertArrayHasKey(2, $decoratedService);
        self::assertSame(Scheduler::class, $decoratedService[0]);
        self::assertSame('scheduler.scheduler', $decoratedService[1]);
        self::assertSame(0, $decoratedService[2]);

        self::assertCount(1, $container->getDefinition(LazyScheduler::class)->getArguments());
        self::assertInstanceOf(Reference::class, $container->getDefinition(LazyScheduler::class)->getArgument(0));
        self::assertSame('scheduler.scheduler', (string) $container->getDefinition(LazyScheduler::class)->getArgument(0));
        self::assertSame(ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $container->getDefinition(LazyScheduler::class)->getArgument(0)->getInvalidBehavior());
        self::assertFalse($container->getDefinition(LazyScheduler::class)->isPublic());
        self::assertTrue($container->getDefinition(LazyScheduler::class)->hasTag('container.preload'));
        self::assertSame(LazyScheduler::class, $container->getDefinition(LazyScheduler::class)->getTag('container.preload')[0]['class']);
    }

    public function testCommandsAreRegistered(): void
    {
        $container = $this->getContainer([
            'path' => '/_foo',
            'timezone' => 'Europe/Paris',
            'transport' => [
                'dsn' => 'memory://first_in_first_out',
            ],
            'tasks' => [],
            'lock_store' => null,
        ]);

        self::assertTrue($container->hasDefinition(ConsumeTasksCommand::class));
        self::assertCount(4, $container->getDefinition(ConsumeTasksCommand::class)->getArguments());
        self::assertInstanceOf(Reference::class, $container->getDefinition(ConsumeTasksCommand::class)->getArgument(0));
        self::assertSame(SchedulerInterface::class, (string) $container->getDefinition(ConsumeTasksCommand::class)->getArgument(0));
        self::assertInstanceOf(Reference::class, $container->getDefinition(ConsumeTasksCommand::class)->getArgument(1));
        self::assertSame(WorkerInterface::class, (string) $container->getDefinition(ConsumeTasksCommand::class)->getArgument(1));
        self::assertInstanceOf(Reference::class, $container->getDefinition(ConsumeTasksCommand::class)->getArgument(2));
        self::assertSame(EventDispatcherInterface::class, (string) $container->getDefinition(ConsumeTasksCommand::class)->getArgument(2));
        self::assertInstanceOf(Reference::class, $container->getDefinition(ConsumeTasksCommand::class)->getArgument(3));
        self::assertSame(LoggerInterface::class, (string) $container->getDefinition(ConsumeTasksCommand::class)->getArgument(3));
        self::assertTrue($container->getDefinition(ConsumeTasksCommand::class)->hasTag('console.command'));
        self::assertTrue($container->getDefinition(ConsumeTasksCommand::class)->hasTag('monolog.logger'));
        self::assertSame('scheduler', $container->getDefinition(ConsumeTasksCommand::class)->getTag('monolog.logger')[0]['channel']);
        self::assertTrue($container->getDefinition(ConsumeTasksCommand::class)->hasTag('container.preload'));
        self::assertSame(ConsumeTasksCommand::class, $container->getDefinition(ConsumeTasksCommand::class)->getTag('container.preload')[0]['class']);

        self::assertTrue($container->hasDefinition(ExecuteTaskCommand::class));
        self::assertCount(4, $container->getDefinition(ExecuteTaskCommand::class)->getArguments());
        self::assertInstanceOf(Reference::class, $container->getDefinition(ExecuteTaskCommand::class)->getArgument(0));
        self::assertSame(EventDispatcherInterface::class, (string) $container->getDefinition(ExecuteTaskCommand::class)->getArgument(0));
        self::assertSame(ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $container->getDefinition(ExecuteTaskCommand::class)->getArgument(0)->getInvalidBehavior());
        self::assertInstanceOf(Reference::class, $container->getDefinition(ExecuteTaskCommand::class)->getArgument(1));
        self::assertSame(SchedulerInterface::class, (string) $container->getDefinition(ExecuteTaskCommand::class)->getArgument(1));
        self::assertSame(ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $container->getDefinition(ExecuteTaskCommand::class)->getArgument(1)->getInvalidBehavior());
        self::assertInstanceOf(Reference::class, $container->getDefinition(ExecuteTaskCommand::class)->getArgument(2));
        self::assertSame(WorkerInterface::class, (string) $container->getDefinition(ExecuteTaskCommand::class)->getArgument(2));
        self::assertSame(ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $container->getDefinition(ExecuteTaskCommand::class)->getArgument(2)->getInvalidBehavior());
        self::assertInstanceOf(Reference::class, $container->getDefinition(ExecuteTaskCommand::class)->getArgument(3));
        self::assertSame(LoggerInterface::class, (string) $container->getDefinition(ExecuteTaskCommand::class)->getArgument(3));
        self::assertSame(ContainerInterface::NULL_ON_INVALID_REFERENCE, $container->getDefinition(ExecuteTaskCommand::class)->getArgument(3)->getInvalidBehavior());
        self::assertTrue($container->getDefinition(ExecuteTaskCommand::class)->hasTag('console.command'));
        self::assertTrue($container->getDefinition(ExecuteTaskCommand::class)->hasTag('monolog.logger'));
        self::assertSame('scheduler', $container->getDefinition(ExecuteTaskCommand::class)->getTag('monolog.logger')[0]['channel']);
        self::assertTrue($container->getDefinition(ExecuteTaskCommand::class)->hasTag('container.preload'));
        self::assertSame(ExecuteTaskCommand::class, $container->getDefinition(ExecuteTaskCommand::class)->getTag('container.preload')[0]['class']);

        self::assertTrue($container->hasDefinition(ListFailedTasksCommand::class));
        self::assertCount(1, $container->getDefinition(ListFailedTasksCommand::class)->getArguments());
        self::assertInstanceOf(Reference::class, $container->getDefinition(ListFailedTasksCommand::class)->getArgument(0));
        self::assertSame(WorkerInterface::class, (string) $container->getDefinition(ListFailedTasksCommand::class)->getArgument(0));
        self::assertTrue($container->getDefinition(ListFailedTasksCommand::class)->hasTag('console.command'));
        self::assertTrue($container->getDefinition(ListFailedTasksCommand::class)->hasTag('container.preload'));
        self::assertSame(ListFailedTasksCommand::class, $container->getDefinition(ListFailedTasksCommand::class)->getTag('container.preload')[0]['class']);

        self::assertTrue($container->hasDefinition(ListTasksCommand::class));
        self::assertCount(1, $container->getDefinition(ListTasksCommand::class)->getArguments());
        self::assertInstanceOf(Reference::class, $container->getDefinition(ListTasksCommand::class)->getArgument(0));
        self::assertSame(SchedulerInterface::class, (string) $container->getDefinition(ListTasksCommand::class)->getArgument(0));
        self::assertTrue($container->getDefinition(ListTasksCommand::class)->hasTag('console.command'));
        self::assertTrue($container->getDefinition(ListTasksCommand::class)->hasTag('container.preload'));
        self::assertSame(ListTasksCommand::class, $container->getDefinition(ListTasksCommand::class)->getTag('container.preload')[0]['class']);

        self::assertTrue($container->hasDefinition(RebootSchedulerCommand::class));
        self::assertCount(4, $container->getDefinition(RebootSchedulerCommand::class)->getArguments());
        self::assertInstanceOf(Reference::class, $container->getDefinition(RebootSchedulerCommand::class)->getArgument(0));
        self::assertSame(SchedulerInterface::class, (string) $container->getDefinition(RebootSchedulerCommand::class)->getArgument(0));
        self::assertInstanceOf(Reference::class, $container->getDefinition(RebootSchedulerCommand::class)->getArgument(1));
        self::assertSame(WorkerInterface::class, (string) $container->getDefinition(RebootSchedulerCommand::class)->getArgument(1));
        self::assertInstanceOf(Reference::class, $container->getDefinition(RebootSchedulerCommand::class)->getArgument(2));
        self::assertSame(EventDispatcherInterface::class, (string) $container->getDefinition(RebootSchedulerCommand::class)->getArgument(2));
        self::assertInstanceOf(Reference::class, $container->getDefinition(RebootSchedulerCommand::class)->getArgument(3));
        self::assertSame(LoggerInterface::class, (string) $container->getDefinition(RebootSchedulerCommand::class)->getArgument(3));
        self::assertTrue($container->getDefinition(RebootSchedulerCommand::class)->hasTag('console.command'));
        self::assertTrue($container->getDefinition(RebootSchedulerCommand::class)->hasTag('monolog.logger'));
        self::assertSame('scheduler', $container->getDefinition(RebootSchedulerCommand::class)->getTag('monolog.logger')[0]['channel']);
        self::assertTrue($container->getDefinition(RebootSchedulerCommand::class)->hasTag('container.preload'));
        self::assertSame(RebootSchedulerCommand::class, $container->getDefinition(RebootSchedulerCommand::class)->getTag('container.preload')[0]['class']);

        self::assertTrue($container->hasDefinition(RemoveFailedTaskCommand::class));
        self::assertCount(2, $container->getDefinition(RemoveFailedTaskCommand::class)->getArguments());
        self::assertInstanceOf(Reference::class, $container->getDefinition(RemoveFailedTaskCommand::class)->getArgument(0));
        self::assertSame(SchedulerInterface::class, (string) $container->getDefinition(RemoveFailedTaskCommand::class)->getArgument(0));
        self::assertInstanceOf(Reference::class, $container->getDefinition(RemoveFailedTaskCommand::class)->getArgument(1));
        self::assertSame(WorkerInterface::class, (string) $container->getDefinition(RemoveFailedTaskCommand::class)->getArgument(1));
        self::assertTrue($container->getDefinition(RemoveFailedTaskCommand::class)->hasTag('console.command'));
        self::assertTrue($container->getDefinition(RemoveFailedTaskCommand::class)->hasTag('container.preload'));
        self::assertSame(RemoveFailedTaskCommand::class, $container->getDefinition(RemoveFailedTaskCommand::class)->getTag('container.preload')[0]['class']);

        self::assertTrue($container->hasDefinition(RetryFailedTaskCommand::class));
        self::assertCount(3, $container->getDefinition(RetryFailedTaskCommand::class)->getArguments());
        self::assertInstanceOf(Reference::class, $container->getDefinition(RetryFailedTaskCommand::class)->getArgument(0));
        self::assertSame(WorkerInterface::class, (string) $container->getDefinition(RetryFailedTaskCommand::class)->getArgument(0));
        self::assertInstanceOf(Reference::class, $container->getDefinition(RetryFailedTaskCommand::class)->getArgument(1));
        self::assertSame(EventDispatcherInterface::class, (string) $container->getDefinition(RetryFailedTaskCommand::class)->getArgument(1));
        self::assertInstanceOf(Reference::class, $container->getDefinition(RetryFailedTaskCommand::class)->getArgument(2));
        self::assertSame(LoggerInterface::class, (string) $container->getDefinition(RetryFailedTaskCommand::class)->getArgument(2));
        self::assertTrue($container->getDefinition(RetryFailedTaskCommand::class)->hasTag('console.command'));
        self::assertTrue($container->getDefinition(RetryFailedTaskCommand::class)->hasTag('monolog.logger'));
        self::assertSame('scheduler', $container->getDefinition(RetryFailedTaskCommand::class)->getTag('monolog.logger')[0]['channel']);
        self::assertTrue($container->getDefinition(RetryFailedTaskCommand::class)->hasTag('container.preload'));
        self::assertSame(RetryFailedTaskCommand::class, $container->getDefinition(RetryFailedTaskCommand::class)->getTag('container.preload')[0]['class']);

        self::assertTrue($container->hasDefinition(YieldTaskCommand::class));
        self::assertCount(1, $container->getDefinition(YieldTaskCommand::class)->getArguments());
        self::assertInstanceOf(Reference::class, $container->getDefinition(YieldTaskCommand::class)->getArgument(0));
        self::assertSame(SchedulerInterface::class, (string) $container->getDefinition(YieldTaskCommand::class)->getArgument(0));
        self::assertTrue($container->getDefinition(YieldTaskCommand::class)->hasTag('console.command'));
        self::assertTrue($container->getDefinition(YieldTaskCommand::class)->hasTag('container.preload'));
        self::assertSame(YieldTaskCommand::class, $container->getDefinition(YieldTaskCommand::class)->getTag('container.preload')[0]['class']);

        self::assertTrue($container->hasDefinition(DebugMiddlewareCommand::class));
        self::assertCount(2, $container->getDefinition(DebugMiddlewareCommand::class)->getArguments());
        self::assertInstanceOf(Reference::class, $container->getDefinition(DebugMiddlewareCommand::class)->getArgument(0));
        self::assertSame(SchedulerMiddlewareStack::class, (string) $container->getDefinition(DebugMiddlewareCommand::class)->getArgument(0));
        self::assertSame(ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $container->getDefinition(DebugMiddlewareCommand::class)->getArgument(0)->getInvalidBehavior());
        self::assertInstanceOf(Reference::class, $container->getDefinition(DebugMiddlewareCommand::class)->getArgument(1));
        self::assertSame(WorkerMiddlewareStack::class, (string) $container->getDefinition(DebugMiddlewareCommand::class)->getArgument(1));
        self::assertSame(ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $container->getDefinition(DebugMiddlewareCommand::class)->getArgument(1)->getInvalidBehavior());
        self::assertTrue($container->getDefinition(DebugMiddlewareCommand::class)->hasTag('console.command'));
        self::assertTrue($container->getDefinition(DebugMiddlewareCommand::class)->hasTag('container.preload'));
        self::assertSame(DebugMiddlewareCommand::class, $container->getDefinition(DebugMiddlewareCommand::class)->getTag('container.preload')[0]['class']);
    }

    public function testExpressionFactoryAndPoliciesAreRegistered(): void
    {
        $container = $this->getContainer([
            'path' => '/_foo',
            'timezone' => 'Europe/Paris',
            'transport' => [
                'dsn' => 'memory://first_in_first_out',
            ],
            'tasks' => [],
            'lock_store' => null,
        ]);

        self::assertTrue($container->hasDefinition(Expression::class));

        self::assertTrue($container->hasDefinition(ExpressionBuilder::class));
        self::assertTrue($container->hasAlias(BuilderInterface::class));
        self::assertCount(1, $container->getDefinition(ExpressionBuilder::class)->getArguments());
        self::assertInstanceOf(TaggedIteratorArgument::class, $container->getDefinition(ExpressionBuilder::class)->getArgument(0));
        self::assertTrue($container->getDefinition(ExpressionBuilder::class)->hasTag('container.preload'));
        self::assertSame(ExpressionBuilder::class, $container->getDefinition(ExpressionBuilder::class)->getTag('container.preload')[0]['class']);

        self::assertTrue($container->hasDefinition(CronExpressionBuilder::class));
        self::assertFalse($container->getDefinition(CronExpressionBuilder::class)->isPublic());
        self::assertTrue($container->getDefinition(CronExpressionBuilder::class)->hasTag('scheduler.expression_builder'));
        self::assertTrue($container->getDefinition(CronExpressionBuilder::class)->hasTag('container.preload'));
        self::assertSame(CronExpressionBuilder::class, $container->getDefinition(CronExpressionBuilder::class)->getTag('container.preload')[0]['class']);

        self::assertTrue($container->hasDefinition(ComputedExpressionBuilder::class));
        self::assertFalse($container->getDefinition(ComputedExpressionBuilder::class)->isPublic());
        self::assertTrue($container->getDefinition(ComputedExpressionBuilder::class)->hasTag('scheduler.expression_builder'));
        self::assertTrue($container->getDefinition(ComputedExpressionBuilder::class)->hasTag('container.preload'));
        self::assertSame(ComputedExpressionBuilder::class, $container->getDefinition(ComputedExpressionBuilder::class)->getTag('container.preload')[0]['class']);

        self::assertTrue($container->hasDefinition(FluentExpressionBuilder::class));
        self::assertFalse($container->getDefinition(FluentExpressionBuilder::class)->isPublic());
        self::assertTrue($container->getDefinition(FluentExpressionBuilder::class)->hasTag('scheduler.expression_builder'));
        self::assertTrue($container->getDefinition(FluentExpressionBuilder::class)->hasTag('container.preload'));
        self::assertSame(FluentExpressionBuilder::class, $container->getDefinition(FluentExpressionBuilder::class)->getTag('container.preload')[0]['class']);

        self::assertTrue($container->hasDefinition(SchedulePolicyOrchestrator::class));
        self::assertTrue($container->hasAlias(SchedulePolicyOrchestratorInterface::class));
        self::assertCount(1, $container->getDefinition(SchedulePolicyOrchestrator::class)->getArguments());
        self::assertInstanceOf(TaggedIteratorArgument::class, $container->getDefinition(SchedulePolicyOrchestrator::class)->getArgument(0));
        self::assertTrue($container->getDefinition(SchedulePolicyOrchestrator::class)->hasTag('container.preload'));
        self::assertSame(SchedulePolicyOrchestrator::class, $container->getDefinition(SchedulePolicyOrchestrator::class)->getTag('container.preload')[0]['class']);

        self::assertTrue($container->hasDefinition(BatchPolicy::class));
        self::assertTrue($container->getDefinition(BatchPolicy::class)->hasTag('container.preload'));
        self::assertSame(BatchPolicy::class, $container->getDefinition(BatchPolicy::class)->getTag('container.preload')[0]['class']);

        self::assertTrue($container->hasDefinition(DeadlinePolicy::class));
        self::assertTrue($container->getDefinition(DeadlinePolicy::class)->hasTag('container.preload'));
        self::assertSame(DeadlinePolicy::class, $container->getDefinition(DeadlinePolicy::class)->getTag('container.preload')[0]['class']);

        self::assertTrue($container->hasDefinition(ExecutionDurationPolicy::class));
        self::assertTrue($container->getDefinition(ExecutionDurationPolicy::class)->hasTag('container.preload'));
        self::assertSame(ExecutionDurationPolicy::class, $container->getDefinition(ExecutionDurationPolicy::class)->getTag('container.preload')[0]['class']);

        self::assertTrue($container->hasDefinition(FirstInFirstOutPolicy::class));
        self::assertTrue($container->getDefinition(FirstInFirstOutPolicy::class)->hasTag('container.preload'));
        self::assertSame(FirstInFirstOutPolicy::class, $container->getDefinition(FirstInFirstOutPolicy::class)->getTag('container.preload')[0]['class']);

        self::assertTrue($container->hasDefinition(FirstInLastOutPolicy::class));
        self::assertTrue($container->getDefinition(FirstInLastOutPolicy::class)->hasTag('container.preload'));
        self::assertSame(FirstInLastOutPolicy::class, $container->getDefinition(FirstInLastOutPolicy::class)->getTag('container.preload')[0]['class']);

        self::assertTrue($container->hasDefinition(IdlePolicy::class));
        self::assertTrue($container->getDefinition(IdlePolicy::class)->hasTag('container.preload'));
        self::assertSame(IdlePolicy::class, $container->getDefinition(IdlePolicy::class)->getTag('container.preload')[0]['class']);

        self::assertTrue($container->hasDefinition(MemoryUsagePolicy::class));
        self::assertTrue($container->getDefinition(MemoryUsagePolicy::class)->hasTag('container.preload'));
        self::assertSame(MemoryUsagePolicy::class, $container->getDefinition(MemoryUsagePolicy::class)->getTag('container.preload')[0]['class']);

        self::assertTrue($container->hasDefinition(NicePolicy::class));
        self::assertTrue($container->getDefinition(NicePolicy::class)->hasTag('container.preload'));
        self::assertSame(NicePolicy::class, $container->getDefinition(NicePolicy::class)->getTag('container.preload')[0]['class']);

        self::assertTrue($container->hasDefinition(RoundRobinPolicy::class));
        self::assertTrue($container->getDefinition(RoundRobinPolicy::class)->hasTag('container.preload'));
        self::assertSame(RoundRobinPolicy::class, $container->getDefinition(RoundRobinPolicy::class)->getTag('container.preload')[0]['class']);
    }

    public function testBuildersAreRegistered(): void
    {
        $container = $this->getContainer([
            'path' => '/_foo',
            'timezone' => 'Europe/Paris',
            'transport' => [
                'dsn' => 'memory://first_in_first_out',
            ],
            'tasks' => [],
            'lock_store' => null,
        ]);

        self::assertTrue($container->hasDefinition(TaskBuilder::class));
        self::assertTrue($container->hasAlias(TaskBuilderInterface::class));
        self::assertCount(2, $container->getDefinition(TaskBuilder::class)->getArguments());
        self::assertInstanceOf(TaggedIteratorArgument::class, $container->getDefinition(TaskBuilder::class)->getArgument(0));
        self::assertInstanceOf(Reference::class, $container->getDefinition(TaskBuilder::class)->getArgument(1));
        self::assertSame('property_accessor', (string) $container->getDefinition(TaskBuilder::class)->getArgument(1));
        self::assertTrue($container->getDefinition(TaskBuilder::class)->hasTag('container.preload'));
        self::assertSame(TaskBuilder::class, $container->getDefinition(TaskBuilder::class)->getTag('container.preload')[0]['class']);

        self::assertTrue($container->hasDefinition(AbstractTaskBuilder::class));
        self::assertFalse($container->getDefinition(AbstractTaskBuilder::class)->isPublic());
        self::assertTrue($container->getDefinition(AbstractTaskBuilder::class)->isAbstract());
        self::assertCount(1, $container->getDefinition(AbstractTaskBuilder::class)->getArguments());
        self::assertInstanceOf(Reference::class, $container->getDefinition(AbstractTaskBuilder::class)->getArgument(0));
        self::assertSame(BuilderInterface::class, (string) $container->getDefinition(AbstractTaskBuilder::class)->getArgument(0));
        self::assertSame(ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $container->getDefinition(AbstractTaskBuilder::class)->getArgument(0)->getInvalidBehavior());
        self::assertTrue($container->getDefinition(AbstractTaskBuilder::class)->hasTag('container.preload'));
        self::assertSame(AbstractTaskBuilder::class, $container->getDefinition(AbstractTaskBuilder::class)->getTag('container.preload')[0]['class']);

        self::assertTrue($container->hasDefinition(CommandBuilder::class));
        self::assertFalse($container->getDefinition(CommandBuilder::class)->isPublic());
        self::assertSame(CommandBuilder::class, $container->getDefinition(CommandBuilder::class)->getClass());
        self::assertCount(1, $container->getDefinition(CommandBuilder::class)->getArguments());
        self::assertInstanceOf(Reference::class, $container->getDefinition(CommandBuilder::class)->getArgument(0));
        self::assertSame(BuilderInterface::class, (string) $container->getDefinition(CommandBuilder::class)->getArgument(0));
        self::assertSame(ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $container->getDefinition(CommandBuilder::class)->getArgument(0)->getInvalidBehavior());
        self::assertTrue($container->getDefinition(CommandBuilder::class)->hasTag('scheduler.task_builder'));
        self::assertTrue($container->getDefinition(CommandBuilder::class)->hasTag('container.preload'));
        self::assertSame(CommandBuilder::class, $container->getDefinition(CommandBuilder::class)->getTag('container.preload')[0]['class']);

        self::assertTrue($container->hasDefinition(HttpBuilder::class));
        self::assertFalse($container->getDefinition(HttpBuilder::class)->isPublic());
        self::assertSame(HttpBuilder::class, $container->getDefinition(HttpBuilder::class)->getClass());
        self::assertCount(1, $container->getDefinition(HttpBuilder::class)->getArguments());
        self::assertInstanceOf(Reference::class, $container->getDefinition(HttpBuilder::class)->getArgument(0));
        self::assertSame(BuilderInterface::class, (string) $container->getDefinition(HttpBuilder::class)->getArgument(0));
        self::assertSame(ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $container->getDefinition(HttpBuilder::class)->getArgument(0)->getInvalidBehavior());
        self::assertTrue($container->getDefinition(HttpBuilder::class)->hasTag('scheduler.task_builder'));
        self::assertTrue($container->getDefinition(HttpBuilder::class)->hasTag('container.preload'));
        self::assertSame(HttpBuilder::class, $container->getDefinition(HttpBuilder::class)->getTag('container.preload')[0]['class']);

        self::assertTrue($container->hasDefinition(NullBuilder::class));
        self::assertFalse($container->getDefinition(NullBuilder::class)->isPublic());
        self::assertSame(NullBuilder::class, $container->getDefinition(NullBuilder::class)->getClass());
        self::assertCount(1, $container->getDefinition(NullBuilder::class)->getArguments());
        self::assertInstanceOf(Reference::class, $container->getDefinition(NullBuilder::class)->getArgument(0));
        self::assertSame(BuilderInterface::class, (string) $container->getDefinition(NullBuilder::class)->getArgument(0));
        self::assertSame(ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $container->getDefinition(NullBuilder::class)->getArgument(0)->getInvalidBehavior());
        self::assertTrue($container->getDefinition(NullBuilder::class)->hasTag('scheduler.task_builder'));
        self::assertTrue($container->getDefinition(NullBuilder::class)->hasTag('container.preload'));
        self::assertSame(NullBuilder::class, $container->getDefinition(NullBuilder::class)->getTag('container.preload')[0]['class']);

        self::assertTrue($container->hasDefinition(ShellBuilder::class));
        self::assertFalse($container->getDefinition(ShellBuilder::class)->isPublic());
        self::assertSame(ShellBuilder::class, $container->getDefinition(ShellBuilder::class)->getClass());
        self::assertCount(1, $container->getDefinition(ShellBuilder::class)->getArguments());
        self::assertInstanceOf(Reference::class, $container->getDefinition(ShellBuilder::class)->getArgument(0));
        self::assertSame(BuilderInterface::class, (string) $container->getDefinition(ShellBuilder::class)->getArgument(0));
        self::assertSame(ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $container->getDefinition(ShellBuilder::class)->getArgument(0)->getInvalidBehavior());
        self::assertTrue($container->getDefinition(ShellBuilder::class)->hasTag('scheduler.task_builder'));
        self::assertTrue($container->getDefinition(ShellBuilder::class)->hasTag('container.preload'));
        self::assertSame(ShellBuilder::class, $container->getDefinition(ShellBuilder::class)->getTag('container.preload')[0]['class']);

        self::assertTrue($container->hasDefinition(ChainedBuilder::class));
        self::assertFalse($container->getDefinition(ChainedBuilder::class)->isPublic());
        self::assertSame(ChainedBuilder::class, $container->getDefinition(ChainedBuilder::class)->getClass());
        self::assertCount(2, $container->getDefinition(ChainedBuilder::class)->getArguments());
        self::assertInstanceOf(Reference::class, $container->getDefinition(ChainedBuilder::class)->getArgument(0));
        self::assertSame(BuilderInterface::class, (string) $container->getDefinition(ChainedBuilder::class)->getArgument(0));
        self::assertSame(ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $container->getDefinition(ChainedBuilder::class)->getArgument(0)->getInvalidBehavior());
        self::assertInstanceOf(TaggedIteratorArgument::class, $container->getDefinition(ChainedBuilder::class)->getArgument('$builders'));
        self::assertSame('scheduler.task_builder', $container->getDefinition(ChainedBuilder::class)->getArgument('$builders')->getTag());
        self::assertTrue($container->getDefinition(ChainedBuilder::class)->hasTag('scheduler.task_builder'));
        self::assertTrue($container->getDefinition(ChainedBuilder::class)->hasTag('container.preload'));
        self::assertSame(ChainedBuilder::class, $container->getDefinition(ChainedBuilder::class)->getTag('container.preload')[0]['class']);
    }

    public function testRunnersAreRegistered(): void
    {
        $container = $this->getContainer([
            'path' => '/_foo',
            'timezone' => 'Europe/Paris',
            'transport' => [
                'dsn' => 'memory://first_in_first_out',
            ],
            'tasks' => [],
            'lock_store' => null,
        ]);

        self::assertTrue($container->hasDefinition('scheduler.application'));
        self::assertCount(1, $container->getDefinition('scheduler.application')->getArguments());
        self::assertInstanceOf(Reference::class, $container->getDefinition('scheduler.application')->getArgument(0));
        self::assertSame(KernelInterface::class, (string) $container->getDefinition('scheduler.application')->getArgument(0));

        self::assertTrue($container->hasAlias(RunnerRegistryInterface::class));
        self::assertTrue($container->hasDefinition(RunnerRegistry::class));
        self::assertFalse($container->getDefinition(RunnerRegistry::class)->isPublic());
        self::assertCount(1, $container->getDefinition(RunnerRegistry::class)->getArguments());
        self::assertInstanceOf(TaggedIteratorArgument::class, $container->getDefinition(RunnerRegistry::class)->getArgument(0));
        self::assertTrue($container->getDefinition(RunnerRegistry::class)->hasTag('container.preload'));
        self::assertSame(RunnerRegistry::class, $container->getDefinition(RunnerRegistry::class)->getTag('container.preload')[0]['class']);

        self::assertTrue($container->hasDefinition(ShellTaskRunner::class));
        self::assertTrue($container->getDefinition(ShellTaskRunner::class)->hasTag('scheduler.runner'));
        self::assertTrue($container->getDefinition(ShellTaskRunner::class)->hasTag('container.preload'));
        self::assertSame(ShellTaskRunner::class, $container->getDefinition(ShellTaskRunner::class)->getTag('container.preload')[0]['class']);

        self::assertTrue($container->hasDefinition(CommandTaskRunner::class));
        self::assertCount(1, $container->getDefinition(CommandTaskRunner::class)->getArguments());
        self::assertInstanceOf(Reference::class, $container->getDefinition(CommandTaskRunner::class)->getArgument(0));
        self::assertSame('scheduler.application', (string) $container->getDefinition(CommandTaskRunner::class)->getArgument(0));
        self::assertTrue($container->getDefinition(CommandTaskRunner::class)->hasTag('scheduler.runner'));
        self::assertTrue($container->getDefinition(CommandTaskRunner::class)->hasTag('container.preload'));
        self::assertSame(CommandTaskRunner::class, $container->getDefinition(CommandTaskRunner::class)->getTag('container.preload')[0]['class']);

        self::assertTrue($container->hasDefinition(CallbackTaskRunner::class));
        self::assertTrue($container->getDefinition(CallbackTaskRunner::class)->hasTag('scheduler.runner'));
        self::assertTrue($container->getDefinition(CallbackTaskRunner::class)->hasTag('container.preload'));
        self::assertSame(CallbackTaskRunner::class, $container->getDefinition(CallbackTaskRunner::class)->getTag('container.preload')[0]['class']);

        self::assertTrue($container->hasDefinition(HttpTaskRunner::class));
        self::assertCount(1, $container->getDefinition(HttpTaskRunner::class)->getArguments());
        self::assertInstanceOf(Reference::class, $container->getDefinition(HttpTaskRunner::class)->getArgument(0));
        self::assertSame(HttpClientInterface::class, (string) $container->getDefinition(HttpTaskRunner::class)->getArgument(0));
        self::assertTrue($container->getDefinition(HttpTaskRunner::class)->hasTag('scheduler.runner'));
        self::assertTrue($container->getDefinition(HttpTaskRunner::class)->hasTag('container.preload'));
        self::assertSame(HttpTaskRunner::class, $container->getDefinition(HttpTaskRunner::class)->getTag('container.preload')[0]['class']);
        self::assertTrue($container->getDefinition(HttpTaskRunner::class)->hasTag('scheduler.extra'));
        self::assertSame('http_client', $container->getDefinition(HttpTaskRunner::class)->getTag('scheduler.extra')[0]['require']);
        self::assertSame('scheduler.runner', $container->getDefinition(HttpTaskRunner::class)->getTag('scheduler.extra')[0]['tag']);

        self::assertTrue($container->hasDefinition(MessengerTaskRunner::class));
        self::assertCount(1, $container->getDefinition(MessengerTaskRunner::class)->getArguments());
        self::assertInstanceOf(Reference::class, $container->getDefinition(MessengerTaskRunner::class)->getArgument(0));
        self::assertSame(MessageBusInterface::class, (string) $container->getDefinition(MessengerTaskRunner::class)->getArgument(0));
        self::assertSame(ContainerInterface::NULL_ON_INVALID_REFERENCE, $container->getDefinition(MessengerTaskRunner::class)->getArgument(0)->getInvalidBehavior());
        self::assertTrue($container->getDefinition(MessengerTaskRunner::class)->hasTag('scheduler.runner'));
        self::assertTrue($container->getDefinition(MessengerTaskRunner::class)->hasTag('container.preload'));
        self::assertSame(MessengerTaskRunner::class, $container->getDefinition(MessengerTaskRunner::class)->getTag('container.preload')[0]['class']);

        self::assertTrue($container->hasDefinition(NotificationTaskRunner::class));
        self::assertCount(1, $container->getDefinition(NotificationTaskRunner::class)->getArguments());
        self::assertInstanceOf(Reference::class, $container->getDefinition(NotificationTaskRunner::class)->getArgument(0));
        self::assertSame(NotifierInterface::class, (string) $container->getDefinition(NotificationTaskRunner::class)->getArgument(0));
        self::assertTrue($container->getDefinition(NotificationTaskRunner::class)->hasTag('scheduler.runner'));
        self::assertTrue($container->getDefinition(NotificationTaskRunner::class)->hasTag('container.preload'));
        self::assertSame(NotificationTaskRunner::class, $container->getDefinition(NotificationTaskRunner::class)->getTag('container.preload')[0]['class']);
        self::assertTrue($container->getDefinition(NotificationTaskRunner::class)->hasTag('scheduler.extra'));
        self::assertSame('notifier', $container->getDefinition(NotificationTaskRunner::class)->getTag('scheduler.extra')[0]['require']);
        self::assertSame('scheduler.runner', $container->getDefinition(NotificationTaskRunner::class)->getTag('scheduler.extra')[0]['tag']);

        self::assertTrue($container->hasDefinition(NullTaskRunner::class));
        self::assertTrue($container->getDefinition(NullTaskRunner::class)->hasTag('scheduler.runner'));
        self::assertTrue($container->getDefinition(NullTaskRunner::class)->hasTag('container.preload'));
        self::assertSame(NullTaskRunner::class, $container->getDefinition(NullTaskRunner::class)->getTag('container.preload')[0]['class']);

        self::assertTrue($container->hasDefinition(ChainedTaskRunner::class));
        self::assertCount(0, $container->getDefinition(ChainedTaskRunner::class)->getArguments());
        self::assertTrue($container->getDefinition(ChainedTaskRunner::class)->hasTag('scheduler.runner'));
        self::assertTrue($container->getDefinition(ChainedTaskRunner::class)->hasTag('container.preload'));
        self::assertSame(ChainedTaskRunner::class, $container->getDefinition(ChainedTaskRunner::class)->getTag('container.preload')[0]['class']);
    }

    public function testNormalizersAreRegistered(): void
    {
        $container = $this->getContainer([
            'path' => '/_foo',
            'timezone' => 'Europe/Paris',
            'transport' => [
                'dsn' => 'memory://first_in_first_out',
            ],
            'tasks' => [],
            'lock_store' => null,
        ]);

        self::assertTrue($container->hasDefinition(TaskNormalizer::class));
        self::assertCount(6, $container->getDefinition(TaskNormalizer::class)->getArguments());
        self::assertInstanceOf(Reference::class, $container->getDefinition(TaskNormalizer::class)->getArgument(0));
        self::assertSame('serializer.normalizer.datetime', (string) $container->getDefinition(TaskNormalizer::class)->getArgument(0));
        self::assertSame(ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $container->getDefinition(TaskNormalizer::class)->getArgument(0)->getInvalidBehavior());
        self::assertInstanceOf(Reference::class, $container->getDefinition(TaskNormalizer::class)->getArgument(1));
        self::assertSame('serializer.normalizer.datetimezone', (string) $container->getDefinition(TaskNormalizer::class)->getArgument(1));
        self::assertSame(ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $container->getDefinition(TaskNormalizer::class)->getArgument(1)->getInvalidBehavior());
        self::assertInstanceOf(Reference::class, $container->getDefinition(TaskNormalizer::class)->getArgument(2));
        self::assertSame('serializer.normalizer.dateinterval', (string) $container->getDefinition(TaskNormalizer::class)->getArgument(2));
        self::assertSame(ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $container->getDefinition(TaskNormalizer::class)->getArgument(2)->getInvalidBehavior());
        self::assertInstanceOf(Reference::class, $container->getDefinition(TaskNormalizer::class)->getArgument(3));
        self::assertSame('serializer.normalizer.object', (string) $container->getDefinition(TaskNormalizer::class)->getArgument(3));
        self::assertSame(ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $container->getDefinition(TaskNormalizer::class)->getArgument(3)->getInvalidBehavior());
        self::assertInstanceOf(Reference::class, $container->getDefinition(TaskNormalizer::class)->getArgument(4));
        self::assertSame(NotificationTaskBagNormalizer::class, (string) $container->getDefinition(TaskNormalizer::class)->getArgument(4));
        self::assertSame(ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $container->getDefinition(TaskNormalizer::class)->getArgument(4)->getInvalidBehavior());
        self::assertInstanceOf(Reference::class, $container->getDefinition(TaskNormalizer::class)->getArgument(5));
        self::assertSame(AccessLockBagNormalizer::class, (string) $container->getDefinition(TaskNormalizer::class)->getArgument(5));
        self::assertSame(ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $container->getDefinition(TaskNormalizer::class)->getArgument(5)->getInvalidBehavior());
        self::assertTrue($container->getDefinition(TaskNormalizer::class)->hasTag('serializer.normalizer'));
        self::assertTrue($container->getDefinition(TaskNormalizer::class)->hasTag('container.preload'));
        self::assertSame(TaskNormalizer::class, $container->getDefinition(TaskNormalizer::class)->getTag('container.preload')[0]['class']);

        self::assertTrue($container->hasDefinition(NotificationTaskBagNormalizer::class));
        self::assertCount(1, $container->getDefinition(NotificationTaskBagNormalizer::class)->getArguments());
        self::assertInstanceOf(Reference::class, $container->getDefinition(NotificationTaskBagNormalizer::class)->getArgument(0));
        self::assertSame('serializer.normalizer.object', (string) $container->getDefinition(NotificationTaskBagNormalizer::class)->getArgument(0));
        self::assertTrue($container->getDefinition(NotificationTaskBagNormalizer::class)->hasTag('serializer.normalizer'));
        self::assertTrue($container->getDefinition(NotificationTaskBagNormalizer::class)->hasTag('container.preload'));
        self::assertSame(NotificationTaskBagNormalizer::class, $container->getDefinition(NotificationTaskBagNormalizer::class)->getTag('container.preload')[0]['class']);

        self::assertTrue($container->hasDefinition(AccessLockBagNormalizer::class));
        self::assertCount(2, $container->getDefinition(AccessLockBagNormalizer::class)->getArguments());
        self::assertInstanceOf(Reference::class, $container->getDefinition(AccessLockBagNormalizer::class)->getArgument(0));
        self::assertSame('serializer.normalizer.object', (string) $container->getDefinition(AccessLockBagNormalizer::class)->getArgument(0));
        self::assertSame(ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $container->getDefinition(AccessLockBagNormalizer::class)->getArgument(0)->getInvalidBehavior());
        self::assertInstanceOf(Reference::class, $container->getDefinition(AccessLockBagNormalizer::class)->getArgument(1));
        self::assertSame(LoggerInterface::class, (string) $container->getDefinition(AccessLockBagNormalizer::class)->getArgument(1));
        self::assertSame(ContainerInterface::NULL_ON_INVALID_REFERENCE, $container->getDefinition(AccessLockBagNormalizer::class)->getArgument(1)->getInvalidBehavior());
        self::assertTrue($container->getDefinition(AccessLockBagNormalizer::class)->hasTag('serializer.normalizer'));
        self::assertTrue($container->getDefinition(AccessLockBagNormalizer::class)->hasTag('container.preload'));
        self::assertSame(AccessLockBagNormalizer::class, $container->getDefinition(AccessLockBagNormalizer::class)->getTag('container.preload')[0]['class']);
    }

    public function testMessengerToolsAreRegistered(): void
    {
        $container = $this->getContainer([
            'path' => '/_foo',
            'timezone' => 'Europe/Paris',
            'transport' => [
                'dsn' => 'memory://first_in_first_out',
            ],
            'tasks' => [],
            'lock_store' => null,
        ]);

        self::assertTrue($container->hasDefinition(TaskToExecuteMessageHandler::class));
        self::assertCount(2, $container->getDefinition(TaskToExecuteMessageHandler::class)->getArguments());
        self::assertInstanceOf(Reference::class, $container->getDefinition(TaskToExecuteMessageHandler::class)->getArgument(0));
        self::assertSame(WorkerInterface::class, (string) $container->getDefinition(TaskToExecuteMessageHandler::class)->getArgument(0));
        self::assertSame(ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $container->getDefinition(TaskToExecuteMessageHandler::class)->getArgument(0)->getInvalidBehavior());
        self::assertInstanceOf(Reference::class, $container->getDefinition(TaskToExecuteMessageHandler::class)->getArgument(1));
        self::assertSame(LoggerInterface::class, (string) $container->getDefinition(TaskToExecuteMessageHandler::class)->getArgument(1));
        self::assertSame(ContainerInterface::NULL_ON_INVALID_REFERENCE, $container->getDefinition(TaskToExecuteMessageHandler::class)->getArgument(1)->getInvalidBehavior());
        self::assertTrue($container->getDefinition(TaskToExecuteMessageHandler::class)->hasTag('messenger.message_handler'));
        self::assertTrue($container->getDefinition(TaskToExecuteMessageHandler::class)->hasTag('container.preload'));
        self::assertSame(TaskToExecuteMessageHandler::class, $container->getDefinition(TaskToExecuteMessageHandler::class)->getTag('container.preload')[0]['class']);

        self::assertTrue($container->hasDefinition(TaskToYieldMessageHandler::class));
        self::assertCount(1, $container->getDefinition(TaskToYieldMessageHandler::class)->getArguments());
        self::assertInstanceOf(Reference::class, $container->getDefinition(TaskToYieldMessageHandler::class)->getArgument(0));
        self::assertSame(SchedulerInterface::class, (string) $container->getDefinition(TaskToYieldMessageHandler::class)->getArgument(0));
        self::assertSame(ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $container->getDefinition(TaskToYieldMessageHandler::class)->getArgument(0)->getInvalidBehavior());
        self::assertTrue($container->getDefinition(TaskToYieldMessageHandler::class)->hasTag('messenger.message_handler'));
        self::assertTrue($container->getDefinition(TaskToYieldMessageHandler::class)->hasTag('container.preload'));
        self::assertSame(TaskToYieldMessageHandler::class, $container->getDefinition(TaskToYieldMessageHandler::class)->getTag('container.preload')[0]['class']);

        self::assertTrue($container->hasDefinition(TaskToPauseMessageHandler::class));
        self::assertCount(1, $container->getDefinition(TaskToPauseMessageHandler::class)->getArguments());
        self::assertInstanceOf(Reference::class, $container->getDefinition(TaskToPauseMessageHandler::class)->getArgument(0));
        self::assertSame(SchedulerInterface::class, (string) $container->getDefinition(TaskToPauseMessageHandler::class)->getArgument(0));
        self::assertSame(ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $container->getDefinition(TaskToPauseMessageHandler::class)->getArgument(0)->getInvalidBehavior());
        self::assertTrue($container->getDefinition(TaskToPauseMessageHandler::class)->hasTag('messenger.message_handler'));
        self::assertTrue($container->getDefinition(TaskToPauseMessageHandler::class)->hasTag('container.preload'));
        self::assertSame(TaskToPauseMessageHandler::class, $container->getDefinition(TaskToPauseMessageHandler::class)->getTag('container.preload')[0]['class']);
    }

    public function testSubscribersAreRegistered(): void
    {
        $container = $this->getContainer([
            'path' => '/_foo',
            'timezone' => 'Europe/Paris',
            'transport' => [
                'dsn' => 'memory://first_in_first_out',
            ],
            'tasks' => [],
            'lock_store' => null,
        ]);

        self::assertTrue($container->hasDefinition(TaskSubscriber::class));
        self::assertCount(6, $container->getDefinition(TaskSubscriber::class)->getArguments());
        self::assertInstanceOf(Reference::class, $container->getDefinition(TaskSubscriber::class)->getArgument(0));
        self::assertSame(SchedulerInterface::class, (string) $container->getDefinition(TaskSubscriber::class)->getArgument(0));
        self::assertInstanceOf(Reference::class, $container->getDefinition(TaskSubscriber::class)->getArgument(1));
        self::assertSame(WorkerInterface::class, (string) $container->getDefinition(TaskSubscriber::class)->getArgument(1));
        self::assertInstanceOf(Reference::class, $container->getDefinition(TaskSubscriber::class)->getArgument(2));
        self::assertSame(EventDispatcherInterface::class, (string) $container->getDefinition(TaskSubscriber::class)->getArgument(2));
        self::assertInstanceOf(Reference::class, $container->getDefinition(TaskSubscriber::class)->getArgument(3));
        self::assertSame(SerializerInterface::class, (string) $container->getDefinition(TaskSubscriber::class)->getArgument(3));
        self::assertInstanceOf(Reference::class, $container->getDefinition(TaskSubscriber::class)->getArgument(4));
        self::assertSame(LoggerInterface::class, (string) $container->getDefinition(TaskSubscriber::class)->getArgument(4));
        self::assertSame('/_foo', $container->getDefinition(TaskSubscriber::class)->getArgument(5));
        self::assertTrue($container->getDefinition(TaskSubscriber::class)->hasTag('kernel.event_subscriber'));
        self::assertTrue($container->getDefinition(TaskSubscriber::class)->hasTag('monolog.logger'));
        self::assertSame('scheduler', $container->getDefinition(TaskSubscriber::class)->getTag('monolog.logger')[0]['channel']);
        self::assertTrue($container->getDefinition(TaskSubscriber::class)->hasTag('container.preload'));
        self::assertSame(TaskSubscriber::class, $container->getDefinition(TaskSubscriber::class)->getTag('container.preload')[0]['class']);

        self::assertTrue($container->hasDefinition(TaskLoggerSubscriber::class));
        self::assertTrue($container->getDefinition(TaskLoggerSubscriber::class)->hasTag('kernel.event_subscriber'));
        self::assertTrue($container->getDefinition(TaskLoggerSubscriber::class)->hasTag('container.preload'));
        self::assertSame(TaskLoggerSubscriber::class, $container->getDefinition(TaskLoggerSubscriber::class)->getTag('container.preload')[0]['class']);

        self::assertTrue($container->hasDefinition(StopWorkerOnSignalSubscriber::class));
        self::assertFalse($container->getDefinition(StopWorkerOnSignalSubscriber::class)->isPublic());
        self::assertCount(1, $container->getDefinition(StopWorkerOnSignalSubscriber::class)->getArguments());
        self::assertInstanceOf(Reference::class, $container->getDefinition(StopWorkerOnSignalSubscriber::class)->getArgument(0));
        self::assertSame(LoggerInterface::class, (string) $container->getDefinition(StopWorkerOnSignalSubscriber::class)->getArgument(0));
        self::assertSame(ContainerInterface::NULL_ON_INVALID_REFERENCE, $container->getDefinition(StopWorkerOnSignalSubscriber::class)->getArgument(0)->getInvalidBehavior());
        self::assertTrue($container->getDefinition(StopWorkerOnSignalSubscriber::class)->hasTag('kernel.event_subscriber'));
        self::assertTrue($container->getDefinition(StopWorkerOnSignalSubscriber::class)->hasTag('container.preload'));
        self::assertSame(StopWorkerOnSignalSubscriber::class, $container->getDefinition(StopWorkerOnSignalSubscriber::class)->getTag('container.preload')[0]['class']);

        self::assertTrue($container->hasDefinition(TaskLifecycleSubscriber::class));
        self::assertFalse($container->getDefinition(TaskLifecycleSubscriber::class)->isPublic());
        self::assertInstanceOf(Reference::class, $container->getDefinition(TaskLifecycleSubscriber::class)->getArgument(0));
        self::assertSame(LoggerInterface::class, (string) $container->getDefinition(TaskLifecycleSubscriber::class)->getArgument(0));
        self::assertTrue($container->getDefinition(TaskLifecycleSubscriber::class)->hasTag('kernel.event_subscriber'));
        self::assertTrue($container->getDefinition(TaskLifecycleSubscriber::class)->hasTag('container.preload'));
        self::assertSame(TaskLifecycleSubscriber::class, $container->getDefinition(TaskLifecycleSubscriber::class)->getTag('container.preload')[0]['class']);

        self::assertTrue($container->hasDefinition(WorkerLifecycleSubscriber::class));
        self::assertFalse($container->getDefinition(WorkerLifecycleSubscriber::class)->isPublic());
        self::assertCount(1, $container->getDefinition(WorkerLifecycleSubscriber::class)->getArguments());
        self::assertInstanceOf(Reference::class, $container->getDefinition(WorkerLifecycleSubscriber::class)->getArgument(0));
        self::assertSame(LoggerInterface::class, (string) $container->getDefinition(WorkerLifecycleSubscriber::class)->getArgument(0));
        self::assertTrue($container->getDefinition(WorkerLifecycleSubscriber::class)->hasTag('kernel.event_subscriber'));
        self::assertTrue($container->getDefinition(WorkerLifecycleSubscriber::class)->hasTag('container.preload'));
        self::assertSame(WorkerLifecycleSubscriber::class, $container->getDefinition(WorkerLifecycleSubscriber::class)->getTag('container.preload')[0]['class']);
    }

    public function testTrackerIsRegistered(): void
    {
        $container = $this->getContainer([
            'path' => '/_foo',
            'timezone' => 'Europe/Paris',
            'transport' => [
                'dsn' => 'memory://first_in_first_out',
            ],
            'tasks' => [],
            'lock_store' => null,
        ]);

        self::assertTrue($container->hasDefinition('scheduler.stop_watch'));

        self::assertTrue($container->hasDefinition(TaskExecutionTracker::class));
        self::assertTrue($container->hasAlias(TaskExecutionTrackerInterface::class));
        self::assertCount(1, $container->getDefinition(TaskExecutionTracker::class)->getArguments());
        self::assertInstanceOf(Reference::class, $container->getDefinition(TaskExecutionTracker::class)->getArgument(0));
        self::assertSame('scheduler.stop_watch', (string) $container->getDefinition(TaskExecutionTracker::class)->getArgument(0));
        self::assertTrue($container->getDefinition(TaskExecutionTracker::class)->hasTag('container.preload'));
        self::assertSame(TaskExecutionTracker::class, $container->getDefinition(TaskExecutionTracker::class)->getTag('container.preload')[0]['class']);
    }

    public function testWorkerIsRegistered(): void
    {
        $container = $this->getContainer([
            'path' => '/_foo',
            'timezone' => 'Europe/Paris',
            'transport' => [
                'dsn' => 'memory://first_in_first_out',
            ],
            'tasks' => [],
            'lock_store' => null,
        ]);

        self::assertTrue($container->hasDefinition(Worker::class));
        self::assertTrue($container->hasAlias(WorkerInterface::class));
        self::assertCount(7, $container->getDefinition(Worker::class)->getArguments());
        self::assertInstanceOf(Reference::class, $container->getDefinition(Worker::class)->getArgument(0));
        self::assertSame(SchedulerInterface::class, (string) $container->getDefinition(Worker::class)->getArgument(0));
        self::assertSame(ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $container->getDefinition(Worker::class)->getArgument(0)->getInvalidBehavior());
        self::assertInstanceOf(Reference::class, $container->getDefinition(Worker::class)->getArgument(1));
        self::assertSame(RunnerRegistryInterface::class, (string) $container->getDefinition(Worker::class)->getArgument(1));
        self::assertSame(ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $container->getDefinition(Worker::class)->getArgument(1)->getInvalidBehavior());
        self::assertInstanceOf(Reference::class, $container->getDefinition(Worker::class)->getArgument(2));
        self::assertSame(TaskExecutionTrackerInterface::class, (string) $container->getDefinition(Worker::class)->getArgument(2));
        self::assertSame(ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $container->getDefinition(Worker::class)->getArgument(2)->getInvalidBehavior());
        self::assertInstanceOf(Reference::class, $container->getDefinition(Worker::class)->getArgument(3));
        self::assertSame(WorkerMiddlewareStack::class, (string) $container->getDefinition(Worker::class)->getArgument(3));
        self::assertSame(ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $container->getDefinition(Worker::class)->getArgument(3)->getInvalidBehavior());
        self::assertInstanceOf(Reference::class, $container->getDefinition(Worker::class)->getArgument(4));
        self::assertSame(EventDispatcherInterface::class, (string) $container->getDefinition(Worker::class)->getArgument(4));
        self::assertSame(ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $container->getDefinition(Worker::class)->getArgument(4)->getInvalidBehavior());
        self::assertInstanceOf(Reference::class, $container->getDefinition(Worker::class)->getArgument(5));
        self::assertSame('scheduler.lock_store.factory', (string) $container->getDefinition(Worker::class)->getArgument(5));
        self::assertSame(ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $container->getDefinition(Worker::class)->getArgument(5)->getInvalidBehavior());
        self::assertInstanceOf(Reference::class, $container->getDefinition(Worker::class)->getArgument(6));
        self::assertSame(LoggerInterface::class, (string) $container->getDefinition(Worker::class)->getArgument(6));
        self::assertSame(ContainerInterface::NULL_ON_INVALID_REFERENCE, $container->getDefinition(Worker::class)->getArgument(6)->getInvalidBehavior());
        self::assertTrue($container->getDefinition(Worker::class)->hasTag('monolog.logger'));
        self::assertSame('scheduler', $container->getDefinition(Worker::class)->getTag('monolog.logger')[0]['channel']);
        self::assertTrue($container->getDefinition(Worker::class)->hasTag('container.preload'));
        self::assertSame(Worker::class, $container->getDefinition(Worker::class)->getTag('container.preload')[0]['class']);

        self::assertTrue($container->hasDefinition('scheduler.lock_store.store'));
        self::assertSame(PersistingStoreInterface::class, $container->getDefinition('scheduler.lock_store.store')->getClass());
        self::assertFalse($container->getDefinition('scheduler.lock_store.store')->isPublic());

        $factory = $container->getDefinition('scheduler.lock_store.store')->getFactory();
        self::assertIsArray($factory);
        self::assertSame(StoreFactory::class, $factory[0]);
        self::assertSame('createStore', (string) $factory[1]);
        self::assertCount(1, $container->getDefinition('scheduler.lock_store.store')->getArguments());
        self::assertSame('flock', $container->getDefinition('scheduler.lock_store.store')->getArgument('$connection'));
        self::assertTrue($container->getDefinition('scheduler.lock_store.store')->hasTag('container.preload'));
        self::assertSame(PersistingStoreInterface::class, $container->getDefinition('scheduler.lock_store.store')->getTag('container.preload')[0]['class']);

        self::assertTrue($container->hasDefinition('scheduler.lock_store.factory'));
        self::assertSame(LockFactory::class, $container->getDefinition('scheduler.lock_store.factory')->getClass());
        self::assertFalse($container->getDefinition('scheduler.lock_store.factory')->isPublic());
        self::assertCount(1, $container->getDefinition('scheduler.lock_store.factory')->getArguments());
        self::assertInstanceOf(Reference::class, $container->getDefinition('scheduler.lock_store.factory')->getArgument('$store'));
        self::assertSame('scheduler.lock_store.store', (string) $container->getDefinition('scheduler.lock_store.factory')->getArgument('$store'));
        self::assertSame(ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $container->getDefinition('scheduler.lock_store.factory')->getArgument('$store')->getInvalidBehavior());
        self::assertCount(1, $container->getDefinition('scheduler.lock_store.factory')->getMethodCalls());
        self::assertSame('setLogger', $container->getDefinition('scheduler.lock_store.factory')->getMethodCalls()[0][0]);
        self::assertInstanceOf(Reference::class, $container->getDefinition('scheduler.lock_store.factory')->getMethodCalls()[0][1][0]);
        self::assertSame(LoggerInterface::class, (string) $container->getDefinition('scheduler.lock_store.factory')->getMethodCalls()[0][1][0]);
        self::assertSame(ContainerInterface::NULL_ON_INVALID_REFERENCE, $container->getDefinition('scheduler.lock_store.factory')->getMethodCalls()[0][1][0]->getInvalidBehavior());
        self::assertTrue($container->getDefinition('scheduler.lock_store.factory')->hasTag('container.preload'));
        self::assertSame(LockFactory::class, $container->getDefinition('scheduler.lock_store.factory')->getTag('container.preload')[0]['class']);
    }

    public function testWorkerIsRegisteredWithLockStore(): void
    {
        $container = $this->getContainer([
            'path' => '/_foo',
            'timezone' => 'Europe/Paris',
            'transport' => [
                'dsn' => 'memory://first_in_first_out',
            ],
            'tasks' => [],
            'lock_store' => 'lock.foo.factory',
        ]);

        self::assertTrue($container->hasDefinition(Worker::class));
        self::assertTrue($container->hasAlias(WorkerInterface::class));
        self::assertCount(7, $container->getDefinition(Worker::class)->getArguments());
        self::assertInstanceOf(Reference::class, $container->getDefinition(Worker::class)->getArgument(0));
        self::assertSame(SchedulerInterface::class, (string) $container->getDefinition(Worker::class)->getArgument(0));
        self::assertSame(ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $container->getDefinition(Worker::class)->getArgument(0)->getInvalidBehavior());
        self::assertInstanceOf(Reference::class, $container->getDefinition(Worker::class)->getArgument(1));
        self::assertSame(RunnerRegistryInterface::class, (string) $container->getDefinition(Worker::class)->getArgument(1));
        self::assertSame(ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $container->getDefinition(Worker::class)->getArgument(1)->getInvalidBehavior());
        self::assertInstanceOf(Reference::class, $container->getDefinition(Worker::class)->getArgument(2));
        self::assertSame(TaskExecutionTrackerInterface::class, (string) $container->getDefinition(Worker::class)->getArgument(2));
        self::assertSame(ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $container->getDefinition(Worker::class)->getArgument(2)->getInvalidBehavior());
        self::assertInstanceOf(Reference::class, $container->getDefinition(Worker::class)->getArgument(3));
        self::assertSame(WorkerMiddlewareStack::class, (string) $container->getDefinition(Worker::class)->getArgument(3));
        self::assertSame(ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $container->getDefinition(Worker::class)->getArgument(3)->getInvalidBehavior());
        self::assertInstanceOf(Reference::class, $container->getDefinition(Worker::class)->getArgument(4));
        self::assertSame(EventDispatcherInterface::class, (string) $container->getDefinition(Worker::class)->getArgument(4));
        self::assertSame(ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $container->getDefinition(Worker::class)->getArgument(4)->getInvalidBehavior());
        self::assertInstanceOf(Reference::class, $container->getDefinition(Worker::class)->getArgument(5));
        self::assertSame('scheduler.lock_store.factory', (string) $container->getDefinition(Worker::class)->getArgument(5));
        self::assertSame(ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $container->getDefinition(Worker::class)->getArgument(5)->getInvalidBehavior());
        self::assertInstanceOf(Reference::class, $container->getDefinition(Worker::class)->getArgument(6));
        self::assertSame(LoggerInterface::class, (string) $container->getDefinition(Worker::class)->getArgument(6));
        self::assertSame(ContainerInterface::NULL_ON_INVALID_REFERENCE, $container->getDefinition(Worker::class)->getArgument(6)->getInvalidBehavior());
        self::assertTrue($container->getDefinition(Worker::class)->hasTag('monolog.logger'));
        self::assertSame('scheduler', $container->getDefinition(Worker::class)->getTag('monolog.logger')[0]['channel']);
        self::assertTrue($container->getDefinition(Worker::class)->hasTag('container.preload'));
        self::assertSame(Worker::class, $container->getDefinition(Worker::class)->getTag('container.preload')[0]['class']);

        self::assertTrue($container->hasDefinition('scheduler.lock_store.factory'));
        self::assertSame(LockFactory::class, $container->getDefinition('scheduler.lock_store.factory')->getClass());
        self::assertFalse($container->getDefinition('scheduler.lock_store.factory')->isPublic());
        self::assertCount(1, $container->getDefinition('scheduler.lock_store.factory')->getArguments());
        self::assertInstanceOf(Reference::class, $container->getDefinition('scheduler.lock_store.factory')->getArgument('$store'));
        self::assertSame('lock.foo.factory', (string) $container->getDefinition('scheduler.lock_store.factory')->getArgument('$store'));
        self::assertSame(ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $container->getDefinition('scheduler.lock_store.factory')->getArgument('$store')->getInvalidBehavior());
        self::assertCount(1, $container->getDefinition('scheduler.lock_store.factory')->getMethodCalls());
        self::assertSame('setLogger', $container->getDefinition('scheduler.lock_store.factory')->getMethodCalls()[0][0]);
        self::assertInstanceOf(Reference::class, $container->getDefinition('scheduler.lock_store.factory')->getMethodCalls()[0][1][0]);
        self::assertSame(LoggerInterface::class, (string) $container->getDefinition('scheduler.lock_store.factory')->getMethodCalls()[0][1][0]);
        self::assertSame(ContainerInterface::NULL_ON_INVALID_REFERENCE, $container->getDefinition('scheduler.lock_store.factory')->getMethodCalls()[0][1][0]->getInvalidBehavior());
        self::assertTrue($container->getDefinition('scheduler.lock_store.factory')->hasTag('container.preload'));
        self::assertSame(LockFactory::class, $container->getDefinition('scheduler.lock_store.factory')->getTag('container.preload')[0]['class']);
    }

    public function testTasksAreRegistered(): void
    {
        $container = $this->getContainer([
            'path' => '/_foo',
            'timezone' => 'Europe/Paris',
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
            'lock_store' => null,
        ]);

        self::assertTrue($container->hasDefinition('scheduler._foo_task'));
        self::assertEquals([
            'name' => 'foo',
            'type' => 'command',
            'command' => 'cache:clear',
            'expression' => '*/5 * * * *',
            'description' => 'A simple cache clear task',
            'options' => [
                'env' => 'test',
            ],
        ], $container->getDefinition('scheduler._foo_task')->getArgument(0));
        self::assertFalse($container->getDefinition('scheduler._foo_task')->isPublic());

        $factory = $container->getDefinition('scheduler._foo_task')->getFactory();
        self::assertIsArray($factory);
        self::assertArrayHasKey(0, $factory);
        self::assertArrayHasKey(1, $factory);
        self::assertInstanceOf(Reference::class, $factory[0]);
        self::assertSame('create', $factory[1]);

        self::assertTrue($container->getDefinition('scheduler._foo_task')->hasTag('scheduler.task'));
        self::assertTrue($container->getDefinition(Scheduler::class)->hasMethodCall('schedule'));
        self::assertInstanceOf(Definition::class, $container->getDefinition(Scheduler::class)->getMethodCalls()[0][1][0]);
    }

    public function testChainedTaskCanBeRegistered(): void
    {
        $container = $this->getContainer([
            'path' => '/_foo',
            'timezone' => 'Europe/Paris',
            'transport' => [
                'dsn' => 'memory://first_in_first_out',
            ],
            'tasks' => [
                'foo' => [
                    'type' => 'chained',
                    'tasks' => [
                        'bar' => [
                            'type' => 'shell',
                            'expression' => '* * * * *',
                        ],
                        'random' => [
                            'type' => 'shell',
                            'expression' => '*/5 * * * *',
                        ],
                    ],
                ],
            ],
        ]);

        self::assertTrue($container->hasDefinition('scheduler._foo_task'));
        self::assertEquals([
            'name' => 'foo',
            'type' => 'chained',
            'tasks' => [
                [
                    'name' => 'bar',
                    'type' => 'shell',
                    'expression' => '* * * * *',
                ],
                [
                    'name' => 'random',
                    'type' => 'shell',
                    'expression' => '*/5 * * * *',
                ],
            ],
        ], $container->getDefinition('scheduler._foo_task')->getArgument(0));
        self::assertFalse($container->getDefinition('scheduler._foo_task')->isPublic());

        $factory = $container->getDefinition('scheduler._foo_task')->getFactory();
        self::assertIsArray($factory);
        self::assertArrayHasKey(0, $factory);
        self::assertArrayHasKey(1, $factory);
        self::assertInstanceOf(Reference::class, $factory[0]);
        self::assertSame('create', $factory[1]);

        self::assertTrue($container->getDefinition('scheduler._foo_task')->hasTag('scheduler.task'));
        self::assertTrue($container->getDefinition(Scheduler::class)->hasMethodCall('schedule'));
        self::assertInstanceOf(Definition::class, $container->getDefinition(Scheduler::class)->getMethodCalls()[0][1][0]);
    }

    public function testDoctrineBridgeCannotBeConfiguredWithInvalidDsn(): void
    {
        $container = $this->getContainer([
            'path' => '/_foo',
            'timezone' => 'Europe/Paris',
            'transport' => [
                'dsn' => 'memory://batch',
            ],
            'tasks' => [],
            'lock_store' => null,
        ]);

        self::assertFalse($container->hasDefinition(SchedulerTransportDoctrineSchemaSubscriber::class));
        self::assertFalse($container->hasDefinition(DoctrineTransportFactory::class));
    }

    /**
     * @dataProvider provideDoctrineDsn
     */
    public function testDoctrineBridgeIsConfigured(string $dsn): void
    {
        $container = $this->getContainer([
            'path' => '/_foo',
            'timezone' => 'Europe/Paris',
            'transport' => [
                'dsn' => $dsn,
            ],
            'tasks' => [],
            'lock_store' => null,
        ]);

        self::assertTrue($container->hasDefinition(SchedulerTransportDoctrineSchemaSubscriber::class));
        self::assertFalse($container->getDefinition(SchedulerTransportDoctrineSchemaSubscriber::class)->isPublic());
        self::assertCount(1, $container->getDefinition(SchedulerTransportDoctrineSchemaSubscriber::class)->getArguments());
        self::assertInstanceOf(Reference::class, $container->getDefinition(SchedulerTransportDoctrineSchemaSubscriber::class)->getArgument(0));
        self::assertSame(TransportInterface::class, (string) $container->getDefinition(SchedulerTransportDoctrineSchemaSubscriber::class)->getArgument(0));
        self::assertTrue($container->getDefinition(SchedulerTransportDoctrineSchemaSubscriber::class)->hasTag('doctrine.event_subscriber'));
        self::assertTrue($container->getDefinition(SchedulerTransportDoctrineSchemaSubscriber::class)->hasTag('container.preload'));
        self::assertSame(SchedulerTransportDoctrineSchemaSubscriber::class, $container->getDefinition(SchedulerTransportDoctrineSchemaSubscriber::class)->getTag('container.preload')[0]['class']);

        self::assertTrue($container->hasDefinition(DoctrineTransportFactory::class));
        self::assertFalse($container->getDefinition(DoctrineTransportFactory::class)->isPublic());
        self::assertCount(2, $container->getDefinition(DoctrineTransportFactory::class)->getArguments());
        self::assertInstanceOf(Reference::class, $container->getDefinition(DoctrineTransportFactory::class)->getArgument(0));
        self::assertSame('doctrine', (string) $container->getDefinition(DoctrineTransportFactory::class)->getArgument(0));
        self::assertInstanceOf(Reference::class, $container->getDefinition(DoctrineTransportFactory::class)->getArgument(1));
        self::assertSame(LoggerInterface::class, (string) $container->getDefinition(DoctrineTransportFactory::class)->getArgument(1));
        self::assertTrue($container->getDefinition(DoctrineTransportFactory::class)->hasTag('scheduler.transport_factory'));
        self::assertTrue($container->getDefinition(DoctrineTransportFactory::class)->hasTag('container.preload'));
        self::assertSame(DoctrineTransportFactory::class, $container->getDefinition(DoctrineTransportFactory::class)->getTag('container.preload')[0]['class']);
    }

    public function testMiddlewareStackAreConfigured(): void
    {
        $container = $this->getContainer([
            'path' => '/_foo',
            'timezone' => 'Europe/Paris',
            'transport' => [
                'dsn' => 'memory://first_in_first_out',
            ],
            'tasks' => [],
            'lock_store' => null,
        ]);

        self::assertTrue($container->hasDefinition(SchedulerMiddlewareStack::class));
        self::assertFalse($container->getDefinition(SchedulerMiddlewareStack::class)->isPublic());
        self::assertCount(1, $container->getDefinition(SchedulerMiddlewareStack::class)->getArguments());
        self::assertInstanceOf(TaggedIteratorArgument::class, $container->getDefinition(SchedulerMiddlewareStack::class)->getArgument(0));
        self::assertTrue($container->getDefinition(SchedulerMiddlewareStack::class)->hasTag('scheduler.middleware_hub'));
        self::assertTrue($container->getDefinition(SchedulerMiddlewareStack::class)->hasTag('container.preload'));
        self::assertSame(SchedulerMiddlewareStack::class, $container->getDefinition(SchedulerMiddlewareStack::class)->getTag('container.preload')[0]['class']);

        self::assertTrue($container->hasDefinition(WorkerMiddlewareStack::class));
        self::assertFalse($container->getDefinition(WorkerMiddlewareStack::class)->isPublic());
        self::assertCount(1, $container->getDefinition(WorkerMiddlewareStack::class)->getArguments());
        self::assertInstanceOf(TaggedIteratorArgument::class, $container->getDefinition(WorkerMiddlewareStack::class)->getArgument(0));
        self::assertTrue($container->getDefinition(WorkerMiddlewareStack::class)->hasTag('scheduler.middleware_hub'));
        self::assertTrue($container->getDefinition(WorkerMiddlewareStack::class)->hasTag('container.preload'));
        self::assertSame(WorkerMiddlewareStack::class, $container->getDefinition(WorkerMiddlewareStack::class)->getTag('container.preload')[0]['class']);

        self::assertTrue($container->hasDefinition(NotifierMiddleware::class));
        self::assertFalse($container->getDefinition(NotifierMiddleware::class)->isPublic());
        self::assertCount(1, $container->getDefinition(NotifierMiddleware::class)->getArguments());
        self::assertInstanceOf(Reference::class, $container->getDefinition(NotifierMiddleware::class)->getArgument(0));
        self::assertSame(NotifierInterface::class, (string) $container->getDefinition(NotifierMiddleware::class)->getArgument(0));
        self::assertTrue($container->getDefinition(NotifierMiddleware::class)->hasTag('scheduler.scheduler_middleware'));
        self::assertTrue($container->getDefinition(NotifierMiddleware::class)->hasTag('scheduler.worker_middleware'));
        self::assertTrue($container->getDefinition(NotifierMiddleware::class)->hasTag('container.preload'));
        self::assertSame(NotifierMiddleware::class, $container->getDefinition(NotifierMiddleware::class)->getTag('container.preload')[0]['class']);

        self::assertTrue($container->hasDefinition(TaskCallbackMiddleware::class));
        self::assertFalse($container->getDefinition(TaskCallbackMiddleware::class)->isPublic());
        self::assertTrue($container->getDefinition(TaskCallbackMiddleware::class)->hasTag('scheduler.scheduler_middleware'));
        self::assertTrue($container->getDefinition(TaskCallbackMiddleware::class)->hasTag('scheduler.worker_middleware'));
        self::assertTrue($container->getDefinition(TaskCallbackMiddleware::class)->hasTag('container.preload'));
        self::assertSame(TaskCallbackMiddleware::class, $container->getDefinition(TaskCallbackMiddleware::class)->getTag('container.preload')[0]['class']);

        self::assertTrue($container->hasDefinition(SingleRunTaskMiddleware::class));
        self::assertFalse($container->getDefinition(SingleRunTaskMiddleware::class)->isPublic());
        self::assertCount(2, $container->getDefinition(SingleRunTaskMiddleware::class)->getArguments());
        self::assertInstanceOf(Reference::class, $container->getDefinition(SingleRunTaskMiddleware::class)->getArgument(0));
        self::assertSame(SchedulerInterface::class, (string) $container->getDefinition(SingleRunTaskMiddleware::class)->getArgument(0));
        self::assertSame(ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $container->getDefinition(SingleRunTaskMiddleware::class)->getArgument(0)->getInvalidBehavior());
        self::assertInstanceOf(Reference::class, $container->getDefinition(SingleRunTaskMiddleware::class)->getArgument(1));
        self::assertSame(LoggerInterface::class, (string) $container->getDefinition(SingleRunTaskMiddleware::class)->getArgument(1));
        self::assertSame(ContainerInterface::NULL_ON_INVALID_REFERENCE, $container->getDefinition(SingleRunTaskMiddleware::class)->getArgument(1)->getInvalidBehavior());
        self::assertTrue($container->getDefinition(SingleRunTaskMiddleware::class)->hasTag('scheduler.worker_middleware'));
        self::assertTrue($container->getDefinition(SingleRunTaskMiddleware::class)->hasTag('container.preload'));
        self::assertSame(SingleRunTaskMiddleware::class, $container->getDefinition(SingleRunTaskMiddleware::class)->getTag('container.preload')[0]['class']);

        self::assertTrue($container->hasDefinition(TaskUpdateMiddleware::class));
        self::assertFalse($container->getDefinition(TaskUpdateMiddleware::class)->isPublic());
        self::assertCount(1, $container->getDefinition(TaskUpdateMiddleware::class)->getArguments());
        self::assertInstanceOf(Reference::class, $container->getDefinition(TaskUpdateMiddleware::class)->getArgument(0));
        self::assertSame(SchedulerInterface::class, (string) $container->getDefinition(TaskUpdateMiddleware::class)->getArgument(0));
        self::assertTrue($container->getDefinition(TaskUpdateMiddleware::class)->hasTag('scheduler.worker_middleware'));
        self::assertTrue($container->getDefinition(TaskUpdateMiddleware::class)->hasTag('container.preload'));
        self::assertSame(TaskUpdateMiddleware::class, $container->getDefinition(TaskUpdateMiddleware::class)->getTag('container.preload')[0]['class']);

        self::assertTrue($container->hasDefinition(TaskLockBagMiddleware::class));
        self::assertFalse($container->getDefinition(TaskLockBagMiddleware::class)->isPublic());
        self::assertCount(2, $container->getDefinition(TaskLockBagMiddleware::class)->getArguments());
        self::assertInstanceOf(Reference::class, $container->getDefinition(TaskLockBagMiddleware::class)->getArgument(0));
        self::assertSame('scheduler.lock_store.factory', (string) $container->getDefinition(TaskLockBagMiddleware::class)->getArgument(0));
        self::assertSame(ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $container->getDefinition(TaskLockBagMiddleware::class)->getArgument(0)->getInvalidBehavior());
        self::assertInstanceOf(Reference::class, $container->getDefinition(TaskLockBagMiddleware::class)->getArgument(1));
        self::assertSame(LoggerInterface::class, (string) $container->getDefinition(TaskLockBagMiddleware::class)->getArgument(1));
        self::assertSame(ContainerInterface::NULL_ON_INVALID_REFERENCE, $container->getDefinition(TaskLockBagMiddleware::class)->getArgument(1)->getInvalidBehavior());
        self::assertTrue($container->getDefinition(TaskLockBagMiddleware::class)->hasTag('container.preload'));
        self::assertSame(TaskLockBagMiddleware::class, $container->getDefinition(TaskLockBagMiddleware::class)->getTag('container.preload')[0]['class']);

        self::assertTrue($container->hasDefinition(TaskExecutionMiddleware::class));
        self::assertFalse($container->getDefinition(TaskExecutionMiddleware::class)->isPublic());
        self::assertCount(0, $container->getDefinition(TaskExecutionMiddleware::class)->getArguments());
        self::assertTrue($container->getDefinition(TaskExecutionMiddleware::class)->hasTag('scheduler.worker_middleware'));
        self::assertTrue($container->getDefinition(TaskExecutionMiddleware::class)->hasTag('container.preload'));
        self::assertSame(TaskExecutionMiddleware::class, $container->getDefinition(TaskExecutionMiddleware::class)->getTag('container.preload')[0]['class']);

        self::assertFalse($container->hasDefinition(MaxExecutionMiddleware::class));
    }

    public function testRateLimiterMiddlewareCanBeConfigured(): void
    {
        $container = $this->getContainer([
            'path' => '/_foo',
            'timezone' => 'Europe/Paris',
            'transport' => [
                'dsn' => 'memory://first_in_first_out',
            ],
            'tasks' => [],
            'lock_store' => null,
            'rate_limiter' => 'foo',
        ]);

        self::assertTrue($container->hasDefinition(MaxExecutionMiddleware::class));
        self::assertFalse($container->getDefinition(MaxExecutionMiddleware::class)->isPublic());
        self::assertCount(2, $container->getDefinition(MaxExecutionMiddleware::class)->getArguments());
        self::assertInstanceOf(Reference::class, $container->getDefinition(MaxExecutionMiddleware::class)->getArgument(0));
        self::assertSame('limiter.foo', (string) $container->getDefinition(MaxExecutionMiddleware::class)->getArgument(0));
        self::assertInstanceOf(Reference::class, $container->getDefinition(MaxExecutionMiddleware::class)->getArgument(1));
        self::assertSame(LoggerInterface::class, (string) $container->getDefinition(MaxExecutionMiddleware::class)->getArgument(1));
        self::assertTrue($container->getDefinition(MaxExecutionMiddleware::class)->hasTag('scheduler.worker_middleware'));
        self::assertTrue($container->getDefinition(MaxExecutionMiddleware::class)->hasTag('container.preload'));
        self::assertSame(MaxExecutionMiddleware::class, $container->getDefinition(MaxExecutionMiddleware::class)->getTag('container.preload')[0]['class']);
    }

    public function testProbeContextIsConfigured(): void
    {
        $container = $this->getContainer([
            'path' => '/_foo',
            'timezone' => 'Europe/Paris',
            'transport' => [
                'dsn' => 'memory://first_in_first_out',
            ],
            'tasks' => [],
            'probe' => [
                'enabled' => true,
            ],
        ]);

        self::assertTrue($container->hasParameter('scheduler.probe_path'));
        self::assertSame('/_probe', $container->getParameter('scheduler.probe_path'));
        self::assertTrue($container->hasParameter('scheduler.probe_enabled'));
        self::assertTrue($container->getParameter('scheduler.probe_enabled'));

        self::assertTrue($container->hasDefinition(Probe::class));
        self::assertTrue($container->hasAlias(ProbeInterface::class));
        self::assertSame(Probe::class, (string) $container->getAlias(ProbeInterface::class));
        self::assertTrue($container->getDefinition(Probe::class)->hasTag('scheduler.probe'));
        self::assertFalse($container->getDefinition(Probe::class)->isPublic());
        self::assertCount(2, $container->getDefinition(Probe::class)->getArguments());
        self::assertInstanceOf(Reference::class, $container->getDefinition(Probe::class)->getArgument(0));
        self::assertSame(SchedulerInterface::class, (string) $container->getDefinition(Probe::class)->getArgument(0));
        self::assertSame(ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $container->getDefinition(Probe::class)->getArgument(0)->getInvalidBehavior());
        self::assertInstanceOf(Reference::class, $container->getDefinition(Probe::class)->getArgument(1));
        self::assertSame(WorkerInterface::class, (string) $container->getDefinition(Probe::class)->getArgument(1));
        self::assertSame(ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $container->getDefinition(Probe::class)->getArgument(1)->getInvalidBehavior());
        self::assertTrue($container->getDefinition(Probe::class)->hasTag('container.preload'));
        self::assertSame(Probe::class, $container->getDefinition(Probe::class)->getTag('container.preload')[0]['class']);

        self::assertTrue($container->hasDefinition(ProbeTaskRunner::class));
        self::assertFalse($container->getDefinition(ProbeTaskRunner::class)->isPublic());
        self::assertCount(0, $container->getDefinition(ProbeTaskRunner::class)->getArguments());
        self::assertTrue($container->getDefinition(ProbeTaskRunner::class)->hasTag('scheduler.runner'));
        self::assertTrue($container->getDefinition(ProbeTaskRunner::class)->hasTag('container.preload'));
        self::assertSame(ProbeTaskRunner::class, $container->getDefinition(ProbeTaskRunner::class)->getTag('container.preload')[0]['class']);

        self::assertTrue($container->hasDefinition(ProbeStateSubscriber::class));
        self::assertFalse($container->getDefinition(ProbeStateSubscriber::class)->isPublic());
        self::assertCount(2, $container->getDefinition(ProbeStateSubscriber::class)->getArguments());
        self::assertInstanceOf(Reference::class, $container->getDefinition(ProbeStateSubscriber::class)->getArgument(0));
        self::assertSame(ProbeInterface::class, (string) $container->getDefinition(ProbeStateSubscriber::class)->getArgument(0));
        self::assertSame(ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $container->getDefinition(ProbeStateSubscriber::class)->getArgument(0)->getInvalidBehavior());
        self::assertSame('/_probe', $container->getDefinition(ProbeStateSubscriber::class)->getArgument(1));
        self::assertTrue($container->getDefinition(ProbeStateSubscriber::class)->hasTag('kernel.event_subscriber'));
        self::assertTrue($container->getDefinition(ProbeStateSubscriber::class)->hasTag('container.preload'));
        self::assertSame(ProbeStateSubscriber::class, $container->getDefinition(ProbeStateSubscriber::class)->getTag('container.preload')[0]['class']);

        self::assertTrue($container->hasDefinition(ProbeTaskMiddleware::class));
        self::assertCount(0, $container->getDefinition(ProbeTaskMiddleware::class)->getArguments());
        self::assertFalse($container->getDefinition(ProbeTaskMiddleware::class)->isPublic());
        self::assertTrue($container->getDefinition(ProbeTaskMiddleware::class)->hasTag('scheduler.worker_middleware'));
        self::assertTrue($container->getDefinition(ProbeTaskMiddleware::class)->hasTag('container.preload'));
        self::assertSame(ProbeTaskMiddleware::class, $container->getDefinition(ProbeTaskMiddleware::class)->getTag('container.preload')[0]['class']);

        self::assertTrue($container->hasDefinition(ProbeTaskBuilder::class));
        self::assertCount(1, $container->getDefinition(ProbeTaskBuilder::class)->getArguments());
        self::assertInstanceOf(Reference::class, $container->getDefinition(ProbeTaskBuilder::class)->getArgument(0));
        self::assertSame(BuilderInterface::class, (string) $container->getDefinition(ProbeTaskBuilder::class)->getArgument(0));
        self::assertSame(ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $container->getDefinition(ProbeTaskBuilder::class)->getArgument(0)->getInvalidBehavior());
        self::assertFalse($container->getDefinition(ProbeTaskBuilder::class)->isPublic());
        self::assertTrue($container->getDefinition(ProbeTaskBuilder::class)->hasTag('scheduler.task_builder'));
        self::assertTrue($container->getDefinition(ProbeTaskBuilder::class)->hasTag('container.preload'));
        self::assertSame(ProbeTaskBuilder::class, $container->getDefinition(ProbeTaskBuilder::class)->getTag('container.preload')[0]['class']);

        self::assertTrue($container->hasDefinition(DebugProbeCommand::class));
        self::assertFalse($container->getDefinition(DebugProbeCommand::class)->isPublic());
        self::assertCount(2, $container->getDefinition(DebugProbeCommand::class)->getArguments());
        self::assertInstanceOf(Reference::class, $container->getDefinition(DebugProbeCommand::class)->getArgument(0));
        self::assertSame(ProbeInterface::class, (string) $container->getDefinition(DebugProbeCommand::class)->getArgument(0));
        self::assertSame(ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $container->getDefinition(DebugProbeCommand::class)->getArgument(0)->getInvalidBehavior());
        self::assertInstanceOf(Reference::class, $container->getDefinition(DebugProbeCommand::class)->getArgument(1));
        self::assertSame(SchedulerInterface::class, (string) $container->getDefinition(DebugProbeCommand::class)->getArgument(1));
        self::assertSame(ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $container->getDefinition(DebugProbeCommand::class)->getArgument(1)->getInvalidBehavior());
        self::assertTrue($container->getDefinition(DebugProbeCommand::class)->hasTag('console.command'));
        self::assertTrue($container->getDefinition(DebugProbeCommand::class)->hasTag('container.preload'));
        self::assertSame(DebugProbeCommand::class, $container->getDefinition(DebugProbeCommand::class)->getTag('container.preload')[0]['class']);

        self::assertTrue($container->hasDefinition(ExecuteExternalProbeCommand::class));
        self::assertFalse($container->getDefinition(ExecuteExternalProbeCommand::class)->isPublic());
        self::assertCount(2, $container->getDefinition(ExecuteExternalProbeCommand::class)->getArguments());
        self::assertInstanceOf(Reference::class, $container->getDefinition(ExecuteExternalProbeCommand::class)->getArgument(0));
        self::assertSame(SchedulerInterface::class, (string) $container->getDefinition(ExecuteExternalProbeCommand::class)->getArgument(0));
        self::assertSame(ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $container->getDefinition(ExecuteExternalProbeCommand::class)->getArgument(0)->getInvalidBehavior());
        self::assertInstanceOf(Reference::class, $container->getDefinition(ExecuteExternalProbeCommand::class)->getArgument(1));
        self::assertSame(WorkerInterface::class, (string) $container->getDefinition(ExecuteExternalProbeCommand::class)->getArgument(1));
        self::assertSame(ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $container->getDefinition(ExecuteExternalProbeCommand::class)->getArgument(1)->getInvalidBehavior());
        self::assertTrue($container->getDefinition(ExecuteExternalProbeCommand::class)->hasTag('console.command'));
        self::assertTrue($container->getDefinition(ExecuteExternalProbeCommand::class)->hasTag('container.preload'));
        self::assertSame(ExecuteExternalProbeCommand::class, $container->getDefinition(ExecuteExternalProbeCommand::class)->getTag('container.preload')[0]['class']);
    }

    public function testProbeTasksCanBeConfigured(): void
    {
        $container = $this->getContainer([
            'path' => '/_foo',
            'timezone' => 'Europe/Paris',
            'transport' => [
                'dsn' => 'memory://first_in_first_out',
            ],
            'tasks' => [],
            'probe' => [
                'enabled' => true,
                'clients' => [
                    'foo' => [
                        'externalProbePath' => '/_external_probe',
                        'errorOnFailedTasks' => true,
                        'delay' => 1000,
                    ],
                ],
            ],
        ]);

        self::assertTrue($container->hasDefinition('scheduler._foo.probe_task'));
        self::assertEquals([
            'name' => 'foo.probe',
            'type' => 'probe',
            'expression' => '* * * * *',
            'externalProbePath' => '/_external_probe',
            'errorOnFailedTasks' => true,
            'delay' => 1000,
        ], $container->getDefinition('scheduler._foo.probe_task')->getArgument(0));
        self::assertFalse($container->getDefinition('scheduler._foo.probe_task')->isPublic());

        $probeTaskFactory = $container->getDefinition('scheduler._foo.probe_task')->getFactory();
        self::assertIsArray($probeTaskFactory);
        self::assertArrayHasKey(0, $probeTaskFactory);
        self::assertInstanceOf(Reference::class, $probeTaskFactory[0]);
        self::assertArrayHasKey(1, $probeTaskFactory);
        self::assertSame('create', $probeTaskFactory[1]);

        self::assertTrue($container->getDefinition('scheduler._foo.probe_task')->hasTag('scheduler.task'));
        self::assertTrue($container->getDefinition(Scheduler::class)->hasMethodCall('schedule'));
        self::assertInstanceOf(Definition::class, $container->getDefinition(Scheduler::class)->getMethodCalls()[0][1][0]);
    }

    public function testDataCollectorIsConfigured(): void
    {
        $container = $this->getContainer([
            'path' => '/_foo',
            'timezone' => 'Europe/Paris',
            'transport' => [
                'dsn' => 'memory://first_in_first_out',
            ],
            'tasks' => [],
            'lock_store' => null,
        ]);

        self::assertTrue($container->hasDefinition(SchedulerDataCollector::class));
        self::assertFalse($container->getDefinition(SchedulerDataCollector::class)->isPublic());
        self::assertCount(2, $container->getDefinition(SchedulerDataCollector::class)->getArguments());
        self::assertInstanceOf(Reference::class, $container->getDefinition(SchedulerDataCollector::class)->getArgument(0));
        self::assertSame(TaskLoggerSubscriber::class, (string) $container->getDefinition(SchedulerDataCollector::class)->getArgument(0));
        self::assertSame(ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $container->getDefinition(SchedulerDataCollector::class)->getArgument(0)->getInvalidBehavior());
        self::assertInstanceOf(Reference::class, $container->getDefinition(SchedulerDataCollector::class)->getArgument(1));
        self::assertSame(ProbeInterface::class, (string) $container->getDefinition(SchedulerDataCollector::class)->getArgument(1));
        self::assertSame(ContainerInterface::NULL_ON_INVALID_REFERENCE, $container->getDefinition(SchedulerDataCollector::class)->getArgument(1)->getInvalidBehavior());
        self::assertTrue($container->getDefinition(SchedulerDataCollector::class)->hasTag('data_collector'));
        self::assertSame('@Scheduler/Collector/data_collector.html.twig', $container->getDefinition(SchedulerDataCollector::class)->getTag('data_collector')[0]['template']);
        self::assertSame(SchedulerDataCollector::NAME, $container->getDefinition(SchedulerDataCollector::class)->getTag('data_collector')[0]['id']);
        self::assertTrue($container->getDefinition(SchedulerDataCollector::class)->hasTag('container.preload'));
        self::assertSame(SchedulerDataCollector::class, $container->getDefinition(SchedulerDataCollector::class)->getTag('container.preload')[0]['class']);
    }

    public function testMercureHubCannotBeRegisteredWithoutBeingEnabled(): void
    {
        $container = $this->getContainer([
            'path' => '/_foo',
            'timezone' => 'Europe/Paris',
            'transport' => [
                'dsn' => 'memory://first_in_first_out',
            ],
            'mercure' => [
                'enabled' => false,
            ],
        ]);

        self::assertFalse($container->hasDefinition('scheduler.mercure_hub'));
        self::assertFalse($container->hasDefinition('scheduler.mercure.token_provider'));
        self::assertFalse($container->hasDefinition(MercureEventSubscriber::class));
    }

    public function testMercureSupportCanBeRegistered(): void
    {
        $container = $this->getContainer([
            'path' => '/_foo',
            'timezone' => 'Europe/Paris',
            'transport' => [
                'dsn' => 'memory://first_in_first_out',
            ],
            'mercure' => [
                'enabled' => true,
                'hub_url' => 'https://www.hub.com/.well-known/mercure',
                'update_url' => 'https://www.update.com',
                'jwt_token' => 'foo',
            ],
        ]);

        self::assertTrue($container->hasDefinition('scheduler.mercure_hub'));
        self::assertCount(2, $container->getDefinition('scheduler.mercure_hub')->getArguments());
        self::assertSame('https://www.hub.com/.well-known/mercure', (string) $container->getDefinition('scheduler.mercure_hub')->getArgument(0));
        self::assertInstanceOf(Reference::class, $container->getDefinition('scheduler.mercure_hub')->getArgument(1));
        self::assertSame('scheduler.mercure.token_provider', (string) $container->getDefinition('scheduler.mercure_hub')->getArgument(1));
        self::assertSame(ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $container->getDefinition('scheduler.mercure_hub')->getArgument(1)->getInvalidBehavior());
        self::assertFalse($container->getDefinition('scheduler.mercure_hub')->isPublic());
        self::assertTrue($container->getDefinition('scheduler.mercure_hub')->hasTag('container.preload'));
        self::assertSame(Hub::class, $container->getDefinition('scheduler.mercure_hub')->getTag('container.preload')[0]['class']);

        self::assertTrue($container->hasDefinition('scheduler.mercure.token_provider'));
        self::assertCount(1, $container->getDefinition('scheduler.mercure.token_provider')->getArguments());
        self::assertSame('foo', (string) $container->getDefinition('scheduler.mercure.token_provider')->getArgument(0));
        self::assertFalse($container->getDefinition('scheduler.mercure.token_provider')->isPublic());
        self::assertTrue($container->getDefinition('scheduler.mercure.token_provider')->hasTag('container.preload'));
        self::assertSame(StaticTokenProvider::class, $container->getDefinition('scheduler.mercure.token_provider')->getTag('container.preload')[0]['class']);

        self::assertTrue($container->hasDefinition(MercureEventSubscriber::class));
        self::assertCount(3, $container->getDefinition(MercureEventSubscriber::class)->getArguments());
        self::assertInstanceOf(Reference::class, $container->getDefinition(MercureEventSubscriber::class)->getArgument(0));
        self::assertSame('scheduler.mercure_hub', (string) $container->getDefinition(MercureEventSubscriber::class)->getArgument(0));
        self::assertSame(ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $container->getDefinition(MercureEventSubscriber::class)->getArgument(0)->getInvalidBehavior());
        self::assertSame('https://www.update.com', (string) $container->getDefinition(MercureEventSubscriber::class)->getArgument(1));
        self::assertInstanceOf(Reference::class, $container->getDefinition(MercureEventSubscriber::class)->getArgument(2));
        self::assertSame(SerializerInterface::class, (string) $container->getDefinition(MercureEventSubscriber::class)->getArgument(2));
        self::assertSame(ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $container->getDefinition(MercureEventSubscriber::class)->getArgument(2)->getInvalidBehavior());
        self::assertFalse($container->getDefinition(MercureEventSubscriber::class)->isPublic());
        self::assertTrue($container->getDefinition(MercureEventSubscriber::class)->hasTag('kernel.event_subscriber'));
        self::assertTrue($container->getDefinition(MercureEventSubscriber::class)->hasTag('container.preload'));
        self::assertSame(MercureEventSubscriber::class, $container->getDefinition(MercureEventSubscriber::class)->getTag('container.preload')[0]['class']);
    }

    public function testPoolSupportCanBeRegistered(): void
    {
        $container = $this->getContainer([
            'path' => '/_foo',
            'timezone' => 'Europe/Paris',
            'transport' => [
                'dsn' => 'memory://first_in_first_out',
            ],
            'pool' => [
                'enabled' => true,
            ],
        ]);

        self::assertTrue($container->getParameter('scheduler.pool_support'));
    }

    public function testConfiguration(): void
    {
        $extension = new SchedulerBundleExtension();

        self::assertInstanceOf(SchedulerBundleConfiguration::class, $extension->getConfiguration([], new ContainerBuilder()));
    }

    /**
     * @return Generator<array<int, string>>
     */
    public function provideDoctrineDsn(): Generator
    {
        yield 'Doctrine version' => ['doctrine://default'];
        yield 'Dbal version' => ['dbal://default'];
    }

    /**
     * @param array<string, mixed> $configuration
     */
    private function getContainer(array $configuration = []): ContainerBuilder
    {
        $containerBuilder = new ContainerBuilder();
        $containerBuilder->registerExtension(new SchedulerBundleExtension());
        $containerBuilder->loadFromExtension('scheduler_bundle', $configuration);

        $containerBuilder->getCompilerPassConfig()->setOptimizationPasses([new ResolveChildDefinitionsPass()]);
        $containerBuilder->getCompilerPassConfig()->setRemovingPasses([]);
        $containerBuilder->getCompilerPassConfig()->setAfterRemovingPasses([]);
        $containerBuilder->compile();

        return $containerBuilder;
    }
}
