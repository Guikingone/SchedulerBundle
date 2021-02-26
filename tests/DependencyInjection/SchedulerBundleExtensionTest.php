<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\DependencyInjection;

use PHPUnit\Framework\TestCase;
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
use SchedulerBundle\DependencyInjection\SchedulerBundleExtension;
use SchedulerBundle\EventListener\StopWorkerOnSignalSubscriber;
use SchedulerBundle\EventListener\TaskExecutionSubscriber;
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
use SchedulerBundle\Middleware\PostExecutionMiddlewareInterface;
use SchedulerBundle\Middleware\PostSchedulingMiddlewareInterface;
use SchedulerBundle\Middleware\PreExecutionMiddlewareInterface;
use SchedulerBundle\Middleware\PreSchedulingMiddlewareInterface;
use SchedulerBundle\Middleware\MaxExecutionMiddleware;
use SchedulerBundle\Middleware\SchedulerMiddlewareStack;
use SchedulerBundle\Middleware\TaskCallbackMiddleware;
use SchedulerBundle\Middleware\WorkerMiddlewareStack;
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
use stdClass;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\Compiler\ResolveChildDefinitionsPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class SchedulerBundleExtensionTest extends TestCase
{
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
        self::assertTrue($container->getDefinition(FailOverTransportFactory::class)->hasTag('scheduler.transport_factory'));
        self::assertTrue($container->getDefinition(FailOverTransportFactory::class)->hasTag('container.preload'));
        self::assertSame(FailOverTransportFactory::class, $container->getDefinition(FailOverTransportFactory::class)->getTag('container.preload')[0]['class']);

        self::assertTrue($container->hasDefinition(LongTailTransportFactory::class));
        self::assertFalse($container->getDefinition(LongTailTransportFactory::class)->isPublic());
        self::assertTrue($container->getDefinition(LongTailTransportFactory::class)->hasTag('scheduler.transport_factory'));
        self::assertTrue($container->getDefinition(LongTailTransportFactory::class)->hasTag('container.preload'));
        self::assertSame(LongTailTransportFactory::class, $container->getDefinition(LongTailTransportFactory::class)->getTag('container.preload')[0]['class']);

        self::assertTrue($container->hasDefinition(RoundRobinTransportFactory::class));
        self::assertFalse($container->getDefinition(RoundRobinTransportFactory::class)->isPublic());
        self::assertTrue($container->getDefinition(RoundRobinTransportFactory::class)->hasTag('scheduler.transport_factory'));
        self::assertTrue($container->getDefinition(RoundRobinTransportFactory::class)->hasTag('container.preload'));
        self::assertSame(RoundRobinTransportFactory::class, $container->getDefinition(RoundRobinTransportFactory::class)->getTag('container.preload')[0]['class']);

        self::assertTrue($container->hasDefinition(RedisTransportFactory::class));
        self::assertFalse($container->getDefinition(RedisTransportFactory::class)->isPublic());
        self::assertTrue($container->getDefinition(RedisTransportFactory::class)->hasTag('scheduler.transport_factory'));
        self::assertTrue($container->getDefinition(RedisTransportFactory::class)->hasTag('container.preload'));
        self::assertSame(RedisTransportFactory::class, $container->getDefinition(RedisTransportFactory::class)->getTag('container.preload')[0]['class']);
    }

    public function testTransportIsRegistered(): void
    {
        $extension = new SchedulerBundleExtension();

        $container = new ContainerBuilder();
        $container->register(SerializerInterface::class, SerializerInterface::class);

        $extension->load([
            'scheduler_bundle' => [
                'path' => '/_foo',
                'timezone' => 'Europe/Paris',
                'transport' => [
                    'dsn' => 'memory://first_in_first_out',
                ],
                'tasks' => [],
                'lock_store' => null,
            ],
        ], $container);

        self::assertTrue($container->hasDefinition('scheduler.transport'));
        self::assertTrue($container->hasAlias(TransportInterface::class));
        self::assertCount(4, $container->getDefinition('scheduler.transport')->getArguments());
        self::assertInstanceOf(Reference::class, $container->getDefinition('scheduler.transport')->getFactory()[0]);
        self::assertSame('createTransport', $container->getDefinition('scheduler.transport')->getFactory()[1]);
        self::assertSame('memory://first_in_first_out', $container->getDefinition('scheduler.transport')->getArgument(0));
        self::assertSame([
            'execution_mode' => 'first_in_first_out',
            'path' => '%kernel.project_dir%/var/tasks',
        ], $container->getDefinition('scheduler.transport')->getArgument(1));
        self::assertInstanceOf(Reference::class, $container->getDefinition('scheduler.transport')->getArgument(2));
        self::assertInstanceOf(Reference::class, $container->getDefinition('scheduler.transport')->getArgument(3));
        self::assertTrue($container->getDefinition('scheduler.transport')->isShared());
        self::assertFalse($container->getDefinition('scheduler.transport')->isPublic());
        self::assertTrue($container->getDefinition('scheduler.transport')->hasTag('container.preload'));
        self::assertSame(TransportInterface::class, $container->getDefinition('scheduler.transport')->getTag('container.preload')[0]['class']);
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
        self::assertCount(6, $container->getDefinition(Scheduler::class)->getArguments());
        self::assertSame('Europe/Paris', $container->getDefinition(Scheduler::class)->getArgument(0));
        self::assertInstanceOf(Reference::class, $container->getDefinition(Scheduler::class)->getArgument(1));
        self::assertInstanceOf(Reference::class, $container->getDefinition(Scheduler::class)->getArgument(2));
        self::assertInstanceOf(Reference::class, $container->getDefinition(Scheduler::class)->getArgument(3));
        self::assertInstanceOf(Reference::class, $container->getDefinition(Scheduler::class)->getArgument(4));
        self::assertInstanceOf(Reference::class, $container->getDefinition(Scheduler::class)->getArgument(5));
        self::assertFalse($container->getDefinition(Scheduler::class)->isPublic());
        self::assertTrue($container->getDefinition(Scheduler::class)->hasTag('monolog.logger'));
        self::assertSame('scheduler', $container->getDefinition(Scheduler::class)->getTag('monolog.logger')[0]['channel']);
        self::assertTrue($container->getDefinition(Scheduler::class)->hasTag('container.preload'));
        self::assertSame(Scheduler::class, $container->getDefinition(Scheduler::class)->getTag('container.preload')[0]['class']);
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
        self::assertInstanceOf(Reference::class, $container->getDefinition(ConsumeTasksCommand::class)->getArgument(1));
        self::assertInstanceOf(Reference::class, $container->getDefinition(ConsumeTasksCommand::class)->getArgument(2));
        self::assertInstanceOf(Reference::class, $container->getDefinition(ConsumeTasksCommand::class)->getArgument(3));
        self::assertTrue($container->getDefinition(ConsumeTasksCommand::class)->hasTag('console.command'));
        self::assertTrue($container->getDefinition(ConsumeTasksCommand::class)->hasTag('monolog.logger'));
        self::assertSame('scheduler', $container->getDefinition(ConsumeTasksCommand::class)->getTag('monolog.logger')[0]['channel']);
        self::assertTrue($container->getDefinition(ConsumeTasksCommand::class)->hasTag('container.preload'));
        self::assertSame(ConsumeTasksCommand::class, $container->getDefinition(ConsumeTasksCommand::class)->getTag('container.preload')[0]['class']);

        self::assertTrue($container->hasDefinition(ListFailedTasksCommand::class));
        self::assertCount(1, $container->getDefinition(ListFailedTasksCommand::class)->getArguments());
        self::assertInstanceOf(Reference::class, $container->getDefinition(ListFailedTasksCommand::class)->getArgument(0));
        self::assertTrue($container->getDefinition(ListFailedTasksCommand::class)->hasTag('console.command'));
        self::assertTrue($container->getDefinition(ListFailedTasksCommand::class)->hasTag('container.preload'));
        self::assertSame(ListFailedTasksCommand::class, $container->getDefinition(ListFailedTasksCommand::class)->getTag('container.preload')[0]['class']);

        self::assertTrue($container->hasDefinition(ListTasksCommand::class));
        self::assertCount(1, $container->getDefinition(ListTasksCommand::class)->getArguments());
        self::assertInstanceOf(Reference::class, $container->getDefinition(ListTasksCommand::class)->getArgument(0));
        self::assertTrue($container->getDefinition(ListTasksCommand::class)->hasTag('console.command'));
        self::assertTrue($container->getDefinition(ListTasksCommand::class)->hasTag('container.preload'));
        self::assertSame(ListTasksCommand::class, $container->getDefinition(ListTasksCommand::class)->getTag('container.preload')[0]['class']);

        self::assertTrue($container->hasDefinition(RebootSchedulerCommand::class));
        self::assertCount(4, $container->getDefinition(RebootSchedulerCommand::class)->getArguments());
        self::assertInstanceOf(Reference::class, $container->getDefinition(RebootSchedulerCommand::class)->getArgument(0));
        self::assertInstanceOf(Reference::class, $container->getDefinition(RebootSchedulerCommand::class)->getArgument(1));
        self::assertInstanceOf(Reference::class, $container->getDefinition(RebootSchedulerCommand::class)->getArgument(2));
        self::assertInstanceOf(Reference::class, $container->getDefinition(RebootSchedulerCommand::class)->getArgument(3));
        self::assertTrue($container->getDefinition(RebootSchedulerCommand::class)->hasTag('console.command'));
        self::assertTrue($container->getDefinition(RebootSchedulerCommand::class)->hasTag('monolog.logger'));
        self::assertSame('scheduler', $container->getDefinition(RebootSchedulerCommand::class)->getTag('monolog.logger')[0]['channel']);
        self::assertTrue($container->getDefinition(RebootSchedulerCommand::class)->hasTag('container.preload'));
        self::assertSame(RebootSchedulerCommand::class, $container->getDefinition(RebootSchedulerCommand::class)->getTag('container.preload')[0]['class']);

        self::assertTrue($container->hasDefinition(RemoveFailedTaskCommand::class));
        self::assertCount(2, $container->getDefinition(RemoveFailedTaskCommand::class)->getArguments());
        self::assertInstanceOf(Reference::class, $container->getDefinition(RemoveFailedTaskCommand::class)->getArgument(0));
        self::assertInstanceOf(Reference::class, $container->getDefinition(RemoveFailedTaskCommand::class)->getArgument(1));
        self::assertTrue($container->getDefinition(RemoveFailedTaskCommand::class)->hasTag('console.command'));
        self::assertTrue($container->getDefinition(RemoveFailedTaskCommand::class)->hasTag('container.preload'));
        self::assertSame(RemoveFailedTaskCommand::class, $container->getDefinition(RemoveFailedTaskCommand::class)->getTag('container.preload')[0]['class']);

        self::assertTrue($container->hasDefinition(RetryFailedTaskCommand::class));
        self::assertCount(3, $container->getDefinition(RetryFailedTaskCommand::class)->getArguments());
        self::assertInstanceOf(Reference::class, $container->getDefinition(RetryFailedTaskCommand::class)->getArgument(0));
        self::assertInstanceOf(Reference::class, $container->getDefinition(RetryFailedTaskCommand::class)->getArgument(1));
        self::assertInstanceOf(Reference::class, $container->getDefinition(RetryFailedTaskCommand::class)->getArgument(2));
        self::assertTrue($container->getDefinition(RetryFailedTaskCommand::class)->hasTag('console.command'));
        self::assertTrue($container->getDefinition(RetryFailedTaskCommand::class)->hasTag('monolog.logger'));
        self::assertSame('scheduler', $container->getDefinition(RetryFailedTaskCommand::class)->getTag('monolog.logger')[0]['channel']);
        self::assertTrue($container->getDefinition(RetryFailedTaskCommand::class)->hasTag('container.preload'));
        self::assertSame(RetryFailedTaskCommand::class, $container->getDefinition(RetryFailedTaskCommand::class)->getTag('container.preload')[0]['class']);

        self::assertTrue($container->hasDefinition(YieldTaskCommand::class));
        self::assertCount(1, $container->getDefinition(YieldTaskCommand::class)->getArguments());
        self::assertInstanceOf(Reference::class, $container->getDefinition(YieldTaskCommand::class)->getArgument(0));
        self::assertTrue($container->getDefinition(YieldTaskCommand::class)->hasTag('console.command'));
        self::assertTrue($container->getDefinition(YieldTaskCommand::class)->hasTag('container.preload'));
        self::assertSame(YieldTaskCommand::class, $container->getDefinition(YieldTaskCommand::class)->getTag('container.preload')[0]['class']);
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
        self::assertTrue($container->getDefinition(CronExpressionBuilder::class)->hasTag('scheduler.expression_builder'));
        self::assertTrue($container->getDefinition(CronExpressionBuilder::class)->hasTag('container.preload'));
        self::assertSame(CronExpressionBuilder::class, $container->getDefinition(CronExpressionBuilder::class)->getTag('container.preload')[0]['class']);

        self::assertTrue($container->hasDefinition(ComputedExpressionBuilder::class));
        self::assertTrue($container->getDefinition(CronExpressionBuilder::class)->hasTag('scheduler.expression_builder'));
        self::assertTrue($container->getDefinition(ComputedExpressionBuilder::class)->hasTag('container.preload'));
        self::assertSame(ComputedExpressionBuilder::class, $container->getDefinition(ComputedExpressionBuilder::class)->getTag('container.preload')[0]['class']);

        self::assertTrue($container->hasDefinition(FluentExpressionBuilder::class));
        self::assertTrue($container->getDefinition(CronExpressionBuilder::class)->hasTag('scheduler.expression_builder'));
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
        self::assertTrue($container->getDefinition(TaskBuilder::class)->hasTag('container.preload'));
        self::assertSame(TaskBuilder::class, $container->getDefinition(TaskBuilder::class)->getTag('container.preload')[0]['class']);

        self::assertTrue($container->hasDefinition(AbstractTaskBuilder::class));
        self::assertFalse($container->getDefinition(AbstractTaskBuilder::class)->isPublic());
        self::assertTrue($container->getDefinition(AbstractTaskBuilder::class)->isAbstract());
        self::assertCount(1, $container->getDefinition(AbstractTaskBuilder::class)->getArguments());
        self::assertInstanceOf(Reference::class, $container->getDefinition(AbstractTaskBuilder::class)->getArgument(0));
        self::assertTrue($container->getDefinition(AbstractTaskBuilder::class)->hasTag('container.preload'));
        self::assertSame(AbstractTaskBuilder::class, $container->getDefinition(AbstractTaskBuilder::class)->getTag('container.preload')[0]['class']);

        self::assertTrue($container->hasDefinition(CommandBuilder::class));
        self::assertFalse($container->getDefinition(CommandBuilder::class)->isPublic());
        self::assertCount(1, $container->getDefinition(CommandBuilder::class)->getArguments());
        self::assertInstanceOf(Reference::class, $container->getDefinition(CommandBuilder::class)->getArgument(0));
        self::assertTrue($container->getDefinition(CommandBuilder::class)->hasTag('scheduler.task_builder'));
        self::assertTrue($container->getDefinition(CommandBuilder::class)->hasTag('container.preload'));
        self::assertSame(CommandBuilder::class, $container->getDefinition(CommandBuilder::class)->getTag('container.preload')[0]['class']);

        self::assertTrue($container->hasDefinition(HttpBuilder::class));
        self::assertFalse($container->getDefinition(HttpBuilder::class)->isPublic());
        self::assertCount(1, $container->getDefinition(HttpBuilder::class)->getArguments());
        self::assertInstanceOf(Reference::class, $container->getDefinition(HttpBuilder::class)->getArgument(0));
        self::assertTrue($container->getDefinition(HttpBuilder::class)->hasTag('scheduler.task_builder'));
        self::assertTrue($container->getDefinition(HttpBuilder::class)->hasTag('container.preload'));
        self::assertSame(HttpBuilder::class, $container->getDefinition(HttpBuilder::class)->getTag('container.preload')[0]['class']);

        self::assertTrue($container->hasDefinition(NullBuilder::class));
        self::assertFalse($container->getDefinition(NullBuilder::class)->isPublic());
        self::assertCount(1, $container->getDefinition(NullBuilder::class)->getArguments());
        self::assertInstanceOf(Reference::class, $container->getDefinition(NullBuilder::class)->getArgument(0));
        self::assertTrue($container->getDefinition(NullBuilder::class)->hasTag('scheduler.task_builder'));
        self::assertTrue($container->getDefinition(NullBuilder::class)->hasTag('container.preload'));
        self::assertSame(NullBuilder::class, $container->getDefinition(NullBuilder::class)->getTag('container.preload')[0]['class']);

        self::assertTrue($container->hasDefinition(ShellBuilder::class));
        self::assertFalse($container->getDefinition(ShellBuilder::class)->isPublic());
        self::assertCount(1, $container->getDefinition(ShellBuilder::class)->getArguments());
        self::assertInstanceOf(Reference::class, $container->getDefinition(ShellBuilder::class)->getArgument(0));
        self::assertTrue($container->getDefinition(ShellBuilder::class)->hasTag('scheduler.task_builder'));
        self::assertTrue($container->getDefinition(ShellBuilder::class)->hasTag('container.preload'));
        self::assertSame(ShellBuilder::class, $container->getDefinition(ShellBuilder::class)->getTag('container.preload')[0]['class']);

        self::assertTrue($container->hasDefinition(ChainedBuilder::class));
        self::assertFalse($container->getDefinition(ChainedBuilder::class)->isPublic());
        self::assertCount(2, $container->getDefinition(ChainedBuilder::class)->getArguments());
        self::assertInstanceOf(Reference::class, $container->getDefinition(ChainedBuilder::class)->getArgument(0));
        self::assertInstanceOf(TaggedIteratorArgument::class, $container->getDefinition(ChainedBuilder::class)->getArgument(1));
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

        self::assertTrue($container->hasDefinition(ShellTaskRunner::class));
        self::assertTrue($container->getDefinition(ShellTaskRunner::class)->hasTag('scheduler.runner'));
        self::assertTrue($container->getDefinition(ShellTaskRunner::class)->hasTag('container.preload'));
        self::assertSame(ShellTaskRunner::class, $container->getDefinition(ShellTaskRunner::class)->getTag('container.preload')[0]['class']);

        self::assertTrue($container->hasDefinition(CommandTaskRunner::class));
        self::assertCount(1, $container->getDefinition(CommandTaskRunner::class)->getArguments());
        self::assertInstanceOf(Reference::class, $container->getDefinition(CommandTaskRunner::class)->getArgument(0));
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
        self::assertTrue($container->getDefinition(HttpTaskRunner::class)->hasTag('scheduler.runner'));
        self::assertTrue($container->getDefinition(HttpTaskRunner::class)->hasTag('container.preload'));
        self::assertSame(HttpTaskRunner::class, $container->getDefinition(HttpTaskRunner::class)->getTag('container.preload')[0]['class']);

        self::assertTrue($container->hasDefinition(MessengerTaskRunner::class));
        self::assertCount(1, $container->getDefinition(MessengerTaskRunner::class)->getArguments());
        self::assertInstanceOf(Reference::class, $container->getDefinition(MessengerTaskRunner::class)->getArgument(0));
        self::assertTrue($container->getDefinition(MessengerTaskRunner::class)->hasTag('scheduler.runner'));
        self::assertTrue($container->getDefinition(MessengerTaskRunner::class)->hasTag('container.preload'));
        self::assertSame(MessengerTaskRunner::class, $container->getDefinition(MessengerTaskRunner::class)->getTag('container.preload')[0]['class']);

        self::assertTrue($container->hasDefinition(NotificationTaskRunner::class));
        self::assertCount(1, $container->getDefinition(NotificationTaskRunner::class)->getArguments());
        self::assertInstanceOf(Reference::class, $container->getDefinition(NotificationTaskRunner::class)->getArgument(0));
        self::assertTrue($container->getDefinition(NotificationTaskRunner::class)->hasTag('scheduler.runner'));
        self::assertTrue($container->getDefinition(NotificationTaskRunner::class)->hasTag('container.preload'));
        self::assertSame(NotificationTaskRunner::class, $container->getDefinition(NotificationTaskRunner::class)->getTag('container.preload')[0]['class']);

        self::assertTrue($container->hasDefinition(NullTaskRunner::class));
        self::assertTrue($container->getDefinition(NullTaskRunner::class)->hasTag('scheduler.runner'));
        self::assertTrue($container->getDefinition(NullTaskRunner::class)->hasTag('container.preload'));
        self::assertSame(NullTaskRunner::class, $container->getDefinition(NullTaskRunner::class)->getTag('container.preload')[0]['class']);

        self::assertTrue($container->hasDefinition(ChainedTaskRunner::class));
        self::assertCount(1, $container->getDefinition(ChainedTaskRunner::class)->getArguments());
        self::assertInstanceOf(TaggedIteratorArgument::class, $container->getDefinition(ChainedTaskRunner::class)->getArgument(0));
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
        self::assertCount(5, $container->getDefinition(TaskNormalizer::class)->getArguments());
        self::assertInstanceOf(Reference::class, $container->getDefinition(TaskNormalizer::class)->getArgument(0));
        self::assertInstanceOf(Reference::class, $container->getDefinition(TaskNormalizer::class)->getArgument(1));
        self::assertInstanceOf(Reference::class, $container->getDefinition(TaskNormalizer::class)->getArgument(2));
        self::assertInstanceOf(Reference::class, $container->getDefinition(TaskNormalizer::class)->getArgument(3));
        self::assertInstanceOf(Reference::class, $container->getDefinition(TaskNormalizer::class)->getArgument(4));
        self::assertTrue($container->getDefinition(TaskNormalizer::class)->hasTag('serializer.normalizer'));
        self::assertTrue($container->getDefinition(TaskNormalizer::class)->hasTag('container.preload'));
        self::assertSame(TaskNormalizer::class, $container->getDefinition(TaskNormalizer::class)->getTag('container.preload')[0]['class']);

        self::assertTrue($container->hasDefinition(NotificationTaskBagNormalizer::class));
        self::assertCount(1, $container->getDefinition(NotificationTaskBagNormalizer::class)->getArguments());
        self::assertInstanceOf(Reference::class, $container->getDefinition(NotificationTaskBagNormalizer::class)->getArgument(0));
        self::assertTrue($container->getDefinition(NotificationTaskBagNormalizer::class)->hasTag('serializer.normalizer'));
        self::assertTrue($container->getDefinition(NotificationTaskBagNormalizer::class)->hasTag('container.preload'));
        self::assertSame(NotificationTaskBagNormalizer::class, $container->getDefinition(NotificationTaskBagNormalizer::class)->getTag('container.preload')[0]['class']);
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

        self::assertTrue($container->hasDefinition(TaskMessageHandler::class));
        self::assertCount(1, $container->getDefinition(TaskMessageHandler::class)->getArguments());
        self::assertInstanceOf(Reference::class, $container->getDefinition(TaskMessageHandler::class)->getArgument(0));
        self::assertTrue($container->getDefinition(TaskMessageHandler::class)->hasTag('messenger.message_handler'));
        self::assertTrue($container->getDefinition(TaskMessageHandler::class)->hasTag('container.preload'));
        self::assertSame(TaskMessageHandler::class, $container->getDefinition(TaskMessageHandler::class)->getTag('container.preload')[0]['class']);

        self::assertTrue($container->hasDefinition(TaskToYieldMessageHandler::class));
        self::assertCount(1, $container->getDefinition(TaskToYieldMessageHandler::class)->getArguments());
        self::assertInstanceOf(Reference::class, $container->getDefinition(TaskToYieldMessageHandler::class)->getArgument(0));
        self::assertTrue($container->getDefinition(TaskToYieldMessageHandler::class)->hasTag('messenger.message_handler'));
        self::assertTrue($container->getDefinition(TaskToYieldMessageHandler::class)->hasTag('container.preload'));
        self::assertSame(TaskToYieldMessageHandler::class, $container->getDefinition(TaskToYieldMessageHandler::class)->getTag('container.preload')[0]['class']);
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
        self::assertInstanceOf(Reference::class, $container->getDefinition(TaskSubscriber::class)->getArgument(1));
        self::assertInstanceOf(Reference::class, $container->getDefinition(TaskSubscriber::class)->getArgument(2));
        self::assertInstanceOf(Reference::class, $container->getDefinition(TaskSubscriber::class)->getArgument(3));
        self::assertInstanceOf(Reference::class, $container->getDefinition(TaskSubscriber::class)->getArgument(4));
        self::assertSame('/_foo', $container->getDefinition(TaskSubscriber::class)->getArgument(5));
        self::assertTrue($container->getDefinition(TaskSubscriber::class)->hasTag('kernel.event_subscriber'));
        self::assertTrue($container->getDefinition(TaskSubscriber::class)->hasTag('monolog.logger'));
        self::assertSame('scheduler', $container->getDefinition(TaskSubscriber::class)->getTag('monolog.logger')[0]['channel']);
        self::assertTrue($container->getDefinition(TaskSubscriber::class)->hasTag('container.preload'));
        self::assertSame(TaskSubscriber::class, $container->getDefinition(TaskSubscriber::class)->getTag('container.preload')[0]['class']);

        self::assertTrue($container->hasDefinition(TaskExecutionSubscriber::class));
        self::assertCount(1, $container->getDefinition(TaskExecutionSubscriber::class)->getArguments());
        self::assertInstanceOf(Reference::class, $container->getDefinition(TaskExecutionSubscriber::class)->getArgument(0));
        self::assertTrue($container->getDefinition(TaskExecutionSubscriber::class)->hasTag('kernel.event_subscriber'));
        self::assertTrue($container->getDefinition(TaskExecutionSubscriber::class)->hasTag('container.preload'));
        self::assertSame(TaskExecutionSubscriber::class, $container->getDefinition(TaskExecutionSubscriber::class)->getTag('container.preload')[0]['class']);

        self::assertTrue($container->hasDefinition(TaskLoggerSubscriber::class));
        self::assertTrue($container->getDefinition(TaskLoggerSubscriber::class)->hasTag('kernel.event_subscriber'));
        self::assertTrue($container->getDefinition(TaskLoggerSubscriber::class)->hasTag('container.preload'));
        self::assertSame(TaskLoggerSubscriber::class, $container->getDefinition(TaskLoggerSubscriber::class)->getTag('container.preload')[0]['class']);

        self::assertTrue($container->hasDefinition(StopWorkerOnSignalSubscriber::class));
        self::assertTrue($container->getDefinition(StopWorkerOnSignalSubscriber::class)->hasTag('kernel.event_subscriber'));
        self::assertTrue($container->getDefinition(StopWorkerOnSignalSubscriber::class)->hasTag('container.preload'));
        self::assertSame(StopWorkerOnSignalSubscriber::class, $container->getDefinition(StopWorkerOnSignalSubscriber::class)->getTag('container.preload')[0]['class']);

        self::assertTrue($container->hasDefinition(TaskLifecycleSubscriber::class));
        self::assertFalse($container->getDefinition(TaskLifecycleSubscriber::class)->isPublic());
        self::assertInstanceOf(Reference::class, $container->getDefinition(TaskLifecycleSubscriber::class)->getArgument(0));
        self::assertTrue($container->getDefinition(TaskLifecycleSubscriber::class)->hasTag('kernel.event_subscriber'));
        self::assertTrue($container->getDefinition(TaskLifecycleSubscriber::class)->hasTag('container.preload'));
        self::assertSame(TaskLifecycleSubscriber::class, $container->getDefinition(TaskLifecycleSubscriber::class)->getTag('container.preload')[0]['class']);

        self::assertTrue($container->hasDefinition(WorkerLifecycleSubscriber::class));
        self::assertFalse($container->getDefinition(WorkerLifecycleSubscriber::class)->isPublic());
        self::assertCount(1, $container->getDefinition(WorkerLifecycleSubscriber::class)->getArguments());
        self::assertInstanceOf(Reference::class, $container->getDefinition(WorkerLifecycleSubscriber::class)->getArgument(0));
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
        self::assertInstanceOf(TaggedIteratorArgument::class, $container->getDefinition(Worker::class)->getArgument(1));
        self::assertInstanceOf(Reference::class, $container->getDefinition(Worker::class)->getArgument(2));
        self::assertInstanceOf(Reference::class, $container->getDefinition(Worker::class)->getArgument(3));
        self::assertInstanceOf(Reference::class, $container->getDefinition(Worker::class)->getArgument(4));
        self::assertInstanceOf(Reference::class, $container->getDefinition(Worker::class)->getArgument(5));
        self::assertNull($container->getDefinition(Worker::class)->getArgument(6));
        self::assertTrue($container->getDefinition(Worker::class)->hasTag('monolog.logger'));
        self::assertSame('scheduler', $container->getDefinition(Worker::class)->getTag('monolog.logger')[0]['channel']);
        self::assertTrue($container->getDefinition(Worker::class)->hasTag('container.preload'));
        self::assertSame(Worker::class, $container->getDefinition(Worker::class)->getTag('container.preload')[0]['class']);
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
            'lock_store' => 'foo',
        ]);

        self::assertTrue($container->hasDefinition(Worker::class));
        self::assertTrue($container->hasAlias(WorkerInterface::class));
        self::assertCount(7, $container->getDefinition(Worker::class)->getArguments());
        self::assertInstanceOf(Reference::class, $container->getDefinition(Worker::class)->getArgument(0));
        self::assertInstanceOf(TaggedIteratorArgument::class, $container->getDefinition(Worker::class)->getArgument(1));
        self::assertInstanceOf(Reference::class, $container->getDefinition(Worker::class)->getArgument(2));
        self::assertInstanceOf(Reference::class, $container->getDefinition(Worker::class)->getArgument(3));
        self::assertInstanceOf(Reference::class, $container->getDefinition(Worker::class)->getArgument(4));
        self::assertInstanceOf(Reference::class, $container->getDefinition(Worker::class)->getArgument(5));
        self::assertInstanceOf(Reference::class, $container->getDefinition(Worker::class)->getArgument(6));
        self::assertTrue($container->getDefinition(Worker::class)->hasTag('scheduler.worker'));
        self::assertTrue($container->getDefinition(Worker::class)->hasTag('monolog.logger'));
        self::assertSame('scheduler', $container->getDefinition(Worker::class)->getTag('monolog.logger')[0]['channel']);
        self::assertTrue($container->getDefinition(Worker::class)->hasTag('container.preload'));
        self::assertSame(Worker::class, $container->getDefinition(Worker::class)->getTag('container.preload')[0]['class']);
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

        self::assertTrue($container->hasDefinition('scheduler.foo_task'));
        self::assertEquals([
            'name' => 'foo',
            'type' => 'command',
            'command' => 'cache:clear',
            'expression' => '*/5 * * * *',
            'description' => 'A simple cache clear task',
            'options' => [
                'env' => 'test',
            ],
        ], $container->getDefinition('scheduler.foo_task')->getArgument(0));
        self::assertFalse($container->getDefinition('scheduler.foo_task')->isPublic());
        self::assertInstanceOf(Reference::class, $container->getDefinition('scheduler.foo_task')->getFactory()[0]);
        self::assertSame('create', $container->getDefinition('scheduler.foo_task')->getFactory()[1]);
        self::assertTrue($container->getDefinition('scheduler.foo_task')->hasTag('scheduler.task'));
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

        self::assertTrue($container->hasDefinition('scheduler.foo_task'));
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
        ], $container->getDefinition('scheduler.foo_task')->getArgument(0));
        self::assertFalse($container->getDefinition('scheduler.foo_task')->isPublic());
        self::assertInstanceOf(Reference::class, $container->getDefinition('scheduler.foo_task')->getFactory()[0]);
        self::assertSame('create', $container->getDefinition('scheduler.foo_task')->getFactory()[1]);
        self::assertTrue($container->getDefinition('scheduler.foo_task')->hasTag('scheduler.task'));
        self::assertTrue($container->getDefinition(Scheduler::class)->hasMethodCall('schedule'));
        self::assertInstanceOf(Definition::class, $container->getDefinition(Scheduler::class)->getMethodCalls()[0][1][0]);
    }

    public function testDoctrineBridgeIsConfigured(): void
    {
        $extension = new SchedulerBundleExtension();

        $container = new ContainerBuilder();
        $container->register('doctrine', stdClass::class);

        $extension->load([
            'scheduler_bundle' => [
                'path' => '/_foo',
                'timezone' => 'Europe/Paris',
                'transport' => [
                    'dsn' => 'memory://first_in_first_out',
                ],
                'tasks' => [],
                'lock_store' => null,
            ],
        ], $container);

        self::assertTrue($container->hasDefinition(SchedulerTransportDoctrineSchemaSubscriber::class));
        self::assertFalse($container->getDefinition(SchedulerTransportDoctrineSchemaSubscriber::class)->isPublic());
        self::assertCount(1, $container->getDefinition(SchedulerTransportDoctrineSchemaSubscriber::class)->getArguments());
        self::assertInstanceOf(TaggedIteratorArgument::class, $container->getDefinition(SchedulerTransportDoctrineSchemaSubscriber::class)->getArgument(0));
        self::assertTrue($container->getDefinition(SchedulerTransportDoctrineSchemaSubscriber::class)->hasTag('doctrine.event_subscriber'));
        self::assertTrue($container->getDefinition(SchedulerTransportDoctrineSchemaSubscriber::class)->hasTag('container.preload'));
        self::assertSame(SchedulerTransportDoctrineSchemaSubscriber::class, $container->getDefinition(SchedulerTransportDoctrineSchemaSubscriber::class)->getTag('container.preload')[0]['class']);

        self::assertTrue($container->hasDefinition(DoctrineTransportFactory::class));
        self::assertFalse($container->getDefinition(DoctrineTransportFactory::class)->isPublic());
        self::assertCount(1, $container->getDefinition(DoctrineTransportFactory::class)->getArguments());
        self::assertInstanceOf(Reference::class, $container->getDefinition(DoctrineTransportFactory::class)->getArgument(0));
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
        self::assertCount(1, $container->getDefinition(MaxExecutionMiddleware::class)->getArguments());
        self::assertInstanceOf(Reference::class, $container->getDefinition(MaxExecutionMiddleware::class)->getArgument(0));
        self::assertTrue($container->getDefinition(MaxExecutionMiddleware::class)->hasTag('scheduler.worker_middleware'));
        self::assertTrue($container->getDefinition(MaxExecutionMiddleware::class)->hasTag('container.preload'));
        self::assertSame(MaxExecutionMiddleware::class, $container->getDefinition(MaxExecutionMiddleware::class)->getTag('container.preload')[0]['class']);
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
        self::assertCount(1, $container->getDefinition(SchedulerDataCollector::class)->getArguments());
        self::assertInstanceOf(Reference::class, $container->getDefinition(SchedulerDataCollector::class)->getArgument(0));
        self::assertTrue($container->getDefinition(SchedulerDataCollector::class)->hasTag('data_collector'));
        self::assertSame('@Scheduler/Collector/data_collector.html.twig', $container->getDefinition(SchedulerDataCollector::class)->getTag('data_collector')[0]['template']);
        self::assertSame(SchedulerDataCollector::NAME, $container->getDefinition(SchedulerDataCollector::class)->getTag('data_collector')[0]['id']);
        self::assertTrue($container->getDefinition(SchedulerDataCollector::class)->hasTag('container.preload'));
        self::assertSame(SchedulerDataCollector::class, $container->getDefinition(SchedulerDataCollector::class)->getTag('container.preload')[0]['class']);
    }

    private function getContainer(array $configuration = []): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->registerExtension(new SchedulerBundleExtension());
        $container->loadFromExtension('scheduler_bundle', $configuration);

        $container->getCompilerPassConfig()->setOptimizationPasses([new ResolveChildDefinitionsPass()]);
        $container->getCompilerPassConfig()->setRemovingPasses([]);
        $container->getCompilerPassConfig()->setAfterRemovingPasses([]);
        $container->compile();

        return $container;
    }
}