<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Car;
use App\Entity\Expense;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class CarControllerTest extends WebTestCase
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

    private function seed(): void
    {
        $toyota = $this->makeCar('toyota', 'Toyota');
        $this->addExpense($toyota, '1250.00', 'пальне', '2026-01-10', 'Повний бак');
        $this->addExpense($toyota, '100.50', 'пальне', '2026-02-10');
        $this->addExpense($toyota, '300.00', 'мийка', '2026-03-10', 'Комплексна');
        $this->makeCar('bmw', 'BMW', 'Germany');
        $this->em->flush();
        $this->em->clear();
    }

    public function testIndexListsCarsWithCountsAndTotals(): void
    {
        $this->seed();
        $crawler = $this->client->request('GET', '/cars');

        self::assertResponseIsSuccessful();
        $text = $crawler->filter('table')->text();
        self::assertStringContainsString('Toyota', $text);
        self::assertStringContainsString('BMW', $text);
        self::assertStringContainsString("1\u{00A0}650,50\u{00A0}грн", $text);
        self::assertStringContainsString("0,00\u{00A0}грн", $text);
        self::assertSame('/cars/toyota', $crawler->selectLink('Toyota')->attr('href'));
    }

    public function testIndexEmptyState(): void
    {
        $this->client->request('GET', '/cars');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.empty', 'Машин ще немає');
        self::assertSelectorTextContains('.empty', 'app:import-cars');
    }

    public function testShowDisplaysExpensesCategoriesAndTotal(): void
    {
        $this->seed();
        $crawler = $this->client->request('GET', '/cars/toyota');

        self::assertResponseIsSuccessful();
        $html = $crawler->text();
        self::assertStringContainsString('Toyota', $html);
        self::assertStringContainsString('Витрати за категоріями', $html);
        self::assertStringContainsString('Повний бак', $html);
        self::assertStringContainsString('пальне', $html);
        self::assertStringContainsString('мийка', $html);
        self::assertStringContainsString("1\u{00A0}350,50\u{00A0}грн", $html);
        self::assertStringContainsString("300,00\u{00A0}грн", $html);
        self::assertStringContainsString("Загальна сума: 1\u{00A0}650,50\u{00A0}грн", $html);

        $rows = $crawler->filter('table')->last()->filter('tbody tr');
        self::assertCount(3, $rows);
        self::assertStringContainsString('10.03.2026', $rows->eq(0)->text());
        self::assertStringContainsString('10.01.2026', $rows->eq(2)->text());
    }

    public function testShowWithoutExpenses(): void
    {
        $this->makeCar('bmw', 'BMW', 'Germany');
        $this->em->flush();
        $this->client->request('GET', '/cars/bmw');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.empty', 'Витрат ще немає');
    }

    public function testShowUnknownSlugReturns404(): void
    {
        $this->client->request('GET', '/cars/nonexistent');
        self::assertResponseStatusCodeSame(404);
    }

    public function testShowInvalidSlugReturns404(): void
    {
        $this->client->request('GET', '/cars/Bad_Slug');
        self::assertResponseStatusCodeSame(404);
    }

    public function testHomeRedirectsToCars(): void
    {
        $this->client->request('GET', '/');
        self::assertResponseRedirects('/cars');
    }

    public function testOutputIsEscaped(): void
    {
        $car = $this->makeCar('xss', '<script>alert(1)</script>');
        $this->addExpense($car, '10.00', '<b>cat</b>', '2026-01-01', '<script>alert(2)</script>');
        $this->em->flush();
        $this->em->clear();

        $this->client->request('GET', '/cars');
        self::assertResponseIsSuccessful();
        $body = (string) $this->client->getResponse()->getContent();
        self::assertStringNotContainsString('<script>alert', $body);
        self::assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $body);

        $this->client->request('GET', '/cars/xss');
        self::assertResponseIsSuccessful();
        $body = (string) $this->client->getResponse()->getContent();
        self::assertStringNotContainsString('<script>alert', $body);
        self::assertStringContainsString('&lt;script&gt;alert(2)&lt;/script&gt;', $body);
        self::assertStringNotContainsString('<b>cat</b>', $body);
    }
}
