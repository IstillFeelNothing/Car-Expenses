<?php

declare(strict_types=1);

namespace App\Tests\Functional\Admin;

use PHPUnit\Framework\Attributes\DataProvider;

final class CrudAccessTest extends AbstractAdminTestCase
{
    /** @return iterable<string, array{string, string}> */
    public static function crudPages(): iterable
    {
        foreach (['car', 'expense'] as $crud) {
            foreach (['index', 'new'] as $page) {
                yield $crud.' '.$page => [$crud, $page];
            }
        }
    }

    private function pageUrl(string $crud, string $page): string
    {
        return $this->url('admin_'.$crud.'_'.$page);
    }

    #[DataProvider('crudPages')]
    public function testAnonymousIsRedirectedToLogin(string $crud, string $page): void
    {
        $this->client->request('GET', $this->pageUrl($crud, $page));

        self::assertResponseRedirects('/login');
    }

    #[DataProvider('crudPages')]
    public function testRoleUserGetsForbidden(string $crud, string $page): void
    {
        $this->client->loginUser($this->makeUser('user@example.com', []));

        $this->client->request('GET', $this->pageUrl($crud, $page));

        self::assertResponseStatusCodeSame(403);
    }

    #[DataProvider('crudPages')]
    public function testRoleAdminIsAllowed(string $crud, string $page): void
    {
        $this->loginAsAdmin();

        $this->client->request('GET', $this->pageUrl($crud, $page));

        self::assertResponseIsSuccessful();
    }

    /**
     * Every GET action of both CRUDs plus the EasyAdmin UI kit pages. Value: [route, needs entity id, route params].
     *
     * @return iterable<string, array{string, bool, array<string, mixed>}>
     */
    public static function getActions(): iterable
    {
        foreach (['car', 'expense'] as $crud) {
            foreach (['edit', 'detail'] as $action) {
                yield $crud.' '.$action => ['admin_'.$crud.'_'.$action, true, []];
            }
            yield $crud.' autocomplete' => ['admin_'.$crud.'_autocomplete', false, ['autocompleteContext' => ['originatingPage' => 'new', 'propertyName' => 'car']]];
            yield $crud.' render-filters' => ['admin_'.$crud.'_render_filters', false, []];
        }
        yield 'ui kit index' => ['admin_ea_ui_kit_index', false, []];
        yield 'ui kit foundation' => ['admin_ea_ui_kit_foundation', false, ['name' => 'colors']];
        yield 'ui kit component' => ['admin_ea_ui_kit_component', false, ['name' => 'badge']];
        yield 'ui kit form fields' => ['admin_ea_ui_kit_form_fields', false, []];
    }

    /** @param array<string, mixed> $params */
    private function actionUrl(string $route, bool $needsId, array $params): string
    {
        if ($needsId) {
            $car = $this->makeCar();
            $id = str_starts_with($route, 'admin_expense_') ? (int) $this->makeExpense($car, '10.00')->getId() : (int) $car->getId();
            $params['entityId'] = (string) $id;
        }

        return $this->url($route, $params);
    }

    /** @param array<string, mixed> $params */
    #[DataProvider('getActions')]
    public function testAnonymousIsRedirectedFromEveryGetAction(string $route, bool $needsId, array $params): void
    {
        $this->client->request('GET', $this->actionUrl($route, $needsId, $params));

        self::assertResponseRedirects('/login');
    }

    /** @param array<string, mixed> $params */
    #[DataProvider('getActions')]
    public function testRoleUserGetsForbiddenOnEveryGetAction(string $route, bool $needsId, array $params): void
    {
        $url = $this->actionUrl($route, $needsId, $params);
        $this->client->loginUser($this->makeUser('user@example.com', []));

        $this->client->request('GET', $url);

        self::assertResponseStatusCodeSame(403);
    }

    /** @param array<string, mixed> $params */
    #[DataProvider('getActions')]
    public function testRoleAdminPassesAuthorizationOnEveryGetAction(string $route, bool $needsId, array $params): void
    {
        $url = $this->actionUrl($route, $needsId, $params);
        $this->loginAsAdmin();

        $this->client->request('GET', $url);

        if (in_array($route, ['admin_car_autocomplete', 'admin_ea_ui_kit_form_fields'], true)) {
            // Car has no association field to autocomplete; the UI kit form page needs ext-intl (absent on some dev machines).
            // Authorization is what matters here: not bounced to /login and not 403.
            self::assertNotContains($this->client->getResponse()->getStatusCode(), [302, 401, 403]);

            return;
        }

        self::assertResponseIsSuccessful();
    }

    /** @return iterable<string, array{string, string, bool}> */
    public static function mutatingPosts(): iterable
    {
        yield 'car delete' => ['admin_car_delete', 'car', true];
        yield 'expense delete' => ['admin_expense_delete', 'expense', true];
        yield 'car batch-delete' => ['admin_car_batch_delete', 'car', false];
        yield 'expense batch-delete' => ['admin_expense_batch_delete', 'expense', false];
        yield 'car new' => ['admin_car_new', 'car', false];
        yield 'car edit' => ['admin_car_edit', 'car', true];
        yield 'expense new' => ['admin_expense_new', 'expense', false];
        yield 'expense edit' => ['admin_expense_edit', 'expense', true];
    }

    /** @return array{int, int, string, string} */
    private function seedAndSnapshot(): array
    {
        $car = $this->makeCar();
        $this->makeExpense($car, '10.00');

        return [(int) $car->getId(), (int) $this->allExpenses()[0]->getId(), 'Toyota', '10.00'];
    }

    private function assertDataUntouched(): void
    {
        $cars = $this->allCars();
        self::assertCount(1, $cars);
        self::assertSame('toyota', $cars[0]->getSlug());
        self::assertSame('Toyota', $cars[0]->getName());
        self::assertSame('Japan', $cars[0]->getCountry());
        $expenses = $this->allExpenses();
        self::assertCount(1, $expenses);
        self::assertSame('10.00', bcadd((string) $expenses[0]->getAmount(), '0', 2));
        self::assertSame('пальне', $expenses[0]->getCategory());
    }

    /** @return array<string, mixed> */
    private function hostilePayload(int $carId): array
    {
        $date = (new \DateTimeImmutable('-1 day'))->format('Y-m-d');

        return [
            'ea' => ['batchActionEntityIds' => [(string) $carId, '1', '2'], 'newForm' => ['btn' => 'saveAndReturn']],
            'batchActionName' => 'delete',
            'batchActionEntityIds' => [(string) $carId, '1', '2'],
            'batchActionUrl' => '/admin/car/batch-delete',
            'batchActionCsrfToken' => 'bogus',
            'token' => 'bogus',
            'Car' => ['slug' => 'hacked', 'name' => 'Hacked', 'country' => 'X'],
            'Expense' => ['car' => (string) $carId, 'amount' => '1.00', 'category' => 'x', 'date' => $date],
        ];
    }

    #[DataProvider('mutatingPosts')]
    public function testAnonymousPostChangesNothing(string $route, string $crud, bool $needsId): void
    {
        [$carId, $expenseId] = $this->seedAndSnapshot();
        $id = 'car' === $crud ? $carId : $expenseId;
        $url = $this->url($route, $needsId ? ['entityId' => (string) $id] : []);

        $this->client->request('POST', $url, $this->hostilePayload($carId));

        self::assertResponseRedirects('/login');
        $this->assertDataUntouched();
    }

    #[DataProvider('mutatingPosts')]
    public function testRoleUserPostChangesNothing(string $route, string $crud, bool $needsId): void
    {
        [$carId, $expenseId] = $this->seedAndSnapshot();
        $id = 'car' === $crud ? $carId : $expenseId;
        $url = $this->url($route, $needsId ? ['entityId' => (string) $id] : []);
        $this->client->loginUser($this->makeUser('user@example.com', []));

        $this->client->request('POST', $url, $this->hostilePayload($carId));

        self::assertResponseStatusCodeSame(403);
        $this->assertDataUntouched();
    }

    public function testDashboardMenuLinksToCrudsAndSite(): void
    {
        $this->loginAsAdmin();

        $this->client->request('GET', '/admin');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists(sprintf('a[href*="%s"]', $this->indexUrl('car')));
        self::assertSelectorExists(sprintf('a[href*="%s"]', $this->indexUrl('expense')));
        self::assertSelectorExists('a[href="/cars"]');
        self::assertSelectorTextContains('body', 'Машини');
        self::assertSelectorTextContains('body', 'Витрати');
    }

    public function testNoCrudForUserEntity(): void
    {
        self::bootKernel();
        $router = static::getContainer()->get('router');

        self::assertNull($router->getRouteCollection()->get('admin_user_index'));
    }
}
