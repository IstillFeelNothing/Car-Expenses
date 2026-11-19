<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class AdminAccessTest extends WebTestCase
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

    public function testAnonymousIsRedirectedFromAdmin(): void
    {
        $this->client->request('GET', '/admin');

        self::assertResponseRedirects('/login');
    }

    public function testRoleUserGetsForbiddenOnAdmin(): void
    {
        $this->client->loginUser($this->makeUser('user@example.com', []));

        $this->client->request('GET', '/admin');

        self::assertResponseStatusCodeSame(403);
    }

    public function testRoleAdminIsAllowedOnAdmin(): void
    {
        $this->client->loginUser($this->makeUser('admin@example.com', ['ROLE_ADMIN']));

        $this->client->request('GET', '/admin');

        self::assertResponseIsSuccessful();
    }

    public function testAdminAccessAfterSuccessfulFormLogin(): void
    {
        $this->makeUser('admin@example.com', ['ROLE_ADMIN']);

        $crawler = $this->client->request('GET', '/login');
        $form = $crawler->filter('form')->form([
            '_username' => 'admin@example.com',
            '_password' => self::PASSWORD,
        ]);
        $this->client->submit($form);
        self::assertResponseRedirects();

        $this->client->request('GET', '/admin');
        self::assertResponseIsSuccessful();
    }

    public function testAdminStillRedirectsAfterWrongPassword(): void
    {
        $this->makeUser('admin@example.com', ['ROLE_ADMIN']);

        $crawler = $this->client->request('GET', '/login');
        $form = $crawler->filter('form')->form([
            '_username' => 'admin@example.com',
            '_password' => 'wrong-password',
        ]);
        $this->client->submit($form);

        $this->client->request('GET', '/admin');
        self::assertResponseRedirects('/login');
    }

    public function testAdminRedirectsAfterLogout(): void
    {
        $this->client->loginUser($this->makeUser('admin@example.com', ['ROLE_ADMIN']));

        $crawler = $this->client->request('GET', '/admin');
        $logoutUrl = (string) $crawler->filter('a[href*="/logout"]')->first()->attr('href');

        $this->client->request('GET', $logoutUrl);
        self::assertResponseRedirects('/login');

        $this->client->request('GET', '/admin');
        self::assertResponseRedirects('/login');
    }
}
