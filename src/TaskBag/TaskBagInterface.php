<?php

declare(strict_types=1);

namespace SchedulerBundle\TaskBag;

use Symfony\Component\Notifier\Notification\Notification;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
interface TaskBagInterface
{
    public function getNotification(): Notification;

    public function getRecipients(): array;
}
