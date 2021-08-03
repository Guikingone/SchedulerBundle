<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\TaskBag;

use PHPUnit\Framework\TestCase;
use SchedulerBundle\TaskBag\ExecutionLockBag;
use Symfony\Component\Lock\Key;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class LockTaskBagTest extends TestCase
{
    public function testBagContains(): void
    {
        $bag = new ExecutionLockBag(new Key('foo'));

        self::assertInstanceOf(Key::class, $bag->getLock());
    }
}
