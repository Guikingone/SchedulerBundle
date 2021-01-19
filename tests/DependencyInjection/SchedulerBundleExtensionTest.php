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
use SchedulerBundle\DataCollector\SchedulerDataCollector;
use SchedulerBundle\DependencyInjection\SchedulerBundleExtension;
use SchedulerBundle\EventListener\StopWorkerOnSignalSubscriber;
use SchedulerBundle\EventListener\TaskExecutionSubscriber;
use SchedulerBundle\EventListener\TaskLoggerSubscriber;
use SchedulerBundle\EventListener\TaskSubscriber;
use SchedulerBundle\Expression\ExpressionFactory;
use SchedulerBundle\Messenger\TaskMessageHandler;
use SchedulerBundle\Runner\CallbackTaskRunner;
use SchedulerBundle\Runner\CommandTaskRunner;
use SchedulerBundle\Runner\HttpTaskRunner;
use SchedulerBundle\Runner\MessengerTaskRunner;
use SchedulerBundle\Runner\NotificationTaskRunner;
use SchedulerBundle\Runner\NullTaskRunner;
use SchedulerBundle\Runner\ShellTaskRunner;
use SchedulerBundle\SchedulePolicy\BatchPolicy;
use SchedulerBundle\SchedulePolicy\DeadlinePolicy;
use SchedulerBundle\SchedulePolicy\ExecutionDurationPolicy;
use SchedulerBundle\SchedulePolicy\FirstInFirstOutPolicy;
use SchedulerBundle\SchedulePolicy\FirstInLastOutPolicy;
use SchedulerBundle\SchedulePolicy\IdlePolicy;
use SchedulerBundle\SchedulePolicy\MemoryUsagePolicy;
use SchedulerBundle\SchedulePolicy\NicePolicy;
use SchedulerBundle\SchedulePolicy\RoundRobinPolicy;
use SchedulerBundle\SchedulePolicy\SchedulePolicyOrchestrator;
use SchedulerBundle\SchedulePolicy\SchedulePolicyOrchestratorInterface;
use SchedulerBundle\Scheduler;
use SchedulerBundle\SchedulerInterface;
use SchedulerBundle\Serializer\TaskNormalizer;
use SchedulerBundle\Task\Builder\ChainedBuilder;
use SchedulerBundle\Task\Builder\CommandBuilder;
use SchedulerBundle\Task\Builder\HttpBuilder;
use SchedulerBundle\Task\Builder\NullBuilder;
use SchedulerBundle\Task\Builder\ShellBuilder;
use SchedulerBundle\Task\TaskBuilder;
use SchedulerBundle\Task\TaskBuilderInterface;
use SchedulerBundle\Task\TaskExecutionTracker;
use SchedulerBundle\Task\TaskExecutionTrackerInterface;
use SchedulerBundle\Transport\FailoverTransportFactory;
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
        $extension = new SchedulerBundleExtension();

        $container = new ContainerBuilder();
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

        self::assertTrue($container->hasParameter('scheduler.timezone'));
        self::assertSame('Europe/Paris', $container->getParameter('scheduler.timezone'));
        self::assertTrue($container->hasParameter('scheduler.trigger_path'));
        self::assertSame('/_foo', $container->getParameter('scheduler.trigger_path'));
    }

    public function testTransportFactoriesAreRegistered(): void
    {
        $extension = new SchedulerBundleExtension();

        $container = new ContainerBuilder();
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

        self::assertTrue($container->hasDefinition(TransportFactory::class));
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

        self::assertTrue($container->hasDefinition(FailoverTransportFactory::class));
        self::assertFalse($container->getDefinition(FailoverTransportFactory::class)->isPublic());
        self::assertTrue($container->getDefinition(FailoverTransportFactory::class)->hasTag('scheduler.transport_factory'));
        self::assertTrue($container->getDefinition(FailoverTransportFactory::class)->hasTag('container.preload'));
        self::assertSame(FailoverTransportFactory::class, $container->getDefinition(FailoverTransportFactory::class)->getTag('container.preload')[0]['class']);

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
        $extension = new SchedulerBundleExtension();

        $container = new ContainerBuilder();
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

        self::assertTrue($container->hasDefinition(Scheduler::class));
        self::assertTrue($container->hasAlias(SchedulerInterface::class));
        self::assertSame('Europe/Paris', $container->getDefinition(Scheduler::class)->getArgument(0));
        self::assertInstanceOf(Reference::class, $container->getDefinition(Scheduler::class)->getArgument(1));
        self::assertInstanceOf(Reference::class, $container->getDefinition(Scheduler::class)->getArgument(2));
        self::assertInstanceOf(Reference::class, $container->getDefinition(Scheduler::class)->getArgument(3));
        self::assertFalse($container->getDefinition(Scheduler::class)->isPublic());
        self::assertTrue($container->getDefinition(Scheduler::class)->hasTag('monolog.logger'));
        self::assertSame('scheduler', $container->getDefinition(Scheduler::class)->getTag('monolog.logger')[0]['channel']);
        self::assertTrue($container->getDefinition(Scheduler::class)->hasTag('container.preload'));
        self::assertSame(Scheduler::class, $container->getDefinition(Scheduler::class)->getTag('container.preload')[0]['class']);
    }

    public function testCommandsAreRegistered(): void
    {
        $extension = new SchedulerBundleExtension();

        $container = new ContainerBuilder();
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

        self::assertTrue($container->hasDefinition(ConsumeTasksCommand::class));
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
        self::assertInstanceOf(Reference::class, $container->getDefinition(ListFailedTasksCommand::class)->getArgument(0));
        self::assertTrue($container->getDefinition(ListFailedTasksCommand::class)->hasTag('console.command'));
        self::assertTrue($container->getDefinition(ListFailedTasksCommand::class)->hasTag('container.preload'));
        self::assertSame(ListFailedTasksCommand::class, $container->getDefinition(ListFailedTasksCommand::class)->getTag('container.preload')[0]['class']);

        self::assertTrue($container->hasDefinition(ListTasksCommand::class));
        self::assertInstanceOf(Reference::class, $container->getDefinition(ListTasksCommand::class)->getArgument(0));
        self::assertTrue($container->getDefinition(ListTasksCommand::class)->hasTag('console.command'));
        self::assertTrue($container->getDefinition(ListTasksCommand::class)->hasTag('container.preload'));
        self::assertSame(ListTasksCommand::class, $container->getDefinition(ListTasksCommand::class)->getTag('container.preload')[0]['class']);

        self::assertTrue($container->hasDefinition(RebootSchedulerCommand::class));
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
        self::assertInstanceOf(Reference::class, $container->getDefinition(RemoveFailedTaskCommand::class)->getArgument(0));
        self::assertInstanceOf(Reference::class, $container->getDefinition(RemoveFailedTaskCommand::class)->getArgument(1));
        self::assertTrue($container->getDefinition(RemoveFailedTaskCommand::class)->hasTag('console.command'));
        self::assertTrue($container->getDefinition(RemoveFailedTaskCommand::class)->hasTag('container.preload'));
        self::assertSame(RemoveFailedTaskCommand::class, $container->getDefinition(RemoveFailedTaskCommand::class)->getTag('container.preload')[0]['class']);

        self::assertTrue($container->hasDefinition(RetryFailedTaskCommand::class));
        self::assertInstanceOf(Reference::class, $container->getDefinition(RetryFailedTaskCommand::class)->getArgument(0));
        self::assertInstanceOf(Reference::class, $container->getDefinition(RetryFailedTaskCommand::class)->getArgument(1));
        self::assertInstanceOf(Reference::class, $container->getDefinition(RetryFailedTaskCommand::class)->getArgument(2));
        self::assertTrue($container->getDefinition(RetryFailedTaskCommand::class)->hasTag('console.command'));
        self::assertTrue($container->getDefinition(RetryFailedTaskCommand::class)->hasTag('monolog.logger'));
        self::assertSame('scheduler', $container->getDefinition(RetryFailedTaskCommand::class)->getTag('monolog.logger')[0]['channel']);
        self::assertTrue($container->getDefinition(RetryFailedTaskCommand::class)->hasTag('container.preload'));
        self::assertSame(RetryFailedTaskCommand::class, $container->getDefinition(RetryFailedTaskCommand::class)->getTag('container.preload')[0]['class']);
    }

    public function testExpressionFactoryAndPoliciesAreRegistered(): void
    {
        $extension = new SchedulerBundleExtension();

        $container = new ContainerBuilder();
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

        self::assertTrue($container->hasDefinition(ExpressionFactory::class));

        self::assertTrue($container->hasDefinition(SchedulePolicyOrchestrator::class));
        self::assertTrue($container->hasAlias(SchedulePolicyOrchestratorInterface::class));
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
        $extension = new SchedulerBundleExtension();

        $container = new ContainerBuilder();
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

        self::assertTrue($container->hasDefinition(TaskBuilder::class));
        self::assertTrue($container->hasAlias(TaskBuilderInterface::class));
        self::assertInstanceOf(TaggedIteratorArgument::class, $container->getDefinition(TaskBuilder::class)->getArgument(0));
        self::assertInstanceOf(Reference::class, $container->getDefinition(TaskBuilder::class)->getArgument(1));
        self::assertTrue($container->getDefinition(TaskBuilder::class)->hasTag('container.preload'));
        self::assertSame(TaskBuilder::class, $container->getDefinition(TaskBuilder::class)->getTag('container.preload')[0]['class']);

        self::assertTrue($container->hasDefinition(CommandBuilder::class));
        self::assertTrue($container->getDefinition(CommandBuilder::class)->hasTag('scheduler.task_builder'));
        self::assertTrue($container->getDefinition(CommandBuilder::class)->hasTag('container.preload'));
        self::assertSame(CommandBuilder::class, $container->getDefinition(CommandBuilder::class)->getTag('container.preload')[0]['class']);

        self::assertTrue($container->hasDefinition(HttpBuilder::class));
        self::assertTrue($container->getDefinition(HttpBuilder::class)->hasTag('scheduler.task_builder'));
        self::assertTrue($container->getDefinition(HttpBuilder::class)->hasTag('container.preload'));
        self::assertSame(HttpBuilder::class, $container->getDefinition(HttpBuilder::class)->getTag('container.preload')[0]['class']);

        self::assertTrue($container->hasDefinition(NullBuilder::class));
        self::assertTrue($container->getDefinition(NullBuilder::class)->hasTag('scheduler.task_builder'));
        self::assertTrue($container->getDefinition(NullBuilder::class)->hasTag('container.preload'));
        self::assertSame(NullBuilder::class, $container->getDefinition(NullBuilder::class)->getTag('container.preload')[0]['class']);

        self::assertTrue($container->hasDefinition(ShellBuilder::class));
        self::assertTrue($container->getDefinition(ShellBuilder::class)->hasTag('scheduler.task_builder'));
        self::assertTrue($container->getDefinition(ShellBuilder::class)->hasTag('container.preload'));
        self::assertSame(ShellBuilder::class, $container->getDefinition(ShellBuilder::class)->getTag('container.preload')[0]['class']);

        self::assertTrue($container->hasDefinition(ChainedBuilder::class));
        self::assertInstanceOf(TaggedIteratorArgument::class, $container->getDefinition(ChainedBuilder::class)->getArgument(0));
        self::assertTrue($container->getDefinition(ChainedBuilder::class)->hasTag('scheduler.task_builder'));
        self::assertTrue($container->getDefinition(ChainedBuilder::class)->hasTag('container.preload'));
        self::assertSame(ChainedBuilder::class, $container->getDefinition(ChainedBuilder::class)->getTag('container.preload')[0]['class']);
    }

    public function testRunnersAreRegistered(): void
    {
        $extension = new SchedulerBundleExtension();

        $container = new ContainerBuilder();
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

        self::assertTrue($container->hasDefinition(ShellTaskRunner::class));
        self::assertTrue($container->getDefinition(ShellTaskRunner::class)->hasTag('scheduler.runner'));
        self::assertTrue($container->getDefinition(ShellTaskRunner::class)->hasTag('container.preload'));
        self::assertSame(ShellTaskRunner::class, $container->getDefinition(ShellTaskRunner::class)->getTag('container.preload')[0]['class']);

        self::assertTrue($container->hasDefinition(CommandTaskRunner::class));
        self::assertInstanceOf(Reference::class, $container->getDefinition(CommandTaskRunner::class)->getArgument(0));
        self::assertTrue($container->getDefinition(CommandTaskRunner::class)->hasTag('scheduler.runner'));
        self::assertTrue($container->getDefinition(CommandTaskRunner::class)->hasTag('container.preload'));
        self::assertSame(CommandTaskRunner::class, $container->getDefinition(CommandTaskRunner::class)->getTag('container.preload')[0]['class']);

        self::assertTrue($container->hasDefinition(CallbackTaskRunner::class));
        self::assertTrue($container->getDefinition(CallbackTaskRunner::class)->hasTag('scheduler.runner'));
        self::assertTrue($container->getDefinition(CallbackTaskRunner::class)->hasTag('container.preload'));
        self::assertSame(CallbackTaskRunner::class, $container->getDefinition(CallbackTaskRunner::class)->getTag('container.preload')[0]['class']);

        self::assertTrue($container->hasDefinition(HttpTaskRunner::class));
        self::assertInstanceOf(Reference::class, $container->getDefinition(HttpTaskRunner::class)->getArgument(0));
        self::assertTrue($container->getDefinition(HttpTaskRunner::class)->hasTag('scheduler.runner'));
        self::assertTrue($container->getDefinition(HttpTaskRunner::class)->hasTag('container.preload'));
        self::assertSame(HttpTaskRunner::class, $container->getDefinition(HttpTaskRunner::class)->getTag('container.preload')[0]['class']);

        self::assertTrue($container->hasDefinition(MessengerTaskRunner::class));
        self::assertInstanceOf(Reference::class, $container->getDefinition(MessengerTaskRunner::class)->getArgument(0));
        self::assertTrue($container->getDefinition(MessengerTaskRunner::class)->hasTag('scheduler.runner'));
        self::assertTrue($container->getDefinition(MessengerTaskRunner::class)->hasTag('container.preload'));
        self::assertSame(MessengerTaskRunner::class, $container->getDefinition(MessengerTaskRunner::class)->getTag('container.preload')[0]['class']);

        self::assertTrue($container->hasDefinition(NotificationTaskRunner::class));
        self::assertInstanceOf(Reference::class, $container->getDefinition(NotificationTaskRunner::class)->getArgument(0));
        self::assertTrue($container->getDefinition(NotificationTaskRunner::class)->hasTag('scheduler.runner'));
        self::assertTrue($container->getDefinition(NotificationTaskRunner::class)->hasTag('container.preload'));
        self::assertSame(NotificationTaskRunner::class, $container->getDefinition(NotificationTaskRunner::class)->getTag('container.preload')[0]['class']);

        self::assertTrue($container->hasDefinition(NullTaskRunner::class));
        self::assertTrue($container->getDefinition(NullTaskRunner::class)->hasTag('scheduler.runner'));
        self::assertTrue($container->getDefinition(NullTaskRunner::class)->hasTag('container.preload'));
        self::assertSame(NullTaskRunner::class, $container->getDefinition(NullTaskRunner::class)->getTag('container.preload')[0]['class']);
    }

    public function testNormalizerIsRegistered(): void
    {
        $extension = new SchedulerBundleExtension();

        $container = new ContainerBuilder();
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

        self::assertTrue($container->hasDefinition(TaskNormalizer::class));
        self::assertInstanceOf(Reference::class, $container->getDefinition(TaskNormalizer::class)->getArgument(0));
        self::assertInstanceOf(Reference::class, $container->getDefinition(TaskNormalizer::class)->getArgument(1));
        self::assertInstanceOf(Reference::class, $container->getDefinition(TaskNormalizer::class)->getArgument(2));
        self::assertInstanceOf(Reference::class, $container->getDefinition(TaskNormalizer::class)->getArgument(3));
        self::assertTrue($container->getDefinition(TaskNormalizer::class)->hasTag('serializer.normalizer'));
        self::assertTrue($container->getDefinition(TaskNormalizer::class)->hasTag('container.preload'));
        self::assertSame(TaskNormalizer::class, $container->getDefinition(TaskNormalizer::class)->getTag('container.preload')[0]['class']);
    }

    public function testMessengerToolsAreRegistered(): void
    {
        $extension = new SchedulerBundleExtension();

        $container = new ContainerBuilder();
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

        self::assertTrue($container->hasDefinition(TaskMessageHandler::class));
        self::assertInstanceOf(Reference::class, $container->getDefinition(TaskMessageHandler::class)->getArgument(0));
        self::assertTrue($container->getDefinition(TaskMessageHandler::class)->hasTag('messenger.message_handler'));
        self::assertTrue($container->getDefinition(TaskMessageHandler::class)->hasTag('container.preload'));
        self::assertSame(TaskMessageHandler::class, $container->getDefinition(TaskMessageHandler::class)->getTag('container.preload')[0]['class']);
    }

    public function testSubscribersAreRegistered(): void
    {
        $extension = new SchedulerBundleExtension();

        $container = new ContainerBuilder();
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

        self::assertTrue($container->hasDefinition(TaskSubscriber::class));
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
    }

    public function testTrackerIsRegistered(): void
    {
        $extension = new SchedulerBundleExtension();

        $container = new ContainerBuilder();
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

        self::assertTrue($container->hasDefinition('scheduler.stop_watch'));

        self::assertTrue($container->hasDefinition(TaskExecutionTracker::class));
        self::assertTrue($container->hasAlias(TaskExecutionTrackerInterface::class));
        self::assertInstanceOf(Reference::class, $container->getDefinition(TaskExecutionTracker::class)->getArgument(0));
        self::assertTrue($container->getDefinition(TaskExecutionTracker::class)->hasTag('container.preload'));
        self::assertSame(TaskExecutionTracker::class, $container->getDefinition(TaskExecutionTracker::class)->getTag('container.preload')[0]['class']);
    }

    public function testWorkerIsRegistered(): void
    {
        $extension = new SchedulerBundleExtension();

        $container = new ContainerBuilder();
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

        self::assertTrue($container->hasDefinition(Worker::class));
        self::assertTrue($container->hasAlias(WorkerInterface::class));
        self::assertInstanceOf(Reference::class, $container->getDefinition(Worker::class)->getArgument(0));
        self::assertInstanceOf(TaggedIteratorArgument::class, $container->getDefinition(Worker::class)->getArgument(1));
        self::assertInstanceOf(Reference::class, $container->getDefinition(Worker::class)->getArgument(2));
        self::assertInstanceOf(Reference::class, $container->getDefinition(Worker::class)->getArgument(3));
        self::assertInstanceOf(Reference::class, $container->getDefinition(Worker::class)->getArgument(4));
        self::assertNull($container->getDefinition(Worker::class)->getArgument(5));
        self::assertTrue($container->getDefinition(Worker::class)->hasTag('monolog.logger'));
        self::assertSame('scheduler', $container->getDefinition(Worker::class)->getTag('monolog.logger')[0]['channel']);
        self::assertTrue($container->getDefinition(Worker::class)->hasTag('container.preload'));
        self::assertSame(Worker::class, $container->getDefinition(Worker::class)->getTag('container.preload')[0]['class']);
    }

    public function testWorkerIsRegisteredWithLockStore(): void
    {
        $extension = new SchedulerBundleExtension();

        $container = new ContainerBuilder();
        $extension->load([
            'scheduler_bundle' => [
                'path' => '/_foo',
                'timezone' => 'Europe/Paris',
                'transport' => [
                    'dsn' => 'memory://first_in_first_out',
                ],
                'tasks' => [],
                'lock_store' => 'foo',
            ],
        ], $container);

        self::assertTrue($container->hasDefinition(Worker::class));
        self::assertTrue($container->hasAlias(WorkerInterface::class));
        self::assertInstanceOf(Reference::class, $container->getDefinition(Worker::class)->getArgument(0));
        self::assertInstanceOf(TaggedIteratorArgument::class, $container->getDefinition(Worker::class)->getArgument(1));
        self::assertInstanceOf(Reference::class, $container->getDefinition(Worker::class)->getArgument(2));
        self::assertInstanceOf(Reference::class, $container->getDefinition(Worker::class)->getArgument(3));
        self::assertInstanceOf(Reference::class, $container->getDefinition(Worker::class)->getArgument(4));
        self::assertInstanceOf(Reference::class, $container->getDefinition(Worker::class)->getArgument(5));
        self::assertTrue($container->getDefinition(Worker::class)->hasTag('monolog.logger'));
        self::assertSame('scheduler', $container->getDefinition(Worker::class)->getTag('monolog.logger')[0]['channel']);
        self::assertTrue($container->getDefinition(Worker::class)->hasTag('container.preload'));
        self::assertSame(Worker::class, $container->getDefinition(Worker::class)->getTag('container.preload')[0]['class']);
    }

    public function testTasksAreRegistered(): void
    {
        $extension = new SchedulerBundleExtension();

        $container = new ContainerBuilder();
        $extension->load([
            'scheduler_bundle' => [
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
            ],
        ], $container);

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
            'queued' => false,
            'timezone' => 'UTC',
            'environment_variables' => [],
            'arguments' => [],
            'client_options' => [],
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
        self::assertInstanceOf(TaggedIteratorArgument::class, $container->getDefinition(SchedulerTransportDoctrineSchemaSubscriber::class)->getArgument(0));
        self::assertTrue($container->getDefinition(SchedulerTransportDoctrineSchemaSubscriber::class)->hasTag('doctrine.event_subscriber'));
        self::assertTrue($container->getDefinition(SchedulerTransportDoctrineSchemaSubscriber::class)->hasTag('container.preload'));
        self::assertSame(SchedulerTransportDoctrineSchemaSubscriber::class, $container->getDefinition(SchedulerTransportDoctrineSchemaSubscriber::class)->getTag('container.preload')[0]['class']);

        self::assertTrue($container->hasDefinition(DoctrineTransportFactory::class));
        self::assertFalse($container->getDefinition(DoctrineTransportFactory::class)->isPublic());
        self::assertInstanceOf(Reference::class, $container->getDefinition(DoctrineTransportFactory::class)->getArgument(0));
        self::assertTrue($container->getDefinition(DoctrineTransportFactory::class)->hasTag('scheduler.transport_factory'));
        self::assertTrue($container->getDefinition(DoctrineTransportFactory::class)->hasTag('container.preload'));
        self::assertSame(DoctrineTransportFactory::class, $container->getDefinition(DoctrineTransportFactory::class)->getTag('container.preload')[0]['class']);
    }

    public function testDataCollectorIsConfigured(): void
    {
        $extension = new SchedulerBundleExtension();

        $container = new ContainerBuilder();
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

        self::assertTrue($container->hasDefinition(SchedulerDataCollector::class));
        self::assertFalse($container->getDefinition(SchedulerDataCollector::class)->isPublic());
        self::assertInstanceOf(Reference::class, $container->getDefinition(SchedulerDataCollector::class)->getArgument(0));
        self::assertTrue($container->getDefinition(SchedulerDataCollector::class)->hasTag('data_collector'));
        self::assertSame('@Scheduler/Collector/data_collector.html.twig', $container->getDefinition(SchedulerDataCollector::class)->getTag('data_collector')[0]['template']);
        self::assertSame(SchedulerDataCollector::NAME, $container->getDefinition(SchedulerDataCollector::class)->getTag('data_collector')[0]['id']);
        self::assertTrue($container->getDefinition(SchedulerDataCollector::class)->hasTag('container.preload'));
        self::assertSame(SchedulerDataCollector::class, $container->getDefinition(SchedulerDataCollector::class)->getTag('container.preload')[0]['class']);
    }
}
