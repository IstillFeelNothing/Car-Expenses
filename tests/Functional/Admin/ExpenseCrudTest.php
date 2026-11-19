<?php

declare(strict_types=1);

namespace App\Tests\Functional\Admin;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\DomCrawler\Crawler;

final class ExpenseCrudTest extends AbstractAdminTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->loginAsAdmin();
    }

    /** @param array<string, string> $overrides */
    private function submitExpense(int $carId, array $overrides = []): Crawler
    {
        $values = $overrides + [
            'car' => (string) $carId,
            'amount' => '1250.50',
            'category' => 'пальне',
            'date' => (new \DateTimeImmutable('-1 day'))->format('Y-m-d'),
            'description' => 'Повний бак',
        ];

        return $this->submitCrudForm($this->newUrl('expense'), 'Expense', $values);
    }

    /**
     * Amount must come back from the DB as a string (never float). SQLite (test DB) has NUMERIC affinity and
     * drops trailing zeros ('1250.5'); MySQL/PostgreSQL return '1250.50'. Normalise to 2 decimals as ExpenseDto does.
     */
    private function decimal(?string $amount): string
    {
        self::assertIsString($amount);

        return bcadd($amount, '0', 2);
    }

    public function testCreateExpenseKeepsAmountAsString(): void
    {
        $car = $this->makeCar();

        $this->submitExpense((int) $car->getId());

        self::assertResponseRedirects();
        $expenses = $this->allExpenses();
        self::assertCount(1, $expenses);
        self::assertSame('1250.50', $this->decimal($expenses[0]->getAmount()));
        self::assertSame('пальне', $expenses[0]->getCategory());
        self::assertSame('Повний бак', $expenses[0]->getDescription());
        self::assertSame('toyota', $expenses[0]->getCar()?->getSlug());
    }

    public function testDescriptionIsOptional(): void
    {
        $car = $this->makeCar();

        $this->submitExpense((int) $car->getId(), ['description' => '']);

        self::assertResponseRedirects();
        $expenses = $this->allExpenses();
        self::assertCount(1, $expenses);
        self::assertNull($expenses[0]->getDescription());
    }

    public function testAmountWithoutDecimalsIsStoredWithTwoDecimals(): void
    {
        $car = $this->makeCar();

        $this->submitExpense((int) $car->getId(), ['amount' => '1000000']);

        self::assertResponseRedirects();
        self::assertSame('1000000.00', $this->decimal($this->allExpenses()[0]->getAmount()));
    }

    /** @return iterable<string, array{string}> */
    public static function invalidAmounts(): iterable
    {
        yield 'zero' => ['0'];
        yield 'negative' => ['-5'];
        yield 'three decimals' => ['1.234'];
        yield 'over limit' => ['1000000.01'];
        yield 'not a number' => ['abc'];
        yield 'blank' => [''];
    }

    #[DataProvider('invalidAmounts')]
    public function testInvalidAmountShowsErrorAndIsNotSaved(string $amount): void
    {
        $car = $this->makeCar();

        $crawler = $this->submitExpense((int) $car->getId(), ['amount' => $amount]);

        self::assertResponseStatusCodeSame(422);
        self::assertGreaterThan(0, $crawler->filter('.invalid-feedback, .form-error-message')->count());
        self::assertCount(0, $this->allExpenses());
    }

    public function testFutureDateIsRejected(): void
    {
        $car = $this->makeCar();

        $crawler = $this->submitExpense((int) $car->getId(), ['date' => (new \DateTimeImmutable('+2 days'))->format('Y-m-d')]);

        self::assertResponseStatusCodeSame(422);
        self::assertStringContainsString('Дата не може бути в майбутньому.', $crawler->filter('body')->text());
        self::assertCount(0, $this->allExpenses());
    }

    public function testTooLongCategoryIsRejected(): void
    {
        $car = $this->makeCar();

        $this->submitExpense((int) $car->getId(), ['category' => str_repeat('a', 51)]);

        self::assertResponseStatusCodeSame(422);
        self::assertCount(0, $this->allExpenses());
    }

    public function testEditExpense(): void
    {
        $car = $this->makeCar();
        $expense = $this->makeExpense($car, '10.00');

        $this->submitCrudForm($this->editUrl('expense', (int) $expense->getId()), 'Expense', ['amount' => '99.99']);

        self::assertResponseRedirects();
        self::assertSame('99.99', $this->decimal($this->allExpenses()[0]->getAmount()));
    }

    public function testEditWithInvalidAmountKeepsOldValue(): void
    {
        $car = $this->makeCar();
        $expense = $this->makeExpense($car, '10.00');

        $this->submitCrudForm($this->editUrl('expense', (int) $expense->getId()), 'Expense', ['amount' => '-5']);

        self::assertResponseStatusCodeSame(422);
        self::assertSame('10.00', $this->decimal($this->allExpenses()[0]->getAmount()));
    }

    public function testIndexSortsByDateDescending(): void
    {
        $car = $this->makeCar();
        $this->makeExpense($car, '1.00', '2026-01-10', 'старе');
        $this->makeExpense($car, '2.00', '2026-03-10', 'нове');
        $this->makeExpense($car, '3.00', '2026-02-10', 'середнє');

        $crawler = $this->client->request('GET', $this->indexUrl('expense'));

        self::assertResponseIsSuccessful();
        $rows = $crawler->filter('table tbody tr')->each(static fn (Crawler $tr): string => $tr->text());
        self::assertCount(3, $rows);
        self::assertStringContainsString('нове', $rows[0]);
        self::assertStringContainsString('середнє', $rows[1]);
        self::assertStringContainsString('старе', $rows[2]);
    }

    public function testNewExpenseIsVisibleOnSiteAndApi(): void
    {
        $car = $this->makeCar();
        $date = (new \DateTimeImmutable('-1 day'))->format('Y-m-d');

        $this->submitExpense((int) $car->getId(), ['amount' => '1250.5', 'date' => $date]);
        self::assertResponseRedirects();

        $this->client->request('GET', '/cars/toyota');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'пальне');

        $this->client->request('GET', '/api/cars/toyota/expenses');
        $data = json_decode((string) $this->client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertCount(1, $data);
        self::assertSame('1250.50', $data[0]['amount']);
        self::assertSame($date, $data[0]['date']);
        self::assertSame('toyota', $data[0]['car']);
    }

    public function testDeleteExpenseKeepsCar(): void
    {
        $car = $this->makeCar();
        $expense = $this->makeExpense($car, '10.00');

        $crawler = $this->client->request('GET', $this->indexUrl('expense'));
        $token = (string) $crawler->filter('form#action-confirmation-form input[name="token"]')->attr('value');
        $this->client->request('POST', $this->url('admin_expense_delete', ['entityId' => (int) $expense->getId()]), ['token' => $token]);

        self::assertResponseRedirects();
        self::assertCount(0, $this->allExpenses());
        self::assertCount(1, $this->allCars());
    }

    public function testDeleteWithoutValidTokenDoesNotDelete(): void
    {
        $car = $this->makeCar();
        $expense = $this->makeExpense($car, '10.00');
        $url = $this->url('admin_expense_delete', ['entityId' => (int) $expense->getId()]);

        $this->client->request('POST', $url, ['token' => 'bogus']);
        // EasyAdmin answers an invalid delete token with a redirect to the dashboard.
        self::assertResponseRedirects($this->url('admin'), 302);
        self::assertCount(1, $this->allExpenses());

        $this->client->request('POST', $url);
        self::assertResponseRedirects($this->url('admin'), 302);
        self::assertCount(1, $this->allExpenses());
    }

    public function testDeleteWithTokenOfAnotherCrudDoesNotDelete(): void
    {
        $car = $this->makeCar();
        $expense = $this->makeExpense($car, '10.00');
        $crawler = $this->client->request('GET', $this->indexUrl('car'));
        $carToken = (string) $crawler->filter('form#action-confirmation-form input[name="token"]')->attr('value');

        $this->client->request('POST', $this->url('admin_expense_delete', ['entityId' => (int) $expense->getId()]), ['token' => $carToken]);

        self::assertResponseRedirects($this->url('admin'), 302);
        self::assertCount(1, $this->allExpenses());
    }

    /** @return iterable<string, array{?string}> */
    public static function badTokens(): iterable
    {
        yield 'bogus token' => ['bogus'];
        yield 'empty token' => [''];
        yield 'missing token' => [null];
    }

    #[DataProvider('badTokens')]
    public function testCreateWithBadCsrfTokenSavesNothing(?string $token): void
    {
        $car = $this->makeCar();
        $values = ['car' => (string) $car->getId(), 'amount' => '5.00', 'category' => 'пальне', 'date' => (new \DateTimeImmutable('-1 day'))->format('Y-m-d'), 'description' => ''];

        $crawler = $this->submitCrudFormWithToken($this->newUrl('expense'), 'Expense', $values, $token);

        self::assertResponseStatusCodeSame(422);
        self::assertStringContainsString('CSRF значення недопустиме', $crawler->filter('body')->text());
        self::assertCount(0, $this->allExpenses());
    }

    #[DataProvider('badTokens')]
    public function testEditWithBadCsrfTokenChangesNothing(?string $token): void
    {
        $car = $this->makeCar();
        $expense = $this->makeExpense($car, '10.00');

        $crawler = $this->submitCrudFormWithToken($this->editUrl('expense', (int) $expense->getId()), 'Expense', ['amount' => '99.99', 'category' => 'hacked'], $token);

        self::assertResponseStatusCodeSame(422);
        self::assertStringContainsString('CSRF значення недопустиме', $crawler->filter('body')->text());
        $expenses = $this->allExpenses();
        self::assertSame('10.00', $this->decimal($expenses[0]->getAmount()));
        self::assertSame('пальне', $expenses[0]->getCategory());
    }

    public function testBatchDeleteWithValidTokenDeletes(): void
    {
        $car = $this->makeCar();
        $a = $this->makeExpense($car, '10.00');
        $b = $this->makeExpense($car, '20.00');
        $token = $this->batchDeleteToken('expense');

        $this->client->request('POST', $this->url('admin_expense_batch_delete'), $this->batchPayload('expense', [(string) $a->getId(), (string) $b->getId()], $token));

        self::assertResponseRedirects();
        self::assertCount(0, $this->allExpenses());
        self::assertCount(1, $this->allCars());
    }

    public function testBatchDeleteWithInvalidTokenDeletesNothing(): void
    {
        $car = $this->makeCar();
        $a = $this->makeExpense($car, '10.00');

        $this->client->request('POST', $this->url('admin_expense_batch_delete'), $this->batchPayload('expense', [(string) $a->getId()], 'bogus'));

        self::assertResponseRedirects($this->url('admin'), 302);
        self::assertCount(1, $this->allExpenses());
    }

    /** @return iterable<string, array{?string}> */
    public static function absentBatchTokens(): iterable
    {
        yield 'empty token' => [''];
        yield 'missing token' => [null];
    }

    #[DataProvider('absentBatchTokens')]
    public function testBatchDeleteWithoutTokenIsBadRequestAndDeletesNothing(?string $token): void
    {
        $car = $this->makeCar();
        $a = $this->makeExpense($car, '10.00');

        $this->client->request('POST', $this->url('admin_expense_batch_delete'), $this->batchPayload('expense', [(string) $a->getId()], $token));

        self::assertResponseStatusCodeSame(400);
        self::assertCount(1, $this->allExpenses());
    }

    public function testBatchDeleteTokenOfCarCrudIsRejected(): void
    {
        $car = $this->makeCar();
        $a = $this->makeExpense($car, '10.00');
        $carToken = $this->batchDeleteToken('car');

        $this->client->request('POST', $this->url('admin_expense_batch_delete'), $this->batchPayload('expense', [(string) $a->getId()], $carToken));

        self::assertResponseRedirects($this->url('admin'), 302);
        self::assertCount(1, $this->allExpenses());
    }
}
