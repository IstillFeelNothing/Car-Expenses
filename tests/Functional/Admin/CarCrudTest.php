<?php

declare(strict_types=1);

namespace App\Tests\Functional\Admin;

use App\Entity\Car;
use PHPUnit\Framework\Attributes\DataProvider;

final class CarCrudTest extends AbstractAdminTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->loginAsAdmin();
    }

    public function testCreateCarViaForm(): void
    {
        $this->submitCrudForm($this->newUrl('car'), 'Car', ['slug' => 'toyota-camry', 'name' => 'Toyota Camry', 'country' => 'Japan']);

        self::assertResponseRedirects();
        $cars = $this->allCars();
        self::assertCount(1, $cars);
        self::assertSame('toyota-camry', $cars[0]->getSlug());
        self::assertMatchesRegularExpression('/\A'.Car::SLUG_PATTERN.'\z/', (string) $cars[0]->getSlug());
        self::assertSame('Toyota Camry', $cars[0]->getName());
    }

    /** @return iterable<string, array{string}> */
    public static function badSlugs(): iterable
    {
        yield 'uppercase and underscore' => ['Bad_Slug'];
        yield 'space' => ['bad slug'];
        yield 'slash' => ['bad/slug'];
        yield 'cyrillic' => ['тойота'];
    }

    #[DataProvider('badSlugs')]
    public function testInvalidSlugShowsErrorAndIsNotSaved(string $slug): void
    {
        $crawler = $this->submitCrudForm($this->newUrl('car'), 'Car', ['slug' => $slug, 'name' => 'Toyota', 'country' => 'Japan']);

        self::assertResponseStatusCodeSame(422);
        self::assertStringContainsString('Slug може містити лише малі латинські літери', $crawler->filter('body')->text());
        self::assertCount(0, $this->allCars());
    }

    public function testBlankFieldsShowErrorsInsteadOfCrashing(): void
    {
        $crawler = $this->submitCrudForm($this->newUrl('car'), 'Car', ['slug' => '', 'name' => '', 'country' => '']);

        self::assertResponseStatusCodeSame(422);
        self::assertGreaterThan(0, $crawler->filter('.invalid-feedback, .form-error-message')->count());
        self::assertCount(0, $this->allCars());
    }

    public function testDuplicateSlugIsRejected(): void
    {
        $this->makeCar('toyota');

        $crawler = $this->submitCrudForm($this->newUrl('car'), 'Car', ['slug' => 'toyota', 'name' => 'Other', 'country' => 'Japan']);

        self::assertResponseStatusCodeSame(422);
        self::assertStringContainsString('Машина з таким slug вже існує.', $crawler->filter('body')->text());
        self::assertCount(1, $this->allCars());
    }

    public function testEditCar(): void
    {
        $car = $this->makeCar('toyota', 'Toyota');

        $this->submitCrudForm($this->editUrl('car', (int) $car->getId()), 'Car', ['name' => 'Toyota Renamed', 'country' => 'Japan']);

        self::assertResponseRedirects();
        $cars = $this->allCars();
        self::assertSame('Toyota Renamed', $cars[0]->getName());
        self::assertSame('toyota', $cars[0]->getSlug());
    }

    public function testEditWithInvalidSlugIsRejected(): void
    {
        $car = $this->makeCar('toyota');

        $this->submitCrudForm($this->editUrl('car', (int) $car->getId()), 'Car', ['slug' => 'Bad_Slug']);

        self::assertResponseStatusCodeSame(422);
        self::assertSame('toyota', $this->allCars()[0]->getSlug());
    }

    public function testDeleteCarRemovesItsExpenses(): void
    {
        $car = $this->makeCar('toyota');
        $this->makeExpense($car, '10.00');
        $this->makeExpense($car, '20.00');
        $other = $this->makeCar('bmw', 'BMW', 'Germany');
        $this->makeExpense($other, '5.00');
        self::assertCount(3, $this->allExpenses());

        $crawler = $this->client->request('GET', $this->indexUrl('car'));
        self::assertResponseIsSuccessful();
        $deleteForm = $crawler->filter('form#action-confirmation-form');
        self::assertCount(1, $deleteForm);
        $token = (string) $deleteForm->filter('input[name="token"]')->attr('value');

        $this->client->request('POST', $this->url('admin_car_delete', ['entityId' => (int) $car->getId()]), ['token' => $token]);

        self::assertResponseRedirects();
        $cars = $this->allCars();
        self::assertCount(1, $cars);
        self::assertSame('bmw', $cars[0]->getSlug());
        $expenses = $this->allExpenses();
        self::assertCount(1, $expenses);
        self::assertSame('5.00', bcadd((string) $expenses[0]->getAmount(), '0', 2));
    }

    public function testDeleteWithoutValidTokenDoesNotDelete(): void
    {
        $car = $this->makeCar('toyota');

        $this->client->request('POST', $this->url('admin_car_delete', ['entityId' => (int) $car->getId()]), ['token' => 'bogus']);

        // EasyAdmin answers an invalid delete token with a redirect to the dashboard (not to the index, not an error).
        self::assertResponseRedirects($this->url('admin'), 302);
        self::assertCount(1, $this->allCars());
    }

    public function testDeleteWithoutTokenDoesNotDelete(): void
    {
        $car = $this->makeCar('toyota');
        $this->makeExpense($car, '10.00');

        $this->client->request('POST', $this->url('admin_car_delete', ['entityId' => (int) $car->getId()]));

        self::assertResponseRedirects($this->url('admin'), 302);
        self::assertCount(1, $this->allCars());
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
        $crawler = $this->submitCrudFormWithToken($this->newUrl('car'), 'Car', ['slug' => 'toyota', 'name' => 'Toyota', 'country' => 'Japan'], $token);

        self::assertResponseStatusCodeSame(422);
        self::assertStringContainsString('The CSRF token is invalid', $crawler->filter('body')->text());
        self::assertCount(0, $this->allCars());
    }

    #[DataProvider('badTokens')]
    public function testEditWithBadCsrfTokenChangesNothing(?string $token): void
    {
        $car = $this->makeCar('toyota', 'Toyota');

        $crawler = $this->submitCrudFormWithToken($this->editUrl('car', (int) $car->getId()), 'Car', ['name' => 'Hacked', 'country' => 'Nowhere'], $token);

        self::assertResponseStatusCodeSame(422);
        self::assertStringContainsString('The CSRF token is invalid', $crawler->filter('body')->text());
        $cars = $this->allCars();
        self::assertSame('Toyota', $cars[0]->getName());
        self::assertSame('Japan', $cars[0]->getCountry());
    }

    public function testBatchDeleteWithValidTokenDeletes(): void
    {
        $a = $this->makeCar('toyota');
        $b = $this->makeCar('bmw', 'BMW', 'Germany');
        $token = $this->batchDeleteToken('car');

        $this->client->request('POST', $this->url('admin_car_batch_delete'), $this->batchPayload('car', [(string) $a->getId(), (string) $b->getId()], $token));

        self::assertResponseRedirects();
        self::assertCount(0, $this->allCars());
    }

    public function testBatchDeleteWithInvalidTokenDeletesNothing(): void
    {
        $a = $this->makeCar('toyota');
        $this->makeExpense($a, '10.00');
        $b = $this->makeCar('bmw', 'BMW', 'Germany');

        $this->client->request('POST', $this->url('admin_car_batch_delete'), $this->batchPayload('car', [(string) $a->getId(), (string) $b->getId()], 'bogus'));

        // Wrong token: redirect to the dashboard.
        self::assertResponseRedirects($this->url('admin'), 302);
        self::assertCount(2, $this->allCars());
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
        $a = $this->makeCar('toyota');
        $this->makeExpense($a, '10.00');

        $this->client->request('POST', $this->url('admin_car_batch_delete'), $this->batchPayload('car', [(string) $a->getId()], $token));

        // EasyAdmin's argument resolver rejects the request before the controller runs.
        self::assertResponseStatusCodeSame(400);
        self::assertCount(1, $this->allCars());
        self::assertCount(1, $this->allExpenses());
    }

    public function testBatchDeleteTokenOfAnotherCrudIsRejected(): void
    {
        $a = $this->makeCar('toyota');
        $this->makeExpense($a, '10.00');
        // A token minted for the Expense CRUD must not authorise deleting Cars.
        $expenseToken = $this->batchDeleteToken('expense');

        $this->client->request('POST', $this->url('admin_car_batch_delete'), $this->batchPayload('car', [(string) $a->getId()], $expenseToken));

        self::assertResponseRedirects($this->url('admin'), 302);
        self::assertCount(1, $this->allCars());
    }

    public function testNewCarIsVisibleOnSiteAndApi(): void
    {
        $this->submitCrudForm($this->newUrl('car'), 'Car', ['slug' => 'honda', 'name' => 'Honda', 'country' => 'Japan']);
        self::assertResponseRedirects();

        $this->client->request('GET', '/cars');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Honda');

        $this->client->request('GET', '/cars/honda');
        self::assertResponseIsSuccessful();

        $this->client->request('GET', '/api/cars');
        $data = json_decode((string) $this->client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertSame([['id' => 'honda', 'name' => 'Honda', 'country' => 'Japan']], $data);
    }

    public function testIndexListsCars(): void
    {
        $this->makeCar('toyota', 'Toyota');

        $this->client->request('GET', $this->indexUrl('car'));

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('table', 'toyota');
    }
}
