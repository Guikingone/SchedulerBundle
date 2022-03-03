<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Bridge\Kairoi\Transport;

use PHPUnit\Framework\TestCase;
use SchedulerBundle\Bridge\Kairoi\Transport\KairoiTransportFactory;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class KairoiTransportFactoryTest extends TestCase
{
    public function testTransportCanSupport(): void
    {
        self::assertFalse((new KairoiTransportFactory())->support('test://'));
        self::assertTrue((new KairoiTransportFactory())->support('kairoi://'));
    }
}
