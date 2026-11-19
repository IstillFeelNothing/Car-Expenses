<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\User;
use App\Repository\Contract\UserByEmailFinder;
use App\Service\AdminCreator;
use Doctrine\DBAL\Driver\Exception as DriverException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Validator\Validation;

final class AdminCreatorTest extends TestCase
{
    public function testCreatesAdminWithHashedPassword(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('persist')->with(self::isInstanceOf(User::class));
        $em->expects(self::once())->method('flush');

        $hasher = $this->createMock(UserPasswordHasherInterface::class);
        $hasher->expects(self::once())
            ->method('hashPassword')
            ->with(self::isInstanceOf(User::class), 'secret123')
            ->willReturn('HASHED');

        $users = $this->createStub(UserByEmailFinder::class);
        $users->method('findOneByEmail')->willReturn(null);

        $user = (new AdminCreator($em, $hasher, $users, Validation::createValidator()))->create('admin@example.com', 'secret123');

        self::assertSame('admin@example.com', $user->getEmail());
        self::assertContains('ROLE_ADMIN', $user->getRoles());
        self::assertContains('ROLE_USER', $user->getRoles());
        self::assertSame('HASHED', $user->getPassword());
        self::assertNotSame('secret123', $user->getPassword());
    }

    public function testNormalizesEmailAndLooksUpDuplicateByNormalizedEmail(): void
    {
        $em = $this->createStub(EntityManagerInterface::class);
        $hasher = $this->createStub(UserPasswordHasherInterface::class);
        $hasher->method('hashPassword')->willReturn('HASHED');
        $users = $this->createMock(UserByEmailFinder::class);
        $users->expects(self::once())
            ->method('findOneByEmail')
            ->with('admin@example.com')
            ->willReturn(null);

        $user = (new AdminCreator($em, $hasher, $users, Validation::createValidator()))->create("  Admin@Example.COM \n", 'secret123');

        self::assertSame('admin@example.com', $user->getEmail());
    }

    public function testRejectsEmailLongerThan180Chars(): void
    {
        $email = str_repeat('a', 64).'@'.str_repeat('b', 63).'.'.str_repeat('c', 50).'.com'; // 183 chars, valid format
        self::assertGreaterThan(180, strlen($email));

        $this->expectException(\InvalidArgumentException::class);

        $this->failingCreator()->create($email, 'secret123');
    }

    public function testAcceptsEmailOfExactly180Chars(): void
    {
        $email = str_repeat('a', 64).'@'.str_repeat('b', 63).'.'.str_repeat('c', 47).'.com';
        self::assertSame(180, strlen($email));

        $em = $this->createStub(EntityManagerInterface::class);
        $hasher = $this->createStub(UserPasswordHasherInterface::class);
        $hasher->method('hashPassword')->willReturn('HASHED');
        $users = $this->createStub(UserByEmailFinder::class);
        $users->method('findOneByEmail')->willReturn(null);

        $user = (new AdminCreator($em, $hasher, $users, Validation::createValidator()))->create($email, 'secret123');

        self::assertSame($email, $user->getEmail());
    }

    public function testRejectsWhitespaceOnlyPassword(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->failingCreator()->create('admin@example.com', str_repeat(' ', 8));
    }

    public function testRejectsPasswordLongerThan4096Bytes(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->failingCreator()->create('admin@example.com', str_repeat('a', 4097));
    }

    public function testAcceptsPasswordOfExactly4096Bytes(): void
    {
        $em = $this->createStub(EntityManagerInterface::class);
        $hasher = $this->createStub(UserPasswordHasherInterface::class);
        $hasher->method('hashPassword')->willReturn('HASHED');
        $users = $this->createStub(UserByEmailFinder::class);
        $users->method('findOneByEmail')->willReturn(null);

        $user = (new AdminCreator($em, $hasher, $users, Validation::createValidator()))->create('admin@example.com', str_repeat('a', 4096));

        self::assertSame('HASHED', $user->getPassword());
    }

    public function testUniqueConstraintRaceIsReportedAsDuplicate(): void
    {
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('flush')->willThrowException(
            new UniqueConstraintViolationException($this->createStub(DriverException::class), null),
        );

        $hasher = $this->createStub(UserPasswordHasherInterface::class);
        $hasher->method('hashPassword')->willReturn('HASHED');
        $users = $this->createStub(UserByEmailFinder::class);
        $users->method('findOneByEmail')->willReturn(null);

        try {
            (new AdminCreator($em, $hasher, $users, Validation::createValidator()))->create('admin@example.com', 'secret123');
            self::fail('Expected InvalidArgumentException.');
        } catch (\InvalidArgumentException $e) {
            self::assertStringContainsString('admin@example.com', $e->getMessage());
            self::assertNull($e->getPrevious());
        }
    }

    #[DataProvider('invalidEmails')]
    public function testRejectsInvalidEmail(string $email): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Некоректний email.');

        $this->failingCreator()->create($email, 'secret123');
    }

    public function testEmailTooLongMessage(): void
    {
        $email = str_repeat('a', 64).'@'.str_repeat('b', 63).'.'.str_repeat('c', 50).'.com';

        $this->expectExceptionMessage('Email не може бути довшим за 180 символів.');

        $this->failingCreator()->create($email, 'secret123');
    }

    public function testWhitespacePasswordMessage(): void
    {
        $this->expectExceptionMessage('Пароль не може складатися лише з пробілів.');

        $this->failingCreator()->create('admin@example.com', str_repeat(' ', 8));
    }

    public function testShortPasswordMessage(): void
    {
        $this->expectExceptionMessage('Пароль має містити щонайменше 8 символів.');

        $this->failingCreator()->create('admin@example.com', '1234567');
    }

    public function testLongPasswordMessage(): void
    {
        $this->expectExceptionMessage('Пароль не може бути довшим за 4096 байт.');

        $this->failingCreator()->create('admin@example.com', str_repeat('a', 4097));
    }

    public function testMultibytePasswordIsLimitedInBytesNotChars(): void
    {
        $this->expectExceptionMessage('Пароль не може бути довшим за 4096 байт.');

        // 2100 кириличних символів = 4200 байт, але < 4096 символів
        $this->failingCreator()->create('admin@example.com', str_repeat('я', 2100));
    }

    /** @return iterable<string, array{string}> */
    public static function invalidEmails(): iterable
    {
        yield 'empty' => [''];
        yield 'spaces only' => ['   '];
        yield 'no at' => ['admin.example.com'];
        yield 'no domain' => ['admin@'];
        yield 'no local part' => ['@example.com'];
    }

    #[DataProvider('shortPasswords')]
    public function testRejectsShortPassword(string $password): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->failingCreator()->create('admin@example.com', $password);
    }

    /** @return iterable<string, array{string}> */
    public static function shortPasswords(): iterable
    {
        yield 'empty' => [''];
        yield '7 chars' => ['1234567'];
    }

    public function testAcceptsPasswordOfExactlyEightChars(): void
    {
        $em = $this->createStub(EntityManagerInterface::class);
        $hasher = $this->createStub(UserPasswordHasherInterface::class);
        $hasher->method('hashPassword')->willReturn('HASHED');
        $users = $this->createStub(UserByEmailFinder::class);
        $users->method('findOneByEmail')->willReturn(null);

        $user = (new AdminCreator($em, $hasher, $users, Validation::createValidator()))->create('admin@example.com', '12345678');

        self::assertSame('HASHED', $user->getPassword());
    }

    public function testRejectsDuplicateEmailWithoutPersisting(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('persist');
        $em->expects(self::never())->method('flush');

        $hasher = $this->createStub(UserPasswordHasherInterface::class);
        $hasher->method('hashPassword')->willReturn('HASHED');

        $users = $this->createStub(UserByEmailFinder::class);
        $users->method('findOneByEmail')->willReturn((new User())->setEmail('admin@example.com'));

        $this->expectException(\InvalidArgumentException::class);

        (new AdminCreator($em, $hasher, $users, Validation::createValidator()))->create('Admin@Example.com', 'secret123');
    }

    /** Creator whose EntityManager must never be touched: used for validation failures. */
    private function failingCreator(): AdminCreator
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('persist');
        $em->expects(self::never())->method('flush');

        $hasher = $this->createStub(UserPasswordHasherInterface::class);
        $hasher->method('hashPassword')->willReturn('HASHED');

        $users = $this->createStub(UserByEmailFinder::class);
        $users->method('findOneByEmail')->willReturn(null);

        return new AdminCreator($em, $hasher, $users, Validation::createValidator());
    }
}
