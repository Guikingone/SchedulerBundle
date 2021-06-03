<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\EventListener;

use PHPUnit\Framework\TestCase;
use SchedulerBundle\Event\TaskScheduledEvent;
use SchedulerBundle\EventListener\MercureEventSubscriber;
use SchedulerBundle\Serializer\NotificationTaskBagNormalizer;
use SchedulerBundle\Serializer\TaskNormalizer;
use SchedulerBundle\Task\NullTask;
use Symfony\Component\HttpClient\Chunk\ServerSentEvent;
use Symfony\Component\HttpClient\EventSourceHttpClient;
use Symfony\Component\Mercure\Hub;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Jwt\StaticTokenProvider;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\DateIntervalNormalizer;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Serializer\Normalizer\DateTimeZoneNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\SerializerInterface;
use function getenv;
use function json_decode;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class MercureEventSubscriberIntegrationTest extends TestCase
{
    private HubInterface $hub;
    private SerializerInterface $serializer;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        if (!getenv('MERCURE_HUB_URL')) {
            self::markTestSkipped('The "MERCURE_HUB_URL" environment variable is required.');
        }

        if (!getenv('MERCURE_PUBLISHER_JWT_KEY')) {
            self::markTestSkipped('The "MERCURE_PUBLISHER_JWT_KEY" environment variable is required.');
        }

        $this->hub = new Hub(getenv('MERCURE_HUB_URL'), new StaticTokenProvider(getenv('MERCURE_PUBLISHER_JWT_KEY')));
        $objectNormalizer = new ObjectNormalizer();

        $this->serializer = new Serializer([
            new TaskNormalizer(
                new DateTimeNormalizer(),
                new DateTimeZoneNormalizer(),
                new DateIntervalNormalizer(),
                $objectNormalizer,
                new NotificationTaskBagNormalizer($objectNormalizer)
            ), $objectNormalizer,
        ], [new JsonEncoder()]);
        $objectNormalizer->setSerializer($this->serializer);
    }

    public function testHubCanPublishUpdateOnTaskScheduled(): void
    {
        $task = new NullTask('foo');

        $subscriber = new MercureEventSubscriber($this->hub, 'https://www.hub.com/', $this->serializer);
        $subscriber->onTaskScheduled(new TaskScheduledEvent($task));

        $eventSourceClient = new EventSourceHttpClient();
        $events = $eventSourceClient->connect(getenv('MERCURE_HUB_URL'));

        while ($events) {
            foreach ($eventSourceClient->stream($events, 2) as $r => $chunk) {
                if ($chunk instanceof ServerSentEvent) {
                    $update = json_decode($chunk->getData());

                    self::assertArrayHasKey('event', $update);
                    self::assertSame('task.scheduled', $update['event']);
                    self::assertArrayHasKey('body', $update);
                }
            }
        }
    }
}
