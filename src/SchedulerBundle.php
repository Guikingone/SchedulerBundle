<?php

declare(strict_types=1);

namespace SchedulerBundle;

use SchedulerBundle\DependencyInjection\SchedulerBundleExtension;
use SchedulerBundle\DependencyInjection\SchedulerPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class SchedulerBundle extends Bundle
{
    /**
     * {@inheritdoc}
     */
    public function getContainerExtension(): SchedulerBundleExtension
    {
        return new SchedulerBundleExtension();
    }

    /**
     * {@inheritdoc}
     */
    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new SchedulerPass());
    }
}
