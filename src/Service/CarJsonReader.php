<?php

declare(strict_types=1);

namespace App\Service;

final class CarJsonReader
{
    /**
     * @return list<mixed> raw records of the JSON array, untouched
     *
     * @throws \InvalidArgumentException when the file is missing/unreadable or is not a JSON array
     */
    public function read(string $jsonPath): array
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

        return $data;
    }
}
