<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Car;
use App\Repository\Contract\CarBySlugFinder;
use Doctrine\ORM\EntityManagerInterface;

final class CarImporter
{
    public function __construct(
        private readonly CarJsonReader $reader,
        private readonly EntityManagerInterface $em,
        private readonly CarBySlugFinder $cars,
    ) {
    }

    /**
     * @throws \InvalidArgumentException when the file is missing/unreadable or is not a JSON array
     */
    public function import(string $jsonPath): ImportResult
    {
        $data = $this->reader->read($jsonPath);

        $created = $updated = $skipped = 0;
        /** @var array<string, Car> $seen */
        $seen = [];

        foreach ($data as $record) {
            if (!is_array($record)) {
                ++$skipped;
                continue;
            }

            $slug = $record['id'] ?? null;
            $name = $record['name'] ?? null;
            $country = $record['country'] ?? null;

            if (
                !self::isNonEmptyString($slug) || !self::isNonEmptyString($name) || !self::isNonEmptyString($country)
                || !Car::isValidSlug($slug)
            ) {
                ++$skipped;
                continue;
            }

            $car = $seen[$slug] ?? $this->cars->findOneBySlug($slug);

            if (null === $car) {
                $car = (new Car())->setSlug($slug);
                $this->em->persist($car);
                ++$created;
            } else {
                ++$updated;
            }

            $car->setName($name)->setCountry($country);
            $seen[$slug] = $car;
        }

        $this->em->flush();

        return new ImportResult($created, $updated, $skipped);
    }

    /** @phpstan-assert-if-true non-empty-string $value */
    private static function isNonEmptyString(mixed $value): bool
    {
        return is_string($value) && '' !== $value;
    }
}
