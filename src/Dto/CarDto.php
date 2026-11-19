<?php

declare(strict_types=1);

namespace App\Dto;

use App\Entity\Car;

final readonly class CarDto implements \JsonSerializable
{
    public function __construct(
        public string $id,
        public string $name,
        public string $country,
    ) {
    }

    /** `id` у контракті API — це slug, а не числовий id. */
    public static function fromEntity(Car $car): self
    {
        return new self((string) $car->getSlug(), (string) $car->getName(), (string) $car->getCountry());
    }

    /** @return array{id: string, name: string, country: string} */
    public function jsonSerialize(): array
    {
        return ['id' => $this->id, 'name' => $this->name, 'country' => $this->country];
    }
}
