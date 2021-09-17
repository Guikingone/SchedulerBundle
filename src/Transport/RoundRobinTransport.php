<?php

declare(strict_types=1);

namespace SchedulerBundle\Transport;

use Closure;
use SchedulerBundle\Exception\TransportException;
use SplObjectStorage;
use Symfony\Component\Stopwatch\Stopwatch;
use Throwable;
use function count;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class RoundRobinTransport extends AbstractCompoundTransport
{
    /**
     * @var SplObjectStorage<object, mixed>
     */
    private SplObjectStorage $sleepingTransports;

    /**
     * @param TransportInterface[] $transports
     */
    public function __construct(
        iterable $transports,
        array $options = []
    ) {
        $this->defineOptions([
            'quantum' => $options['quantum'],
        ], [
            'quantum' => 'int',
        ]);

        $this->sleepingTransports = new SplObjectStorage();

        parent::__construct($transports);
    }

    /**
     * @return mixed
     */
    protected function execute(Closure $func)
    {
        if ([] === $this->transports) {
            throw new TransportException('No transport found');
        }

        while ($this->sleepingTransports->count() !== count($this->transports)) {
            foreach ($this->transports as $transport) {
                if ($this->sleepingTransports->contains($transport)) {
                    continue;
                }

                $stopWatch = new Stopwatch();

                try {
                    $stopWatch->start('quantum');

                    return $func($transport);
                } catch (Throwable $throwable) {
                    $this->sleepingTransports->attach($transport);

                    continue;
                } finally {
                    $event = $stopWatch->stop('quantum');

                    $duration = $event->getDuration() / 1000;
                    if ($duration > (count($this->transports) * $this->options['quantum'])) {
                        $this->sleepingTransports->attach($transport);
                    }
                }
            }
        }

        throw new TransportException('All the transports failed to execute the requested action');
    }
}
