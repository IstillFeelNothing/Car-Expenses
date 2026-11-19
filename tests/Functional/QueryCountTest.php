<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Car;
use App\Entity\Expense;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\DataCollector\DoctrineDataCollector;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/** Захист від N+1: кількість SQL-запитів не залежить від кількості машин/витрат. */
final class QueryCountTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = static::createClient();
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
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->em->clear();
        $this->em->createQuery('DELETE FROM App\Entity\Expense')->execute();
        $this->em->createQuery('DELETE FROM App\Entity\Car')->execute();
    }

    private function seedCars(int $count, int $expensesPerCar): void
    {
        for ($i = 1; $i <= $count; ++$i) {
            $car = (new Car())->setSlug('car-'.$i)->setName('Car '.$i)->setCountry('X');
            for ($j = 1; $j <= $expensesPerCar; ++$j) {
                $car->addExpense(
                    (new Expense())->setAmount('10.00')->setCategory('пальне')
                        ->setDate(new \DateTimeImmutable('2026-01-'.sprintf('%02d', $j))),
                );
            }
            $this->em->persist($car);
        }
        $this->em->flush();
        $this->em->clear();
    }

    private function queryCount(string $url): int
    {
        // Свіжий клієнт/ядро: лог запитів не містить запитів засівання даних.
        static::ensureKernelShutdown();
        $this->client = static::createClient();
        $this->client->enableProfiler();
        $this->client->request('GET', $url);
        self::assertResponseIsSuccessful();
        $profile = $this->client->getProfile();
        self::assertNotFalse($profile, 'Профайлер недоступний у test-середовищі.');
        $collector = $profile->getCollector('db');
        self::assertInstanceOf(DoctrineDataCollector::class, $collector);

        return $collector->getQueryCount();
    }

    public function testCarsPageQueryCountDoesNotDependOnNumberOfCars(): void
    {
        $this->seedCars(2, 3);
        $small = $this->queryCount('/cars');

        $this->clean();
        $this->seedCars(5, 3);
        $large = $this->queryCount('/cars');

        self::assertGreaterThan(0, $small);
        self::assertSame($small, $large, 'N+1 на /cars: кількість запитів залежить від числа машин.');
    }

    public function testApiExpensesQueryCountDoesNotDependOnNumberOfExpenses(): void
    {
        $this->seedCars(1, 2);
        $small = $this->queryCount('/api/cars/car-1/expenses');

        $this->clean();
        $this->seedCars(1, 20);
        $large = $this->queryCount('/api/cars/car-1/expenses');

        self::assertGreaterThan(0, $small);
        self::assertSame($small, $large, 'N+1 в API витрат: кількість запитів залежить від числа витрат.');
    }

    public function testShowPageQueryCountDoesNotDependOnNumberOfExpenses(): void
    {
        $this->seedCars(1, 2);
        $small = $this->queryCount('/cars/car-1');

        $this->clean();
        $this->seedCars(1, 20);
        $large = $this->queryCount('/cars/car-1');

        self::assertGreaterThan(0, $small);
        self::assertSame($small, $large);
    }
}
