<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Expression;

use PHPUnit\Framework\TestCase;
use SchedulerBundle\Expression\FluentExpressionBuilder;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class FluentExpressionBuilderTest extends TestCase
{
    public function testBuilderSupport(): void
    {
        $builder = new FluentExpressionBuilder();

        self::assertFalse($builder->support('* * * * *'));
        self::assertTrue($builder->support('next monday'));
    }
}
