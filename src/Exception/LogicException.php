<?php

declare(strict_types=1);

namespace SchedulerBundle\Exception;

use LogicException as InternalLogicException;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class LogicException extends InternalLogicException implements ExceptionInterface
{
}
