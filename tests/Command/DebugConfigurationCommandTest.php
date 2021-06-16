<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Command;

use PHPUnit\Framework\TestCase;
use SchedulerBundle\Command\DebugConfigurationCommand;
use SchedulerBundle\Transport\Configuration\InMemoryConfiguration;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class DebugConfigurationCommandTest extends TestCase
{
    public function testCommandIsConfigured(): void
    {
        $command = new DebugConfigurationCommand(new InMemoryConfiguration());

        $tester = new CommandTester($command);
        $tester->execute([]);

        self::assertSame('scheduler:debug:configuration', $command->getName());
        self::assertSame('Display the options stored in the current transport configuration', $command->getDescription());
        self::assertSame(0, $command->getDefinition()->getArgumentCount());
        self::assertCount(0, $command->getDefinition()->getOptions());
    }

    public function testCommandCanDisplayDefaultConfiguration(): void
    {
        $tester = new CommandTester(new DebugConfigurationCommand(new InMemoryConfiguration()));
        $tester->execute([]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('[INFO] Found 1 configuration key', $tester->getDisplay());
        self::assertStringContainsString('Key', $tester->getDisplay());
        self::assertStringContainsString('execution_mode', $tester->getDisplay());
        self::assertStringContainsString('Value', $tester->getDisplay());
        self::assertStringContainsString('first_in_first_out', $tester->getDisplay());
    }

    public function testCommandCanDisplayWholeConfiguration(): void
    {
        $tester = new CommandTester(new DebugConfigurationCommand(new InMemoryConfiguration([
            'foo' => 'bar',
        ], [
            'foo' => 'string',
        ])));
        $tester->execute([]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('[INFO] Found 2 configuration keys', $tester->getDisplay());
        self::assertStringContainsString('Key', $tester->getDisplay());
        self::assertStringContainsString('execution_mode', $tester->getDisplay());
        self::assertStringContainsString('foo', $tester->getDisplay());
        self::assertStringContainsString('Value', $tester->getDisplay());
        self::assertStringContainsString('first_in_first_out', $tester->getDisplay());
        self::assertStringContainsString('bar', $tester->getDisplay());
    }
}
