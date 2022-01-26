<?php

declare(strict_types=1);

namespace SchedulerBundle\DependencyInjection;

use SchedulerBundle\SchedulerInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use function array_keys;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class SchedulerPass implements CompilerPassInterface
{
    public function __construct(
        private string $schedulerExtraTag = 'scheduler.extra',
        private string $schedulerEntryPointTag = 'scheduler.entry_point'
    ) {
    }

    /**
     * {@inheritdoc}
     */
    public function process(ContainerBuilder $container): void
    {
        $this->registerExtra($container);
        $this->registerSchedulerEntrypoint($container);
    }

    private function registerExtra(ContainerBuilder $container): void
    {
        foreach ($container->findTaggedServiceIds($this->schedulerExtraTag) as $service => $args) {
            if (!$container->hasDefinition($args[0]['require'])) {
                $container->removeDefinition($service);

                continue;
            }

            $container->getDefinition($service)->addTag($args[0]['tag']);
        }
    }

    private function registerSchedulerEntrypoint(ContainerBuilder $container): void
    {
        foreach (array_keys($container->findTaggedServiceIds($this->schedulerEntryPointTag)) as $service) {
            $container->getDefinition($service)->addMethodCall('schedule', [
                new Reference(SchedulerInterface::class),
            ]);
        }
    }
}
