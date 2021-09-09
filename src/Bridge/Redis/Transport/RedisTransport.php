<?php

declare(strict_types=1);

namespace SchedulerBundle\Bridge\Redis\Transport;

use SchedulerBundle\SchedulePolicy\SchedulePolicyOrchestratorInterface;
use SchedulerBundle\Transport\AbstractExternalTransport;
use SchedulerBundle\Task\LazyTask;
use SchedulerBundle\Task\LazyTaskList;
use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Task\TaskListInterface;
use SchedulerBundle\Transport\AbstractTransport;
use SchedulerBundle\Transport\Configuration\ConfigurationInterface;
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
        ConfigurationInterface $configuration,
        SerializerInterface $serializer,
        SchedulePolicyOrchestratorInterface $schedulePolicyOrchestrator
    ) {
        $this->defineOptions(array_merge([
            'host' => $options['host'],
            'password' => $options['password'] ?? null,
            'port' => $options['port'],
            'scheme' => null,
            'timeout' => $options['timeout'],
            'auth' => $options['auth'] ?? null,
            'dbindex' => 0,
            'transaction_mode' => $options['transaction_mode'] ?? null,
            'list' => $options['list'],
            'execution_mode' => $options['execution_mode'],
        ], $options), [
            'host' => 'string',
            'password' => ['string', 'null'],
            'port' => 'int',
            'scheme' => ['string', 'null'],
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
