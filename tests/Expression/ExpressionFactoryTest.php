<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Expression;

use PHPUnit\Framework\TestCase;
use SchedulerBundle\Expression\ExpressionFactory;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class ExpressionFactoryTest extends TestCase
{
    public function testEverySpecificMinutesExpressionCanBeCreated(): void
    {
        $expression = (new ExpressionFactory())->everySpecificMinutes('*/3');

        self::assertSame('*/3 * * * *', $expression);
    }

    public function testEverySpecificHoursExpressionCanBeCreated(): void
    {
        $expression = (new ExpressionFactory())->everySpecificHours('10');

        self::assertSame('* 10 * * *', $expression);
    }

    public function testEverySpecificDaysExpressionCanBeCreated(): void
    {
        $expression = (new ExpressionFactory())->everySpecificDays('10');

        self::assertSame('* * 10 * *', $expression);
    }

    public function testEverySpecificMonthsExpressionCanBeCreated(): void
    {
        $expression = (new ExpressionFactory())->everySpecificMonths('2');

        self::assertSame('* * * 2 *', $expression);
    }

    public function testEverySpecificDayOfWeekExpressionCanBeCreated(): void
    {
        $expression = (new ExpressionFactory())->everySpecificDaysOfWeek('2');

        self::assertSame('* * * * 2', $expression);
    }

    public function testEvery5MinutesExpressionCanBeCreated(): void
    {
        $expression = (new ExpressionFactory())->every5Minutes();

        self::assertSame('*/5 * * * *', $expression);
    }

    public function testEvery10MinutesExpressionCanBeCreated(): void
    {
        $expression = (new ExpressionFactory())->every10Minutes();

        self::assertSame('*/10 * * * *', $expression);
    }

    public function testEvery15MinutesExpressionCanBeCreated(): void
    {
        $expression = (new ExpressionFactory())->every15Minutes();

        self::assertSame('*/15 * * * *', $expression);
    }

    public function testEvery20MinutesExpressionCanBeCreated(): void
    {
        $expression = (new ExpressionFactory())->every20Minutes();

        self::assertSame('*/20 * * * *', $expression);
    }

    public function testEvery25MinutesExpressionCanBeCreated(): void
    {
        $expression = (new ExpressionFactory())->every25Minutes();

        self::assertSame('*/25 * * * *', $expression);
    }

    public function testEvery30MinutesExpressionCanBeCreated(): void
    {
        $expression = (new ExpressionFactory())->every30Minutes();

        self::assertSame('*/30 * * * *', $expression);
    }

    public function testEveryHoursExpressionCanBeCreated(): void
    {
        $expression = (new ExpressionFactory())->everyHours();

        self::assertSame('0 * * * *', $expression);
    }

    public function testEveryDaysExpressionCanBeCreated(): void
    {
        $expression = (new ExpressionFactory())->everyDays();

        self::assertSame('0 0 * * *', $expression);
    }

    public function testEveryWeeksExpressionCanBeCreated(): void
    {
        $expression = (new ExpressionFactory())->everyWeeks();

        self::assertSame('0 0 * * 0', $expression);
    }

    public function testEveryMonthsExpressionCanBeCreated(): void
    {
        $expression = (new ExpressionFactory())->everyMonths();

        self::assertSame('0 0 1 * *', $expression);
    }

    public function testEveryYearsExpressionCanBeCreated(): void
    {
        $expression = (new ExpressionFactory())->everyYears();

        self::assertSame('0 0 1 1 *', $expression);
    }

    public function testSpecificExpressionCanBeCreated(): void
    {
        $expression = (new ExpressionFactory())->at('10:20');

        self::assertSame('20 10 * * *', $expression);
    }

    public function testNewExpressionCanBePassed(): void
    {
        $factory = new ExpressionFactory();
        $factory->setExpression('*/45 * * * *');

        self::assertSame('*/45 * * * *', $factory->getExpression());
        self::assertSame('*/45 * * * *', (string) $factory);
    }

    public function testMacroCanBePassed(): void
    {
        $factory = new ExpressionFactory();
        $factory->setExpression('@reboot');

        self::assertSame('@reboot', $factory->getExpression());
        self::assertSame('@reboot', (string) $factory);
    }
}
