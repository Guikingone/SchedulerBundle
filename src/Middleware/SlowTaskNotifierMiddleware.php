<?php

declare(strict_types=1);

namespace SchedulerBundle\Middleware;

use SchedulerBundle\Task\TaskInterface;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Symfony\Component\Serializer\SerializerInterface;
use function json_encode;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class SlowTaskNotifierMiddleware implements PostExecutionMiddlewareInterface
{
    private HubInterface $hub;
    private string $updateUrl;
    private SerializerInterface $serializer;

    public function __construct(
        HubInterface $hub,
        string $updateUrl,
        SerializerInterface $serializer
    ) {
        $this->hub = $hub;
        $this->updateUrl = $updateUrl;
        $this->serializer = $serializer;
    }

    /**
     * {@inheritdoc}
     */
    public function postExecute(TaskInterface $task): void
    {
        if (!$this->hub instanceof HubInterface) {
            return;
        }

        $this->hub->publish(new Update($this->updateUrl, json_encode([
            'event' => 'task.slow_execution',
            'body' => [
                'task' => $this->serializer->serialize($task, 'json'),
            ],
        ])));
    }
}
