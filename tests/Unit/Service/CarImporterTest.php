<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Car;
use App\Repository\CarRepository;
use App\Service\CarImporter;
use App\Service\ImportResult;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

final class CarImporterTest extends TestCase
{
    /** @var list<string> */
    private array $tmpFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->tmpFiles as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        $this->tmpFiles = [];
    }

    private function writeFixture(string $contents): string
    {
        $path = tempnam(sys_get_temp_dir(), 'car_import_');
        self::assertNotFalse($path);
        file_put_contents($path, $contents);
        $this->tmpFiles[] = $path;

        return $path;
    }

    /** @param array<mixed> $data */
    private function writeJson(array $data): string
    {
        return $this->writeFixture(json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
    }

    /**
     * @param array<string, Car> $existing existing cars by slug
     *
     * @return array{0: CarImporter, 1: EntityManagerInterface&MockObject, 2: CarRepository&Stub}
     */
    private function makeImporter(array $existing = [], bool $emIsStub = false): array
    {
        $em = $emIsStub
            ? $this->createStub(EntityManagerInterface::class)
            : $this->createMock(EntityManagerInterface::class);
        $repo = $this->createStub(CarRepository::class);
        $repo->method('findOneBy')->willReturnCallback(
            static fn (array $criteria): ?Car => $existing[$criteria['slug']] ?? null,
        );

        return [new CarImporter($em, $repo), $em, $repo];
    }

    private function car(string $slug, string $name, string $country): Car
    {
        return (new Car())->setSlug($slug)->setName($name)->setCountry($country);
    }

    public function testCreatesNewCars(): void
    {
        $path = $this->writeJson([
            ['id' => 'toyota', 'name' => 'Toyota', 'country' => 'Japan'],
            ['id' => 'bmw', 'name' => 'BMW', 'country' => 'Germany'],
        ]);
        [$importer, $em] = $this->makeImporter();

        $persisted = [];
        $em->expects(self::exactly(2))->method('persist')->willReturnCallback(
            static function (object $o) use (&$persisted): void {
                $persisted[] = $o;
            },
        );
        $em->expects(self::once())->method('flush');

        $result = $importer->import($path);

        self::assertInstanceOf(ImportResult::class, $result);
        self::assertSame(2, $result->created);
        self::assertSame(0, $result->updated);
        self::assertSame(0, $result->skipped);
        self::assertContainsOnlyInstancesOf(Car::class, $persisted);
        self::assertSame('toyota', $persisted[0]->getSlug());
        self::assertSame('Toyota', $persisted[0]->getName());
        self::assertSame('Japan', $persisted[0]->getCountry());
        self::assertSame('bmw', $persisted[1]->getSlug());
    }

    public function testUpdatesExistingCars(): void
    {
        $toyota = $this->car('toyota', 'Old', 'Old');
        $path = $this->writeJson([['id' => 'toyota', 'name' => 'Toyota', 'country' => 'Japan']]);
        [$importer, $em] = $this->makeImporter(['toyota' => $toyota]);

        $em->expects(self::never())->method('persist');
        $em->expects(self::once())->method('flush');

        $result = $importer->import($path);

        self::assertSame(0, $result->created);
        self::assertSame(1, $result->updated);
        self::assertSame(0, $result->skipped);
        self::assertSame('Toyota', $toyota->getName());
        self::assertSame('Japan', $toyota->getCountry());
    }

    public function testUnchangedExistingCarStillCountsAsUpdated(): void
    {
        $toyota = $this->car('toyota', 'Toyota', 'Japan');
        $path = $this->writeJson([['id' => 'toyota', 'name' => 'Toyota', 'country' => 'Japan']]);
        [$importer] = $this->makeImporter(['toyota' => $toyota], emIsStub: true);

        $result = $importer->import($path);

        self::assertSame(0, $result->created);
        self::assertSame(1, $result->updated);
    }

    public function testMixedCreatedUpdatedSkipped(): void
    {
        $toyota = $this->car('toyota', 'Old', 'Old');
        $path = $this->writeJson([
            ['id' => 'toyota', 'name' => 'Toyota', 'country' => 'Japan'],
            ['id' => 'bmw', 'name' => 'BMW', 'country' => 'Germany'],
            ['id' => 'broken', 'name' => 'Broken'],
        ]);
        [$importer, $em] = $this->makeImporter(['toyota' => $toyota]);

        $em->expects(self::once())->method('persist');
        $em->expects(self::once())->method('flush');

        $result = $importer->import($path);

        self::assertSame(1, $result->created);
        self::assertSame(1, $result->updated);
        self::assertSame(1, $result->skipped);
    }

    /** @return iterable<string, array{mixed}> */
    public static function invalidRecords(): iterable
    {
        yield 'missing id' => [['name' => 'Toyota', 'country' => 'Japan']];
        yield 'missing name' => [['id' => 'toyota', 'country' => 'Japan']];
        yield 'missing country' => [['id' => 'toyota', 'name' => 'Toyota']];
        yield 'empty id' => [['id' => '', 'name' => 'Toyota', 'country' => 'Japan']];
        yield 'empty name' => [['id' => 'toyota', 'name' => '', 'country' => 'Japan']];
        yield 'empty country' => [['id' => 'toyota', 'name' => 'Toyota', 'country' => '']];
        yield 'numeric id' => [['id' => 1, 'name' => 'Toyota', 'country' => 'Japan']];
        yield 'null name' => [['id' => 'toyota', 'name' => null, 'country' => 'Japan']];
        yield 'array country' => [['id' => 'toyota', 'name' => 'Toyota', 'country' => ['Japan']]];
        yield 'scalar record' => ['just a string'];
        yield 'null record' => [null];
        yield 'slug with uppercase and underscore' => [['id' => 'Bad_Slug', 'name' => 'Toyota', 'country' => 'Japan']];
        yield 'slug with space' => [['id' => 'My Car', 'name' => 'Toyota', 'country' => 'Japan']];
        yield 'slug uppercase' => [['id' => 'TOYOTA', 'name' => 'Toyota', 'country' => 'Japan']];
        yield 'slug inner space' => [['id' => 'toy ota', 'name' => 'Toyota', 'country' => 'Japan']];
        yield 'slug cyrillic' => [['id' => 'тойота', 'name' => 'Toyota', 'country' => 'Japan']];
        yield 'slug trailing newline' => [['id' => "toyota\n", 'name' => 'Toyota', 'country' => 'Japan']];
    }

    #[DataProvider('invalidRecords')]
    public function testSkipsInvalidRecord(mixed $record): void
    {
        $path = $this->writeJson([
            $record,
            ['id' => 'bmw', 'name' => 'BMW', 'country' => 'Germany'],
        ]);
        [$importer, $em] = $this->makeImporter();

        $em->expects(self::once())->method('persist');
        $em->expects(self::once())->method('flush');

        $result = $importer->import($path);

        self::assertSame(1, $result->created);
        self::assertSame(0, $result->updated);
        self::assertSame(1, $result->skipped);
    }

    public function testDuplicateSlugInFileUpdatesCreatedCar(): void
    {
        $path = $this->writeJson([
            ['id' => 'toyota', 'name' => 'Toyota', 'country' => 'Japan'],
            ['id' => 'toyota', 'name' => 'Toyota Motor', 'country' => 'Nippon'],
        ]);
        // Repository never sees unflushed entities, so it always returns null.
        [$importer, $em] = $this->makeImporter();

        $persisted = [];
        $em->expects(self::once())->method('persist')->willReturnCallback(
            static function (object $o) use (&$persisted): void {
                $persisted[] = $o;
            },
        );
        $em->expects(self::once())->method('flush');

        $result = $importer->import($path);

        self::assertSame(1, $result->created);
        self::assertSame(1, $result->updated);
        self::assertSame(0, $result->skipped);
        self::assertCount(1, $persisted);
        self::assertSame('Toyota Motor', $persisted[0]->getName());
        self::assertSame('Nippon', $persisted[0]->getCountry());
    }

    public function testEmptyArrayReturnsZeros(): void
    {
        $path = $this->writeJson([]);
        [$importer, $em] = $this->makeImporter();

        $em->expects(self::never())->method('persist');
        $em->expects(self::atMost(1))->method('flush');

        $result = $importer->import($path);

        self::assertSame(0, $result->created);
        self::assertSame(0, $result->updated);
        self::assertSame(0, $result->skipped);
    }

    public function testMissingFileThrows(): void
    {
        [$importer, $em] = $this->makeImporter();
        $em->expects(self::never())->method('flush');

        $this->expectException(\InvalidArgumentException::class);

        $importer->import(sys_get_temp_dir().'/does_not_exist_'.uniqid().'.json');
    }

    public function testUnreadablePathThrows(): void
    {
        [$importer, $em] = $this->makeImporter();
        $em->expects(self::never())->method('flush');

        $this->expectException(\InvalidArgumentException::class);

        // A directory is not a readable file.
        $importer->import(sys_get_temp_dir());
    }

    /** @return iterable<string, array{string}> */
    public static function invalidJsonDocuments(): iterable
    {
        yield 'broken json' => ['{"id": "toyota", '];
        yield 'empty file' => [''];
        yield 'object root' => ['{"id": "toyota", "name": "Toyota", "country": "Japan"}'];
        yield 'string root' => ['"toyota"'];
        yield 'number root' => ['42'];
        yield 'null root' => ['null'];
    }

    #[DataProvider('invalidJsonDocuments')]
    public function testInvalidJsonOrNonArrayRootThrows(string $contents): void
    {
        $path = $this->writeFixture($contents);
        [$importer, $em] = $this->makeImporter();
        $em->expects(self::never())->method('persist');
        $em->expects(self::never())->method('flush');

        $this->expectException(\InvalidArgumentException::class);

        $importer->import($path);
    }

    public function testFlushCalledExactlyOnceForManyRecords(): void
    {
        $records = [];
        for ($i = 1; $i <= 5; ++$i) {
            $records[] = ['id' => "car-$i", 'name' => "Car $i", 'country' => 'X'];
        }
        $path = $this->writeJson($records);
        [$importer, $em] = $this->makeImporter();

        $em->expects(self::exactly(5))->method('persist');
        $em->expects(self::once())->method('flush');

        $result = $importer->import($path);

        self::assertSame(5, $result->created);
    }

    public function testImportResultIsReadonlyValueObject(): void
    {
        $result = new ImportResult(created: 1, updated: 2, skipped: 3);

        self::assertSame(1, $result->created);
        self::assertSame(2, $result->updated);
        self::assertSame(3, $result->skipped);
        self::assertTrue((new \ReflectionClass(ImportResult::class))->isReadOnly());
    }
}
