<?php

declare(strict_types=1);

namespace SchedulerBundle\Bridge\ApiPlatform;

use ApiPlatform\Core\DataProvider\ItemDataProviderInterface;
use ApiPlatform\Core\DataProvider\RestrictedDataProviderInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Transport\TransportInterface;
use Throwable;
use function sprintf;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class ItemDataProvider implements ItemDataProviderInterface, RestrictedDataProviderInterface
{
    private TransportInterface $transport;
    private LoggerInterface $logger;

    public function __construct(
        TransportInterface $transport,
        ?LoggerInterface $logger = null
    ) {
        $this->transport = $transport;
        $this->logger = $logger ?: new NullLogger();
    }

    /**
     * {@inheritdoc}
     */
    public function supports(string $resourceClass, string $operationName = null, array $context = []): bool
    {
        return $resourceClass === TaskInterface::class;
    }

    /**
     * {@inheritdoc}
     */
    public function getItem(string $resourceClass, $id, string $operationName = null, array $context = []): TaskInterface
    {
        try {
            $task = $this->transport->get($id);
        } catch (Throwable $throwable) {
            $this->logger->critical(sprintf('The task "%s" cannot be found', $id), [
                'error' => $throwable->getMessage(),
            ]);

            throw $throwable;
        }

        return $task;
    }
}
