<?php

declare(strict_types=1);

namespace SchedulerBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class SchedulerPass implements CompilerPassInterface
{
    private string $schedulerExtraTag;

    public function __construct(string $schedulerExtraTag = 'scheduler.extra')
    {
        $this->schedulerExtraTag = $schedulerExtraTag;
    }

    /**
     * {@inheritdoc}
     */
    public function process(ContainerBuilder $container): void
    {
        $this->registerExtra($container);
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
}
