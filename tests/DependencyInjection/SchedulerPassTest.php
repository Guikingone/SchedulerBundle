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
}
