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
    public function load(array $configs, ContainerBuilder $containerBuilder): void
    {
        $schedulerBundleConfiguration = new SchedulerBundleConfiguration();

        $config = $this->processConfiguration($schedulerBundleConfiguration, $configs);

        $this->registerParameters($containerBuilder, $config);
        $this->registerAutoConfigure($containerBuilder);
        $this->registerTransportFactories($containerBuilder, $config);
        $this->registerTransport($containerBuilder, $config);
        $this->registerScheduler($containerBuilder);
        $this->registerCommands($containerBuilder);
        $this->registerExpressionFactoryAndPolicies($containerBuilder);
        $this->registerBuilders($containerBuilder);
        $this->registerRunners($containerBuilder);
        $this->registerNormalizer($containerBuilder);
        $this->registerMessengerTools($containerBuilder);
        $this->registerSubscribers($containerBuilder);
        $this->registerTracker($containerBuilder);
        $this->registerWorker($containerBuilder, $config);
        $this->registerTasks($containerBuilder, $config);
        $this->registerDoctrineBridge($containerBuilder, $config);
        $this->registerRedisBridge($containerBuilder);
        $this->registerMiddlewareStacks($containerBuilder, $config);
        $this->registerDataCollector($containerBuilder);
    }

    private function registerParameters(ContainerBuilder $containerBuilder, array $configuration): void
    {
        $containerBuilder->setParameter('scheduler.timezone', $configuration['timezone']);
        $containerBuilder->setParameter('scheduler.trigger_path', $configuration['path']);
    }

    private function registerAutoConfigure(ContainerBuilder $containerBuilder): void
    {
        $containerBuilder->registerForAutoconfiguration(RunnerInterface::class)->addTag('scheduler.runner');
        $containerBuilder->registerForAutoconfiguration(TransportInterface::class)->addTag('scheduler.transport');
        $containerBuilder->registerForAutoconfiguration(TransportFactoryInterface::class)->addTag('scheduler.transport_factory');
        $containerBuilder->registerForAutoconfiguration(PolicyInterface::class)->addTag('scheduler.schedule_policy');
        $containerBuilder->registerForAutoconfiguration(WorkerInterface::class)->addTag('scheduler.worker');
        $containerBuilder->registerForAutoconfiguration(MiddlewareStackInterface::class)->addTag('scheduler.middleware_hub');
        $containerBuilder->registerForAutoconfiguration(PreSchedulingMiddlewareInterface::class)->addTag('scheduler.scheduler_middleware');
        $containerBuilder->registerForAutoconfiguration(PostSchedulingMiddlewareInterface::class)->addTag('scheduler.scheduler_middleware');
        $containerBuilder->registerForAutoconfiguration(PreExecutionMiddlewareInterface::class)->addTag('scheduler.worker_middleware');
        $containerBuilder->registerForAutoconfiguration(PostExecutionMiddlewareInterface::class)->addTag('scheduler.worker_middleware');
        $containerBuilder->registerForAutoconfiguration(ExpressionBuilderInterface::class)->addTag('scheduler.expression_builder');
    }

    private function registerTransportFactories(ContainerBuilder $containerBuilder, array $configuration): void
    {
        $containerBuilder->register(TransportFactory::class, TransportFactory::class)
            ->setArguments([
                new TaggedIteratorArgument('scheduler.transport_factory'),
            ])
            ->addTag('container.preload', [
                'class' => TransportFactory::class,
            ])
        ;

        $containerBuilder->setAlias(TransportFactoryInterface::class, TransportFactory::class);

        $containerBuilder->register(InMemoryTransportFactory::class, InMemoryTransportFactory::class)
            ->setPublic(false)
            ->addTag('scheduler.transport_factory')
            ->addTag('container.preload', [
                'class' => InMemoryTransportFactory::class,
            ])
        ;

        $containerBuilder->register(FilesystemTransportFactory::class, FilesystemTransportFactory::class)
            ->setPublic(false)
            ->addTag('scheduler.transport_factory')
            ->addTag('container.preload', [
                'class' => FilesystemTransportFactory::class,
            ])
        ;

        $containerBuilder->register(FailOverTransportFactory::class, FailOverTransportFactory::class)
            ->setArguments([
                new TaggedIteratorArgument('scheduler.transport_factory'),
            ])
            ->setPublic(false)
            ->addTag('scheduler.transport_factory')
            ->addTag('container.preload', [
                'class' => FailOverTransportFactory::class,
            ])
        ;

        $containerBuilder->register(LongTailTransportFactory::class, LongTailTransportFactory::class)
            ->setArguments([
                new TaggedIteratorArgument('scheduler.transport_factory'),
            ])
            ->setPublic(false)
            ->addTag('scheduler.transport_factory')
            ->addTag('container.preload', [
                'class' => LongTailTransportFactory::class,
            ])
        ;

        $containerBuilder->register(RoundRobinTransportFactory::class, RoundRobinTransportFactory::class)
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
            $containerBuilder->register(CacheTransportFactory::class, CacheTransportFactory::class)
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

    private function registerTransport(ContainerBuilder $containerBuilder, array $configuration): void
    {
        $containerBuilder->register('scheduler.transport', TransportInterface::class)
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

        $containerBuilder->setAlias(TransportInterface::class, 'scheduler.transport');
    }

    private function registerScheduler(ContainerBuilder $containerBuilder): void
    {
        $containerBuilder->register(Scheduler::class, Scheduler::class)
            ->setArguments([
                $containerBuilder->getParameter('scheduler.timezone'),
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

        $containerBuilder->setAlias(SchedulerInterface::class, Scheduler::class);
    }

    private function registerCommands(ContainerBuilder $containerBuilder): void
    {
        $containerBuilder->register(ConsumeTasksCommand::class, ConsumeTasksCommand::class)
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

        $containerBuilder->register(ListFailedTasksCommand::class, ListFailedTasksCommand::class)
            ->setArguments([
                new Reference(WorkerInterface::class, ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE),
            ])
            ->addTag('console.command')
            ->addTag('container.preload', [
                'class' => ListFailedTasksCommand::class,
            ])
        ;

        $containerBuilder->register(ListTasksCommand::class, ListTasksCommand::class)
            ->setArguments([
                new Reference(SchedulerInterface::class, ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE),
            ])
            ->addTag('console.command')
            ->addTag('container.preload', [
                'class' => ListTasksCommand::class,
            ])
        ;

        $containerBuilder->register(RebootSchedulerCommand::class, RebootSchedulerCommand::class)
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

        $containerBuilder->register(RemoveFailedTaskCommand::class, RemoveFailedTaskCommand::class)
            ->setArguments([
                new Reference(SchedulerInterface::class, ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE),
                new Reference(WorkerInterface::class, ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE),
            ])
            ->addTag('console.command')
            ->addTag('container.preload', [
                'class' => RemoveFailedTaskCommand::class,
            ])
        ;

        $containerBuilder->register(RetryFailedTaskCommand::class, RetryFailedTaskCommand::class)
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

        $containerBuilder->register(YieldTaskCommand::class, YieldTaskCommand::class)
            ->setArguments([
                new Reference(SchedulerInterface::class, ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE),
            ])
            ->addTag('console.command')
            ->addTag('container.preload', [
                'class' => YieldTaskCommand::class,
            ])
        ;
    }

    private function registerExpressionFactoryAndPolicies(ContainerBuilder $containerBuilder): void
    {
        $containerBuilder->register(Expression::class, Expression::class);

        $containerBuilder->register(ExpressionBuilder::class, ExpressionBuilder::class)
            ->setArguments([
                new TaggedIteratorArgument('scheduler.expression_builder'),
            ])
            ->addTag('container.preload', [
                'class' => ExpressionBuilder::class,
            ])
        ;
        $containerBuilder->setAlias(BuilderInterface::class, ExpressionBuilder::class);

        $containerBuilder->register(CronExpressionBuilder::class, CronExpressionBuilder::class)
            ->setPublic(false)
            ->addTag('scheduler.expression_builder')
            ->addTag('container.preload', [
                'class' => CronExpressionBuilder::class,
            ])
        ;

        $containerBuilder->register(ComputedExpressionBuilder::class, ComputedExpressionBuilder::class)
            ->setPublic(false)
            ->addTag('scheduler.expression_builder')
            ->addTag('container.preload', [
                'class' => ComputedExpressionBuilder::class,
            ])
        ;

        $containerBuilder->register(FluentExpressionBuilder::class, FluentExpressionBuilder::class)
            ->setPublic(false)
            ->addTag('scheduler.expression_builder')
            ->addTag('container.preload', [
                'class' => FluentExpressionBuilder::class,
            ])
        ;

        $containerBuilder->register(SchedulePolicyOrchestrator::class, SchedulePolicyOrchestrator::class)
            ->setArguments([
                new TaggedIteratorArgument('scheduler.schedule_policy'),
            ])
            ->addTag('container.preload', [
                'class' => SchedulePolicyOrchestrator::class,
            ])
        ;
        $containerBuilder->setAlias(SchedulePolicyOrchestratorInterface::class, SchedulePolicyOrchestrator::class);

        $containerBuilder->register(BatchPolicy::class, BatchPolicy::class)
            ->addTag('scheduler.schedule_policy')
            ->addTag('container.preload', [
                'class' => BatchPolicy::class,
            ])
        ;

        $containerBuilder->register(DeadlinePolicy::class, DeadlinePolicy::class)
            ->addTag('scheduler.schedule_policy')
            ->addTag('container.preload', [
                'class' => DeadlinePolicy::class,
            ])
        ;

        $containerBuilder->register(ExecutionDurationPolicy::class, ExecutionDurationPolicy::class)
            ->addTag('scheduler.schedule_policy')
            ->addTag('container.preload', [
                'class' => ExecutionDurationPolicy::class,
            ])
        ;

        $containerBuilder->register(FirstInFirstOutPolicy::class, FirstInFirstOutPolicy::class)
            ->addTag('scheduler.schedule_policy')
            ->addTag('container.preload', [
                'class' => FirstInFirstOutPolicy::class,
            ])
        ;

        $containerBuilder->register(FirstInLastOutPolicy::class, FirstInLastOutPolicy::class)
            ->addTag('scheduler.schedule_policy')
            ->addTag('container.preload', [
                'class' => FirstInLastOutPolicy::class,
            ])
        ;

        $containerBuilder->register(IdlePolicy::class, IdlePolicy::class)
            ->addTag('scheduler.schedule_policy')
            ->addTag('container.preload', [
                'class' => IdlePolicy::class,
            ])
        ;

        $containerBuilder->register(MemoryUsagePolicy::class, MemoryUsagePolicy::class)
            ->addTag('scheduler.schedule_policy')
            ->addTag('container.preload', [
                'class' => MemoryUsagePolicy::class,
            ])
        ;

        $containerBuilder->register(NicePolicy::class, NicePolicy::class)
            ->addTag('scheduler.schedule_policy')
            ->addTag('container.preload', [
                'class' => NicePolicy::class,
            ])
        ;

        $containerBuilder->register(RoundRobinPolicy::class, RoundRobinPolicy::class)
            ->addTag('scheduler.schedule_policy')
            ->addTag('container.preload', [
                'class' => RoundRobinPolicy::class,
            ])
        ;
    }

    private function registerBuilders(ContainerBuilder $containerBuilder): void
    {
        $containerBuilder->register(TaskBuilder::class, TaskBuilder::class)
            ->setArguments([
                new TaggedIteratorArgument('scheduler.task_builder'),
                new Reference('property_accessor'),
            ])
            ->addTag('container.preload', [
                'class' => TaskBuilder::class,
            ])
        ;
        $containerBuilder->setAlias(TaskBuilderInterface::class, TaskBuilder::class);

        $containerBuilder->register(AbstractTaskBuilder::class, AbstractTaskBuilder::class)
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
        $containerBuilder->setDefinition(CommandBuilder::class, $commandBuilderDefinition)
            ->setPublic(false)
            ->addTag('scheduler.task_builder')
            ->addTag('container.preload', [
                'class' => CommandBuilder::class,
            ])
        ;

        $httpBuilderDefinition = new ChildDefinition(AbstractTaskBuilder::class);
        $httpBuilderDefinition->setClass(HttpBuilder::class);
        $containerBuilder->setDefinition(HttpBuilder::class, $httpBuilderDefinition)
            ->setPublic(false)
            ->addTag('scheduler.task_builder')
            ->addTag('container.preload', [
                'class' => HttpBuilder::class,
            ])
        ;

        $nullBuilderDefinition = new ChildDefinition(AbstractTaskBuilder::class);
        $nullBuilderDefinition->setClass(NullBuilder::class);
        $containerBuilder->setDefinition(NullBuilder::class, $nullBuilderDefinition)
            ->setPublic(false)
            ->addTag('scheduler.task_builder')
            ->addTag('container.preload', [
                'class' => NullBuilder::class,
            ])
        ;

        $shellBuilderDefinition = new ChildDefinition(AbstractTaskBuilder::class);
        $shellBuilderDefinition->setClass(ShellBuilder::class);
        $containerBuilder->setDefinition(ShellBuilder::class, $shellBuilderDefinition)
            ->setPublic(false)
            ->addTag('scheduler.task_builder')
            ->addTag('container.preload', [
                'class' => ShellBuilder::class,
            ])
        ;

        $chainedBuilderDefinition = new ChildDefinition(AbstractTaskBuilder::class);
        $chainedBuilderDefinition->setClass(ChainedBuilder::class);
        $containerBuilder->setDefinition(ChainedBuilder::class, $chainedBuilderDefinition)
            ->setArgument(1, new TaggedIteratorArgument('scheduler.task_builder'))
            ->setPublic(false)
            ->addTag('scheduler.task_builder')
            ->addTag('container.preload', [
                'class' => ChainedBuilder::class,
            ])
        ;
    }

    private function registerRunners(ContainerBuilder $containerBuilder): void
    {
        $containerBuilder->register('scheduler.application', Application::class)
            ->setArguments([
                new Reference(KernelInterface::class, ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE),
            ])
        ;

        $containerBuilder->register(ShellTaskRunner::class, ShellTaskRunner::class)
            ->addTag('scheduler.runner')
            ->addTag('container.preload', [
                'class' => ShellTaskRunner::class,
            ])
        ;

        $containerBuilder->register(CommandTaskRunner::class, CommandTaskRunner::class)
            ->setArguments([
                new Reference('scheduler.application', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE),
            ])
            ->addTag('scheduler.runner')
            ->addTag('container.preload', [
                'class' => CommandTaskRunner::class,
            ])
        ;

        $containerBuilder->register(CallbackTaskRunner::class, CallbackTaskRunner::class)
            ->addTag('scheduler.runner')
            ->addTag('container.preload', [
                'class' => CallbackTaskRunner::class,
            ])
        ;

        $containerBuilder->register(HttpTaskRunner::class, HttpTaskRunner::class)
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

        $containerBuilder->register(MessengerTaskRunner::class, MessengerTaskRunner::class)
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

        $containerBuilder->register(NotificationTaskRunner::class, NotificationTaskRunner::class)
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

        $containerBuilder->register(NullTaskRunner::class, NullTaskRunner::class)
            ->addTag('scheduler.runner')
            ->addTag('container.preload', [
                'class' => NullTaskRunner::class,
            ])
        ;

        $containerBuilder->register(ChainedTaskRunner::class, ChainedTaskRunner::class)
            ->setArguments([
                new TaggedIteratorArgument('scheduler.runner'),
            ])
            ->addTag('scheduler.runner')
            ->addTag('container.preload', [
                'class' => ChainedTaskRunner::class,
            ])
        ;
    }

    private function registerNormalizer(ContainerBuilder $containerBuilder): void
    {
        $containerBuilder->register(TaskNormalizer::class, TaskNormalizer::class)
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

        $containerBuilder->register(NotificationTaskBagNormalizer::class, NotificationTaskBagNormalizer::class)
            ->setArguments([
                new Reference('serializer.normalizer.object', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE),
            ])
            ->addTag('serializer.normalizer')
            ->addTag('container.preload', [
                'class' => NotificationTaskBagNormalizer::class,
            ])
        ;
    }

    private function registerMessengerTools(ContainerBuilder $containerBuilder): void
    {
        $containerBuilder->register(TaskMessageHandler::class, TaskMessageHandler::class)
            ->setArguments([
                new Reference(WorkerInterface::class, ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE),
            ])
            ->addTag('messenger.message_handler')
            ->addTag('container.preload', [
                'class' => TaskMessageHandler::class,
            ])
        ;

        $containerBuilder->register(TaskToYieldMessageHandler::class, TaskToYieldMessageHandler::class)
            ->setArguments([
                new Reference(SchedulerInterface::class, ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE),
            ])
            ->addTag('messenger.message_handler')
            ->addTag('container.preload', [
                'class' => TaskToYieldMessageHandler::class,
            ])
        ;
    }

    private function registerSubscribers(ContainerBuilder $containerBuilder): void
    {
        $containerBuilder->register(TaskSubscriber::class, TaskSubscriber::class)
            ->setArguments([
                new Reference(SchedulerInterface::class, ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE),
                new Reference(WorkerInterface::class, ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE),
                new Reference(EventDispatcherInterface::class, ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE),
                new Reference(SerializerInterface::class, ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE),
                new Reference(LoggerInterface::class, ContainerInterface::NULL_ON_INVALID_REFERENCE),
                $containerBuilder->getParameter('scheduler.trigger_path'),
            ])
            ->addTag('kernel.event_subscriber')
            ->addTag('monolog.logger', [
                'channel' => 'scheduler',
            ])
            ->addTag('container.preload', [
                'class' => TaskSubscriber::class,
            ])
        ;

        $containerBuilder->register(TaskLoggerSubscriber::class, TaskLoggerSubscriber::class)
            ->addTag('kernel.event_subscriber')
            ->addTag('container.preload', [
                'class' => TaskLoggerSubscriber::class,
            ])
        ;

        $containerBuilder->register(StopWorkerOnSignalSubscriber::class, StopWorkerOnSignalSubscriber::class)
            ->addTag('kernel.event_subscriber')
            ->addTag('container.preload', [
                'class' => StopWorkerOnSignalSubscriber::class,
            ])
        ;

        $containerBuilder->register(TaskLifecycleSubscriber::class, TaskLifecycleSubscriber::class)
            ->setArguments([
                new Reference(LoggerInterface::class, ContainerInterface::NULL_ON_INVALID_REFERENCE),
            ])
            ->setPublic(false)
            ->addTag('kernel.event_subscriber')
            ->addTag('container.preload', [
                'class' => TaskLifecycleSubscriber::class,
            ])
        ;

        $containerBuilder->register(WorkerLifecycleSubscriber::class, WorkerLifecycleSubscriber::class)
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

    private function registerTracker(ContainerBuilder $containerBuilder): void
    {
        $containerBuilder->register('scheduler.stop_watch', Stopwatch::class);

        $containerBuilder->register(TaskExecutionTracker::class, TaskExecutionTracker::class)
            ->setArguments([
                new Reference('scheduler.stop_watch', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE),
            ])
            ->addTag('container.preload', [
                'class' => TaskExecutionTracker::class,
            ])
        ;
        $containerBuilder->setAlias(TaskExecutionTrackerInterface::class, TaskExecutionTracker::class);
    }

    private function registerWorker(ContainerBuilder $containerBuilder, array $configuration): void
    {
        $containerBuilder->register(Worker::class, Worker::class)
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
        $containerBuilder->setAlias(WorkerInterface::class, Worker::class);
    }

    private function registerTasks(ContainerBuilder $containerBuilder, array $configuration): void
    {
        foreach ($configuration['tasks'] as $name => $taskConfiguration) {
            $taskDefinition = $containerBuilder->register(sprintf('scheduler.%s_task', $name), TaskInterface::class)
                ->setFactory([new Reference(TaskBuilderInterface::class), 'create'])
                ->setArguments([
                    array_merge(['name' => $name], $taskConfiguration),
                ])
                ->addTag('scheduler.task')
                ->setPublic(false)
            ;

            $containerBuilder->getDefinition(Scheduler::class)
                ->addMethodCall('schedule', [$taskDefinition])
            ;
        }
    }

    private function registerDoctrineBridge(ContainerBuilder $containerBuilder, array $configuration): void
    {
        if (0 !== strpos($configuration['transport']['dsn'], 'doctrine://')) {
            return;
        }

        $containerBuilder->register(SchedulerTransportDoctrineSchemaSubscriber::class, SchedulerTransportDoctrineSchemaSubscriber::class)
            ->setArguments([
                new Reference(TransportInterface::class, ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE),
            ])
            ->setPublic(false)
            ->addTag('doctrine.event_subscriber')
            ->addTag('container.preload', [
                'class' => SchedulerTransportDoctrineSchemaSubscriber::class,
            ])
        ;

        $containerBuilder->register(DoctrineTransportFactory::class, DoctrineTransportFactory::class)
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

    private function registerRedisBridge(ContainerBuilder $containerBuilder): void
    {
        if (!class_exists(Redis::class)) {
            return;
        }

        $containerBuilder->register(RedisTransportFactory::class, RedisTransportFactory::class)
            ->setPublic(false)
            ->addTag('scheduler.transport_factory')
            ->addTag('container.preload', [
                'class' => RedisTransportFactory::class,
            ])
        ;
    }

    private function registerMiddlewareStacks(ContainerBuilder $containerBuilder, array $configuration): void
    {
        $containerBuilder->register(SchedulerMiddlewareStack::class, SchedulerMiddlewareStack::class)
            ->setArguments([
                new TaggedIteratorArgument('scheduler.scheduler_middleware'),
            ])
            ->setPublic(false)
            ->addTag('scheduler.middleware_hub')
            ->addTag('container.preload', [
                'class' => SchedulerMiddlewareStack::class,
            ])
        ;

        $containerBuilder->register(WorkerMiddlewareStack::class, WorkerMiddlewareStack::class)
            ->setArguments([
                new TaggedIteratorArgument('scheduler.worker_middleware'),
            ])
            ->setPublic(false)
            ->addTag('scheduler.middleware_hub')
            ->addTag('container.preload', [
                'class' => WorkerMiddlewareStack::class,
            ])
        ;

        $containerBuilder->register(NotifierMiddleware::class, NotifierMiddleware::class)
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

        $containerBuilder->register(TaskCallbackMiddleware::class, TaskCallbackMiddleware::class)
            ->setPublic(false)
            ->addTag('scheduler.scheduler_middleware')
            ->addTag('scheduler.worker_middleware')
            ->addTag('container.preload', [
                'class' => TaskCallbackMiddleware::class,
            ])
        ;

        $containerBuilder->register(SingleRunTaskMiddleware::class, SingleRunTaskMiddleware::class)
            ->setArguments([
                new Reference(SchedulerInterface::class, ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE),
            ])
            ->setPublic(false)
            ->addTag('scheduler.worker_middleware')
            ->addTag('container.preload', [
                'class' => SingleRunTaskMiddleware::class,
            ])
        ;

        $containerBuilder->register(TaskUpdateMiddleware::class, TaskUpdateMiddleware::class)
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
            $containerBuilder->register(MaxExecutionMiddleware::class, MaxExecutionMiddleware::class)
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

    private function registerDataCollector(ContainerBuilder $containerBuilder): void
    {
        $containerBuilder->register(SchedulerDataCollector::class, SchedulerDataCollector::class)
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
