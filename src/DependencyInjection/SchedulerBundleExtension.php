<?php

declare(strict_types=1);

namespace SchedulerBundle\DependencyInjection;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Redis;
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
use SchedulerBundle\Middleware\PostSchedulingMiddlewareInterface;
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
use SchedulerBundle\Middleware\PostExecutionMiddlewareInterface;
use SchedulerBundle\Middleware\PreExecutionMiddlewareInterface;
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
use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\TaskBag\TaskBagInterface;
use SchedulerBundle\Transport\CacheTransportFactory;
use SchedulerBundle\Transport\Configuration\ConfigurationFactory;
use SchedulerBundle\Transport\Configuration\ConfigurationFactoryInterface;
use SchedulerBundle\Transport\Configuration\ConfigurationInterface as TransportConfigurationInterface;
use SchedulerBundle\Transport\Configuration\InMemoryConfigurationFactory;
use SchedulerBundle\Transport\Configuration\LazyConfigurationFactory;
use SchedulerBundle\Transport\Dsn;
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
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Extension\Extension;
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
use Symfony\Component\Stopwatch\Stopwatch;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use function array_key_exists;
use function array_merge;
use function class_exists;
use function interface_exists;
use function sprintf;
use function strpos;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class SchedulerBundleExtension extends Extension
{
    private const SCHEDULER_EXPRESSION_BUILDER_TAG = 'scheduler.expression_builder';
    private const SCHEDULER_RUNNER_TAG = 'scheduler.runner';
    private const SCHEDULER_TASK_BUILDER_TAG = 'scheduler.task_builder';
    private const SCHEDULER_PROBE_TAG = 'scheduler.probe';
    private const SCHEDULER_SCHEDULER_MIDDLEWARE_TAG = 'scheduler.scheduler_middleware';
    private const SCHEDULER_WORKER_MIDDLEWARE_TAG = 'scheduler.worker_middleware';
    private const SCHEDULER_TRANSPORT_FACTORY_TAG = 'scheduler.transport_factory';
    private const SCHEDULER_SCHEDULE_POLICY = 'scheduler.schedule_policy';
    private const TRANSPORT_CONFIGURATION_TAG = 'scheduler.configuration';
    private const TRANSPORT_CONFIGURATION_FACTORY_TAG = 'scheduler.configuration_factory';

    public function load(array $configs, ContainerBuilder $container): void
    {
        $schedulerBundleConfiguration = new SchedulerBundleConfiguration();

        $config = $this->processConfiguration($schedulerBundleConfiguration, $configs);

        if (!array_key_exists('transport', $config)) {
            return;
        }

        $this->registerParameters($container, $config);
        $this->registerAutoConfigure($container);
        $this->registerConfigurationFactories($container);
        $this->registerConfiguration($container, $config);
        $this->registerTransportFactories($container, $config);
        $this->registerTransport($container, $config);
        $this->registerLockStore($container, $config);
        $this->registerScheduler($container);
        $this->registerCommands($container);
        $this->registerExpressionFactoryAndPolicies($container);
        $this->registerBuilders($container);
        $this->registerRunners($container);
        $this->registerNormalizer($container);
        $this->registerMessengerTools($container);
        $this->registerSubscribers($container);
        $this->registerTracker($container);
        $this->registerWorker($container);
        $this->registerTasks($container, $config);
        $this->registerDoctrineBridge($container, $config);
        $this->registerRedisBridge($container);
        $this->registerMiddlewareStacks($container, $config);
        $this->registerProbeContext($container, $config);
        $this->registerMercureSupport($container, $config);
        $this->registerDataCollector($container);
    }

    /**
     * @param array<string, mixed> $configuration
     */
    private function registerParameters(ContainerBuilder $container, array $configuration): void
    {
        $container->setParameter('scheduler.timezone', $configuration['timezone']);
        $container->setParameter('scheduler.trigger_path', $configuration['path']);
        $container->setParameter('scheduler.scheduler_mode', $configuration['scheduler']['mode'] ?? 'default');
        $container->setParameter('scheduler.probe_enabled', $configuration['probe']['enabled'] ?? false);
        $container->setParameter('scheduler.mercure_support', $configuration['mercure']['enabled']);
        $container->setParameter('scheduler.pool_support', $configuration['pool']['enabled']);
    }

    private function registerAutoConfigure(ContainerBuilder $container): void
    {
        $container->registerForAutoconfiguration(RunnerInterface::class)->addTag(self::SCHEDULER_RUNNER_TAG);
        $container->registerForAutoconfiguration(TransportInterface::class)->addTag('scheduler.transport');
        $container->registerForAutoconfiguration(TransportFactoryInterface::class)->addTag(self::SCHEDULER_TRANSPORT_FACTORY_TAG);
        $container->registerForAutoconfiguration(TransportConfigurationInterface::class)->addTag(self::TRANSPORT_CONFIGURATION_TAG);
        $container->registerForAutoconfiguration(ConfigurationFactoryInterface::class)->addTag(self::TRANSPORT_CONFIGURATION_FACTORY_TAG);
        $container->registerForAutoconfiguration(PolicyInterface::class)->addTag(self::SCHEDULER_SCHEDULE_POLICY);
        $container->registerForAutoconfiguration(WorkerInterface::class)->addTag('scheduler.worker');
        $container->registerForAutoconfiguration(MiddlewareStackInterface::class)->addTag('scheduler.middleware_hub');
        $container->registerForAutoconfiguration(PreSchedulingMiddlewareInterface::class)->addTag(self::SCHEDULER_SCHEDULER_MIDDLEWARE_TAG);
        $container->registerForAutoconfiguration(PostSchedulingMiddlewareInterface::class)->addTag(self::SCHEDULER_SCHEDULER_MIDDLEWARE_TAG);
        $container->registerForAutoconfiguration(PreExecutionMiddlewareInterface::class)->addTag(self::SCHEDULER_WORKER_MIDDLEWARE_TAG);
        $container->registerForAutoconfiguration(PostExecutionMiddlewareInterface::class)->addTag(self::SCHEDULER_WORKER_MIDDLEWARE_TAG);
        $container->registerForAutoconfiguration(ExpressionBuilderInterface::class)->addTag(self::SCHEDULER_EXPRESSION_BUILDER_TAG);
        $container->registerForAutoconfiguration(BuilderInterface::class)->addTag(self::SCHEDULER_TASK_BUILDER_TAG);
        $container->registerForAutoconfiguration(ProbeInterface::class)->addTag(self::SCHEDULER_PROBE_TAG);
        $container->registerForAutoconfiguration(TaskBagInterface::class)->addTag('scheduler.task_bag');
    }

    private function registerConfigurationFactories(ContainerBuilder $container): void
    {
        $container->register(ConfigurationFactory::class, ConfigurationFactory::class)
            ->setArguments([
                new TaggedIteratorArgument(self::TRANSPORT_CONFIGURATION_FACTORY_TAG),
            ])
            ->setPublic(false)
            ->addTag('container.preload', [
                'class' => ConfigurationFactory::class,
            ])
        ;

        $container->register(InMemoryConfigurationFactory::class, InMemoryConfigurationFactory::class)
            ->setPublic(false)
            ->addTag(self::TRANSPORT_CONFIGURATION_FACTORY_TAG)
            ->addTag('container.preload', [
                'class' => InMemoryConfigurationFactory::class,
            ])
        ;

        $container->register(LazyConfigurationFactory::class, LazyConfigurationFactory::class)
            ->setArguments([
                new TaggedIteratorArgument(self::TRANSPORT_CONFIGURATION_FACTORY_TAG),
            ])
            ->setPublic(false)
            ->addTag(self::TRANSPORT_CONFIGURATION_FACTORY_TAG)
            ->addTag('container.preload', [
                'class' => LazyConfigurationFactory::class,
            ])
        ;
    }

    /**
     * @param ContainerBuilder     $container
     * @param array<string, mixed> $configuration
     */
    private function registerConfiguration(ContainerBuilder $container, array $configuration): void
    {
        $container->register(self::TRANSPORT_CONFIGURATION_TAG, TransportConfigurationInterface::class)
            ->setFactory([new Reference(ConfigurationFactory::class, ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE), 'build'])
            ->setArguments([
                $configuration['configuration']['dsn'],
                new Reference(SerializerInterface::class, ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE),
            ])
            ->setPublic(false)
            ->addTag(self::TRANSPORT_CONFIGURATION_TAG)
            ->addTag('container.preload', [
                'class' => TransportConfigurationInterface::class,
            ])
        ;

        $container->setAlias(TransportConfigurationInterface::class, self::TRANSPORT_CONFIGURATION_TAG);
    }

    private function registerTransportFactories(ContainerBuilder $container, array $configuration): void
    {
        $container->register(TransportFactory::class, TransportFactory::class)
            ->setArguments([
                new TaggedIteratorArgument(self::SCHEDULER_TRANSPORT_FACTORY_TAG),
            ])
            ->addTag('container.preload', [
                'class' => TransportFactory::class,
            ])
        ;

        $container->setAlias(TransportFactoryInterface::class, TransportFactory::class);

        $container->register(InMemoryTransportFactory::class, InMemoryTransportFactory::class)
            ->setPublic(false)
            ->addTag(self::SCHEDULER_TRANSPORT_FACTORY_TAG)
            ->addTag('container.preload', [
                'class' => InMemoryTransportFactory::class,
            ])
        ;

        $container->register(FilesystemTransportFactory::class, FilesystemTransportFactory::class)
            ->setPublic(false)
            ->addTag(self::SCHEDULER_TRANSPORT_FACTORY_TAG)
            ->addTag('container.preload', [
                'class' => FilesystemTransportFactory::class,
            ])
        ;

        $container->register(FailOverTransportFactory::class, FailOverTransportFactory::class)
            ->setArguments([
                new TaggedIteratorArgument(self::SCHEDULER_TRANSPORT_FACTORY_TAG),
            ])
            ->setPublic(false)
            ->addTag(self::SCHEDULER_TRANSPORT_FACTORY_TAG)
            ->addTag('container.preload', [
                'class' => FailOverTransportFactory::class,
            ])
        ;

        $container->register(LongTailTransportFactory::class, LongTailTransportFactory::class)
            ->setArguments([
                new TaggedIteratorArgument(self::SCHEDULER_TRANSPORT_FACTORY_TAG),
            ])
            ->setPublic(false)
            ->addTag(self::SCHEDULER_TRANSPORT_FACTORY_TAG)
            ->addTag('container.preload', [
                'class' => LongTailTransportFactory::class,
            ])
        ;

        $container->register(RoundRobinTransportFactory::class, RoundRobinTransportFactory::class)
            ->setArguments([
                new TaggedIteratorArgument(self::SCHEDULER_TRANSPORT_FACTORY_TAG),
            ])
            ->setPublic(false)
            ->addTag(self::SCHEDULER_TRANSPORT_FACTORY_TAG)
            ->addTag('container.preload', [
                'class' => RoundRobinTransportFactory::class,
            ])
        ;

        $container->register(LazyTransportFactory::class, LazyTransportFactory::class)
            ->setArguments([
                new TaggedIteratorArgument(self::SCHEDULER_TRANSPORT_FACTORY_TAG),
            ])
            ->setPublic(false)
            ->addTag(self::SCHEDULER_TRANSPORT_FACTORY_TAG)
            ->addTag('container.preload', [
                'class' => LazyTransportFactory::class,
            ])
        ;

        if (0 !== strpos($configuration['transport']['dsn'], 'cache://')) {
            return;
        }

        $container->register(CacheTransportFactory::class, CacheTransportFactory::class)
            ->setArguments([
                new Reference(
                    sprintf('cache.%s', Dsn::fromString($configuration['transport']['dsn'])->getHost()),
                    ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE
                ),
            ])
            ->setPublic(false)
            ->addTag(self::SCHEDULER_TRANSPORT_FACTORY_TAG)
            ->addTag('container.preload', [
                'class' => CacheTransportFactory::class,
            ])
        ;
    }

    /**
     * @param array<string, mixed> $configuration
     */
    private function registerTransport(ContainerBuilder $container, array $configuration): void
    {
        $container->register('scheduler.transport', TransportInterface::class)
            ->setFactory([new Reference(TransportFactoryInterface::class), 'createTransport'])
            ->setArguments([
                $configuration['transport']['dsn'],
                $configuration['transport']['options'],
                new Reference(SerializerInterface::class, ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE),
                new Reference(SchedulePolicyOrchestratorInterface::class, ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE),
            ])
            ->setPublic(false)
            ->addTag('scheduler.transport')
            ->setShared(true)
            ->addTag('container.preload', [
                'class' => TransportInterface::class,
            ])
        ;

        $container->setAlias(TransportInterface::class, 'scheduler.transport');
    }

    /**
     * @param array<string, mixed> $configuration
     */
    private function registerLockStore(ContainerBuilder $container, array $configuration): void
    {
        if (null === $configuration['lock_store']) {
            $container->register('scheduler.lock_store.store', PersistingStoreInterface::class)
                ->setFactory([StoreFactory::class, 'createStore'])
                ->setArgument('$connection', 'flock')
                ->setPublic(false)
                ->addTag('container.preload', [
                    'class' => PersistingStoreInterface::class,
                ])
            ;
        }

        $container->register('scheduler.lock_store.factory', LockFactory::class)
            ->setArgument('$store', new Reference($configuration['lock_store'] ?? 'scheduler.lock_store.store', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE))
            ->addMethodCall('setLogger', [
                new Reference(LoggerInterface::class, ContainerInterface::NULL_ON_INVALID_REFERENCE),
            ])
            ->setPublic(false)
            ->addTag('container.preload', [
                'class' => LockFactory::class,
            ])
        ;
    }

    private function registerScheduler(ContainerBuilder $container): void
    {
        $container->register(Scheduler::class, Scheduler::class)
            ->setArguments([
                $container->getParameter('scheduler.timezone'),
                new Reference(TransportInterface::class, ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE),
                new Reference(SchedulerMiddlewareStack::class, ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE),
                new Reference(EventDispatcherInterface::class, ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE),
                new Reference(MessageBusInterface::class, ContainerInterface::NULL_ON_INVALID_REFERENCE),
            ])
            ->setPublic(false)
            ->addTag('monolog.logger', [
                'channel' => 'scheduler',
            ])
            ->addTag('container.preload', [
                'class' => Scheduler::class,
            ])
        ;

        $container->setAlias(SchedulerInterface::class, Scheduler::class);

        if ('lazy' === $container->getParameter('scheduler.scheduler_mode')) {
            $container->register(LazyScheduler::class, LazyScheduler::class)
                ->setDecoratedService(Scheduler::class, 'scheduler.scheduler')
                ->setArguments([
                    new Reference('scheduler.scheduler', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE),
                ])
                ->setPublic(false)
                ->addTag('container.preload', [
                    'class' => LazyScheduler::class,
                ])
            ;
        }
    }

    private function registerCommands(ContainerBuilder $container): void
    {
        $container->register(ConsumeTasksCommand::class, ConsumeTasksCommand::class)
            ->setArguments([
                new Reference(SchedulerInterface::class, ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE),
                new Reference(WorkerInterface::class, ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE),
                new Reference(EventDispatcherInterface::class, ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE),
                new Reference(LoggerInterface::class, ContainerInterface::NULL_ON_INVALID_REFERENCE),
            ])
            ->addTag('console.command')
            ->addTag('monolog.logger', [
                'channel' => 'scheduler',
            ])
            ->addTag('container.preload', [
                'class' => ConsumeTasksCommand::class,
            ])
        ;

        $container->register(ExecuteTaskCommand::class, ExecuteTaskCommand::class)
            ->setArguments([
                new Reference(EventDispatcherInterface::class, ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE),
                new Reference(SchedulerInterface::class, ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE),
                new Reference(WorkerInterface::class, ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE),
                new Reference(LoggerInterface::class, ContainerInterface::NULL_ON_INVALID_REFERENCE),
            ])
            ->addTag('console.command')
            ->addTag('monolog.logger', [
                'channel' => 'scheduler',
            ])
            ->addTag('container.preload', [
                'class' => ExecuteTaskCommand::class,
            ])
        ;

        $container->register(ListFailedTasksCommand::class, ListFailedTasksCommand::class)
            ->setArguments([
                new Reference(WorkerInterface::class, ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE),
            ])
            ->addTag('console.command')
            ->addTag('container.preload', [
                'class' => ListFailedTasksCommand::class,
            ])
        ;

        $container->register(ListTasksCommand::class, ListTasksCommand::class)
            ->setArguments([
                new Reference(SchedulerInterface::class, ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE),
            ])
            ->addTag('console.command')
            ->addTag('container.preload', [
                'class' => ListTasksCommand::class,
            ])
        ;

        $container->register(RebootSchedulerCommand::class, RebootSchedulerCommand::class)
            ->setArguments([
                new Reference(SchedulerInterface::class, ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE),
                new Reference(WorkerInterface::class, ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE),
                new Reference(EventDispatcherInterface::class, ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE),
                new Reference(LoggerInterface::class, ContainerInterface::NULL_ON_INVALID_REFERENCE),
            ])
            ->addTag('console.command')
            ->addTag('monolog.logger', [
                'channel' => 'scheduler',
            ])
            ->addTag('container.preload', [
                'class' => RebootSchedulerCommand::class,
            ])
        ;

        $container->register(RemoveFailedTaskCommand::class, RemoveFailedTaskCommand::class)
            ->setArguments([
                new Reference(SchedulerInterface::class, ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE),
                new Reference(WorkerInterface::class, ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE),
            ])
            ->addTag('console.command')
            ->addTag('container.preload', [
                'class' => RemoveFailedTaskCommand::class,
            ])
        ;

        $container->register(RetryFailedTaskCommand::class, RetryFailedTaskCommand::class)
            ->setArguments([
                new Reference(WorkerInterface::class, ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE),
                new Reference(EventDispatcherInterface::class, ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE),
                new Reference(LoggerInterface::class, ContainerInterface::NULL_ON_INVALID_REFERENCE),
            ])
            ->addTag('console.command')
            ->addTag('monolog.logger', [
                'channel' => 'scheduler',
            ])
            ->addTag('container.preload', [
                'class' => RetryFailedTaskCommand::class,
            ])
        ;

        $container->register(YieldTaskCommand::class, YieldTaskCommand::class)
            ->setArguments([
                new Reference(SchedulerInterface::class, ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE),
            ])
            ->addTag('console.command')
            ->addTag('container.preload', [
                'class' => YieldTaskCommand::class,
            ])
        ;

        $container->register(DebugMiddlewareCommand::class, DebugMiddlewareCommand::class)
            ->setArguments([
                new Reference(SchedulerMiddlewareStack::class, ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE),
                new Reference(WorkerMiddlewareStack::class, ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE),
            ])
            ->addTag('console.command')
            ->addTag('container.preload', [
                'class' => DebugMiddlewareCommand::class,
            ])
        ;
    }

    private function registerExpressionFactoryAndPolicies(ContainerBuilder $container): void
    {
        $container->register(Expression::class, Expression::class);

        $container->register(ExpressionBuilder::class, ExpressionBuilder::class)
            ->setArguments([
                new TaggedIteratorArgument(self::SCHEDULER_EXPRESSION_BUILDER_TAG),
            ])
            ->addTag('container.preload', [
                'class' => ExpressionBuilder::class,
            ])
        ;
        $container->setAlias(BuilderInterface::class, ExpressionBuilder::class);

        $container->register(CronExpressionBuilder::class, CronExpressionBuilder::class)
            ->setPublic(false)
            ->addTag(self::SCHEDULER_EXPRESSION_BUILDER_TAG)
            ->addTag('container.preload', [
                'class' => CronExpressionBuilder::class,
            ])
        ;

        $container->register(ComputedExpressionBuilder::class, ComputedExpressionBuilder::class)
            ->setPublic(false)
            ->addTag(self::SCHEDULER_EXPRESSION_BUILDER_TAG)
            ->addTag('container.preload', [
                'class' => ComputedExpressionBuilder::class,
            ])
        ;

        $container->register(FluentExpressionBuilder::class, FluentExpressionBuilder::class)
            ->setPublic(false)
            ->addTag(self::SCHEDULER_EXPRESSION_BUILDER_TAG)
            ->addTag('container.preload', [
                'class' => FluentExpressionBuilder::class,
            ])
        ;

        $container->register(SchedulePolicyOrchestrator::class, SchedulePolicyOrchestrator::class)
            ->setArguments([
                new TaggedIteratorArgument(self::SCHEDULER_SCHEDULE_POLICY),
            ])
            ->addTag('container.preload', [
                'class' => SchedulePolicyOrchestrator::class,
            ])
        ;
        $container->setAlias(SchedulePolicyOrchestratorInterface::class, SchedulePolicyOrchestrator::class);

        $container->register(BatchPolicy::class, BatchPolicy::class)
            ->addTag(self::SCHEDULER_SCHEDULE_POLICY)
            ->addTag('container.preload', [
                'class' => BatchPolicy::class,
            ])
        ;

        $container->register(DeadlinePolicy::class, DeadlinePolicy::class)
            ->addTag(self::SCHEDULER_SCHEDULE_POLICY)
            ->addTag('container.preload', [
                'class' => DeadlinePolicy::class,
            ])
        ;

        $container->register(ExecutionDurationPolicy::class, ExecutionDurationPolicy::class)
            ->addTag(self::SCHEDULER_SCHEDULE_POLICY)
            ->addTag('container.preload', [
                'class' => ExecutionDurationPolicy::class,
            ])
        ;

        $container->register(FirstInFirstOutPolicy::class, FirstInFirstOutPolicy::class)
            ->addTag(self::SCHEDULER_SCHEDULE_POLICY)
            ->addTag('container.preload', [
                'class' => FirstInFirstOutPolicy::class,
            ])
        ;

        $container->register(FirstInLastOutPolicy::class, FirstInLastOutPolicy::class)
            ->addTag(self::SCHEDULER_SCHEDULE_POLICY)
            ->addTag('container.preload', [
                'class' => FirstInLastOutPolicy::class,
            ])
        ;

        $container->register(IdlePolicy::class, IdlePolicy::class)
            ->addTag(self::SCHEDULER_SCHEDULE_POLICY)
            ->addTag('container.preload', [
                'class' => IdlePolicy::class,
            ])
        ;

        $container->register(MemoryUsagePolicy::class, MemoryUsagePolicy::class)
            ->addTag(self::SCHEDULER_SCHEDULE_POLICY)
            ->addTag('container.preload', [
                'class' => MemoryUsagePolicy::class,
            ])
        ;

        $container->register(NicePolicy::class, NicePolicy::class)
            ->addTag(self::SCHEDULER_SCHEDULE_POLICY)
            ->addTag('container.preload', [
                'class' => NicePolicy::class,
            ])
        ;

        $container->register(RoundRobinPolicy::class, RoundRobinPolicy::class)
            ->addTag(self::SCHEDULER_SCHEDULE_POLICY)
            ->addTag('container.preload', [
                'class' => RoundRobinPolicy::class,
            ])
        ;
    }

    private function registerBuilders(ContainerBuilder $container): void
    {
        $container->register(TaskBuilder::class, TaskBuilder::class)
            ->setArguments([
                new TaggedIteratorArgument(self::SCHEDULER_TASK_BUILDER_TAG),
                new Reference('property_accessor'),
            ])
            ->addTag('container.preload', [
                'class' => TaskBuilder::class,
            ])
        ;
        $container->setAlias(TaskBuilderInterface::class, TaskBuilder::class);

        $container->register(AbstractTaskBuilder::class, AbstractTaskBuilder::class)
            ->setArguments([
                new Reference(BuilderInterface::class, ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE),
            ])
            ->setAbstract(true)
            ->setPublic(false)
            ->addTag('container.preload', [
                'class' => AbstractTaskBuilder::class,
            ])
        ;

        $container->setDefinition(CommandBuilder::class, new ChildDefinition(AbstractTaskBuilder::class))
            ->setPublic(false)
            ->addTag(self::SCHEDULER_TASK_BUILDER_TAG)
            ->addTag('container.preload', [
                'class' => CommandBuilder::class,
            ])
        ;

        $container->setDefinition(HttpBuilder::class, new ChildDefinition(AbstractTaskBuilder::class))
            ->setPublic(false)
            ->addTag(self::SCHEDULER_TASK_BUILDER_TAG)
            ->addTag('container.preload', [
                'class' => HttpBuilder::class,
            ])
        ;

        $container->setDefinition(NullBuilder::class, new ChildDefinition(AbstractTaskBuilder::class))
            ->setPublic(false)
            ->addTag(self::SCHEDULER_TASK_BUILDER_TAG)
            ->addTag('container.preload', [
                'class' => NullBuilder::class,
            ])
        ;

        $container->setDefinition(ShellBuilder::class, new ChildDefinition(AbstractTaskBuilder::class))
            ->setPublic(false)
            ->addTag(self::SCHEDULER_TASK_BUILDER_TAG)
            ->addTag('container.preload', [
                'class' => ShellBuilder::class,
            ])
        ;

        $container->setDefinition(ChainedBuilder::class, new ChildDefinition(AbstractTaskBuilder::class))
            ->setArgument('$builders', new TaggedIteratorArgument(self::SCHEDULER_TASK_BUILDER_TAG))
            ->setPublic(false)
            ->addTag(self::SCHEDULER_TASK_BUILDER_TAG)
            ->addTag('container.preload', [
                'class' => ChainedBuilder::class,
            ])
        ;
    }

    private function registerRunners(ContainerBuilder $container): void
    {
        $container->register('scheduler.application', Application::class)
            ->setArguments([
                new Reference(KernelInterface::class, ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE),
            ])
        ;

        $container->register(RunnerRegistry::class, RunnerRegistry::class)
            ->setArguments([
                new TaggedIteratorArgument(self::SCHEDULER_RUNNER_TAG),
            ])
            ->setPublic(false)
            ->addTag('container.preload', [
                'class' => RunnerRegistry::class,
            ])
        ;
        $container->setAlias(RunnerRegistryInterface::class, RunnerRegistry::class);

        $container->register(ShellTaskRunner::class, ShellTaskRunner::class)
            ->addTag(self::SCHEDULER_RUNNER_TAG)
            ->addTag('container.preload', [
                'class' => ShellTaskRunner::class,
            ])
        ;

        $container->register(CommandTaskRunner::class, CommandTaskRunner::class)
            ->setArguments([
                new Reference('scheduler.application', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE),
            ])
            ->addTag(self::SCHEDULER_RUNNER_TAG)
            ->addTag('container.preload', [
                'class' => CommandTaskRunner::class,
            ])
        ;

        $container->register(CallbackTaskRunner::class, CallbackTaskRunner::class)
            ->addTag(self::SCHEDULER_RUNNER_TAG)
            ->addTag('container.preload', [
                'class' => CallbackTaskRunner::class,
            ])
        ;

        $container->register(HttpTaskRunner::class, HttpTaskRunner::class)
            ->setArguments([
                new Reference(HttpClientInterface::class, ContainerInterface::NULL_ON_INVALID_REFERENCE),
            ])
            ->addTag(self::SCHEDULER_RUNNER_TAG)
            ->addTag('scheduler.extra', [
                'require' => 'http_client',
                'tag' => self::SCHEDULER_RUNNER_TAG,
            ])
            ->addTag('container.preload', [
                'class' => HttpTaskRunner::class,
            ])
        ;

        $container->register(NotificationTaskRunner::class, NotificationTaskRunner::class)
            ->setArguments([
                new Reference(NotifierInterface::class, ContainerInterface::NULL_ON_INVALID_REFERENCE),
            ])
            ->addTag(self::SCHEDULER_RUNNER_TAG)
            ->addTag('scheduler.extra', [
                'require' => 'notifier',
                'tag' => self::SCHEDULER_RUNNER_TAG,
            ])
            ->addTag('container.preload', [
                'class' => NotificationTaskRunner::class,
            ])
        ;

        $container->register(NullTaskRunner::class, NullTaskRunner::class)
            ->addTag(self::SCHEDULER_RUNNER_TAG)
            ->addTag('container.preload', [
                'class' => NullTaskRunner::class,
            ])
        ;

        $container->register(ChainedTaskRunner::class, ChainedTaskRunner::class)
            ->addTag(self::SCHEDULER_RUNNER_TAG)
            ->addTag('container.preload', [
                'class' => ChainedTaskRunner::class,
            ])
        ;
    }

    private function registerNormalizer(ContainerBuilder $container): void
    {
        $container->register(TaskNormalizer::class, TaskNormalizer::class)
            ->setArguments([
                new Reference('serializer.normalizer.datetime', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE),
                new Reference('serializer.normalizer.datetimezone', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE),
                new Reference('serializer.normalizer.dateinterval', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE),
                new Reference('serializer.normalizer.object', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE),
                new Reference(NotificationTaskBagNormalizer::class, ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE),
                new Reference(AccessLockBagNormalizer::class, ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE),
            ])
            ->addTag('serializer.normalizer')
            ->addTag('container.preload', [
                'class' => TaskNormalizer::class,
            ])
        ;

        $container->register(NotificationTaskBagNormalizer::class, NotificationTaskBagNormalizer::class)
            ->setArguments([
                new Reference('serializer.normalizer.object', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE),
            ])
            ->addTag('serializer.normalizer')
            ->addTag('container.preload', [
                'class' => NotificationTaskBagNormalizer::class,
            ])
        ;

        $container->register(AccessLockBagNormalizer::class, AccessLockBagNormalizer::class)
            ->setArguments([
                new Reference('serializer.normalizer.object', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE),
                new Reference(LoggerInterface::class, ContainerInterface::NULL_ON_INVALID_REFERENCE),
            ])
            ->addTag('serializer.normalizer')
            ->addTag('container.preload', [
                'class' => AccessLockBagNormalizer::class,
            ])
        ;
    }

    private function registerMessengerTools(ContainerBuilder $container): void
    {
        $container->register(TaskToExecuteMessageHandler::class, TaskToExecuteMessageHandler::class)
            ->setArguments([
                new Reference(WorkerInterface::class, ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE),
                new Reference(LoggerInterface::class, ContainerInterface::NULL_ON_INVALID_REFERENCE),
            ])
            ->addTag('messenger.message_handler')
            ->addTag('container.preload', [
                'class' => TaskToExecuteMessageHandler::class,
            ])
        ;

        $container->register(TaskToYieldMessageHandler::class, TaskToYieldMessageHandler::class)
            ->setArguments([
                new Reference(SchedulerInterface::class, ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE),
            ])
            ->addTag('messenger.message_handler')
            ->addTag('container.preload', [
                'class' => TaskToYieldMessageHandler::class,
            ])
        ;

        $container->register(TaskToPauseMessageHandler::class, TaskToPauseMessageHandler::class)
            ->setArguments([
                new Reference(SchedulerInterface::class, ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE),
            ])
            ->addTag('messenger.message_handler')
            ->addTag('container.preload', [
                'class' => TaskToPauseMessageHandler::class,
            ])
        ;

        if (interface_exists(MessageBusInterface::class)) {
            $container->register(MessengerTaskRunner::class, MessengerTaskRunner::class)
                ->setArguments([
                    new Reference(MessageBusInterface::class, ContainerInterface::NULL_ON_INVALID_REFERENCE),
                ])
                ->addTag(self::SCHEDULER_RUNNER_TAG)
                ->addTag('container.preload', [
                    'class' => MessengerTaskRunner::class,
                ])
            ;
        }
    }

    private function registerSubscribers(ContainerBuilder $container): void
    {
        $container->register(TaskSubscriber::class, TaskSubscriber::class)
            ->setArguments([
                new Reference(SchedulerInterface::class, ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE),
                new Reference(WorkerInterface::class, ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE),
                new Reference(EventDispatcherInterface::class, ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE),
                new Reference(SerializerInterface::class, ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE),
                new Reference(LoggerInterface::class, ContainerInterface::NULL_ON_INVALID_REFERENCE),
                $container->getParameter('scheduler.trigger_path'),
            ])
            ->addTag('kernel.event_subscriber')
            ->addTag('monolog.logger', [
                'channel' => 'scheduler',
            ])
            ->addTag('container.preload', [
                'class' => TaskSubscriber::class,
            ])
        ;

        $container->register(TaskLoggerSubscriber::class, TaskLoggerSubscriber::class)
            ->addTag('kernel.event_subscriber')
            ->addTag('container.preload', [
                'class' => TaskLoggerSubscriber::class,
            ])
        ;

        $container->register(StopWorkerOnSignalSubscriber::class, StopWorkerOnSignalSubscriber::class)
            ->setArguments([
                new Reference(LoggerInterface::class, ContainerInterface::NULL_ON_INVALID_REFERENCE),
            ])
            ->addTag('kernel.event_subscriber')
            ->addTag('container.preload', [
                'class' => StopWorkerOnSignalSubscriber::class,
            ])
        ;

        $container->register(TaskLifecycleSubscriber::class, TaskLifecycleSubscriber::class)
            ->setArguments([
                new Reference(LoggerInterface::class, ContainerInterface::NULL_ON_INVALID_REFERENCE),
            ])
            ->setPublic(false)
            ->addTag('kernel.event_subscriber')
            ->addTag('container.preload', [
                'class' => TaskLifecycleSubscriber::class,
            ])
        ;

        $container->register(WorkerLifecycleSubscriber::class, WorkerLifecycleSubscriber::class)
            ->setArguments([
                new Reference(LoggerInterface::class, ContainerInterface::NULL_ON_INVALID_REFERENCE),
            ])
            ->setPublic(false)
            ->addTag('kernel.event_subscriber')
            ->addTag('container.preload', [
                'class' => WorkerLifecycleSubscriber::class,
            ])
        ;
    }

    private function registerTracker(ContainerBuilder $container): void
    {
        $container->register('scheduler.stop_watch', Stopwatch::class);

        $container->register(TaskExecutionTracker::class, TaskExecutionTracker::class)
            ->setArguments([
                new Reference('scheduler.stop_watch', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE),
            ])
            ->addTag('container.preload', [
                'class' => TaskExecutionTracker::class,
            ])
        ;
        $container->setAlias(TaskExecutionTrackerInterface::class, TaskExecutionTracker::class);
    }

    private function registerWorker(ContainerBuilder $container): void
    {
        $container->register(Worker::class, Worker::class)
            ->setArguments([
                new Reference(SchedulerInterface::class, ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE),
                new Reference(RunnerRegistryInterface::class, ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE),
                new Reference(TaskExecutionTrackerInterface::class, ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE),
                new Reference(WorkerMiddlewareStack::class, ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE),
                new Reference(EventDispatcherInterface::class, ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE),
                new Reference('scheduler.lock_store.factory', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE),
                new Reference(LoggerInterface::class, ContainerInterface::NULL_ON_INVALID_REFERENCE),
            ])
            ->addTag('scheduler.worker')
            ->addTag('monolog.logger', [
                'channel' => 'scheduler',
            ])
            ->addTag('container.preload', [
                'class' => Worker::class,
            ])
        ;
        $container->setAlias(WorkerInterface::class, Worker::class);
    }

    /**
     * @param array<string, mixed> $configuration
     */
    private function registerTasks(ContainerBuilder $container, array $configuration): void
    {
        foreach ($configuration['tasks'] as $name => $taskConfiguration) {
            $taskDefinition = $container->register(sprintf('scheduler._%s_task', $name), TaskInterface::class)
                ->setFactory([new Reference(TaskBuilderInterface::class), 'create'])
                ->setArguments([
                    array_merge(['name' => $name], $taskConfiguration),
                ])
                ->addTag('scheduler.task')
                ->setPublic(false)
            ;

            $container->getDefinition(Scheduler::class)
                ->addMethodCall('schedule', [$taskDefinition])
            ;
        }
    }

    private function registerDoctrineBridge(ContainerBuilder $container, array $configuration): void
    {
        if (0 !== strpos($configuration['transport']['dsn'], 'doctrine://') && 0 !== strpos($configuration['transport']['dsn'], 'dbal://')) {
            return;
        }

        $container->register(SchedulerTransportDoctrineSchemaSubscriber::class, SchedulerTransportDoctrineSchemaSubscriber::class)
            ->setArguments([
                new Reference(TransportInterface::class, ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE),
            ])
            ->setPublic(false)
            ->addTag('doctrine.event_subscriber')
            ->addTag('container.preload', [
                'class' => SchedulerTransportDoctrineSchemaSubscriber::class,
            ])
        ;

        $container->register(DoctrineTransportFactory::class, DoctrineTransportFactory::class)
            ->setArguments([
                new Reference('doctrine', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE),
                new Reference(LoggerInterface::class, ContainerInterface::NULL_ON_INVALID_REFERENCE),
            ])
            ->setPublic(false)
            ->addTag(self::SCHEDULER_TRANSPORT_FACTORY_TAG)
            ->addTag('container.preload', [
                'class' => DoctrineTransportFactory::class,
            ])
        ;
    }

    private function registerRedisBridge(ContainerBuilder $container): void
    {
        if (!class_exists(Redis::class)) {
            return;
        }

        $container->register(RedisTransportFactory::class, RedisTransportFactory::class)
            ->setPublic(false)
            ->addTag(self::SCHEDULER_TRANSPORT_FACTORY_TAG)
            ->addTag('container.preload', [
                'class' => RedisTransportFactory::class,
            ])
        ;
    }

    /**
     * @param array<string, mixed> $configuration
     */
    private function registerMiddlewareStacks(ContainerBuilder $container, array $configuration): void
    {
        $container->register(SchedulerMiddlewareStack::class, SchedulerMiddlewareStack::class)
            ->setArguments([
                new TaggedIteratorArgument(self::SCHEDULER_SCHEDULER_MIDDLEWARE_TAG),
            ])
            ->setPublic(false)
            ->addTag('scheduler.middleware_hub')
            ->addTag('container.preload', [
                'class' => SchedulerMiddlewareStack::class,
            ])
        ;

        $container->register(WorkerMiddlewareStack::class, WorkerMiddlewareStack::class)
            ->setArguments([
                new TaggedIteratorArgument(self::SCHEDULER_WORKER_MIDDLEWARE_TAG),
            ])
            ->setPublic(false)
            ->addTag('scheduler.middleware_hub')
            ->addTag('container.preload', [
                'class' => WorkerMiddlewareStack::class,
            ])
        ;

        $container->register(NotifierMiddleware::class, NotifierMiddleware::class)
            ->setArguments([
                new Reference(NotifierInterface::class, ContainerInterface::NULL_ON_INVALID_REFERENCE),
            ])
            ->setPublic(false)
            ->addTag(self::SCHEDULER_SCHEDULER_MIDDLEWARE_TAG)
            ->addTag(self::SCHEDULER_WORKER_MIDDLEWARE_TAG)
            ->addTag('container.preload', [
                'class' => NotifierMiddleware::class,
            ])
        ;

        $container->register(TaskCallbackMiddleware::class, TaskCallbackMiddleware::class)
            ->setPublic(false)
            ->addTag(self::SCHEDULER_SCHEDULER_MIDDLEWARE_TAG)
            ->addTag(self::SCHEDULER_WORKER_MIDDLEWARE_TAG)
            ->addTag('container.preload', [
                'class' => TaskCallbackMiddleware::class,
            ])
        ;

        $container->register(SingleRunTaskMiddleware::class, SingleRunTaskMiddleware::class)
            ->setArguments([
                new Reference(SchedulerInterface::class, ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE),
                new Reference(LoggerInterface::class, ContainerInterface::NULL_ON_INVALID_REFERENCE),
            ])
            ->setPublic(false)
            ->addTag(self::SCHEDULER_WORKER_MIDDLEWARE_TAG)
            ->addTag('container.preload', [
                'class' => SingleRunTaskMiddleware::class,
            ])
        ;

        $container->register(TaskUpdateMiddleware::class, TaskUpdateMiddleware::class)
            ->setArguments([
                new Reference(SchedulerInterface::class, ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE),
            ])
            ->setPublic(false)
            ->addTag(self::SCHEDULER_WORKER_MIDDLEWARE_TAG)
            ->addTag('container.preload', [
                'class' => TaskUpdateMiddleware::class,
            ])
        ;

        $container->register(TaskLockBagMiddleware::class, TaskLockBagMiddleware::class)
            ->setArguments([
                new Reference('scheduler.lock_store.factory', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE),
                new Reference(LoggerInterface::class, ContainerInterface::NULL_ON_INVALID_REFERENCE),
            ])
            ->setPublic(false)
            ->addTag(self::SCHEDULER_WORKER_MIDDLEWARE_TAG)
            ->addTag('container.preload', [
                'class' => TaskLockBagMiddleware::class,
            ])
        ;

        $container->register(TaskExecutionMiddleware::class, TaskExecutionMiddleware::class)
            ->setPublic(false)
            ->addTag(self::SCHEDULER_WORKER_MIDDLEWARE_TAG)
            ->addTag('container.preload', [
                'class' => TaskExecutionMiddleware::class,
            ])
        ;

        if (null !== $configuration['rate_limiter']) {
            $container->register(MaxExecutionMiddleware::class, MaxExecutionMiddleware::class)
                ->setArguments([
                    new Reference(sprintf('limiter.%s', $configuration['rate_limiter']), ContainerInterface::NULL_ON_INVALID_REFERENCE),
                    new Reference(LoggerInterface::class, ContainerInterface::NULL_ON_INVALID_REFERENCE),
                ])
                ->setPublic(false)
                ->addTag(self::SCHEDULER_WORKER_MIDDLEWARE_TAG)
                ->addTag('container.preload', [
                    'class' => MaxExecutionMiddleware::class,
                ])
            ;
        }
    }

    /**
     * @param array<string, mixed> $configuration
     */
    private function registerProbeContext(ContainerBuilder $container, array $configuration): void
    {
        if (!array_key_exists('probe', $configuration)) {
            return;
        }

        if (!$configuration['probe']['enabled']) {
            return;
        }

        $container->setParameter('scheduler.probe_path', $configuration['probe']['path']);

        $container->register(Probe::class, Probe::class)
            ->setArguments([
                new Reference(SchedulerInterface::class, ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE),
                new Reference(WorkerInterface::class, ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE),
            ])
            ->setPublic(false)
            ->addTag(self::SCHEDULER_PROBE_TAG)
            ->addTag('container.preload', [
                'class' => Probe::class,
            ])
        ;
        $container->setAlias(ProbeInterface::class, Probe::class);

        $container->register(ProbeStateSubscriber::class, ProbeStateSubscriber::class)
            ->setArguments([
                new Reference(ProbeInterface::class, ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE),
                $configuration['probe']['path'],
            ])
            ->setPublic(false)
            ->addTag('kernel.event_subscriber')
            ->addTag('container.preload', [
                'class' => ProbeStateSubscriber::class,
            ])
        ;

        $container->register(ProbeTaskRunner::class, ProbeTaskRunner::class)
            ->setPublic(false)
            ->addTag(self::SCHEDULER_RUNNER_TAG)
            ->addTag('container.preload', [
                'class' => ProbeTaskRunner::class,
            ])
        ;

        $container->register(ProbeTaskMiddleware::class, ProbeTaskMiddleware::class)
            ->setPublic(false)
            ->addTag(self::SCHEDULER_WORKER_MIDDLEWARE_TAG)
            ->addTag('container.preload', [
                'class' => ProbeTaskMiddleware::class,
            ])
        ;

        $container->setDefinition(ProbeTaskBuilder::class, new ChildDefinition(AbstractTaskBuilder::class))
            ->setPublic(false)
            ->addTag(self::SCHEDULER_TASK_BUILDER_TAG)
            ->addTag('container.preload', [
                'class' => ProbeTaskBuilder::class,
            ])
        ;

        $container->register(DebugProbeCommand::class, DebugProbeCommand::class)
            ->setArguments([
                new Reference(ProbeInterface::class, ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE),
                new Reference(SchedulerInterface::class, ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE),
            ])
            ->setPublic(false)
            ->addTag('console.command')
            ->addTag('container.preload', [
                'class' => DebugProbeCommand::class,
            ])
        ;

        $container->register(ExecuteExternalProbeCommand::class, ExecuteExternalProbeCommand::class)
            ->setArguments([
                new Reference(SchedulerInterface::class, ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE),
                new Reference(WorkerInterface::class, ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE),
            ])
            ->setPublic(false)
            ->addTag('console.command')
            ->addTag('container.preload', [
                'class' => ExecuteExternalProbeCommand::class,
            ])
        ;
    }

    private function registerMercureSupport(ContainerBuilder $container, array $config): void
    {
        if (!$container->getParameter('scheduler.mercure_support')) {
            return;
        }

        $container->register('scheduler.mercure_hub', Hub::class)
            ->setArguments([
                $config['mercure']['hub_url'],
                new Reference('scheduler.mercure.token_provider', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE),
            ])
            ->setPublic(false)
            ->addTag('container.preload', [
                'class' => Hub::class,
            ])
        ;

        $container->register('scheduler.mercure.token_provider', StaticTokenProvider::class)
            ->setArguments([
                $config['mercure']['jwt_token'],
            ])
            ->setPublic(false)
            ->addTag('container.preload', [
                'class' => StaticTokenProvider::class,
            ])
        ;

        $container->register(MercureEventSubscriber::class, MercureEventSubscriber::class)
            ->setArguments([
                new Reference('scheduler.mercure_hub', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE),
                $config['mercure']['update_url'],
                new Reference(SerializerInterface::class, ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE),
            ])
            ->setPublic(false)
            ->addTag('kernel.event_subscriber')
            ->addTag('container.preload', [
                'class' => MercureEventSubscriber::class,
            ])
        ;
    }

    private function registerDataCollector(ContainerBuilder $container): void
    {
        $container->register(SchedulerDataCollector::class, SchedulerDataCollector::class)
            ->setArguments([
                new Reference(TaskLoggerSubscriber::class, ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE),
                new Reference(ProbeInterface::class, ContainerInterface::NULL_ON_INVALID_REFERENCE),
            ])
            ->setPublic(false)
            ->addTag('data_collector', [
                'template' => '@Scheduler/Collector/data_collector.html.twig',
                'id'       => SchedulerDataCollector::NAME,
            ])
            ->addTag('container.preload', [
                'class' => SchedulerDataCollector::class,
            ])
        ;
    }

    /**
     * {@inheritdoc}
     */
    public function getConfiguration(array $config, ContainerBuilder $container): ConfigurationInterface
    {
        return new SchedulerBundleConfiguration();
    }
}
