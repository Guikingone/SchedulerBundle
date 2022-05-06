<?php

declare(strict_types=1);

namespace SchedulerBundle\Expression;

use Stringable;
use SchedulerBundle\Exception\InvalidArgumentException;
use SchedulerBundle\Exception\InvalidExpressionException;
use function array_key_exists;
use function count;
use function explode;
use function implode;
use function sprintf;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class Expression implements Stringable
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

    public const ALLOWED_MACROS = [
        self::ANNUALLY_MACRO => '0 0 1 1 *',
        self::YEARLY_MACRO => '0 0 1 1 *',
        self::DAILY_MACRO => '0 0 * * *',
        self::WEEKLY_MACRO => '0 0 * * 0',
        self::MONTHLY_MACRO => '0 0 1 * *',
        self::REBOOT_MACRO => '@reboot',
    ];

    private string $expression = '* * * * *';

    public function __toString(): string
    {
        return $this->expression;
    }

    public static function createFromString(string $expression): self
    {
        $self = new self();
        $self->setExpression(expression: $expression);

        return $self;
    }

    public function setExpression(string $expression): void
    {
        if (str_starts_with(haystack: $expression, needle: '@')) {
            $this->setMacro(macro: $expression);

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
        return $this->changeExpression(position: 0, value: $minutes);
    }

    public function everySpecificHours(string $hours): string
    {
        return $this->changeExpression(position: 1, value: $hours);
    }

    public function everySpecificDays(string $days): string
    {
        return $this->changeExpression(position: 2, value: $days);
    }

    public function everySpecificDaysOfWeek(string $days): string
    {
        return $this->changeExpression(position: 4, value: $days);
    }

    public function everySpecificMonths(string $months): string
    {
        return $this->changeExpression(position: 3, value: $months);
    }

    public function every5Minutes(): string
    {
        return $this->changeExpression(position: 0, value: '*/5');
    }

    public function every10Minutes(): string
    {
        return $this->changeExpression(position: 0, value: '*/10');
    }

    public function every15Minutes(): string
    {
        return $this->changeExpression(position: 0, value: '*/15');
    }

    public function every20Minutes(): string
    {
        return $this->changeExpression(position: 0, value: '*/20');
    }

    public function every25Minutes(): string
    {
        return $this->changeExpression(position: 0, value: '*/25');
    }

    public function every30Minutes(): string
    {
        return $this->changeExpression(position: 0, value: '*/30');
    }

    public function everyHours(): string
    {
        return $this->changeExpression(position: 0, value: '0');
    }

    public function everyDays(): string
    {
        $this->setExpression(expression: self::ALLOWED_MACROS[self::DAILY_MACRO]);

        return $this->expression;
    }

    public function everyWeeks(): string
    {
        $this->setExpression(expression: self::ALLOWED_MACROS[self::WEEKLY_MACRO]);

        return $this->expression;
    }

    public function everyMonths(): string
    {
        $this->setExpression(expression: self::ALLOWED_MACROS[self::MONTHLY_MACRO]);

        return $this->expression;
    }

    public function everyYears(): string
    {
        $this->setExpression(expression: self::ALLOWED_MACROS[self::YEARLY_MACRO]);

        return $this->expression;
    }

    public function at(string $time): string
    {
        $fields = explode(separator: ':', string: $time);

        $this->changeExpression(position: 0, value: 2 === count(value: $fields) ? $fields[1] : '0');
        $this->changeExpression(position: 1, value: $fields[0]);

        return $this->expression;
    }

    private function setMacro(string $macro): string
    {
        if (!array_key_exists(key: $macro, array: self::ALLOWED_MACROS)) {
            throw new InvalidExpressionException(message: sprintf('The desired macro "%s" is not supported!', $macro));
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
        $fields = explode(separator: ' ', string: $this->expression);
        if (!array_key_exists(key: $position, array: $fields)) {
            throw new InvalidArgumentException(message: 'The desired position is not valid');
        }

        $fields[$position] = $value;

        return $this->expression = implode(separator: ' ', array: $fields);
    }
}
