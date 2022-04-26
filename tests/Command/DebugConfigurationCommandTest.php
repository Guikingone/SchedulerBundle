<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Command;

use PHPUnit\Framework\TestCase;
use SchedulerBundle\Command\DebugConfigurationCommand;
use SchedulerBundle\Transport\Configuration\InMemoryConfiguration;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class DebugConfigurationCommandTest extends TestCase
{
    public function testCommandIsConfigured(): void
    {
        $command = new DebugConfigurationCommand(new InMemoryConfiguration());

        self::assertSame('scheduler:debug:configuration', $command->getName());
        self::assertSame('Display the current transport configuration keys and values', $command->getDescription());
        self::assertSame(0, $command->getDefinition()->getArgumentCount());
        self::assertCount(0, $command->getDefinition()->getOptions());

        self::assertSame(
            $command->getHelp(),
            <<<'EOF'
                The <info>%command.name%</info> command display the current configuration.

                    <info>php %command.full_name%</info>
                EOF
        );
    }

    public function testCommandCannotDisplayKeys(): void
    {
        $command = new DebugConfigurationCommand(new InMemoryConfiguration());

        $tester = new CommandTester($command);
        $tester->execute([]);

        self::assertSame(0, $tester->getStatusCode());
        self::assertStringContainsString('[INFO] Found 1 keys', $tester->getDisplay());
        self::assertStringContainsString('Key', $tester->getDisplay());
        self::assertStringContainsString('Value', $tester->getDisplay());
        self::assertStringContainsString('execution_mode', $tester->getDisplay());
        self::assertStringContainsString('first_in_first_out', $tester->getDisplay());
    }
}
