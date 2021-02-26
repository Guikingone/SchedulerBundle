<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Bridge\ApiPlatform\Filter;

use PHPUnit\Framework\TestCase;
use SchedulerBundle\Bridge\ApiPlatform\Filter\SearchFilter;
use SchedulerBundle\Task\TaskInterface;
use stdClass;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class SearchFilterTest extends TestCase
{
    public function testFilterCanDefineDescription(): void
    {
        $filter = new SearchFilter();

        self::assertEmpty($filter->getDescription(stdClass::class));
        self::assertNotEmpty($filter->getDescription(TaskInterface::class));
    }
}
