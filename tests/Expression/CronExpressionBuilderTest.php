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
        $builder = new CronExpressionBuilder();

        self::assertFalse($builder->support('next monday'));
        self::assertTrue($builder->support('* * * * *'));
    }

    public function testBuilderCanBuildExpression(): void
    {
        $builder = new CronExpressionBuilder();

        $expression = $builder->build('* * * * *');

        self::assertSame('* * * * *', $expression->getExpression());
    }
}
