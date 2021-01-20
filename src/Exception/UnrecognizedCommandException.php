<?php

declare(strict_types=1);

namespace SchedulerBundle\Exception;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class UnrecognizedCommandException extends \InvalidArgumentException implements ExceptionInterface
{
}
