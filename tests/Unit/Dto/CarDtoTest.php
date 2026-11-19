<?php

declare(strict_types=1);

namespace App\Tests\Unit\Dto;

use App\Dto\CarDto;
use App\Entity\Car;
use PHPUnit\Framework\TestCase;

final class CarDtoTest extends TestCase
{
    private function car(): Car
    {
        return (new Car())->setSlug('toyota')->setName('Toyota')->setCountry('Japan');
    }

    public function testImplementsJsonSerializable(): void
    {
        self::assertInstanceOf(\JsonSerializable::class, CarDto::fromEntity($this->car()));
    }

    public function testKeysAndOrder(): void
    {
        $data = CarDto::fromEntity($this->car())->jsonSerialize();

        self::assertSame(['id', 'name', 'country'], array_keys($data));
    }

    public function testIdIsSlugString(): void
    {
        $data = CarDto::fromEntity($this->car())->jsonSerialize();

        self::assertSame('toyota', $data['id']);
        self::assertSame('Toyota', $data['name']);
        self::assertSame('Japan', $data['country']);
    }

    public function testJsonEncode(): void
    {
        self::assertSame(
            '{"id":"toyota","name":"Toyota","country":"Japan"}',
            json_encode(CarDto::fromEntity($this->car()), JSON_THROW_ON_ERROR),
        );
    }

    public function testIdIsSlugEvenWhenNumericIdSet(): void
    {
        $car = $this->car();
        (new \ReflectionProperty(Car::class, 'id'))->setValue($car, 42);

        self::assertSame('toyota', CarDto::fromEntity($car)->jsonSerialize()['id']);
    }
}
