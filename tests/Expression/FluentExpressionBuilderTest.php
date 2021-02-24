<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Expression;

use Generator;
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

    /**
     * @dataProvider provideExpression
     */
    public function testBuilderCanBuild(string $expression, string $endExpression): void
    {
        $builder = new FluentExpressionBuilder();
        $finalExpression = $builder->build($expression);

        self::assertSame($finalExpression->getExpression(), $endExpression);
    }

    public function provideExpression(): Generator
    {
        yield ['first monday of January 1980 10:00', '0 10 7 1 1'];
        yield ['last monday of July 1970 10:00', '0 10 27 7 1'];
        yield ['first day of January 2008', '0 0 1 1 2'];
        yield ['12/22/78', '0 0 22 12 5'];
        yield ['10/Oct/2000:13:55:36 -0700', '55 20 10 10 2'];
    }
}
