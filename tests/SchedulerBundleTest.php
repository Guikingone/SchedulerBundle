<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle;

use PHPUnit\Framework\TestCase;
use SchedulerBundle\DependencyInjection\SchedulerBundleExtension;
use SchedulerBundle\SchedulerBundle;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class SchedulerBundleTest extends TestCase
{
    public function testExtensionIsReturned(): void
    {
        $bundle = new SchedulerBundle();

        self::assertInstanceOf(SchedulerBundleExtension::class, $bundle->getContainerExtension());
    }

    public function testCompilerPassIsConfigured(): void
    {
        $container = $this->createMock(ContainerBuilder::class);
        $container->expects(self::once())->method('addCompilerPass');

        $bundle = new SchedulerBundle();
        $bundle->build($container);
    }
}
