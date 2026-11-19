<?php

declare(strict_types=1);

namespace App\Tests\Functional\Admin;

use App\Entity\Car;
use App\Entity\Expense;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/** Спільна основа адмін-тестів: чисті таблиці, користувачі, генерація URL EasyAdmin. */
abstract class AbstractAdminTestCase extends WebTestCase
{
    protected const PASSWORD = 'secret123';

    protected KernelBrowser $client;
    protected EntityManagerInterface $em;

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
        $this->em->createQuery('DELETE FROM '.Expense::class)->execute();
        $this->em->createQuery('DELETE FROM '.Car::class)->execute();
        $this->em->createQuery('DELETE FROM '.User::class)->execute();
        $this->em->clear();
    }

    /** @param list<string> $roles */
    protected function makeUser(string $email, array $roles): User
    {
        $user = (new User())->setEmail($email)->setRoles($roles);
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        $user->setPassword($hasher->hashPassword($user, self::PASSWORD));
        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }

    protected function loginAsAdmin(): void
    {
        $this->client->loginUser($this->makeUser('admin@example.com', ['ROLE_ADMIN']));
    }

    protected function makeCar(string $slug = 'toyota', string $name = 'Toyota', string $country = 'Japan'): Car
    {
        $car = (new Car())->setSlug($slug)->setName($name)->setCountry($country);
        $this->em->persist($car);
        $this->em->flush();

        return $car;
    }

    protected function makeExpense(Car $car, string $amount, string $date = '-1 day', string $category = 'пальне'): Expense
    {
        $expense = (new Expense())->setAmount($amount)->setCategory($category)->setDate(new \DateTimeImmutable($date));
        $car->addExpense($expense);
        $this->em->flush();

        return $expense;
    }

    /** @param array<string, scalar> $params */
    protected function url(string $route, array $params = []): string
    {
        return static::getContainer()->get(UrlGeneratorInterface::class)->generate($route, $params);
    }

    protected function indexUrl(string $crud): string
    {
        return $this->url('admin_'.$crud.'_index');
    }

    protected function newUrl(string $crud): string
    {
        return $this->url('admin_'.$crud.'_new');
    }

    protected function editUrl(string $crud, int $id): string
    {
        return $this->url('admin_'.$crud.'_edit', ['entityId' => $id]);
    }

    /** Форма EasyAdmin (name = коротке ім'я сутності) з CSRF-токеном із сторінки. */
    protected function crudForm(Crawler $crawler, string $formName): \Symfony\Component\DomCrawler\Form
    {
        return $crawler->filter(sprintf('form[name="%s"]', $formName))->form();
    }

    /**
     * @param array<string, string> $values
     */
    protected function submitCrudForm(string $url, string $formName, array $values): Crawler
    {
        $crawler = $this->client->request('GET', $url);
        self::assertResponseIsSuccessful();
        $form = $this->crudForm($crawler, $formName);
        $prefixed = [];
        foreach ($values as $field => $value) {
            $prefixed[$formName.'['.$field.']'] = $value;
        }

        return $this->client->submit($form, $prefixed);
    }

    /**
     * Like submitCrudForm(), but with the form CSRF token replaced by $token (null = field removed from the request).
     *
     * @param array<string, string> $values
     */
    protected function submitCrudFormWithToken(string $url, string $formName, array $values, ?string $token): Crawler
    {
        $crawler = $this->client->request('GET', $url);
        self::assertResponseIsSuccessful();
        $form = $this->crudForm($crawler, $formName);
        $tokenField = $formName.'[_token]';
        self::assertTrue($form->has($tokenField), 'The EasyAdmin form must carry a CSRF token field.');
        if (null === $token) {
            $form->remove($tokenField);
        } else {
            $form[$tokenField] = $token;
        }
        $prefixed = [];
        foreach ($values as $field => $value) {
            $prefixed[$formName.'['.$field.']'] = $value;
        }

        return $this->client->submit($form, $prefixed);
    }

    /**
     * Batch-delete CSRF token as rendered on the CRUD index page.
     */
    protected function batchDeleteToken(string $crud): string
    {
        $crawler = $this->client->request('GET', $this->indexUrl($crud));
        self::assertResponseIsSuccessful();
        $action = $crawler->filter('[data-action-csrf-token]');
        self::assertGreaterThan(0, $action->count(), 'Index page must render the batch-delete action.');

        return (string) $action->first()->attr('data-action-csrf-token');
    }

    /**
     * @param list<string> $ids
     *
     * @return array<string, string|list<string>>
     */
    protected function batchPayload(string $crud, array $ids, ?string $token): array
    {
        $payload = [
            'batchActionName' => 'batchDelete',
            'batchActionEntityIds' => $ids,
            'batchActionUrl' => $this->url('admin_'.$crud.'_batch_delete'),
            'entityFqcn' => 'car' === $crud ? Car::class : Expense::class,
        ];
        if (null !== $token) {
            $payload['batchActionCsrfToken'] = $token;
        }

        return $payload;
    }

    /** @return list<Car> */
    protected function allCars(): array
    {
        $this->em->clear();

        return $this->em->getRepository(Car::class)->findAll();
    }

    /** @return list<Expense> */
    protected function allExpenses(): array
    {
        $this->em->clear();

        return $this->em->getRepository(Expense::class)->findAll();
    }
}
