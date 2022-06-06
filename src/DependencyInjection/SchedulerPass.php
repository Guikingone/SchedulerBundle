<?php

declare(strict_types=1);

namespace SchedulerBundle\DependencyInjection;

use SchedulerBundle\SchedulerInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
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
        $this->registerExtra(container: $container);
        $this->registerSchedulerEntrypoint(container: $container);
    }

    private function registerExtra(ContainerBuilder $container): void
    {
        foreach ($container->findTaggedServiceIds(name: $this->schedulerExtraTag) as $service => $args) {
            if (!$container->hasDefinition(id: $args[0]['require'])) {
                $container->removeDefinition(id: $service);

                continue;
            }

            $container->getDefinition(id: $service)->addTag(name: $args[0]['tag']);
        }
    }

    private function registerSchedulerEntrypoint(ContainerBuilder $container): void
    {
        foreach (array_keys(array: $container->findTaggedServiceIds(name: $this->schedulerEntryPointTag)) as $service) {
            $container->getDefinition(id: $service)->addMethodCall(method: 'schedule', arguments: [
                new Reference(id: SchedulerInterface::class, invalidBehavior: ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE),
            ]);
        }
    }
}
