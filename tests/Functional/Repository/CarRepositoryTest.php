<?php

declare(strict_types=1);

namespace App\Tests\Functional\Repository;

use App\Entity\Car;
use App\Entity\Expense;
use App\Repository\CarRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class CarRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->clean();
    }

    protected function tearDown(): void
    {
        $this->clean();
        parent::tearDown();
    }

    private function clean(): void
    {
        $this->em->clear();
        $this->em->createQuery('DELETE FROM App\Entity\Expense')->execute();
        $this->em->createQuery('DELETE FROM App\Entity\Car')->execute();
    }

    public function testFindAllWithExpensesOrdersSameDateByIdDesc(): void
    {
        $car = (new Car())->setSlug('toyota')->setName('Toyota')->setCountry('Japan');
        foreach (['перша', 'друга', 'третя'] as $desc) {
            $car->addExpense(
                (new Expense())->setAmount('10.00')->setCategory('пальне')
                    ->setDate(new \DateTimeImmutable('2026-01-10'))->setDescription($desc),
            );
        }
        $car->addExpense(
            (new Expense())->setAmount('5.00')->setCategory('мийка')
                ->setDate(new \DateTimeImmutable('2026-02-01'))->setDescription('новіша'),
        );
        $this->em->persist($car);
        $this->em->flush();
        $this->em->clear();

        $cars = static::getContainer()->get(CarRepository::class)->findAllWithExpenses();

        self::assertCount(1, $cars);
        $descriptions = array_map(
            static fn (Expense $e): ?string => $e->getDescription(),
            $cars[0]->getExpenses()->toArray(),
        );
        self::assertSame(['новіша', 'третя', 'друга', 'перша'], array_values($descriptions));
    }
}
