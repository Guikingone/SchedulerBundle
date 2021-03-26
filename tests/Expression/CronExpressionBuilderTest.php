<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Expression;

use PHPUnit\Framework\TestCase;
use SchedulerBundle\Expression\CronExpressionBuilder;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class CronExpressionBuilderTest extends TestCase
{
    public function testBuilderSupport(): void
    {
        $cronExpressionBuilder = new CronExpressionBuilder();

        self::assertFalse($cronExpressionBuilder->support('next monday'));
        self::assertTrue($cronExpressionBuilder->support('* * * * *'));
    }

    public function testBuilderCanBuildExpression(): void
    {
        $cronExpressionBuilder = new CronExpressionBuilder();
        $expression = $cronExpressionBuilder->build('* * * * *');

        self::assertSame('* * * * *', $expression->getExpression());
    }
}
