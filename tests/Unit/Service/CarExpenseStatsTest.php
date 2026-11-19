<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Car;
use App\Entity\Expense;
use App\Service\CarExpenseStats;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CarExpenseStatsTest extends TestCase
{
    private function makeCar(): Car
    {
        return (new Car())->setSlug('toyota')->setName('Toyota')->setCountry('Japan');
    }

    private function addExpense(Car $car, string $amount, string $category = 'пальне', string $date = '2026-01-01'): void
    {
        $car->addExpense(
            (new Expense())
                ->setAmount($amount)
                ->setCategory($category)
                ->setDate(new \DateTimeImmutable($date))
        );
    }

    /**
     * @param list<string> $amounts
     */
    private function carWithAmounts(array $amounts): Car
    {
        $car = $this->makeCar();
        foreach ($amounts as $amount) {
            $this->addExpense($car, $amount);
        }

        return $car;
    }

    // ---- total() ----

    public function testTotalSumsAmounts(): void
    {
        $car = $this->makeCar();
        $this->addExpense($car, '10.50', 'пальне', '2026-01-01');
        $this->addExpense($car, '4.50', 'мийка', '2026-01-02');

        self::assertSame('15.00', (new CarExpenseStats())->total($car));
    }

    public function testTotalOfCarWithoutExpensesIsZero(): void
    {
        self::assertSame('0.00', (new CarExpenseStats())->total($this->makeCar()));
    }

    /**
     * @param list<string> $amounts
     */
    #[DataProvider('totalProvider')]
    public function testTotalIsExactStringWithTwoDecimals(array $amounts, string $expected): void
    {
        $result = (new CarExpenseStats())->total($this->carWithAmounts($amounts));

        self::assertSame($expected, $result);
        self::assertMatchesRegularExpression('/^\d+\.\d{2}$/', $result);
    }

    /**
     * @return iterable<string, array{list<string>, string}>
     */
    public static function totalProvider(): iterable
    {
        yield 'single' => [['12.34'], '12.34'];
        yield 'integers without decimals' => [['10', '5'], '15.00'];
        yield 'one decimal digit' => [['0.5', '0.5'], '1.00'];
        yield 'limit plus one cent' => [['1000000.00', '0.01'], '1000000.01'];
        yield 'three tenths no float error' => [['0.10', '0.10', '0.10'], '0.30'];
        yield 'many cents' => [array_fill(0, 100, '0.01'), '1.00'];
        yield 'classic float trap' => [['0.1', '0.2'], '0.30'];
    }

    public function testTotalDoesNotDependOnInsertionOrder(): void
    {
        $amounts = ['0.10', '999.99', '12.34', '0.01', '1000000.00'];

        $forward = (new CarExpenseStats())->total($this->carWithAmounts($amounts));
        $backward = (new CarExpenseStats())->total($this->carWithAmounts(array_reverse($amounts)));

        self::assertSame('1001012.44', $forward);
        self::assertSame($forward, $backward);
    }

    // ---- totalForPeriod() ----

    public function testTotalForPeriodIncludesFromAndExcludesTo(): void
    {
        $car = $this->makeCar();
        $this->addExpense($car, '1.00', 'пальне', '2026-01-01'); // == from, входить
        $this->addExpense($car, '2.00', 'пальне', '2026-01-15'); // всередині
        $this->addExpense($car, '4.00', 'пальне', '2026-02-01'); // == to, не входить
        $this->addExpense($car, '8.00', 'пальне', '2025-12-31'); // до from
        $this->addExpense($car, '16.00', 'пальне', '2026-02-02'); // після to

        $result = (new CarExpenseStats())->totalForPeriod(
            $car,
            new \DateTimeImmutable('2026-01-01'),
            new \DateTimeImmutable('2026-02-01'),
        );

        self::assertSame('3.00', $result);
    }

    public function testTotalForPeriodBoundaryDateEqualToFromIsIncluded(): void
    {
        $car = $this->carWithAmounts(['5.25']);

        $result = (new CarExpenseStats())->totalForPeriod(
            $car,
            new \DateTimeImmutable('2026-01-01'),
            new \DateTimeImmutable('2026-01-02'),
        );

        self::assertSame('5.25', $result);
    }

    public function testTotalForPeriodBoundaryDateEqualToToIsExcluded(): void
    {
        $car = $this->carWithAmounts(['5.25']);

        $result = (new CarExpenseStats())->totalForPeriod(
            $car,
            new \DateTimeImmutable('2025-12-31'),
            new \DateTimeImmutable('2026-01-01'),
        );

        self::assertSame('0.00', $result);
    }

    public function testTotalForPeriodWithNoMatchingExpensesIsZero(): void
    {
        $car = $this->carWithAmounts(['5.25']);

        $result = (new CarExpenseStats())->totalForPeriod(
            $car,
            new \DateTimeImmutable('2030-01-01'),
            new \DateTimeImmutable('2030-12-31'),
        );

        self::assertSame('0.00', $result);
    }

    public function testTotalForPeriodOfCarWithoutExpensesIsZero(): void
    {
        $result = (new CarExpenseStats())->totalForPeriod(
            $this->makeCar(),
            new \DateTimeImmutable('2026-01-01'),
            new \DateTimeImmutable('2026-12-31'),
        );

        self::assertSame('0.00', $result);
    }

    #[DataProvider('invalidPeriodProvider')]
    public function testTotalForPeriodWithFromNotBeforeToIsZero(string $from, string $to): void
    {
        $car = $this->carWithAmounts(['5.25']); // дата 2026-01-01

        $result = (new CarExpenseStats())->totalForPeriod(
            $car,
            new \DateTimeImmutable($from),
            new \DateTimeImmutable($to),
        );

        self::assertSame('0.00', $result);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function invalidPeriodProvider(): iterable
    {
        yield 'from equals to on expense date' => ['2026-01-01', '2026-01-01'];
        yield 'from after to around expense date' => ['2026-02-01', '2025-12-01'];
        yield 'reversed wide range' => ['2027-01-01', '2020-01-01'];
    }

    public function testTotalForPeriodIsExactStringWithTwoDecimals(): void
    {
        $car = $this->makeCar();
        $this->addExpense($car, '0.10', 'пальне', '2026-03-01');
        $this->addExpense($car, '0.10', 'пальне', '2026-03-02');
        $this->addExpense($car, '0.10', 'пальне', '2026-03-03');

        $result = (new CarExpenseStats())->totalForPeriod(
            $car,
            new \DateTimeImmutable('2026-03-01'),
            new \DateTimeImmutable('2026-04-01'),
        );

        self::assertSame('0.30', $result);
        self::assertMatchesRegularExpression('/^\d+\.\d{2}$/', $result);
    }

    public function testTotalForPeriodDoesNotDependOnInsertionOrder(): void
    {
        $rows = [
            ['1.10', '2026-01-05'],
            ['2.20', '2026-01-10'],
            ['3.30', '2026-02-01'],
            ['4.40', '2025-12-31'],
        ];
        $from = new \DateTimeImmutable('2026-01-01');
        $to = new \DateTimeImmutable('2026-02-01');

        $results = [];
        foreach ([$rows, array_reverse($rows)] as $ordered) {
            $car = $this->makeCar();
            foreach ($ordered as [$amount, $date]) {
                $this->addExpense($car, $amount, 'пальне', $date);
            }
            $results[] = (new CarExpenseStats())->totalForPeriod($car, $from, $to);
        }

        self::assertSame('3.30', $results[0]);
        self::assertSame($results[0], $results[1]);
    }

    // ---- totalsByCategory() ----

    public function testTotalsByCategoryOfCarWithoutExpensesIsEmpty(): void
    {
        self::assertSame([], (new CarExpenseStats())->totalsByCategory($this->makeCar()));
    }

    public function testTotalsByCategoryGroupsAndSumsAsStrings(): void
    {
        $car = $this->makeCar();
        $this->addExpense($car, '10.50', 'пальне');
        $this->addExpense($car, '4.50', 'пальне');
        $this->addExpense($car, '3.00', 'мийка');

        self::assertSame(
            ['пальне' => '15.00', 'мийка' => '3.00'],
            (new CarExpenseStats())->totalsByCategory($car),
        );
    }

    public function testTotalsByCategoryIgnoresCaseAndSurroundingWhitespace(): void
    {
        $car = $this->makeCar();
        $this->addExpense($car, '10.00', 'Пальне');
        $this->addExpense($car, '5.00', ' пальне ');
        $this->addExpense($car, '1.00', "\tПАЛЬНЕ\n");

        self::assertSame(
            ['пальне' => '16.00'],
            (new CarExpenseStats())->totalsByCategory($car),
        );
    }

    public function testTotalsByCategoryKeysAreLowercasedAndTrimmed(): void
    {
        $car = $this->makeCar();
        $this->addExpense($car, '1.00', '  Мийка  ');
        $this->addExpense($car, '2.00', 'Fuel');

        self::assertSame(
            ['fuel' => '2.00', 'мийка' => '1.00'],
            (new CarExpenseStats())->totalsByCategory($car),
        );
    }

    public function testTotalsByCategorySortedBySumDescending(): void
    {
        $car = $this->makeCar();
        $this->addExpense($car, '1.00', 'а-мала');
        $this->addExpense($car, '100.00', 'я-велика');
        $this->addExpense($car, '50.00', 'б-середня');

        $result = (new CarExpenseStats())->totalsByCategory($car);

        self::assertSame(['я-велика', 'б-середня', 'а-мала'], array_keys($result));
        self::assertSame(['100.00', '50.00', '1.00'], array_values($result));
    }

    public function testTotalsByCategoryTiesSortedAlphabeticallyCyrillic(): void
    {
        $car = $this->makeCar();
        $this->addExpense($car, '5.00', 'шини');
        $this->addExpense($car, '5.00', 'Мийка');
        $this->addExpense($car, '5.00', 'ремонт');
        $this->addExpense($car, '5.00', 'Єдиний податок');
        $this->addExpense($car, '9.00', 'страхування');

        $result = (new CarExpenseStats())->totalsByCategory($car);

        self::assertSame(
            ['страхування', 'єдиний податок', 'мийка', 'ремонт', 'шини'],
            array_keys($result),
        );
    }

    public function testTotalsByCategoryDoesNotDependOnInsertionOrder(): void
    {
        $rows = [
            ['1.10', 'Пальне'],
            ['2.20', ' мийка'],
            ['3.30', 'пальне '],
            ['2.20', 'ремонт'],
            ['0.01', 'мийка'],
        ];

        $results = [];
        foreach ([$rows, array_reverse($rows)] as $ordered) {
            $car = $this->makeCar();
            foreach ($ordered as [$amount, $category]) {
                $this->addExpense($car, $amount, $category);
            }
            $results[] = (new CarExpenseStats())->totalsByCategory($car);
        }

        self::assertSame(['пальне' => '4.40', 'мийка' => '2.21', 'ремонт' => '2.20'], $results[0]);
        self::assertSame($results[0], $results[1]);
        self::assertSame(array_keys($results[0]), array_keys($results[1]));
    }

    public function testTotalsByCategoryValuesAreExactStringsWithTwoDecimals(): void
    {
        $car = $this->makeCar();
        $this->addExpense($car, '0.10', 'пальне');
        $this->addExpense($car, '0.10', 'пальне');
        $this->addExpense($car, '0.10', 'пальне');
        $this->addExpense($car, '7', 'мийка');

        $result = (new CarExpenseStats())->totalsByCategory($car);

        self::assertSame(['мийка' => '7.00', 'пальне' => '0.30'], $result);
        foreach ($result as $sum) {
            self::assertIsString($sum);
            self::assertMatchesRegularExpression('/^\d+\.\d{2}$/', $sum);
        }
    }
}
