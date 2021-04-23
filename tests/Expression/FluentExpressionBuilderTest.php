<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Expression;

use DateTimeImmutable;
use DateTimeZone;
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
        $fluentExpressionBuilder = new FluentExpressionBuilder();

        self::assertFalse($fluentExpressionBuilder->support('* * * * *'));
        self::assertTrue($fluentExpressionBuilder->support('next monday'));
    }

    /**
     * @dataProvider provideExpression
     */
    public function testBuilderCanBuild(string $expression, string $endExpression): void
    {
        $fluentExpressionBuilder = new FluentExpressionBuilder();
        $finalExpression = $fluentExpressionBuilder->build($expression);

        self::assertSame($finalExpression->getExpression(), $endExpression);
    }

    /**
     * @dataProvider provideExpressionWithTimezone
     */
    public function testBuilderCanBuildWithTimezone(string $expression, string $endExpression, string $timezone): void
    {
        $fluentExpressionBuilder = new FluentExpressionBuilder();
        $finalExpression = $fluentExpressionBuilder->build($expression, $timezone);

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

    public function provideExpressionWithTimezone(): Generator
    {
        yield ['first monday of January 1980 10:00', '0 10 7 1 1', 'UTC'];
        yield ['last monday of July 1970 10:00', '0 10 27 7 1', 'UTC'];
        yield ['first day of January 2008', '0 0 1 1 2', 'UTC'];
        yield ['12/22/78', '0 0 22 12 5', 'UTC'];
        yield ['10/Oct/2000:13:55:36 -0700', '55 20 10 10 2', 'UTC'];

        // Test with a different timezone
        $datetime = new DateTimeImmutable();
        $datetime = $datetime->setTimezone(new DateTimeZone('Europe/Paris'));
        $datetime = $datetime->modify("+1 minute");
        yield [
            $datetime->format(DateTimeImmutable::RFC3339),
            sprintf(
                '%d %s %s %s %s',
                (int) $datetime->format('i'),
                $datetime->format('G'),
                $datetime->format('j'),
                $datetime->format('n'),
                $datetime->format('w')
            ),
            'Europe/Paris',
        ];
    }
}
