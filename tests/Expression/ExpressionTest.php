<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Expression;

use PHPUnit\Framework\TestCase;
use SchedulerBundle\Exception\InvalidExpressionException;
use SchedulerBundle\Expression\Expression;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class ExpressionTest extends TestCase
{
    public function testEverySpecificMinutesExpressionCanBeCreated(): void
    {
        $expression = (new Expression())->everySpecificMinutes('*/3');

        self::assertSame('*/3 * * * *', $expression);
    }

    public function testEverySpecificHoursExpressionCanBeCreated(): void
    {
        $expression = (new Expression())->everySpecificHours('10');

        self::assertSame('* 10 * * *', $expression);
    }

    public function testEverySpecificDaysExpressionCanBeCreated(): void
    {
        $expression = (new Expression())->everySpecificDays('10');

        self::assertSame('* * 10 * *', $expression);
    }

    public function testEverySpecificMonthsExpressionCanBeCreated(): void
    {
        $expression = (new Expression())->everySpecificMonths('2');

        self::assertSame('* * * 2 *', $expression);
    }

    public function testEverySpecificDayOfWeekExpressionCanBeCreated(): void
    {
        $expression = (new Expression())->everySpecificDaysOfWeek('2');

        self::assertSame('* * * * 2', $expression);
    }

    public function testEvery5MinutesExpressionCanBeCreated(): void
    {
        $expression = (new Expression())->every5Minutes();

        self::assertSame('*/5 * * * *', $expression);
    }

    public function testEvery10MinutesExpressionCanBeCreated(): void
    {
        $expression = (new Expression())->every10Minutes();

        self::assertSame('*/10 * * * *', $expression);
    }

    public function testEvery15MinutesExpressionCanBeCreated(): void
    {
        $expression = (new Expression())->every15Minutes();

        self::assertSame('*/15 * * * *', $expression);
    }

    public function testEvery20MinutesExpressionCanBeCreated(): void
    {
        $expression = (new Expression())->every20Minutes();

        self::assertSame('*/20 * * * *', $expression);
    }

    public function testEvery25MinutesExpressionCanBeCreated(): void
    {
        $expression = (new Expression())->every25Minutes();

        self::assertSame('*/25 * * * *', $expression);
    }

    public function testEvery30MinutesExpressionCanBeCreated(): void
    {
        $expression = (new Expression())->every30Minutes();

        self::assertSame('*/30 * * * *', $expression);
    }

    public function testEveryHoursExpressionCanBeCreated(): void
    {
        $expression = (new Expression())->everyHours();

        self::assertSame('0 * * * *', $expression);
    }

    public function testEveryDaysExpressionCanBeCreated(): void
    {
        $expression = (new Expression())->everyDays();

        self::assertSame('0 0 * * *', $expression);
    }

    public function testEveryWeeksExpressionCanBeCreated(): void
    {
        $expression = (new Expression())->everyWeeks();

        self::assertSame('0 0 * * 0', $expression);
    }

    public function testEveryMonthsExpressionCanBeCreated(): void
    {
        $expression = (new Expression())->everyMonths();

        self::assertSame('0 0 1 * *', $expression);
    }

    public function testEveryYearsExpressionCanBeCreated(): void
    {
        $expression = (new Expression())->everyYears();

        self::assertSame('0 0 1 1 *', $expression);
    }

    public function testSpecificExpressionCanBeCreated(): void
    {
        $expression = (new Expression())->at('10:20');

        self::assertSame('20 10 * * *', $expression);
    }

    public function testNewExpressionCanBePassed(): void
    {
        $expression = new Expression();
        $expression->setExpression('*/45 * * * *');

        self::assertSame('*/45 * * * *', $expression->getExpression());
        self::assertSame('*/45 * * * *', (string) $expression);
    }

    public function testInvalidMacroCannotBePassed(): void
    {
        $expression = new Expression();

        self::expectException(InvalidExpressionException::class);
        self::expectExceptionMessage('The desired macro "@foo" is not supported!');
        self::expectExceptionCode(0);
        $expression->setExpression('@foo');
    }

    public function testMacroCanBePassed(): void
    {
        $expression = new Expression();
        $expression->setExpression('@reboot');

        self::assertSame('@reboot', $expression->getExpression());
        self::assertSame('@reboot', (string) $expression);
    }
}
