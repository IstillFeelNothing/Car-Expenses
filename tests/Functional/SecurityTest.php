<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class SecurityTest extends WebTestCase
{
    private const PASSWORD = 'secret123';

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
        $this->em->createQuery('DELETE FROM '.User::class)->execute();
        $this->em->clear();
    }

    /** @param list<string> $roles */
    private function makeUser(string $email, array $roles): User
    {
        $user = (new User())->setEmail($email)->setRoles($roles);
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        $user->setPassword($hasher->hashPassword($user, self::PASSWORD));
        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }

    public function testLoginPageShowsForm(): void
    {
        $this->client->request('GET', '/login');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('form input[name="_username"]');
        self::assertSelectorExists('form input[name="_password"]');
        self::assertSelectorExists('form input[name="_csrf_token"]');
        self::assertSelectorExists('form button[type="submit"], form input[type="submit"]');
    }

    public function testSuccessfulLoginAuthenticates(): void
    {
        $this->makeUser('admin@example.com', ['ROLE_ADMIN']);

        $crawler = $this->client->request('GET', '/login');
        $form = $crawler->filter('form')->form([
            '_username' => 'admin@example.com',
            '_password' => self::PASSWORD,
        ]);
        $this->client->submit($form);

        self::assertResponseRedirects();
        self::assertStringNotContainsString('/login', (string) $this->client->getResponse()->headers->get('Location'));

        // Authenticated user is bounced away from the login page to car_index.
        $this->client->request('GET', '/login');
        self::assertResponseRedirects('/cars');
    }

    public function testWrongPasswordRedirectsBackWithError(): void
    {
        $this->makeUser('admin@example.com', ['ROLE_ADMIN']);

        $crawler = $this->client->request('GET', '/login');
        $form = $crawler->filter('form')->form([
            '_username' => 'admin@example.com',
            '_password' => 'wrong-password',
        ]);
        $this->client->submit($form);

        self::assertResponseRedirects('/login');
        $crawler = $this->client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSame('Invalid credentials.', trim($crawler->filter('.flash-error')->text()));
        self::assertSame('admin@example.com', $crawler->filter('input[name="_username"]')->attr('value'));

        // Still anonymous: login page is shown, not redirected.
        $this->client->request('GET', '/login');
        self::assertResponseIsSuccessful();
    }

    private function loginErrorFor(string $email, string $password): string
    {
        $crawler = $this->client->request('GET', '/login');
        $this->client->submit($crawler->filter('form')->form(['_username' => $email, '_password' => $password]));
        self::assertResponseRedirects('/login');
        $crawler = $this->client->followRedirect();

        return trim($crawler->filter('.flash-error')->text());
    }

    public function testUnknownUserAndWrongPasswordGiveSameError(): void
    {
        $this->makeUser('admin@example.com', ['ROLE_ADMIN']);

        $unknown = $this->loginErrorFor('nobody@example.com', 'whatever123');
        $wrong = $this->loginErrorFor('admin@example.com', 'wrong-password');

        self::assertSame('Invalid credentials.', $unknown);
        self::assertSame($unknown, $wrong);
    }

    /** @return iterable<string, array{array<string, string>}> */
    public static function badCsrfPayloads(): iterable
    {
        yield 'missing token' => [[]];
        yield 'invalid token' => [['_csrf_token' => 'not-a-valid-token']];
    }

    /** @param array<string, string> $extra */
    #[DataProvider('badCsrfPayloads')]
    public function testBadCsrfTokenDoesNotLogIn(array $extra): void
    {
        $this->makeUser('admin@example.com', ['ROLE_ADMIN']);

        $this->client->request('POST', '/login', ['_username' => 'admin@example.com', '_password' => self::PASSWORD] + $extra);

        self::assertResponseRedirects('/login');
        $crawler = $this->client->followRedirect();
        self::assertSame('Invalid CSRF token.', trim($crawler->filter('.flash-error')->text()));

        $this->client->request('GET', '/login');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('form input[name="_username"]');
    }

    public function testExternalTargetPathIsIgnored(): void
    {
        $this->makeUser('admin@example.com', ['ROLE_ADMIN']);

        $crawler = $this->client->request('GET', '/login');
        $token = (string) $crawler->filter('input[name="_csrf_token"]')->attr('value');

        $this->client->request('POST', '/login', [
            '_username' => 'admin@example.com',
            '_password' => self::PASSWORD,
            '_csrf_token' => $token,
            '_target_path' => 'https://evil.example/',
        ]);

        self::assertResponseRedirects();
        $location = (string) $this->client->getResponse()->headers->get('Location');
        self::assertStringNotContainsString('evil.example', $location);
        $host = parse_url($location, PHP_URL_HOST);
        self::assertTrue(null === $host || false === $host || 'localhost' === $host, 'Unexpected host: '.$location);
    }

    public function testLogoutSignsOut(): void
    {
        $this->client->loginUser($this->makeUser('admin@example.com', ['ROLE_ADMIN']));

        $this->client->request('GET', '/logout');
        self::assertResponseRedirects('/login');

        $this->client->request('GET', '/login');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('form input[name="_username"]');
    }
}
