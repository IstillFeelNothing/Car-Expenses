<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Service\UkrainianCollator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class UkrainianCollatorTest extends TestCase
{
    private const ALPHABET = 'абвгґдеєжзиіїйклмнопрстуфхцчшщьюя';

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function lessThanProvider(): iterable
    {
        yield 'а before б' => ['а', 'б'];
        yield 'г before ґ' => ['г', 'ґ'];
        yield 'ґ before д' => ['ґ', 'д'];
        yield 'г before д (ґ між ними)' => ['г', 'д'];
        yield 'е before є' => ['е', 'є'];
        yield 'є before ж' => ['є', 'ж'];
        yield 'и before і' => ['и', 'і'];
        yield 'і before ї' => ['і', 'ї'];
        yield 'ї before й' => ['ї', 'й'];
        yield 'й before к' => ['й', 'к'];
        yield 'ь before ю' => ['ь', 'ю'];
        yield 'ю before я' => ['ю', 'я'];
        yield 'second letter decides' => ['аб', 'ав'];
        yield 'first letter wins over rest' => ['бя', 'га'];
        yield 'ґума before дах' => ['ґума', 'дах'];
        yield 'гума before ґума' => ['гума', 'ґума'];
        yield 'мийка before ремонт' => ['мийка', 'ремонт'];
        yield 'єдиний before мийка' => ['єдиний', 'мийка'];
    }

    #[DataProvider('lessThanProvider')]
    public function testFirstIsLessThanSecond(string $a, string $b): void
    {
        $collator = new UkrainianCollator();

        self::assertLessThan(0, $collator->compare($a, $b));
        self::assertGreaterThan(0, $collator->compare($b, $a));
    }

    public function testWholeAlphabetIsInOrderLetterByLetter(): void
    {
        $collator = new UkrainianCollator();
        $letters = mb_str_split(self::ALPHABET);

        for ($i = 1, $n = count($letters); $i < $n; ++$i) {
            self::assertLessThan(
                0,
                $collator->compare($letters[$i - 1], $letters[$i]),
                sprintf('"%s" має бути перед "%s"', $letters[$i - 1], $letters[$i]),
            );
        }
    }

    public function testGHasUkrainianPlaceBetweenGAndD(): void
    {
        $collator = new UkrainianCollator();

        // За кодовими точками ґ (U+0491) була б після я — тут має бути між г і д.
        self::assertLessThan(0, $collator->compare('ґ', 'д'));
        self::assertGreaterThan(0, $collator->compare('ґ', 'г'));
        self::assertLessThan(0, $collator->compare('ґ', 'я'));
    }

    public function testShorterStringComesFirstOnEqualPrefix(): void
    {
        $collator = new UkrainianCollator();

        self::assertLessThan(0, $collator->compare('авто', 'автомобіль'));
        self::assertGreaterThan(0, $collator->compare('автомобіль', 'авто'));
        self::assertLessThan(0, $collator->compare('', 'а'));
        self::assertGreaterThan(0, $collator->compare('а', ''));
    }

    public function testDifferenceBeforeEndBeatsLength(): void
    {
        $collator = new UkrainianCollator();

        // 'аб' коротше за 'ав...', але порівняння йде по першій відмінній літері.
        self::assertLessThan(0, $collator->compare('аб', 'авхххх'));
        self::assertGreaterThan(0, $collator->compare('авхххх', 'аб'));
    }

    #[DataProvider('equalProvider')]
    public function testEqualStringsReturnZero(string $value): void
    {
        self::assertSame(0, (new UkrainianCollator())->compare($value, $value));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function equalProvider(): iterable
    {
        yield 'empty' => [''];
        yield 'single letter' => ['ї'];
        yield 'word' => ['пальне'];
        yield 'with space' => ['єдиний податок'];
        yield 'latin' => ['fuel'];
        yield 'digits' => ['2026'];
        yield 'mixed' => ['шини r17'];
    }

    public function testNonAlphabetCharactersGoAfterCyrillicLetters(): void
    {
        $collator = new UkrainianCollator();

        self::assertLessThan(0, $collator->compare('я', 'a'));
        self::assertLessThan(0, $collator->compare('я', 'z'));
        self::assertLessThan(0, $collator->compare('я', '0'));
        self::assertLessThan(0, $collator->compare('ю', '9'));
        self::assertGreaterThan(0, $collator->compare('fuel', 'пальне'));
        self::assertGreaterThan(0, $collator->compare('1', 'я'));
    }

    public function testDigitsGoBeforeLatinLettersByCodePoint(): void
    {
        $collator = new UkrainianCollator();

        self::assertLessThan(0, $collator->compare('1', '2'));
        self::assertLessThan(0, $collator->compare('9', 'a'));
        self::assertLessThan(0, $collator->compare('a', 'b'));
    }

    public function testSpaceGoesAfterLettersAndBeforeDigits(): void
    {
        $collator = new UkrainianCollator();

        // 'a b' та 'ab': пробіл (32+1000) після літери б (індекс 1) -> 'аб' < 'а ' ; пробіл < '0'.
        self::assertLessThan(0, $collator->compare('аб', 'а '));
        self::assertLessThan(0, $collator->compare('а ', 'а0'));
    }

    public function testNonAlphabetCharInsideWordKeepsStablePosition(): void
    {
        $collator = new UkrainianCollator();

        self::assertLessThan(0, $collator->compare('а-мала', 'б-середня'));
        self::assertLessThan(0, $collator->compare('шини', 'шини2'));
        self::assertLessThan(0, $collator->compare('шини', 'шини r17'));
        self::assertLessThan(0, $collator->compare('шини1', 'шини2'));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function symmetryProvider(): iterable
    {
        yield 'letters' => ['а', 'я'];
        yield 'ґ and д' => ['ґ', 'д'];
        yield 'prefix' => ['авто', 'автомобіль'];
        yield 'equal' => ['мийка', 'мийка'];
        yield 'latin vs cyrillic' => ['fuel', 'пальне'];
        yield 'empty vs word' => ['', 'пальне'];
        yield 'digits' => ['10', '2'];
    }

    #[DataProvider('symmetryProvider')]
    public function testCompareIsAntisymmetric(string $a, string $b): void
    {
        $collator = new UkrainianCollator();

        self::assertSame(
            $collator->compare($a, $b) <=> 0,
            -($collator->compare($b, $a) <=> 0),
        );
    }

    public function testCompareReturnsInt(): void
    {
        self::assertIsInt((new UkrainianCollator())->compare('а', 'б'));
    }

    public function testUsortProducesUkrainianOrder(): void
    {
        $words = ['шини', 'ґума', 'мийка', 'єдиний податок', 'гума', 'дах', 'їжа', 'іграшки', 'йод', 'ремонт', 'ага'];
        $collator = new UkrainianCollator();

        usort($words, $collator->compare(...));

        self::assertSame(
            ['ага', 'гума', 'ґума', 'дах', 'єдиний податок', 'іграшки', 'їжа', 'йод', 'мийка', 'ремонт', 'шини'],
            $words,
        );
    }

    public function testUsortPutsLatinAndDigitsAfterCyrillic(): void
    {
        $words = ['fuel', 'ремонт', '2026', 'аренда', 'abc', 'я'];
        $collator = new UkrainianCollator();

        usort($words, $collator->compare(...));

        self::assertSame(['аренда', 'ремонт', 'я', '2026', 'abc', 'fuel'], $words);
    }

    public function testUsortIsIndependentOfInitialOrder(): void
    {
        $words = ['авто', 'автомобіль', 'ґанок', 'гараж', 'дім', 'a', '1'];
        $collator = new UkrainianCollator();

        $forward = $words;
        $backward = array_reverse($words);
        usort($forward, $collator->compare(...));
        usort($backward, $collator->compare(...));

        self::assertSame(['авто', 'автомобіль', 'гараж', 'ґанок', 'дім', '1', 'a'], $forward);
        self::assertSame($forward, $backward);
    }

    public function testInstancesShareSameOrdering(): void
    {
        // Порядок абетки кешується статично — другий екземпляр поводиться так само.
        $first = new UkrainianCollator();
        $second = new UkrainianCollator();

        self::assertSame($first->compare('ґ', 'д'), $second->compare('ґ', 'д'));
        self::assertSame($first->compare('є', 'е'), $second->compare('є', 'е'));
    }
}
