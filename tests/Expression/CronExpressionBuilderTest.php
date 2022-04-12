<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Expression;

use PHPUnit\Framework\TestCase;
use SchedulerBundle\Expression\CronExpressionBuilder;
use Throwable;

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

    /**
     * @throws Throwable {@see ExpressionBuilderInterface::build()}
     */
    public function testBuilderCanBuildExpression(): void
    {
        $cronExpressionBuilder = new CronExpressionBuilder();
        $expression = $cronExpressionBuilder->build('* * * * *');

        self::assertSame('* * * * *', $expression->getExpression());
    }
}
