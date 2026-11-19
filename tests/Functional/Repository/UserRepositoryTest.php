<?php

declare(strict_types=1);

namespace App\Tests\Functional\Repository;

use App\Entity\User;
use App\Repository\Contract\UserByEmailFinder;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class UserRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->clean();
        $this->em->persist((new User())->setEmail('admin@example.com')->setRoles(['ROLE_ADMIN'])->setPassword('x'));
        $this->em->flush();
        $this->em->clear();
    }

    protected function tearDown(): void
    {
        $this->clean();
        parent::tearDown();
    }

    private function clean(): void
    {
        $this->em->clear();
        $this->em->createQuery('DELETE FROM '.User::class)->execute();
    }

    public function testContractIsAutowiredToUserRepository(): void
    {
        self::assertInstanceOf(UserRepository::class, static::getContainer()->get(UserByEmailFinder::class));
    }

    public function testFindOneByEmail(): void
    {
        $repo = static::getContainer()->get(UserRepository::class);

        self::assertSame('admin@example.com', $repo->findOneByEmail('admin@example.com')?->getEmail());
        self::assertNull($repo->findOneByEmail('other@example.com'));
    }

    public function testFindOneByEmailIsExactMatchCaseSensitive(): void
    {
        // Поточна поведінка: точний збіг (як findOneBy); нормалізацію регістру робить AdminCreator.
        self::assertNull(static::getContainer()->get(UserRepository::class)->findOneByEmail('Admin@Example.com'));
    }
}
