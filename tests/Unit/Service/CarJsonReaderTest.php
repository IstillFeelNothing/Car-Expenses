<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Service\CarJsonReader;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CarJsonReaderTest extends TestCase
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
        $path = tempnam(sys_get_temp_dir(), 'car_reader_');
        self::assertNotFalse($path);
        file_put_contents($path, $contents);
        $this->tmpFiles[] = $path;

        return $path;
    }

    public function testMissingFileThrows(): void
    {
        $path = sys_get_temp_dir().'/does_not_exist_'.uniqid().'.json';

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(sprintf('File "%s" does not exist or is not readable.', $path));

        (new CarJsonReader())->read($path);
    }

    public function testDirectoryIsNotReadableFile(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('does not exist or is not readable.');

        (new CarJsonReader())->read(sys_get_temp_dir());
    }

    public function testEmptyFileThrowsInvalidJson(): void
    {
        $path = $this->writeFixture('');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(sprintf('Invalid JSON in "%s": ', $path));

        (new CarJsonReader())->read($path);
    }

    public function testBrokenJsonThrowsWithPreviousJsonException(): void
    {
        $path = $this->writeFixture('{"id": "toyota", ');

        try {
            (new CarJsonReader())->read($path);
            self::fail('Expected exception was not thrown.');
        } catch (\InvalidArgumentException $e) {
            self::assertStringStartsWith(sprintf('Invalid JSON in "%s": ', $path), $e->getMessage());
            self::assertInstanceOf(\JsonException::class, $e->getPrevious());
        }
    }

    /** @return iterable<string, array{string}> */
    public static function nonListRoots(): iterable
    {
        yield 'object root' => ['{"id": "toyota", "name": "Toyota", "country": "Japan"}'];
        yield 'string root' => ['"toyota"'];
        yield 'number root' => ['42'];
        yield 'null root' => ['null'];
        yield 'bool root' => ['true'];
    }

    #[DataProvider('nonListRoots')]
    public function testNonListRootThrows(string $contents): void
    {
        $path = $this->writeFixture($contents);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('JSON root must be an array of cars.');

        (new CarJsonReader())->read($path);
    }

    public function testReturnsValidList(): void
    {
        $path = $this->writeFixture('[{"id":"toyota","name":"Toyota","country":"Japan"},{"id":"bmw","name":"BMW","country":"Germany"}]');

        self::assertSame([
            ['id' => 'toyota', 'name' => 'Toyota', 'country' => 'Japan'],
            ['id' => 'bmw', 'name' => 'BMW', 'country' => 'Germany'],
        ], (new CarJsonReader())->read($path));
    }

    public function testEmptyListIsValid(): void
    {
        self::assertSame([], (new CarJsonReader())->read($this->writeFixture('[]')));
    }

    public function testNonStandardElementsAreReturnedAsIs(): void
    {
        $path = $this->writeFixture('["str", 1, null, true, [1, 2], {"id": 5}]');

        self::assertSame(['str', 1, null, true, [1, 2], ['id' => 5]], (new CarJsonReader())->read($path));
    }
}
