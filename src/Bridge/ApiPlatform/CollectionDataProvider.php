<?php

declare(strict_types=1);

namespace SchedulerBundle\Bridge\ApiPlatform;

use ApiPlatform\Core\DataProvider\ContextAwareCollectionDataProviderInterface;
use ApiPlatform\Core\DataProvider\RestrictedDataProviderInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use SchedulerBundle\Bridge\ApiPlatform\Filter\SearchFilter;
use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Task\TaskListInterface;
use SchedulerBundle\Transport\TransportInterface;
use Throwable;
use function array_key_exists;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class CollectionDataProvider implements ContextAwareCollectionDataProviderInterface, RestrictedDataProviderInterface
{
    private SearchFilter $searchFilter;
    private TransportInterface $transport;
    private LoggerInterface $logger;

    public function __construct(
        SearchFilter $searchFilter,
        TransportInterface $transport,
        ?LoggerInterface $logger = null
    ) {
        $this->searchFilter = $searchFilter;
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
    public function getCollection(string $resourceClass, string $operationName = null, array $context = []): TaskListInterface
    {
        try {
            $list = $this->transport->list();
        } catch (Throwable $throwable) {
            $this->logger->critical('The list cannot be retrieved', [
                'error' => $throwable->getMessage(),
            ]);

            throw $throwable;
        }

        if (array_key_exists('filters', $context) && [] !== $context['filters']) {
            return $this->searchFilter->filter($list, $context['filters']);
        }

        return $list;
    }
}
