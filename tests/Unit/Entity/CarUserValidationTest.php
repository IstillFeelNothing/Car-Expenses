<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Car;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class CarUserValidationTest extends KernelTestCase
{
    private ValidatorInterface $validator;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();
        $this->validator = $container->get(ValidatorInterface::class);
        $this->em = $container->get(EntityManagerInterface::class);
    }

    private function makeCar(string $slug = 'toyota'): Car
    {
        return (new Car())->setSlug($slug)->setName('Toyota')->setCountry('Japan');
    }

    public function testValidCar(): void
    {
        self::assertCount(0, $this->validator->validate($this->makeCar()));
    }

    public function testCarRejectsBlankAndTooLongFields(): void
    {
        $car = (new Car())->setSlug('')->setName(str_repeat('n', 101))->setCountry(str_repeat('c', 101));

        self::assertGreaterThan(0, count($this->validator->validateProperty($car, 'slug')));
        self::assertCount(1, $this->validator->validateProperty($car, 'name'));
        self::assertCount(1, $this->validator->validateProperty($car, 'country'));
        self::assertCount(1, $this->validator->validateProperty($this->makeCar(str_repeat('s', 51)), 'slug'));
    }

    /** @return iterable<string, array{string}> */
    public static function invalidSlugs(): iterable
    {
        yield 'underscore and uppercase' => ['Bad_Slug'];
        yield 'space' => ['My Car'];
        yield 'uppercase' => ['TOYOTA'];
        yield 'inner space' => ['toy ota'];
        yield 'cyrillic' => ['тойота'];
        yield 'slash' => ['a/b'];
        yield 'trailing newline' => ["toyota\n"];
    }

    #[DataProvider('invalidSlugs')]
    public function testCarRejectsInvalidSlugFormat(string $slug): void
    {
        $violations = $this->validator->validateProperty($this->makeCar($slug), 'slug');

        self::assertCount(1, $violations);
        self::assertStringContainsString('a-z', (string) $violations[0]->getMessage());
    }

    /** @return iterable<string, array{string}> */
    public static function validSlugs(): iterable
    {
        yield 'simple' => ['toyota'];
        yield 'with dash' => ['land-rover'];
        yield 'with digits' => ['bmw-3'];
        yield 'only digits' => ['911'];
        yield 'max length' => [str_repeat('a', 50)];
    }

    #[DataProvider('validSlugs')]
    public function testCarAcceptsValidSlugFormat(string $slug): void
    {
        self::assertCount(0, $this->validator->validateProperty($this->makeCar($slug), 'slug'));
    }

    public function testSlugPatternConstantMatchesRouteFormat(): void
    {
        self::assertSame('[a-z0-9-]+', Car::SLUG_PATTERN);
    }

    public function testCarSlugMustBeUnique(): void
    {
        $this->resetSchema();
        $this->em->persist($this->makeCar());
        $this->em->flush();

        self::assertCount(1, $this->validator->validate($this->makeCar()));
    }

    public function testUserEmailMustBeValidAndUnique(): void
    {
        $user = (new User())->setEmail('not-an-email')->setPassword('hash');
        self::assertGreaterThan(0, count($this->validator->validateProperty($user, 'email')));

        $this->resetSchema();
        $this->em->persist((new User())->setEmail('admin@example.com')->setPassword('hash'));
        $this->em->flush();

        $duplicate = (new User())->setEmail('admin@example.com')->setPassword('hash');
        self::assertCount(1, $this->validator->validate($duplicate));
    }

    public function testUserAlwaysHasRoleUser(): void
    {
        $user = (new User())->setEmail('a@b.co')->setRoles(['ROLE_ADMIN']);

        self::assertSame(['ROLE_ADMIN', 'ROLE_USER'], $user->getRoles());
        self::assertSame('a@b.co', $user->getUserIdentifier());
    }

    /** Очищає таблиці (схему створюють міграції) для перевірки UniqueEntity. */
    private function resetSchema(): void
    {
        $conn = $this->em->getConnection();
        foreach (['expense', 'car', '"user"'] as $table) {
            $conn->executeStatement('DELETE FROM '.$table);
        }
    }

    protected function tearDown(): void
    {
        $this->resetSchema();
        $this->em->clear();
        parent::tearDown();
    }
}
