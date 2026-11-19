<?php

declare(strict_types=1);

namespace App\Service;

final class UkrainianCollator
{
    private const UKRAINIAN_ALPHABET = 'абвгґдеєжзиіїйклмнопрстуфхцчшщьюя';

    /** @var array<string,int>|null */
    private static ?array $charOrder = null;

    public function compare(string $a, string $b): int
    {
        $order = self::$charOrder ??= array_flip(mb_str_split(self::UKRAINIAN_ALPHABET));

        $ca = mb_str_split($a);
        $cb = mb_str_split($b);
        $len = min(count($ca), count($cb));
        for ($i = 0; $i < $len; ++$i) {
            $ka = $order[$ca[$i]] ?? mb_ord($ca[$i]) + 1000;
            $kb = $order[$cb[$i]] ?? mb_ord($cb[$i]) + 1000;
            if ($ka !== $kb) {
                return $ka <=> $kb;
            }
        }

        return count($ca) <=> count($cb);
    }
}
