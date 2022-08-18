<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\DependencyInjection;

use PHPUnit\Framework\TestCase;
use SchedulerBundle\DependencyInjection\SchedulerPass;
use SchedulerBundle\SchedulerInterface;
use SchedulerBundle\Task\TaskInterface;
use stdClass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Reference;
use Tests\SchedulerBundle\DependencyInjection\Assets\SchedulerEntryPoint;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class SchedulerPassTest extends TestCase
{
    public function testSchedulerExtraCannotBeRegisteredWithoutDependency(): void
    {
        $containerBuilder = new ContainerBuilder();
        $containerBuilder->register('scheduler.foo_task', TaskInterface::class)->addTag('scheduler.extra', [
            'require' => 'scheduler.task_builder',
            'tag' => 'scheduler.tag',
        ]);

        (new SchedulerPass())->process($containerBuilder);

        self::assertFalse($containerBuilder->hasDefinition('scheduler.foo_task'));
    }

    public function testSchedulerExtraCanBeRegisteredWithDependency(): void
    {
        $containerBuilder = new ContainerBuilder();
        $containerBuilder->register('scheduler.task_builder', stdClass::class);
        $containerBuilder->register('scheduler.bar_task', TaskInterface::class)->addTag('scheduler.extra', [
            'require' => 'http_client',
            'tag' => 'scheduler.tag',
        ]);
        $containerBuilder->register('scheduler.foo_task', TaskInterface::class)->addTag('scheduler.extra', [
            'require' => 'scheduler.task_builder',
            'tag' => 'scheduler.tag',
        ]);

        (new SchedulerPass())->process($containerBuilder);

        self::assertTrue($containerBuilder->hasDefinition('scheduler.foo_task'));
        self::assertTrue($containerBuilder->getDefinition('scheduler.foo_task')->hasTag('scheduler.tag'));
        self::assertFalse($containerBuilder->hasDefinition('scheduler.bar_task'));
    }

    public function testSchedulerEntryPointCanBeRegistered(): void
    {
        $containerBuilder = new ContainerBuilder();
        $containerBuilder->register(SchedulerEntryPoint::class, SchedulerEntryPoint::class)
            ->addTag('scheduler.entry_point')
        ;

        (new SchedulerPass())->process($containerBuilder);

        self::assertTrue($containerBuilder->hasDefinition(SchedulerEntryPoint::class));
        self::assertCount(1, $containerBuilder->getDefinition(SchedulerEntryPoint::class)->getMethodCalls());
        self::assertSame('schedule', $containerBuilder->getDefinition(SchedulerEntryPoint::class)->getMethodCalls()[0][0]);
        self::assertInstanceOf(Reference::class, $containerBuilder->getDefinition(SchedulerEntryPoint::class)->getMethodCalls()[0][1][0]);
        self::assertSame(SchedulerInterface::class, (string) $containerBuilder->getDefinition(SchedulerEntryPoint::class)->getMethodCalls()[0][1][0]);
        self::assertSame(ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $containerBuilder->getDefinition(SchedulerEntryPoint::class)->getMethodCalls()[0][1][0]->getInvalidBehavior());
    }
}
