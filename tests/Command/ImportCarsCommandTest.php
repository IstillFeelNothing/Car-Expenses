<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Entity\Car;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class ImportCarsCommandTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private CommandTester $tester;
    private string $projectDir;
    private string $originalCwd;

    /** @var list<string> */
    private array $tmpFiles = [];

    /** @var list<string> */
    private array $tmpDirs = [];

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();
        $this->em = $container->get(EntityManagerInterface::class);
        $this->projectDir = self::$kernel->getProjectDir();
        $this->originalCwd = (string) getcwd();
        $this->clearCars();

        $command = (new Application(self::$kernel))->find('app:import-cars');
        $this->tester = new CommandTester($command);
    }

    protected function tearDown(): void
    {
        chdir($this->originalCwd);
        $this->clearCars();
        foreach ($this->tmpFiles as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        foreach ($this->tmpDirs as $dir) {
            if (is_dir($dir)) {
                rmdir($dir);
            }
        }
        parent::tearDown();
    }

    private function clearCars(): void
    {
        $this->em->clear();
        $this->em->createQuery('DELETE FROM '.Car::class)->execute();
        $this->em->clear();
    }

    private function writeFile(string $contents, ?string $dir = null): string
    {
        $path = tempnam($dir ?? sys_get_temp_dir(), 'car_cmd_');
        self::assertNotFalse($path);
        file_put_contents($path, $contents);
        $this->tmpFiles[] = $path;

        return $path;
    }

    /** @param array<mixed> $data */
    private function writeJson(array $data, ?string $dir = null): string
    {
        return $this->writeFile(json_encode($data, JSON_THROW_ON_ERROR), $dir);
    }

    /** @return list<array<string, string>> */
    private function sampleCars(): array
    {
        return [
            ['id' => 'toyota', 'name' => 'Toyota', 'country' => 'Japan'],
            ['id' => 'bmw', 'name' => 'BMW', 'country' => 'Germany'],
        ];
    }

    private function carCount(): int
    {
        $this->em->clear();

        return $this->em->getRepository(Car::class)->count([]);
    }

    public function testImportsCarsFromJsonFile(): void
    {
        $path = $this->writeJson($this->sampleCars());

        $this->tester->execute(['file' => $path]);

        self::assertSame(Command::SUCCESS, $this->tester->getStatusCode());
        $output = $this->tester->getDisplay();
        self::assertStringContainsStringIgnoringCase('created', $output);
        self::assertStringContainsStringIgnoringCase('updated', $output);
        self::assertStringContainsStringIgnoringCase('skipped', $output);
        self::assertMatchesRegularExpression('/created\D*2/i', $output);

        self::assertSame(2, $this->carCount());
        $toyota = $this->em->getRepository(Car::class)->findOneBy(['slug' => 'toyota']);
        self::assertNotNull($toyota);
        self::assertSame('Toyota', $toyota->getName());
        self::assertSame('Japan', $toyota->getCountry());
    }

    public function testSecondRunIsIdempotent(): void
    {
        $path = $this->writeJson($this->sampleCars());

        $this->tester->execute(['file' => $path]);
        $this->tester->execute(['file' => $path]);

        self::assertSame(Command::SUCCESS, $this->tester->getStatusCode());
        $output = $this->tester->getDisplay();
        self::assertMatchesRegularExpression('/created\D*0/i', $output);
        self::assertMatchesRegularExpression('/updated\D*2/i', $output);
        self::assertSame(2, $this->carCount());
    }

    public function testFileArgumentDefaultsToDataCarsJson(): void
    {
        $definition = (new Application(self::$kernel))->find('app:import-cars')->getDefinition();

        self::assertTrue($definition->hasArgument('file'));
        self::assertFalse($definition->getArgument('file')->isRequired());
        self::assertSame('data/cars.json', $definition->getArgument('file')->getDefault());
    }

    public function testRelativePathIsResolvedFromProjectDirNotCwd(): void
    {
        $dir = $this->projectDir.'/var/import_cmd_test_'.uniqid();
        mkdir($dir, 0777, true);
        $this->tmpDirs[] = $dir;
        $path = $this->writeJson($this->sampleCars(), $dir);
        $relative = 'var/'.basename($dir).'/'.basename($path);

        // cwd is elsewhere: a cwd-based resolution would not find the file.
        chdir(sys_get_temp_dir());
        $this->tester->execute(['file' => $relative]);

        self::assertSame(Command::SUCCESS, $this->tester->getStatusCode());
        self::assertSame(2, $this->carCount());
    }

    public function testMissingFileFailsWithErrorMessage(): void
    {
        $this->tester->execute(['file' => sys_get_temp_dir().'/no_such_file_'.uniqid().'.json']);

        self::assertSame(Command::FAILURE, $this->tester->getStatusCode());
        $output = $this->tester->getDisplay();
        self::assertStringContainsString('ERROR', $output);
        self::assertStringContainsString('does not exist', $output);
        self::assertStringNotContainsString('Stack trace', $output);
        self::assertSame(0, $this->carCount());
    }

    public function testInvalidJsonFailsWithErrorMessage(): void
    {
        $path = $this->writeFile('{"broken": ');

        $this->tester->execute(['file' => $path]);

        self::assertSame(Command::FAILURE, $this->tester->getStatusCode());
        $output = $this->tester->getDisplay();
        self::assertStringContainsString('ERROR', $output);
        self::assertStringContainsString('Invalid JSON', $output);
        self::assertStringNotContainsString('Stack trace', $output);
    }

    public function testSkippedRecordsShowWarningButDoNotFail(): void
    {
        $path = $this->writeJson([
            ['id' => 'toyota', 'name' => 'Toyota', 'country' => 'Japan'],
            ['id' => 'broken', 'name' => 'No country'],
        ]);

        $this->tester->execute(['file' => $path]);

        self::assertSame(Command::SUCCESS, $this->tester->getStatusCode());
        $output = $this->tester->getDisplay();
        self::assertStringContainsString('WARNING', $output);
        self::assertMatchesRegularExpression('/skipped\D*1/i', $output);
        self::assertSame(1, $this->carCount());
    }
}
