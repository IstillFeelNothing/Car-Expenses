<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\CarImporter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Path;

#[AsCommand(name: 'app:import-cars', description: 'Імпортує машини з JSON-файлу (upsert за slug)')]
final class ImportCarsCommand extends Command
{
    public function __construct(
        private readonly CarImporter $importer,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument(
            'file',
            InputArgument::OPTIONAL,
            'Шлях до JSON-файлу (відносний — від кореня проєкту)',
            'data/cars.json',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $file = (string) $input->getArgument('file');
        $path = Path::isAbsolute($file) ? $file : Path::join($this->projectDir, $file);

        try {
            $result = $this->importer->import($path);
        } catch (\InvalidArgumentException $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        $io->success(sprintf(
            'Import finished: created %d, updated %d, skipped %d.',
            $result->created,
            $result->updated,
            $result->skipped,
        ));

        if ($result->skipped > 0) {
            $io->warning(sprintf('Skipped %d invalid record(s): id, name and country must be non-empty strings.', $result->skipped));
        }

        return Command::SUCCESS;
    }
}
