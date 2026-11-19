<?php

declare(strict_types=1);

namespace App\Tests\Functional\Repository;

use App\Entity\Car;
use App\Entity\Expense;
use App\Repository\CarRepository;
use App\Repository\Contract\CarBySlugFinder;
use App\Repository\Contract\CarSlugExistence;
use App\Repository\Contract\CarWithExpensesFinder;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class CarRepositoryLookupTest extends KernelTestCase
{
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->clean();
        $this->em->persist((new Car())->setSlug('toyota')->setName('Toyota')->setCountry('Japan'));
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
        $this->em->createQuery('DELETE FROM '.Expense::class)->execute();
        $this->em->createQuery('DELETE FROM '.Car::class)->execute();
    }

    public function testContractsAreAutowiredToCarRepository(): void
    {
        $c = static::getContainer();

        self::assertInstanceOf(CarRepository::class, $c->get(CarBySlugFinder::class));
        self::assertInstanceOf(CarRepository::class, $c->get(CarSlugExistence::class));
        self::assertInstanceOf(CarRepository::class, $c->get(CarWithExpensesFinder::class));
    }

    public function testFindOneBySlug(): void
    {
        $repo = static::getContainer()->get(CarRepository::class);

        self::assertSame('Toyota', $repo->findOneBySlug('toyota')?->getName());
        self::assertNull($repo->findOneBySlug('bmw'));
        self::assertNull($repo->findOneBySlug(''));
    }

    public function testExistsBySlug(): void
    {
        $repo = static::getContainer()->get(CarRepository::class);

        self::assertTrue($repo->existsBySlug('toyota'));
        self::assertFalse($repo->existsBySlug('bmw'));
        self::assertFalse($repo->existsBySlug(''));
    }

    public function testExistsBySlugDoesNotHydrateEntity(): void
    {
        $repo = static::getContainer()->get(CarRepository::class);
        $this->em->clear();

        $repo->existsBySlug('toyota');

        self::assertFalse($this->em->getUnitOfWork()->size() > 0);
    }
}
