<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\AdminCreator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:create-admin', description: 'Створює адміністратора (пароль вводиться приховано)')]
final class CreateAdminCommand extends Command
{
    public function __construct(private readonly AdminCreator $creator)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('email', InputArgument::REQUIRED, 'Email адміністратора');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $password = $io->askHidden('Пароль');
        $repeat = $io->askHidden('Повторіть пароль');

        if (null === $password || '' === $password) {
            $io->error('Пароль не може бути порожнім.');

            return Command::FAILURE;
        }

        if ($password !== $repeat) {
            $io->error('Паролі не збігаються.');

            return Command::FAILURE;
        }

        try {
            $user = $this->creator->create((string) $input->getArgument('email'), $password);
        } catch (\InvalidArgumentException $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        $io->success(sprintf('Адміністратора %s створено.', $user->getEmail()));

        return Command::SUCCESS;
    }
}
