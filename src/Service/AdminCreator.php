<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class AdminCreator
{
    private const MIN_PASSWORD_LENGTH = 8;
    private const MAX_PASSWORD_BYTES = 4096;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $hasher,
        private readonly UserRepository $users,
    ) {
    }

    /**
     * @throws \InvalidArgumentException якщо email некоректний, пароль занадто короткий або користувач вже існує
     */
    public function create(string $email, string $plainPassword): User
    {
        $email = mb_strtolower(trim($email));

        if (false === filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Некоректний email.');
        }

        if (mb_strlen($email) > User::EMAIL_MAX_LENGTH) {
            throw new \InvalidArgumentException(sprintf('Email не може бути довшим за %d символів.', User::EMAIL_MAX_LENGTH));
        }

        if ('' === trim($plainPassword)) {
            throw new \InvalidArgumentException('Пароль не може складатися лише з пробілів.');
        }

        if (mb_strlen($plainPassword) < self::MIN_PASSWORD_LENGTH) {
            throw new \InvalidArgumentException(sprintf('Пароль має містити щонайменше %d символів.', self::MIN_PASSWORD_LENGTH));
        }

        if (strlen($plainPassword) > self::MAX_PASSWORD_BYTES) {
            throw new \InvalidArgumentException(sprintf('Пароль не може бути довшим за %d байт.', self::MAX_PASSWORD_BYTES));
        }

        $duplicate = new \InvalidArgumentException(sprintf('Користувач з email "%s" вже існує.', $email));

        if (null !== $this->users->findOneBy(['email' => $email])) {
            throw $duplicate;
        }

        $user = (new User())->setEmail($email)->setRoles(['ROLE_ADMIN']);
        $user->setPassword($this->hasher->hashPassword($user, $plainPassword));

        $this->em->persist($user);

        try {
            $this->em->flush();
        } catch (UniqueConstraintViolationException) {
            // Гонка: паралельний запит встиг створити користувача між перевіркою та flush.
            throw $duplicate;
        }

        return $user;
    }
}
