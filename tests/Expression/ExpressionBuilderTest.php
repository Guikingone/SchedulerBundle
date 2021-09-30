<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Expression;

use PHPUnit\Framework\TestCase;
use SchedulerBundle\Exception\InvalidArgumentException;
use SchedulerBundle\Exception\RuntimeException;
use SchedulerBundle\Expression\Expression;
use SchedulerBundle\Expression\ExpressionBuilder;
use SchedulerBundle\Expression\ExpressionBuilderInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class ExpressionBuilderTest extends TestCase
{
    public function testBuilderCannotBuildExpressionWithoutBuilders(): void
    {
        $expressionBuilder = new ExpressionBuilder([]);

        self::expectException(RuntimeException::class);
        self::expectExceptionMessage('No builder found');
        self::expectExceptionCode(0);
        $expressionBuilder->build('* * * * *');
    }

    public function testBuilderCannotBuildExpressionWithoutSupportingBuilder(): void
    {
        $builder = $this->createMock(ExpressionBuilderInterface::class);
        $builder->expects(self::once())->method('support')->willReturn(false);

        $secondBuilder = $this->createMock(ExpressionBuilderInterface::class);
        $secondBuilder->expects(self::once())->method('support')->willReturn(false);

        $builder = new ExpressionBuilder([
            $builder,
            $secondBuilder,
        ]);

        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage('The expression cannot be used');
        self::expectExceptionCode(0);
        $builder->build('* * * * *');
    }

    public function testBuilderCanBuildExpression(): void
    {
        $builder = $this->createMock(ExpressionBuilderInterface::class);
        $builder->expects(self::once())->method('support')->willReturn(true);
        $builder->expects(self::once())->method('build')
            ->with(self::equalTo('* * * * *'), self::equalTo('UTC'))
            ->willReturn(Expression::createFromString('* * * * *'))
        ;

        $secondBuilder = $this->createMock(ExpressionBuilderInterface::class);
        $secondBuilder->expects(self::once())->method('support')->willReturn(false);

        $builder = new ExpressionBuilder([
            $secondBuilder,
            $builder,
        ]);

        $expression = $builder->build('* * * * *');

        self::assertSame('* * * * *', $expression->getExpression());
    }

    public function testBuilderCanBuildExpressionWithTimezone(): void
    {
        $builder = $this->createMock(ExpressionBuilderInterface::class);
        $builder->expects(self::once())->method('support')->willReturn(true);
        $builder->expects(self::once())->method('build')
            ->with(self::equalTo('* * * * *'), self::equalTo('Europe/Paris'))
            ->willReturn(Expression::createFromString('* * * * *'))
        ;

        $secondBuilder = $this->createMock(ExpressionBuilderInterface::class);
        $secondBuilder->expects(self::once())->method('support')->willReturn(false);

        $builder = new ExpressionBuilder([
            $secondBuilder,
            $builder,
        ]);

        $expression = $builder->build('* * * * *', 'Europe/Paris');

        self::assertSame('* * * * *', $expression->getExpression());
    }
}
