<?php

declare(strict_types=1);

namespace App\Tests\Unit\Twig;

use App\Twig\MoneyExtension;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MoneyExtensionTest extends TestCase
{
    #[DataProvider('cases')]
    public function testFormat(string|int $in, string $expected): void
    {
        self::assertSame($expected, (new MoneyExtension())->formatMoney($in));
    }

    /** @return iterable<string, array{string|int, string}> */
    public static function cases(): iterable
    {
        $n = "\u{00A0}";
        yield 'thousands' => ['1250.00', "1{$n}250,00{$n}грн"];
        yield 'one decimal' => ['10.5', "10,50{$n}грн"];
        yield 'integer' => ['7', "7,00{$n}грн"];
        yield 'zero' => ['0.00', "0,00{$n}грн"];
        yield 'million' => ['1000000.00', "1{$n}000{$n}000,00{$n}грн"];
        yield 'int' => [1250, "1{$n}250,00{$n}грн"];
        yield 'int zero' => [0, "0,00{$n}грн"];
        yield 'negative' => ['-1250.00', "-1{$n}250,00{$n}грн"];
        yield 'negative int' => [-5, "-5,00{$n}грн"];
        yield 'leading zeros' => ['007.5', "7,50{$n}грн"];
        yield 'negative string int' => ['-5', "-5,00{$n}грн"];
        yield 'long leading zeros' => ['0000000000000000005', "5,00{$n}грн"];
        yield 'negative long leading zeros' => ['-0000000000000000005', "-5,00{$n}грн"];
        yield 'negative zero' => ['-0', "0,00{$n}грн"];
        yield 'negative zero decimals' => ['-0.00', "0,00{$n}грн"];
    }

    /** @return iterable<string, array{string}> */
    public static function doubleSign(): iterable
    {
        yield 'double minus' => ['--5'];
        yield 'lone minus' => ['-'];
        yield 'minus plus' => ['-+5'];
    }

    #[DataProvider('doubleSign')]
    public function testBadSignThrows(string $in): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new MoneyExtension())->formatMoney($in);
    }

    #[DataProvider('tooLarge')]
    public function testTooLargeHasOwnMessage(string $in): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('завелика');

        (new MoneyExtension())->formatMoney($in);
    }

    /** @return iterable<string, array{string}> */
    public static function tooLarge(): iterable
    {
        yield '17 digits' => ['10000000000000000'];
        yield '17 digits with fraction' => ['10000000000000000.50'];
        yield 'negative 17 digits' => ['-10000000000000000'];
    }

    public function testNegativeTooManyDecimalsMessage(): void
    {
        $this->expectExceptionMessage('у сумі "-1.234" більше 2 знаків після крапки.');

        (new MoneyExtension())->formatMoney('-1.234');
    }

    /** @return iterable<string, array{mixed}> */
    public static function invalidInputs(): iterable
    {
        yield 'null' => [null];
        yield 'letters' => ['abc'];
        yield 'empty' => [''];
        yield 'exponent' => ['1e3'];
        yield 'comma decimal' => ['1,5'];
        yield 'three decimals' => ['1.234'];
        yield 'three decimals zeros' => ['10.000'];
        yield 'whitespace' => [' 1.50'];
        yield 'trailing newline' => ["1.50\n"];
        yield 'plus sign' => ['+1.50'];
        yield 'no fraction digits' => ['1.'];
        yield 'float' => [1.5];
        yield 'bool' => [true];
        yield 'array' => [['1']];
    }

    #[DataProvider('invalidInputs')]
    public function testInvalidInputThrows(mixed $in): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new MoneyExtension())->formatMoney($in);
    }

    public function testNullMessageIsClear(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('null');

        (new MoneyExtension())->formatMoney(null);
    }

    public function testTooManyDecimalsMessageMentionsLimit(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('2');

        (new MoneyExtension())->formatMoney('1.234');
    }

    public function testFilterRegistered(): void
    {
        self::assertSame('money', (new MoneyExtension())->getFilters()[0]->getName());
    }
}
