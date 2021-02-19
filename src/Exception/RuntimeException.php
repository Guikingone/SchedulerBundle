<?php

declare(strict_types=1);

namespace SchedulerBundle\Exception;

use RuntimeException as InternalRuntimeException;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
class RuntimeException extends InternalRuntimeException implements ExceptionInterface
{
}
