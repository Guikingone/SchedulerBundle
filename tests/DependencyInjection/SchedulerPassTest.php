<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\DependencyInjection;

use stdClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use SchedulerBundle\DependencyInjection\SchedulerPass;
use SchedulerBundle\Task\TaskInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class SchedulerPassTest extends TestCase
{
    public function testSchedulerExtraCannotBeRegisteredWithoutDependency(): void
    {
        $container = new ContainerBuilder();
        $container->register('scheduler.foo_task', TaskInterface::class)->addTag('scheduler.extra', [
            'require' => 'scheduler.task_builder',
            'tag' => 'scheduler.tag',
        ]);

        (new SchedulerPass())->process($container);

        self::assertFalse($container->hasDefinition('scheduler.foo_task'));
    }

    public function testSchedulerExtraCanBeRegisteredWithDependency(): void
    {
        $container = new ContainerBuilder();
        $container->register('scheduler.task_builder', stdClass::class);
        $container->register('scheduler.bar_task', TaskInterface::class)->addTag('scheduler.extra', [
            'require' => 'http_client',
            'tag' => 'scheduler.tag',
        ]);
        $container->register('scheduler.foo_task', TaskInterface::class)->addTag('scheduler.extra', [
            'require' => 'scheduler.task_builder',
            'tag' => 'scheduler.tag',
        ]);

        (new SchedulerPass())->process($container);

        self::assertTrue($container->hasDefinition('scheduler.foo_task'));
        self::assertTrue($container->getDefinition('scheduler.foo_task')->hasTag('scheduler.tag'));
        self::assertFalse($container->hasDefinition('scheduler.bar_task'));
    }
}
