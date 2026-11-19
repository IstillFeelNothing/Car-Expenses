import datetime
from dataclasses import dataclass
from decimal import Decimal, InvalidOperation
from typing import Any


class InvalidExpenseDataError(Exception):
    pass


@dataclass(frozen=True)
class Expense:
    amount: Decimal
    category: str
    date: datetime.date
    description: str = ""
    expense_id: int | None = None
    car: str = ""

    @classmethod
    def from_dict(cls, data: dict[str, Any]) -> "Expense":
        """Створює витрату з елемента відповіді `GET /api/cars/{slug}/expenses`.

        Бізнес-правила (діапазон суми, довжину категорії) валідує Symfony;
        тут перевіряються лише структура й типи.
        """
        if not isinstance(data, dict):
            raise InvalidExpenseDataError("Дані витрати повинні бути словником.")

        for field in ("amount", "category", "date"):
            if field not in data:
                raise InvalidExpenseDataError(f"Обов'язкове поле '{field}' відсутнє.")

        raw_amount = data["amount"]
        if not isinstance(raw_amount, str):
            raise InvalidExpenseDataError(
                f"Сума повинна бути рядком, отримано: {type(raw_amount)}"
            )
        try:
            amount = Decimal(raw_amount.strip())
        except InvalidOperation:
            raise InvalidExpenseDataError(
                f"Некоректний формат суми: {raw_amount!r}"
            ) from None
        if not amount.is_finite():
            raise InvalidExpenseDataError(f"Некоректна сума: {raw_amount!r}")

        category = data["category"]
        if not isinstance(category, str):
            raise InvalidExpenseDataError(
                f"Категорія повинна бути рядком, отримано: {type(category)}"
            )

        try:
            expense_date = datetime.date.fromisoformat(str(data["date"]))
        except ValueError as err:
            raise InvalidExpenseDataError(f"Некоректний формат дати: {err}") from err

        raw_desc = data.get("description")
        if raw_desc is None:
            description = ""
        elif isinstance(raw_desc, str):
            description = raw_desc
        else:
            raise InvalidExpenseDataError(
                f"Опис повинен бути рядком, отримано: {type(raw_desc)}"
            )

        raw_id = data.get("id")
        if raw_id is not None and (type(raw_id) is not int or raw_id < 0):
            raise InvalidExpenseDataError(f"Невалідний ID: {raw_id!r}")

        car = data.get("car", "")
        if not isinstance(car, str):
            raise InvalidExpenseDataError(
                f"Поле 'car' повинно бути рядком, отримано: {type(car)}"
            )

        return cls(
            amount=amount,
            category=category,
            date=expense_date,
            description=description,
            expense_id=raw_id,
            car=car,
        )
