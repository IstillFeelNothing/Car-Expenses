<?php

declare(strict_types=1);

namespace App\Tests\Unit\Dto;

use App\Dto\ExpenseDto;
use App\Entity\Car;
use App\Entity\Expense;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ExpenseDtoTest extends TestCase
{
    private function expense(string $amount = '1250.00', ?string $description = 'Повний бак', ?int $id = 7, bool $withCar = true): Expense
    {
        $expense = (new Expense())
            ->setAmount($amount)
            ->setCategory('пальне')
            ->setDate(new \DateTimeImmutable('2026-11-01 15:30:00'))
            ->setDescription($description);

        if ($withCar) {
            (new Car())->setSlug('toyota')->setName('Toyota')->setCountry('Japan')->addExpense($expense);
        }
        if (null !== $id) {
            (new \ReflectionProperty(Expense::class, 'id'))->setValue($expense, $id);
        }

        return $expense;
    }

    public function testImplementsJsonSerializable(): void
    {
        self::assertInstanceOf(\JsonSerializable::class, ExpenseDto::fromEntity($this->expense()));
    }

    public function testKeysAndOrder(): void
    {
        $data = ExpenseDto::fromEntity($this->expense())->jsonSerialize();

        self::assertSame(['id', 'car', 'amount', 'category', 'date', 'description'], array_keys($data));
    }

    public function testValuesAndTypes(): void
    {
        $data = ExpenseDto::fromEntity($this->expense())->jsonSerialize();

        self::assertSame(7, $data['id']);
        self::assertSame('toyota', $data['car']);
        self::assertSame('1250.00', $data['amount']);
        self::assertSame('пальне', $data['category']);
        self::assertSame('2026-11-01', $data['date']);
        self::assertSame('Повний бак', $data['description']);
    }

    public function testNullDescription(): void
    {
        $data = ExpenseDto::fromEntity($this->expense(description: null))->jsonSerialize();

        self::assertNull($data['description']);
    }

    #[DataProvider('amounts')]
    public function testAmountFormatting(string $in, string $expected): void
    {
        $data = ExpenseDto::fromEntity($this->expense(amount: $in))->jsonSerialize();

        self::assertIsString($data['amount']);
        self::assertSame($expected, $data['amount']);
    }

    /** @return iterable<string, array{string, string}> */
    public static function amounts(): iterable
    {
        yield 'integer' => ['1250', '1250.00'];
        yield 'one decimal' => ['10.5', '10.50'];
        yield 'small' => ['0.1', '0.10'];
        yield 'already formatted' => ['1250.00', '1250.00'];
        yield 'million' => ['1000000.00', '1000000.00'];
    }

    public function testJsonEncodeKeepsAmountAsString(): void
    {
        $json = json_encode(
            ExpenseDto::fromEntity($this->expense(amount: '10.5')),
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE,
        );

        self::assertSame(
            '{"id":7,"car":"toyota","amount":"10.50","category":"пальне","date":"2026-11-01","description":"Повний бак"}',
            $json,
        );
    }

    public function testThrowsWithoutCar(): void
    {
        $this->expectException(\LogicException::class);

        ExpenseDto::fromEntity($this->expense(withCar: false));
    }

    public function testThrowsWithoutId(): void
    {
        $this->expectException(\LogicException::class);

        ExpenseDto::fromEntity($this->expense(id: null));
    }
}
