<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\TaskBag;

use PHPUnit\Framework\TestCase;
use SchedulerBundle\TaskBag\AccessLockBag;
use Symfony\Component\Lock\Key;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class AccessLockBagTest extends TestCase
{
    public function testBagCanReturnKey(): void
    {
        $bag = new AccessLockBag(new Key('foo'));

        self::assertInstanceOf(Key::class, $bag->getKey());
    }
}
