<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Car;
use App\Repository\CarRepository;
use Doctrine\ORM\EntityManagerInterface;

final class CarImporter
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly CarRepository $cars,
    ) {
    }

    /**
     * @throws \InvalidArgumentException when the file is missing/unreadable or is not a JSON array
     */
    public function import(string $jsonPath): ImportResult
    {
        if (!is_file($jsonPath) || !is_readable($jsonPath)) {
            throw new \InvalidArgumentException(sprintf('File "%s" does not exist or is not readable.', $jsonPath));
        }

        $contents = @file_get_contents($jsonPath);
        if (false === $contents) {
            throw new \InvalidArgumentException(sprintf('Cannot read file "%s".', $jsonPath));
        }

        try {
            $data = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new \InvalidArgumentException(sprintf('Invalid JSON in "%s": %s', $jsonPath, $e->getMessage()), 0, $e);
        }

        // An object root decodes to an associative array, so require a list.
        if (!is_array($data) || !array_is_list($data)) {
            throw new \InvalidArgumentException('JSON root must be an array of cars.');
        }

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

            $car = $seen[$slug] ?? $this->cars->findOneBy(['slug' => $slug]);

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
