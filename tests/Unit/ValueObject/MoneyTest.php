<?php

declare(strict_types=1);

namespace App\Tests\Unit\ValueObject;

use App\ValueObject\Money;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MoneyTest extends TestCase
{
    /** @return iterable<string,array{string,string}> */
    public static function validAmounts(): iterable
    {
        yield 'integer' => ['1250', '1250.00'];
        yield 'one decimal' => ['10.5', '10.50'];
        yield 'two decimals' => ['10.05', '10.05'];
        yield 'zero' => ['0', '0.00'];
        yield 'cents only' => ['0.07', '0.07'];
        yield 'leading zeros' => ['007.5', '7.50'];
        yield 'large' => ['999999999999.99', '999999999999.99'];
    }

    #[DataProvider('validAmounts')]
    public function testParsesAndNormalizes(string $input, string $expected): void
    {
        self::assertSame($expected, Money::fromString($input)->toString());
        self::assertSame($expected, (string) Money::fromString($input));
    }

    /** @return iterable<string,array{string}> */
    public static function invalidAmounts(): iterable
    {
        yield 'empty' => [''];
        yield 'letters' => ['abc'];
        yield 'negative' => ['-1'];
        yield 'three decimals' => ['1.234'];
        yield 'comma' => ['1,5'];
        yield 'trailing dot' => ['1.'];
        yield 'leading dot' => ['.5'];
        yield 'exponent' => ['1e3'];
        yield 'leading space' => [' 1.00'];
        yield 'trailing newline' => ["1.00\n"];
        yield 'too many digits' => ['99999999999999999999'];
    }

    #[DataProvider('invalidAmounts')]
    public function testRejectsInvalid(string $input): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Money::fromString($input);
    }

    #[DataProvider('validAmounts')]
    public function testIsValidAccepts(string $input, string $normalized): void
    {
        self::assertTrue(Money::isValid($input));
    }

    #[DataProvider('invalidAmounts')]
    public function testIsValidRejects(string $input): void
    {
        self::assertFalse(Money::isValid($input));
    }

    public function testDecimalPlaces(): void
    {
        self::assertSame(0, Money::decimalPlaces('12'));
        self::assertSame(1, Money::decimalPlaces('1.5'));
        self::assertSame(3, Money::decimalPlaces('1.234'));
        self::assertNull(Money::decimalPlaces('-1'));
        self::assertNull(Money::decimalPlaces('abc'));
        self::assertNull(Money::decimalPlaces('1.'));
    }

    /** @return iterable<string,array{string,string}> */
    public static function groupedCases(): iterable
    {
        $n = "\u{00A0}";
        yield 'zero' => ['0', "0,00{$n}грн"];
        yield 'cents' => ['0.07', "0,07{$n}грн"];
        yield 'one decimal' => ['10.5', "10,50{$n}грн"];
        yield 'hundreds' => ['999.99', "999,99{$n}грн"];
        yield 'thousands' => ['1250.5', "1{$n}250,50{$n}грн"];
        yield 'million' => ['1000000', "1{$n}000{$n}000,00{$n}грн"];
        yield 'leading zeros' => ['007.5', "7,50{$n}грн"];
    }

    #[DataProvider('groupedCases')]
    public function testFormatGrouped(string $input, string $expected): void
    {
        self::assertSame($expected, Money::fromString($input)->formatGrouped());
    }

    public function testMessagesDistinguishInvalidAndTooLarge(): void
    {
        try {
            Money::fromString('abc');
            self::fail('Expected exception');
        } catch (\InvalidArgumentException $e) {
            self::assertSame('Invalid amount "abc".', $e->getMessage());
        }
        try {
            Money::fromString('99999999999999999');
            self::fail('Expected exception');
        } catch (\InvalidArgumentException $e) {
            self::assertSame('Amount "99999999999999999" is too large.', $e->getMessage());
        }
    }

    public function testLeadingZerosDoNotCountTowardsLimit(): void
    {
        self::assertSame('5.00', Money::fromString('0000000000000000005')->toString());
        self::assertTrue(Money::isValid('0000000000000000005'));
        self::assertFalse(Money::isTooLarge('0000000000000000005'));
    }

    public function testIsTooLarge(): void
    {
        self::assertTrue(Money::isTooLarge('99999999999999999'));
        self::assertTrue(Money::isTooLarge('99999999999999999.50'));
        self::assertFalse(Money::isTooLarge('9999999999999999'));
        self::assertFalse(Money::isTooLarge('abc'));
        self::assertFalse(Money::isTooLarge('99999999999999999.123'));
    }

    public function testZero(): void
    {
        self::assertSame('0.00', Money::zero()->toString());
        self::assertSame(0, Money::zero()->compareTo(Money::fromString('0')));
    }

    public function testAddCarriesCents(): void
    {
        self::assertSame('1.00', Money::fromString('0.99')->add(Money::fromString('0.01'))->toString());
        self::assertSame('15.00', Money::fromString('10.50')->add(Money::fromString('4.50'))->toString());
    }

    public function testAddIsImmutable(): void
    {
        $a = Money::fromString('1.00');
        $a->add(Money::fromString('2.00'));
        self::assertSame('1.00', $a->toString());
    }

    public function testAddLargeAmounts(): void
    {
        $sum = Money::fromString('1000000000000.50')->add(Money::fromString('2000000000000.75'));
        self::assertSame('3000000000001.25', $sum->toString());
    }

    public function testOverflowThrows(): void
    {
        $big = Money::fromString('9999999999999999.99');
        $this->expectException(\OverflowException::class);
        $sum = Money::zero();
        for ($i = 0; $i < 1000; ++$i) {
            $sum = $sum->add($big);
        }
    }

    public function testComparison(): void
    {
        $a = Money::fromString('1.01');
        $b = Money::fromString('1.1');
        self::assertTrue($b->isGreaterThan($a));
        self::assertFalse($a->isGreaterThan($b));
        self::assertFalse($a->isGreaterThan(Money::fromString('1.01')));
        self::assertSame(-1, $a->compareTo($b));
        self::assertSame(1, $b->compareTo($a));
        self::assertSame(0, $a->compareTo(Money::fromString('1.01')));
    }
}
