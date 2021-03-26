<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Transport;

use Generator;
use PHPUnit\Framework\TestCase;
use SchedulerBundle\Exception\InvalidArgumentException;
use SchedulerBundle\Transport\Dsn;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class DsnTest extends TestCase
{
    public function testDsnCannotBeCreatedWithInvalidDsn(): void
    {
        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage('The "foo://" scheduler DSN is invalid.');
        self::expectExceptionCode(0);
        Dsn::fromString('foo://');
    }

    /**
     * @dataProvider provideDsn
     */
    public function testDsnCanBeCreated(string $input, Dsn $dsn): void
    {
        self::assertEquals($dsn, Dsn::fromString($input));
    }

    public function provideDsn(): Generator
    {
        yield 'Redis transport DSN' => [
            'redis://127.0.0.1:6379/_symfony_scheduler_tasks?dbindex=1',
            new Dsn('redis', '127.0.0.1', '/_symfony_scheduler_tasks', null, null, 6379, [
                'dbindex' => 1,
            ]),
        ];
        yield 'Doctrine transport DSN - Default' => [
            'doctrine://default',
            new Dsn('doctrine', 'default', null, null, null, null, []),
        ];
        yield 'Doctrine transport DSN - Table name' => [
            'doctrine://default?table_name=_symfony_scheduler_tasks',
            new Dsn('doctrine', 'default', null, null, null, null, [
                'table_name' => '_symfony_scheduler_tasks',
            ]),
        ];
        yield 'Doctrine transport DSN - Auto setup' => [
            'doctrine://default?table_name=_symfony_scheduler_tasks&auto_setup=true',
            new Dsn('doctrine', 'default', null, null, null, null, [
                'auto_setup' => 'true',
                'table_name' => '_symfony_scheduler_tasks',
            ]),
        ];
        yield 'Doctrine transport DSN - Execution mode' => [
            'doctrine://default?table_name=_symfony_scheduler_tasks&execution_mode=first_in_first_out',
            new Dsn('doctrine', 'default', null, null, null, null, [
                'execution_mode' => 'first_in_first_out',
                'table_name' => '_symfony_scheduler_tasks',
            ]),
        ];
        yield 'Memory transport DSN - Default' => [
            'memory://batch',
            new Dsn('memory', 'batch', null, null, null, null, []),
        ];
        yield 'Filesystem transport DSN - Default' => [
            'filesystem://first_in_first_out',
            new Dsn('filesystem', 'first_in_first_out', null, null, null, null, []),
        ];
        yield 'Filesystem transport DSN - Custom path' => [
            'filesystem://first_in_first_out?path=/srv/app',
            new Dsn('filesystem', 'first_in_first_out', null, null, null, null, [
                'path' => '/srv/app',
            ]),
        ];
        yield 'FailOver transport' => [
            'failover://(memory://first_in_first_out || memory://last_in_first_out)',
            new Dsn('failover', '(memory', '//first_in_first_out || memory://last_in_first_out)', null, null, null, [
                'memory://first_in_first_out || memory://last_in_first_out',
            ]),
        ];
    }
}
