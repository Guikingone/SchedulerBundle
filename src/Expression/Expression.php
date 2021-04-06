<?php

declare(strict_types=1);

namespace SchedulerBundle\Expression;

use SchedulerBundle\Exception\InvalidArgumentException;
use SchedulerBundle\Exception\InvalidExpressionException;
use function array_key_exists;
use function count;
use function explode;
use function implode;
use function sprintf;
use function strpos;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class Expression
{
    /**
     * @var string
     */
    public const ANNUALLY_MACRO = '@annually';

    /**
     * @var string
     */
    public const YEARLY_MACRO = '@yearly';

    /**
     * @var string
     */
    public const REBOOT_MACRO = '@reboot';

    /**
     * @var string
     */
    public const DAILY_MACRO = '@daily';

    /**
     * @var string
     */
    public const WEEKLY_MACRO = '@weekly';

    /**
     * @var string
     */
    public const MONTHLY_MACRO = '@monthly';

    private const ALLOWED_MACROS = [
        self::ANNUALLY_MACRO => '0 0 1 1 *',
        self::YEARLY_MACRO => '0 0 1 1 *',
        self::DAILY_MACRO => '0 0 * * *',
        self::WEEKLY_MACRO => '0 0 * * 0',
        self::MONTHLY_MACRO => '0 0 1 * *',
        self::REBOOT_MACRO => 'reboot',
    ];

    private string $expression = '* * * * *';

    public function __toString(): string
    {
        return $this->expression;
    }

    public static function createFromString(string $expression): self
    {
        $self = new self();
        $self->setExpression($expression);

        return $self;
    }

    public function setExpression(string $expression): void
    {
        if (0 === strpos($expression, '@')) {
            $this->setMacro($expression);

            return;
        }

        $this->expression = $expression;
    }

    public function getExpression(): string
    {
        return $this->expression;
    }

    public function everySpecificMinutes(string $minutes): string
    {
        return $this->changeExpression(0, $minutes);
    }

    public function everySpecificHours(string $hours): string
    {
        return $this->changeExpression(1, $hours);
    }

    public function everySpecificDays(string $days): string
    {
        return $this->changeExpression(2, $days);
    }

    public function everySpecificDaysOfWeek(string $days): string
    {
        return $this->changeExpression(4, $days);
    }

    public function everySpecificMonths(string $months): string
    {
        return $this->changeExpression(3, $months);
    }

    public function every5Minutes(): string
    {
        return $this->changeExpression(0, '*/5');
    }

    public function every10Minutes(): string
    {
        return $this->changeExpression(0, '*/10');
    }

    public function every15Minutes(): string
    {
        return $this->changeExpression(0, '*/15');
    }

    public function every20Minutes(): string
    {
        return $this->changeExpression(0, '*/20');
    }

    public function every25Minutes(): string
    {
        return $this->changeExpression(0, '*/25');
    }

    public function every30Minutes(): string
    {
        return $this->changeExpression(0, '*/30');
    }

    public function everyHours(): string
    {
        return $this->changeExpression(0, '0');
    }

    public function everyDays(): string
    {
        $this->setExpression(self::ALLOWED_MACROS[self::DAILY_MACRO]);

        return $this->expression;
    }

    public function everyWeeks(): string
    {
        $this->setExpression(self::ALLOWED_MACROS[self::WEEKLY_MACRO]);

        return $this->expression;
    }

    public function everyMonths(): string
    {
        $this->setExpression(self::ALLOWED_MACROS[self::MONTHLY_MACRO]);

        return $this->expression;
    }

    public function everyYears(): string
    {
        $this->setExpression(self::ALLOWED_MACROS[self::YEARLY_MACRO]);

        return $this->expression;
    }

    public function at(string $time): string
    {
        $fields = explode(':', $time);

        $this->changeExpression(0, 2 === count($fields) ? $fields[1] : '0');
        $this->changeExpression(1, $fields[0]);

        return $this->expression;
    }

    private function setMacro(string $macro): string
    {
        if (!array_key_exists($macro, self::ALLOWED_MACROS)) {
            throw new InvalidExpressionException(sprintf('The desired macro "%s" is not supported!', $macro));
        }

        $this->expression = $macro;

        return $this->expression;
    }

    /**
     * @param int    $position A valid position (refer to cron syntax if needed)
     * @param string $value    A valid value (typed to string to prevent type changes)
     *
     * @return string The updated expression
     */
    private function changeExpression(int $position, string $value): string
    {
        $fields = explode(' ', $this->expression);
        if (!array_key_exists($position, $fields)) {
            throw new InvalidArgumentException('The desired position is not valid');
        }

        $fields[$position] = $value;

        return $this->expression = implode(' ', $fields);
    }
}
