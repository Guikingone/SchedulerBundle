<?php

declare(strict_types=1);

namespace SchedulerBundle\Transport;

use Closure;
use SchedulerBundle\Exception\TransportException;
use SplObjectStorage;
use Throwable;
use function array_merge;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class FailOverTransport extends AbstractCompoundTransport
{
    /**
     * @var SplObjectStorage<object, mixed>
     */
    private SplObjectStorage $failedTransports;

    /**
     * @param array<string, mixed> $options
     */
    public function __construct(
        TransportRegistryInterface $registry,
        array $options = []
    ) {
        $this->defineOptions(array_merge([
            'mode' => 'normal',
        ], $options), [
            'mode' => 'string',
        ]);

        $this->failedTransports = new SplObjectStorage();

        parent::__construct($registry);
    }

    /**
     * @return mixed
     */
    protected function execute(Closure $func)
    {
        if (0 === $this->registry->count()) {
            throw new TransportException('No transport found');
        }

        foreach ($this->registry as $transport) {
            if ($this->failedTransports->contains($transport)) {
                continue;
            }

            try {
                return $func($transport);
            } catch (Throwable) {
                $this->failedTransports->attach($transport);

                continue;
            }
        }

        throw new TransportException('All the transports failed to execute the requested action');
    }
}
