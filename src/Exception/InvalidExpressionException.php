<?php

declare(strict_types=1);

namespace SchedulerBundle\Exception;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 *
 * @experimental in 5.3
 */
final class InvalidExpressionException extends \InvalidArgumentException implements ExceptionInterface
{
}
