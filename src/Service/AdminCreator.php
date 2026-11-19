<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Repository\Contract\UserByEmailFinder;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class AdminCreator
{
    private const MIN_PASSWORD_LENGTH = 8;
    private const MAX_PASSWORD_BYTES = 4096;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $hasher,
        private readonly UserByEmailFinder $users,
        private readonly ValidatorInterface $validator,
    ) {
    }

    /**
     * @throws \InvalidArgumentException якщо email некоректний, пароль занадто короткий або користувач вже існує
     */
    public function create(string $email, string $plainPassword): User
    {
        $email = mb_strtolower(trim($email));

        $this->assertValid($email, [
            new Assert\NotBlank(message: 'Некоректний email.'),
            new Assert\Email(message: 'Некоректний email.', mode: Assert\Email::VALIDATION_MODE_HTML5),
            new Assert\Length(
                max: User::EMAIL_MAX_LENGTH,
                maxMessage: sprintf('Email не може бути довшим за %d символів.', User::EMAIL_MAX_LENGTH),
            ),
        ]);

        $this->assertValid($plainPassword, [
            new Assert\NotBlank(message: 'Пароль не може складатися лише з пробілів.', normalizer: 'trim'),
            new Assert\Length(
                min: self::MIN_PASSWORD_LENGTH,
                minMessage: sprintf('Пароль має містити щонайменше %d символів.', self::MIN_PASSWORD_LENGTH),
            ),
            new Assert\Length(
                max: self::MAX_PASSWORD_BYTES,
                maxMessage: sprintf('Пароль не може бути довшим за %d байт.', self::MAX_PASSWORD_BYTES),
                countUnit: Assert\Length::COUNT_BYTES,
            ),
        ]);

        $duplicate = new \InvalidArgumentException(sprintf('Користувач з email "%s" вже існує.', $email));

        if (null !== $this->users->findOneByEmail($email)) {
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

    /**
     * @param list<Constraint> $constraints
     */
    private function assertValid(string $value, array $constraints): void
    {
        $violations = $this->validator->validate($value, $constraints);

        if (count($violations) > 0) {
            throw new \InvalidArgumentException((string) $violations[0]->getMessage());
        }
    }
}
