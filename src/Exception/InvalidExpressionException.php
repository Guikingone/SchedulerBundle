<?php

declare(strict_types=1);

namespace SchedulerBundle\Exception;

use InvalidArgumentException;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class InvalidExpressionException extends InvalidArgumentException implements ExceptionInterface
{
}
