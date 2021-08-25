<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Expression;

use Generator;
use PHPUnit\Framework\TestCase;
use SchedulerBundle\Expression\ComputedExpressionBuilder;
use function explode;

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

    public function testBuilderCanHandleMinutes(): void
    {
        $builder = new ComputedExpressionBuilder();

        $expression = $builder->build('# * * * *');
        $explodedExpression = explode(' ', $expression->getExpression());

        self::assertGreaterThanOrEqual(0, $explodedExpression[0]);
        self::assertLessThanOrEqual(59, $explodedExpression[0]);
        self::assertSame('*', $explodedExpression[1]);
        self::assertSame('*', $explodedExpression[2]);
        self::assertSame('*', $explodedExpression[3]);
        self::assertSame('*', $explodedExpression[4]);
    }

    public function testBuilderCanHandleHours(): void
    {
        $builder = new ComputedExpressionBuilder();

        $expression = $builder->build('* # * * *');
        $explodedExpression = explode(' ', $expression->getExpression());

        self::assertSame('*', $explodedExpression[0]);
        self::assertGreaterThanOrEqual(0, $explodedExpression[1]);
        self::assertLessThanOrEqual(23, $explodedExpression[1]);
        self::assertSame('*', $explodedExpression[2]);
        self::assertSame('*', $explodedExpression[3]);
        self::assertSame('*', $explodedExpression[4]);
    }

    public function testBuilderCanHandleDays(): void
    {
        $builder = new ComputedExpressionBuilder();

        $expression = $builder->build('* * # * *');
        $explodedExpression = explode(' ', $expression->getExpression());

        self::assertSame('*', $explodedExpression[0]);
        self::assertSame('*', $explodedExpression[1]);
        self::assertGreaterThanOrEqual(1, $explodedExpression[2]);
        self::assertLessThanOrEqual(31, $explodedExpression[2]);
        self::assertSame('*', $explodedExpression[3]);
        self::assertSame('*', $explodedExpression[4]);
    }

    public function testBuilderCanHandleMonths(): void
    {
        $builder = new ComputedExpressionBuilder();

        $expression = $builder->build('* * * # *');
        $explodedExpression = explode(' ', $expression->getExpression());

        self::assertSame('*', $explodedExpression[0]);
        self::assertSame('*', $explodedExpression[1]);
        self::assertSame('*', $explodedExpression[2]);
        self::assertGreaterThanOrEqual(1, $explodedExpression[3]);
        self::assertLessThanOrEqual(12, $explodedExpression[3]);
        self::assertSame('*', $explodedExpression[4]);
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
