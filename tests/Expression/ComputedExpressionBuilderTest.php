<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Expression;

use Generator;
use PHPUnit\Framework\TestCase;
use SchedulerBundle\Expression\ComputedExpressionBuilder;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class ComputedExpressionBuilderTest extends TestCase
{
    public function testBuilderSupport(): void
    {
        $builder = new ComputedExpressionBuilder();

        self::assertFalse($builder->support('* * * * *'));
        self::assertTrue($builder->support('# * * * *'));
    }

    /**
     * @dataProvider provideExpression
     */
    public function testBuilderCanBuild(string $expression): void
    {
        $builder = new ComputedExpressionBuilder();

        $finalExpression = $builder->build($expression);

        self::assertNotSame($finalExpression->getExpression(), $expression);
    }

    public function provideExpression(): Generator
    {
        yield ['# * * * *'];
        yield ['* # * * *'];
        yield ['* * # * *'];
        yield ['* * * # *'];
        yield ['* * * * #'];
        yield ['# * * * #'];
        yield ['# # * * *'];
        yield ['* # # * *'];
        yield ['* * # # *'];
        yield ['* * * # #'];
    }
}
