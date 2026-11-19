<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class CreateAdminCommandTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private CommandTester $tester;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->clearUsers();

        $this->tester = new CommandTester((new Application(self::$kernel))->find('app:create-admin'));
    }

    protected function tearDown(): void
    {
        $this->clearUsers();
        parent::tearDown();
    }

    private function clearUsers(): void
    {
        $this->em->clear();
        $this->em->createQuery('DELETE FROM '.User::class)->execute();
        $this->em->clear();
    }

    private function userCount(): int
    {
        $this->em->clear();

        return $this->em->getRepository(User::class)->count([]);
    }

    /** @param list<string> $inputs */
    private function runWith(string $email, array $inputs): int
    {
        $this->tester->setInputs($inputs);

        return $this->tester->execute(['email' => $email], ['interactive' => true]);
    }

    public function testCommandIsRegistered(): void
    {
        $command = (new Application(self::$kernel))->find('app:create-admin');

        self::assertSame('app:create-admin', $command->getName());
        self::assertTrue($command->getDefinition()->hasArgument('email'));
        self::assertFalse($command->getDefinition()->hasArgument('password'));
        self::assertFalse($command->getDefinition()->hasOption('password'));
    }

    public function testCreatesAdmin(): void
    {
        $status = $this->runWith('admin@example.com', ['secret123', 'secret123']);

        self::assertSame(Command::SUCCESS, $status);
        $output = $this->tester->getDisplay();
        self::assertStringContainsString('admin@example.com', $output);
        self::assertStringNotContainsString('secret123', $output);

        $this->em->clear();
        $user = $this->em->getRepository(User::class)->findOneBy(['email' => 'admin@example.com']);
        self::assertNotNull($user);
        self::assertContains('ROLE_ADMIN', $user->getRoles());
        self::assertNotSame('secret123', $user->getPassword());

        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        self::assertTrue($hasher->isPasswordValid($user, 'secret123'));
        self::assertFalse($hasher->isPasswordValid($user, 'wrong-password'));
    }

    public function testPasswordMismatchFails(): void
    {
        $status = $this->runWith('admin@example.com', ['secret123', 'different123']);

        self::assertSame(Command::FAILURE, $status);
        $output = $this->tester->getDisplay();
        self::assertStringContainsString('ERROR', $output);
        self::assertStringNotContainsString('secret123', $output);
        self::assertStringNotContainsString('different123', $output);
        self::assertSame(0, $this->userCount());
    }

    public function testEmptyPasswordFails(): void
    {
        $status = $this->runWith('admin@example.com', ['', '']);

        self::assertSame(Command::FAILURE, $status);
        self::assertSame(0, $this->userCount());
    }

    public function testInvalidEmailFailsWithoutTraceback(): void
    {
        $status = $this->runWith('not-an-email', ['secret123', 'secret123']);

        self::assertSame(Command::FAILURE, $status);
        $output = $this->tester->getDisplay();
        self::assertStringContainsString('ERROR', $output);
        self::assertStringNotContainsString('Stack trace', $output);
        self::assertSame(0, $this->userCount());
    }

    public function testWeakPasswordFails(): void
    {
        $status = $this->runWith('admin@example.com', ['1234567', '1234567']);

        self::assertSame(Command::FAILURE, $status);
        $output = $this->tester->getDisplay();
        self::assertStringContainsString('ERROR', $output);
        self::assertStringNotContainsString('Stack trace', $output);
        self::assertSame(0, $this->userCount());
    }

    public function testTooLongEmailFailsWithoutTraceback(): void
    {
        $email = str_repeat('a', 64).'@'.str_repeat('b', 63).'.'.str_repeat('c', 50).'.com';
        $status = $this->runWith($email, ['secret123', 'secret123']);

        self::assertSame(Command::FAILURE, $status);
        $output = $this->tester->getDisplay();
        self::assertStringContainsString('ERROR', $output);
        self::assertStringNotContainsString('Stack trace', $output);
        self::assertSame(0, $this->userCount());
    }

    public function testWhitespaceOnlyPasswordFailsWithoutTraceback(): void
    {
        $spaces = str_repeat(' ', 8);
        $status = $this->runWith('admin@example.com', [$spaces, $spaces]);

        self::assertSame(Command::FAILURE, $status);
        $output = $this->tester->getDisplay();
        self::assertStringContainsString('ERROR', $output);
        self::assertStringNotContainsString('Stack trace', $output);
        self::assertSame(0, $this->userCount());
    }

    public function testDuplicateEmailFails(): void
    {
        self::assertSame(Command::SUCCESS, $this->runWith('admin@example.com', ['secret123', 'secret123']));

        $status = $this->runWith('Admin@Example.com', ['another123', 'another123']);

        self::assertSame(Command::FAILURE, $status);
        $output = $this->tester->getDisplay();
        self::assertStringContainsString('ERROR', $output);
        self::assertStringNotContainsString('Stack trace', $output);
        self::assertSame(1, $this->userCount());
    }
}
