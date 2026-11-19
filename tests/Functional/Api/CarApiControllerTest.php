<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Entity\Car;
use App\Entity\Expense;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class CarApiControllerTest extends WebTestCase
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
        $this->em->clear();
        $this->em->createQuery('DELETE FROM App\Entity\Expense')->execute();
        $this->em->createQuery('DELETE FROM App\Entity\Car')->execute();
    }

    private function makeCar(string $slug, string $name, string $country = 'Japan'): Car
    {
        $car = (new Car())->setSlug($slug)->setName($name)->setCountry($country);
        $this->em->persist($car);

        return $car;
    }

    private function addExpense(Car $car, string $amount, string $category, string $date, ?string $desc = null): void
    {
        $car->addExpense(
            (new Expense())->setAmount($amount)->setCategory($category)
                ->setDate(new \DateTimeImmutable($date))->setDescription($desc),
        );
    }

    /** @return array<mixed> */
    private function json(): array
    {
        $decoded = json_decode((string) $this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);

        return $decoded;
    }

    public function testListSortedByNameWithContractKeys(): void
    {
        $this->makeCar('toyota', 'Toyota');
        $this->makeCar('bmw', 'BMW', 'Germany');
        $this->makeCar('audi', 'Audi', 'Germany');
        $this->em->flush();
        $this->em->clear();

        $this->client->request('GET', '/api/cars');

        self::assertResponseStatusCodeSame(200);
        self::assertResponseHeaderSame('Content-Type', 'application/json');
        $data = $this->json();
        self::assertSame(['Audi', 'BMW', 'Toyota'], array_column($data, 'name'));
        self::assertSame(['audi', 'bmw', 'toyota'], array_column($data, 'id'));
        foreach ($data as $row) {
            self::assertSame(['id', 'name', 'country'], array_keys($row));
        }
        self::assertSame(['id' => 'toyota', 'name' => 'Toyota', 'country' => 'Japan'], $data[2]);
    }

    public function testListEmpty(): void
    {
        $this->client->request('GET', '/api/cars');

        self::assertResponseStatusCodeSame(200);
        self::assertSame('[]', (string) $this->client->getResponse()->getContent());
    }

    public function testExpensesOrderedByDateDescThenIdDesc(): void
    {
        $toyota = $this->makeCar('toyota', 'Toyota');
        $this->addExpense($toyota, '100', 'пальне', '2026-01-10', 'Перший');
        $this->addExpense($toyota, '300.00', 'мийка', '2026-03-10');
        $this->addExpense($toyota, '20.5', 'сервіс', '2026-01-10', 'Другий');
        $this->addExpense($toyota, '1250.00', 'пальне', '2026-02-10', 'Повний бак');
        $other = $this->makeCar('bmw', 'BMW', 'Germany');
        $this->addExpense($other, '999.00', 'інше', '2026-04-01');
        $this->em->flush();
        $this->em->clear();

        $this->client->request('GET', '/api/cars/toyota/expenses');

        self::assertResponseStatusCodeSame(200);
        self::assertResponseHeaderSame('Content-Type', 'application/json');
        $data = $this->json();
        self::assertCount(4, $data);
        self::assertSame(
            ['2026-03-10', '2026-02-10', '2026-01-10', '2026-01-10'],
            array_column($data, 'date'),
        );
        // однакова дата: більший id першим (витрата "Другий" збережена пізніше)
        self::assertSame('Другий', $data[2]['description']);
        self::assertSame('Перший', $data[3]['description']);
        self::assertGreaterThan($data[3]['id'], $data[2]['id']);
        self::assertSame(['300.00', '1250.00', '20.50', '100.00'], array_column($data, 'amount'));
    }

    public function testExpenseContractKeysAndTypes(): void
    {
        $toyota = $this->makeCar('toyota', 'Toyota');
        $this->addExpense($toyota, '1250', 'пальне', '2026-01-10');
        $this->addExpense($toyota, '10.5', 'мийка', '2026-01-09', 'Опис');
        $this->em->flush();
        $this->em->clear();

        $this->client->request('GET', '/api/cars/toyota/expenses');

        $data = $this->json();
        self::assertCount(2, $data);
        foreach ($data as $row) {
            // сумісність з Python Expense.from_dict()
            self::assertSame(['id', 'car', 'amount', 'category', 'date', 'description'], array_keys($row));
            self::assertIsInt($row['id']);
            self::assertSame('toyota', $row['car']);
            self::assertIsString($row['amount']);
            self::assertMatchesRegularExpression('/^\d+\.\d{2}$/', $row['amount']);
            self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $row['date']);
        }
        self::assertSame('1250.00', $data[0]['amount']);
        self::assertNull($data[0]['description']);
        self::assertSame('10.50', $data[1]['amount']);
        self::assertSame('Опис', $data[1]['description']);
        self::assertStringContainsString('"amount":"1250.00"', (string) $this->client->getResponse()->getContent());
    }

    public function testExpensesEmpty(): void
    {
        $this->makeCar('bmw', 'BMW', 'Germany');
        $this->em->flush();

        $this->client->request('GET', '/api/cars/bmw/expenses');

        self::assertResponseStatusCodeSame(200);
        self::assertResponseHeaderSame('Content-Type', 'application/json');
        self::assertSame('[]', (string) $this->client->getResponse()->getContent());
    }

    public function testUnknownCarReturnsJson404(): void
    {
        $this->client->request('GET', '/api/cars/nonexistent/expenses');

        self::assertResponseStatusCodeSame(404);
        self::assertResponseHeaderSame('Content-Type', 'application/json');
        self::assertSame(['error' => 'Машину не знайдено'], $this->json());
    }

    public function testInvalidSlugReturnsJson404(): void
    {
        $this->client->request('GET', '/api/cars/Bad_Slug/expenses');

        self::assertResponseStatusCodeSame(404);
        self::assertResponseHeaderSame('Content-Type', 'application/json');
        self::assertSame(['error' => 'Машину не знайдено'], $this->json());
    }

    /** @return iterable<string, array{string}> */
    public static function unsafeMethods(): iterable
    {
        yield 'POST' => ['POST'];
        yield 'PUT' => ['PUT'];
        yield 'PATCH' => ['PATCH'];
        yield 'DELETE' => ['DELETE'];
    }

    #[DataProvider('unsafeMethods')]
    public function testUnsafeMethodsAreNotAllowedWithJson405(string $method): void
    {
        $this->makeCar('toyota', 'Toyota');
        $this->em->flush();

        foreach (['/api/cars/toyota/expenses', '/api/cars'] as $url) {
            $this->client->request($method, $url);

            self::assertResponseStatusCodeSame(405);
            self::assertResponseHeaderSame('Content-Type', 'application/json');
            self::assertStringContainsString('GET', (string) $this->client->getResponse()->headers->get('Allow'));
            self::assertSame(['error' => 'Метод не дозволений'], $this->json());
        }
    }

    public function testUnknownApiRouteReturnsJson404(): void
    {
        $this->client->request('GET', '/api/nope');

        self::assertResponseStatusCodeSame(404);
        self::assertResponseHeaderSame('Content-Type', 'application/json');
        self::assertSame(['error' => 'Не знайдено'], $this->json());
    }

    public function testCarWithoutExpensesSegmentReturnsJson404(): void
    {
        $this->makeCar('x', 'X');
        $this->em->flush();

        $this->client->request('GET', '/api/cars/x');

        self::assertResponseStatusCodeSame(404);
        self::assertResponseHeaderSame('Content-Type', 'application/json');
        self::assertSame(['error' => 'Не знайдено'], $this->json());
    }

    public function testNonApiUnknownRouteStaysHtml404(): void
    {
        $this->client->request('GET', '/nope');

        self::assertResponseStatusCodeSame(404);
        self::assertStringContainsString('text/html', (string) $this->client->getResponse()->headers->get('Content-Type'));
        self::assertFalse(str_starts_with(ltrim((string) $this->client->getResponse()->getContent()), '{'));
    }
}
