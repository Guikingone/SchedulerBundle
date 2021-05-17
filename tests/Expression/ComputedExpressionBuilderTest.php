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
        $computedExpressionBuilder = new ComputedExpressionBuilder();

        self::assertFalse($computedExpressionBuilder->support('* * * * *'));
        self::assertTrue($computedExpressionBuilder->support('# * * * *'));
    }

    /**
     * @dataProvider provideExpression
     */
    public function testBuilderCanBuild(string $expression): void
    {
        $computedExpressionBuilder = new ComputedExpressionBuilder();

        $finalExpression = $computedExpressionBuilder->build($expression);

        self::assertNotSame($finalExpression->getExpression(), $expression);
    }

    /**
     * @return Generator<array<int, string>>
     */
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
