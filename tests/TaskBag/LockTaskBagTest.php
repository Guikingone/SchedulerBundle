<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\TaskBag;

use PHPUnit\Framework\TestCase;
use SchedulerBundle\TaskBag\LockTaskBag;
use Symfony\Component\Lock\Key;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class LockTaskBagTest extends TestCase
{
    public function testBagContains(): void
    {
        $bag = new LockTaskBag(new Key('foo'));

        self::assertInstanceOf(Key::class, $bag->getKey());
    }
}
