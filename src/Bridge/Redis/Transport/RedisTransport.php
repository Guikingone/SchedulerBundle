<?php

declare(strict_types=1);

namespace SchedulerBundle\Bridge\Redis\Transport;

use SchedulerBundle\SchedulePolicy\SchedulePolicyOrchestratorInterface;
use SchedulerBundle\Transport\AbstractExternalTransport;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class RedisTransport extends AbstractExternalTransport
{
    /**
     * @param array<string, mixed|int|float|string|bool|array|null> $options
     */
    public function __construct(
        array $options,
        SerializerInterface $serializer,
        SchedulePolicyOrchestratorInterface $schedulePolicyOrchestrator
    ) {
        $this->defineOptions([
            'host' => $options['host'],
            'password' => $options['password'] ?? null,
            'port' => $options['port'],
            'scheme' => $options['scheme'],
            'timeout' => $options['timeout'],
            'auth' => $options['auth'] ?? null,
            'dbindex' => 0,
            'transaction_mode' => $options['transaction_mode'] ?? null,
            'list' => $options['list'],
            'execution_mode' => $options['execution_mode'],
        ], [
            'host' => 'string',
            'password' => ['string', 'null'],
            'port' => 'int',
            'scheme' => 'string',
            'timeout' => 'int',
            'auth' => ['string', 'null'],
            'dbindex' => 'int',
            'transaction_mode' => ['string', 'null'],
            'list' => 'string',
        ]);

        parent::__construct(
            new Connection($this->getOptions(), $serializer),
            $schedulePolicyOrchestrator
        );
    }
}
