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
use SchedulerBundle\Command\ListFailedTasksCommand;
use SchedulerBundle\Command\ListTasksCommand;
use SchedulerBundle\Command\RebootSchedulerCommand;
use SchedulerBundle\Command\RemoveFailedTaskCommand;
use SchedulerBundle\Command\RetryFailedTaskCommand;
use SchedulerBundle\Command\YieldTaskCommand;
use SchedulerBundle\DataCollector\SchedulerDataCollector;
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
use SchedulerBundle\Messenger\TaskMessageHandler;
use SchedulerBundle\Messenger\TaskToYieldMessageHandler;
use SchedulerBundle\Middleware\MiddlewareStackInterface;
use SchedulerBundle\Middleware\NotifierMiddleware;
use SchedulerBundle\Middleware\PostSchedulingMiddlewareInterface;
use SchedulerBundle\Middleware\PreSchedulingMiddlewareInterface;
use SchedulerBundle\Middleware\MaxExecutionMiddleware;
use SchedulerBundle\Middleware\SchedulerMiddlewareStack;
use SchedulerBundle\Middleware\SingleRunTaskMiddleware;
use SchedulerBundle\Middleware\TaskCallbackMiddleware;
use SchedulerBundle\Middleware\TaskUpdateMiddleware;
use SchedulerBundle\Middleware\WorkerMiddlewareStack;
use SchedulerBundle\Middleware\PostExecutionMiddlewareInterface;
use SchedulerBundle\Middleware\PreExecutionMiddlewareInterface;
use SchedulerBundle\Runner\CallbackTaskRunner;
use SchedulerBundle\Runner\ChainedTaskRunner;
use SchedulerBundle\Runner\CommandTaskRunner;
use SchedulerBundle\Runner\HttpTaskRunner;
use SchedulerBundle\Runner\MessengerTaskRunner;
use SchedulerBundle\Runner\NotificationTaskRunner;
use SchedulerBundle\Runner\NullTaskRunner;
use SchedulerBundle\Runner\RunnerInterface;
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
use SchedulerBundle\Serializer\NotificationTaskBagNormalizer;
use SchedulerBundle\Serializer\TaskNormalizer;
use SchedulerBundle\Task\Builder\AbstractTaskBuilder;
use SchedulerBundle\Task\Builder\ChainedBuilder;
use SchedulerBundle\Task\Builder\CommandBuilder;
use SchedulerBundle\Task\Builder\HttpBuilder;
use SchedulerBundle\Task\Builder\NullBuilder;
use SchedulerBundle\Task\Builder\ShellBuilder;
use SchedulerBundle\Task\TaskBuilder;
use SchedulerBundle\Task\TaskBuilderInterface;
use SchedulerBundle\Task\TaskExecutionTracker;
use SchedulerBundle\Task\TaskExecutionTrackerInterface;
use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Transport\CacheTransportFactory;
use SchedulerBundle\Transport\Dsn;
use SchedulerBundle\Transport\FailOverTransportFactory;
use SchedulerBundle\Transport\FilesystemTransportFactory;
use SchedulerBundle\Transport\InMemoryTransportFactory;
use SchedulerBundle\Transport\LongTailTransportFactory;
use SchedulerBundle\Transport\RoundRobinTransportFactory;
use SchedulerBundle\Transport\TransportFactory;
use SchedulerBundle\Transport\TransportFactoryInterface;
use SchedulerBundle\Transport\TransportInterface;
use SchedulerBundle\Worker\Worker;
use SchedulerBundle\Worker\WorkerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Notifier\NotifierInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Stopwatch\Stopwatch;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use function array_merge;
use function class_exists;
use function sprintf;
use function strpos;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class SchedulerBundleExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $schedulerBundleConfiguration = new SchedulerBundleConfiguration();

        $config = $this->processConfiguration($schedulerBundleConfiguration, $configs);

        $this->registerParameters($container, $config);
        $this->registerAutoConfigure($container);
        $this->registerTransportFactories($container, $config);
        $this->registerTransport($container, $config);
        $this->registerScheduler($container);
        $this->registerCommands($container);
        $this->registerExpressionFactoryAndPolicies($container);
        $this->registerBuilders($container);
        $this->registerRunners($container);
        $this->registerNormalizer($container);
        $this->registerMessengerTools($container);
        $this->registerSubscribers($container);
        $this->registerTracker($container);
        $this->registerWorker($container, $config);
        $this->registerTasks($container, $config);
        $this->registerDoctrineBridge($container, $config);
        $this->registerRedisBridge($container);
        $this->registerMiddlewareStacks($container, $config);
        $this->registerDataCollector($container);
    }

    private function registerParameters(ContainerBuilder $container, array $configuration): void
    {
        $container->setParameter('scheduler.timezone', $configuration['timezone']);
        $container->setParameter('scheduler.trigger_path', $configuration['path']);
    }

    private function registerAutoConfigure(ContainerBuilder $container): void
    {
        $container->registerForAutoconfiguration(RunnerInterface::class)->addTag('scheduler.runner');
        $container->registerForAutoconfiguration(TransportInterface::class)->addTag('scheduler.transport');
        $container->registerForAutoconfiguration(TransportFactoryInterface::class)->addTag('scheduler.transport_factory');
        $container->registerForAutoconfiguration(PolicyInterface::class)->addTag('scheduler.schedule_policy');
        $container->registerForAutoconfiguration(WorkerInterface::class)->addTag('scheduler.worker');
        $container->registerForAutoconfiguration(MiddlewareStackInterface::class)->addTag('scheduler.middleware_hub');
        $container->registerForAutoconfiguration(PreSchedulingMiddlewareInterface::class)->addTag('scheduler.scheduler_middleware');
        $container->registerForAutoconfiguration(PostSchedulingMiddlewareInterface::class)->addTag('scheduler.scheduler_middleware');
        $container->registerForAutoconfiguration(PreExecutionMiddlewareInterface::class)->addTag('scheduler.worker_middleware');
        $container->registerForAutoconfiguration(PostExecutionMiddlewareInterface::class)->addTag('scheduler.worker_middleware');
        $container->registerForAutoconfiguration(ExpressionBuilderInterface::class)->addTag('scheduler.expression_builder');
    }

    private function registerTransportFactories(ContainerBuilder $container, array $configuration): void
    {
        $container->register(TransportFactory::class, TransportFactory::class)
            ->setArguments([
                new TaggedIteratorArgument('scheduler.transport_factory'),
            ])
            ->addTag('container.preload', [
                'class' => TransportFactory::class,
            ])
        ;

        $container->setAlias(TransportFactoryInterface::class, TransportFactory::class);

        $container->register(InMemoryTransportFactory::class, InMemoryTransportFactory::class)
            ->setPublic(false)
            ->addTag('scheduler.transport_factory')
            ->addTag('container.preload', [
                'class' => InMemoryTransportFactory::class,
            ])
        ;

        $container->register(FilesystemTransportFactory::class, FilesystemTransportFactory::class)
            ->setPublic(false)
            ->addTag('scheduler.transport_factory')
            ->addTag('container.preload', [
                'class' => FilesystemTransportFactory::class,
            ])
        ;

        $container->register(FailOverTransportFactory::class, FailOverTransportFactory::class)
            ->setArguments([
                new TaggedIteratorArgument('scheduler.transport_factory'),
            ])
            ->setPublic(false)
            ->addTag('scheduler.transport_factory')
            ->addTag('container.preload', [
                'class' => FailOverTransportFactory::class,
            ])
        ;

        $container->register(LongTailTransportFactory::class, LongTailTransportFactory::class)
            ->setArguments([
                new TaggedIteratorArgument('scheduler.transport_factory'),
            ])
            ->setPublic(false)
            ->addTag('scheduler.transport_factory')
            ->addTag('container.preload', [
                'class' => LongTailTransportFactory::class,
            ])
        ;

        $container->register(RoundRobinTransportFactory::class, RoundRobinTransportFactory::class)
            ->setArguments([
                new TaggedIteratorArgument('scheduler.transport_factory'),
            ])
            ->setPublic(false)
            ->addTag('scheduler.transport_factory')
            ->addTag('container.preload', [
                'class' => RoundRobinTransportFactory::class,
            ])
        ;

        if (0 === strpos($configuration['transport']['dsn'], 'cache://')) {
            $container->register(CacheTransportFactory::class, CacheTransportFactory::class)
                ->setArguments([
                    new Reference(
                        sprintf('cache.%s', Dsn::fromString($configuration['transport']['dsn'])->getHost()),
                        ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE
                    ),
                ])
                ->setPublic(false)
                ->addTag('scheduler.transport_factory')
                ->addTag('container.preload', [
                    'class' => CacheTransportFactory::class,
                ])
            ;
        }
    }

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

    private function registerScheduler(ContainerBuilder $container): void
    {
        $container->register(Scheduler::class, Scheduler::class)
            ->setArguments([
                $container->getParameter('scheduler.timezone'),
                new Reference(TransportInterface::class, ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE),
                new Reference(SchedulerMiddlewareStack::class, ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE),
                new Reference(EventDispatcherInterface::class, ContainerInterface::NULL_ON_INVALID_REFERENCE),
                new Reference(MessageBusInterface::class, ContainerInterface::NULL_ON_INVALID_REFERENCE),
            ])
            ->addTag('monolog.logger', [
                'channel' => 'scheduler',
            ])
            ->addTag('container.preload', [
                'class' => Scheduler::class,
            ])
        ;

        $container->setAlias(SchedulerInterface::class, Scheduler::class);
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
    }

    private function registerExpressionFactoryAndPolicies(ContainerBuilder $container): void
    {
        $container->register(Expression::class, Expression::class);

        $container->register(ExpressionBuilder::class, ExpressionBuilder::class)
            ->setArguments([
                new TaggedIteratorArgument('scheduler.expression_builder'),
            ])
            ->addTag('container.preload', [
                'class' => ExpressionBuilder::class,
            ])
        ;
        $container->setAlias(BuilderInterface::class, ExpressionBuilder::class);

        $container->register(CronExpressionBuilder::class, CronExpressionBuilder::class)
            ->setPublic(false)
            ->addTag('scheduler.expression_builder')
            ->addTag('container.preload', [
                'class' => CronExpressionBuilder::class,
            ])
        ;

        $container->register(ComputedExpressionBuilder::class, ComputedExpressionBuilder::class)
            ->setPublic(false)
            ->addTag('scheduler.expression_builder')
            ->addTag('container.preload', [
                'class' => ComputedExpressionBuilder::class,
            ])
        ;

        $container->register(FluentExpressionBuilder::class, FluentExpressionBuilder::class)
            ->setPublic(false)
            ->addTag('scheduler.expression_builder')
            ->addTag('container.preload', [
                'class' => FluentExpressionBuilder::class,
            ])
        ;

        $container->register(SchedulePolicyOrchestrator::class, SchedulePolicyOrchestrator::class)
            ->setArguments([
                new TaggedIteratorArgument('scheduler.schedule_policy'),
            ])
            ->addTag('container.preload', [
                'class' => SchedulePolicyOrchestrator::class,
            ])
        ;
        $container->setAlias(SchedulePolicyOrchestratorInterface::class, SchedulePolicyOrchestrator::class);

        $container->register(BatchPolicy::class, BatchPolicy::class)
            ->addTag('scheduler.schedule_policy')
            ->addTag('container.preload', [
                'class' => BatchPolicy::class,
            ])
        ;

        $container->register(DeadlinePolicy::class, DeadlinePolicy::class)
            ->addTag('scheduler.schedule_policy')
            ->addTag('container.preload', [
                'class' => DeadlinePolicy::class,
            ])
        ;

        $container->register(ExecutionDurationPolicy::class, ExecutionDurationPolicy::class)
            ->addTag('scheduler.schedule_policy')
            ->addTag('container.preload', [
                'class' => ExecutionDurationPolicy::class,
            ])
        ;

        $container->register(FirstInFirstOutPolicy::class, FirstInFirstOutPolicy::class)
            ->addTag('scheduler.schedule_policy')
            ->addTag('container.preload', [
                'class' => FirstInFirstOutPolicy::class,
            ])
        ;

        $container->register(FirstInLastOutPolicy::class, FirstInLastOutPolicy::class)
            ->addTag('scheduler.schedule_policy')
            ->addTag('container.preload', [
                'class' => FirstInLastOutPolicy::class,
            ])
        ;

        $container->register(IdlePolicy::class, IdlePolicy::class)
            ->addTag('scheduler.schedule_policy')
            ->addTag('container.preload', [
                'class' => IdlePolicy::class,
            ])
        ;

        $container->register(MemoryUsagePolicy::class, MemoryUsagePolicy::class)
            ->addTag('scheduler.schedule_policy')
            ->addTag('container.preload', [
                'class' => MemoryUsagePolicy::class,
            ])
        ;

        $container->register(NicePolicy::class, NicePolicy::class)
            ->addTag('scheduler.schedule_policy')
            ->addTag('container.preload', [
                'class' => NicePolicy::class,
            ])
        ;

        $container->register(RoundRobinPolicy::class, RoundRobinPolicy::class)
            ->addTag('scheduler.schedule_policy')
            ->addTag('container.preload', [
                'class' => RoundRobinPolicy::class,
            ])
        ;
    }

    private function registerBuilders(ContainerBuilder $container): void
    {
        $container->register(TaskBuilder::class, TaskBuilder::class)
            ->setArguments([
                new TaggedIteratorArgument('scheduler.task_builder'),
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

        $commandBuilderDefinition = new ChildDefinition(AbstractTaskBuilder::class);
        $commandBuilderDefinition->setClass(CommandBuilder::class);
        $container->setDefinition(CommandBuilder::class, $commandBuilderDefinition)
            ->setPublic(false)
            ->addTag('scheduler.task_builder')
            ->addTag('container.preload', [
                'class' => CommandBuilder::class,
            ])
        ;

        $httpBuilderDefinition = new ChildDefinition(AbstractTaskBuilder::class);
        $httpBuilderDefinition->setClass(HttpBuilder::class);
        $container->setDefinition(HttpBuilder::class, $httpBuilderDefinition)
            ->setPublic(false)
            ->addTag('scheduler.task_builder')
            ->addTag('container.preload', [
                'class' => HttpBuilder::class,
            ])
        ;

        $nullBuilderDefinition = new ChildDefinition(AbstractTaskBuilder::class);
        $nullBuilderDefinition->setClass(NullBuilder::class);
        $container->setDefinition(NullBuilder::class, $nullBuilderDefinition)
            ->setPublic(false)
            ->addTag('scheduler.task_builder')
            ->addTag('container.preload', [
                'class' => NullBuilder::class,
            ])
        ;

        $shellBuilderDefinition = new ChildDefinition(AbstractTaskBuilder::class);
        $shellBuilderDefinition->setClass(ShellBuilder::class);
        $container->setDefinition(ShellBuilder::class, $shellBuilderDefinition)
            ->setPublic(false)
            ->addTag('scheduler.task_builder')
            ->addTag('container.preload', [
                'class' => ShellBuilder::class,
            ])
        ;

        $chainedBuilderDefinition = new ChildDefinition(AbstractTaskBuilder::class);
        $chainedBuilderDefinition->setClass(ChainedBuilder::class);
        $container->setDefinition(ChainedBuilder::class, $chainedBuilderDefinition)
            ->setArgument(1, new TaggedIteratorArgument('scheduler.task_builder'))
            ->setPublic(false)
            ->addTag('scheduler.task_builder')
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

        $container->register(ShellTaskRunner::class, ShellTaskRunner::class)
            ->addTag('scheduler.runner')
            ->addTag('container.preload', [
                'class' => ShellTaskRunner::class,
            ])
        ;

        $container->register(CommandTaskRunner::class, CommandTaskRunner::class)
            ->setArguments([
                new Reference('scheduler.application', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE),
            ])
            ->addTag('scheduler.runner')
            ->addTag('container.preload', [
                'class' => CommandTaskRunner::class,
            ])
        ;

        $container->register(CallbackTaskRunner::class, CallbackTaskRunner::class)
            ->addTag('scheduler.runner')
            ->addTag('container.preload', [
                'class' => CallbackTaskRunner::class,
            ])
        ;

        $container->register(HttpTaskRunner::class, HttpTaskRunner::class)
            ->setArguments([
                new Reference(HttpClientInterface::class, ContainerInterface::NULL_ON_INVALID_REFERENCE),
            ])
            ->addTag('scheduler.runner')
            ->addTag('scheduler.extra', [
                'require' => 'http_client',
                'tag' => 'scheduler.runner',
            ])
            ->addTag('container.preload', [
                'class' => HttpTaskRunner::class,
            ])
        ;

        $container->register(MessengerTaskRunner::class, MessengerTaskRunner::class)
            ->setArguments([
                new Reference(MessageBusInterface::class, ContainerInterface::NULL_ON_INVALID_REFERENCE),
            ])
            ->addTag('scheduler.runner')
            ->addTag('scheduler.extra', [
                'require' => 'messenger.default_bus',
                'tag' => 'scheduler.runner',
            ])
            ->addTag('container.preload', [
                'class' => MessengerTaskRunner::class,
            ])
        ;

        $container->register(NotificationTaskRunner::class, NotificationTaskRunner::class)
            ->setArguments([
                new Reference(NotifierInterface::class, ContainerInterface::NULL_ON_INVALID_REFERENCE),
            ])
            ->addTag('scheduler.runner')
            ->addTag('scheduler.extra', [
                'require' => 'notifier',
                'tag' => 'scheduler.runner',
            ])
            ->addTag('container.preload', [
                'class' => NotificationTaskRunner::class,
            ])
        ;

        $container->register(NullTaskRunner::class, NullTaskRunner::class)
            ->addTag('scheduler.runner')
            ->addTag('container.preload', [
                'class' => NullTaskRunner::class,
            ])
        ;

        $container->register(ChainedTaskRunner::class, ChainedTaskRunner::class)
            ->setArguments([
                new TaggedIteratorArgument('scheduler.runner'),
            ])
            ->addTag('scheduler.runner')
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
    }

    private function registerMessengerTools(ContainerBuilder $container): void
    {
        $container->register(TaskMessageHandler::class, TaskMessageHandler::class)
            ->setArguments([
                new Reference(WorkerInterface::class, ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE),
            ])
            ->addTag('messenger.message_handler')
            ->addTag('container.preload', [
                'class' => TaskMessageHandler::class,
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

    private function registerWorker(ContainerBuilder $container, array $configuration): void
    {
        $container->register(Worker::class, Worker::class)
            ->setArguments([
                new Reference(SchedulerInterface::class, ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE),
                new TaggedIteratorArgument('scheduler.runner'),
                new Reference(TaskExecutionTrackerInterface::class, ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE),
                new Reference(WorkerMiddlewareStack::class, ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE),
                new Reference(EventDispatcherInterface::class, ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE),
                new Reference(LoggerInterface::class, ContainerInterface::NULL_ON_INVALID_REFERENCE),
                null !== $configuration['lock_store'] && 0 !== strpos('@', (string) $configuration['lock_store']) ? new Reference($configuration['lock_store']) : null,
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

    private function registerTasks(ContainerBuilder $container, array $configuration): void
    {
        foreach ($configuration['tasks'] as $name => $taskConfiguration) {
            $taskDefinition = $container->register(sprintf('scheduler.%s_task', $name), TaskInterface::class)
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
        if (0 !== strpos($configuration['transport']['dsn'], 'doctrine://')) {
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
            ->addTag('scheduler.transport_factory')
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
            ->addTag('scheduler.transport_factory')
            ->addTag('container.preload', [
                'class' => RedisTransportFactory::class,
            ])
        ;
    }

    private function registerMiddlewareStacks(ContainerBuilder $container, array $configuration): void
    {
        $container->register(SchedulerMiddlewareStack::class, SchedulerMiddlewareStack::class)
            ->setArguments([
                new TaggedIteratorArgument('scheduler.scheduler_middleware'),
            ])
            ->setPublic(false)
            ->addTag('scheduler.middleware_hub')
            ->addTag('container.preload', [
                'class' => SchedulerMiddlewareStack::class,
            ])
        ;

        $container->register(WorkerMiddlewareStack::class, WorkerMiddlewareStack::class)
            ->setArguments([
                new TaggedIteratorArgument('scheduler.worker_middleware'),
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
            ->addTag('scheduler.scheduler_middleware')
            ->addTag('scheduler.worker_middleware')
            ->addTag('container.preload', [
                'class' => NotifierMiddleware::class,
            ])
        ;

        $container->register(TaskCallbackMiddleware::class, TaskCallbackMiddleware::class)
            ->setPublic(false)
            ->addTag('scheduler.scheduler_middleware')
            ->addTag('scheduler.worker_middleware')
            ->addTag('container.preload', [
                'class' => TaskCallbackMiddleware::class,
            ])
        ;

        $container->register(SingleRunTaskMiddleware::class, SingleRunTaskMiddleware::class)
            ->setArguments([
                new Reference(SchedulerInterface::class, ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE),
            ])
            ->setPublic(false)
            ->addTag('scheduler.worker_middleware')
            ->addTag('container.preload', [
                'class' => SingleRunTaskMiddleware::class,
            ])
        ;

        $container->register(TaskUpdateMiddleware::class, TaskUpdateMiddleware::class)
            ->setArguments([
                new Reference(SchedulerInterface::class, ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE),
            ])
            ->setPublic(false)
            ->addTag('scheduler.worker_middleware')
            ->addTag('container.preload', [
                'class' => TaskUpdateMiddleware::class,
            ])
        ;

        if (null !== $configuration['rate_limiter']) {
            $container->register(MaxExecutionMiddleware::class, MaxExecutionMiddleware::class)
                ->setArguments([
                    new Reference(sprintf('limiter.%s', $configuration['rate_limiter']), ContainerInterface::NULL_ON_INVALID_REFERENCE),
                    new Reference(LoggerInterface::class, ContainerInterface::NULL_ON_INVALID_REFERENCE),
                ])
                ->setPublic(false)
                ->addTag('scheduler.worker_middleware')
                ->addTag('container.preload', [
                    'class' => MaxExecutionMiddleware::class,
                ])
            ;
        }
    }

    private function registerDataCollector(ContainerBuilder $container): void
    {
        $container->register(SchedulerDataCollector::class, SchedulerDataCollector::class)
            ->setArguments([
                new Reference(TaskLoggerSubscriber::class, ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE),
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
}
