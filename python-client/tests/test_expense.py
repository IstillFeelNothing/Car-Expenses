import dataclasses
import datetime
from decimal import Decimal
from typing import Any

import pytest

from expense import Expense, InvalidExpenseDataError

API_ITEM: dict[str, Any] = {
    "id": 1,
    "car": "toyota",
    "amount": "1250.00",
    "category": "пальне",
    "date": "2026-11-01",
    "description": "Повний бак",
}


def test_expense_from_dict_valid_api_item() -> None:
    exp = Expense.from_dict(API_ITEM)

    assert exp.expense_id == 1
    assert exp.car == "toyota"
    assert exp.amount == Decimal("1250.00")
    assert isinstance(exp.amount, Decimal)
    assert exp.category == "пальне"
    assert exp.date == datetime.date(2026, 11, 1)
    assert exp.description == "Повний бак"


def test_expense_from_dict_null_description_becomes_empty() -> None:
    assert Expense.from_dict({**API_ITEM, "description": None}).description == ""


def test_expense_from_dict_without_optional_fields() -> None:
    exp = Expense.from_dict(
        {"amount": "10.00", "category": "мийка", "date": "2026-11-02"}
    )

    assert exp.car == ""
    assert exp.expense_id is None
    assert exp.description == ""


def test_expense_from_dict_ignores_unknown_fields() -> None:
    exp = Expense.from_dict({**API_ITEM, "new_field": 42})

    assert exp.amount == Decimal("1250.00")


def test_expense_amount_keeps_decimal_precision() -> None:
    exp = Expense.from_dict({**API_ITEM, "amount": "0.10"})

    assert exp.amount + Decimal("0.20") == Decimal("0.30")


def test_expense_supports_dataclasses_replace_and_hash() -> None:
    exp = Expense.from_dict(API_ITEM)

    updated = dataclasses.replace(exp, amount=Decimal("200.00"))

    assert updated.amount == Decimal("200.00")
    assert updated.car == "toyota"
    assert len({exp, updated}) == 2


def test_expense_is_immutable() -> None:
    exp = Expense.from_dict(API_ITEM)

    with pytest.raises(dataclasses.FrozenInstanceError):
        exp.amount = Decimal("20.00")  # type: ignore[misc]


@pytest.mark.parametrize(
    "invalid_data",
    [
        "not a dict",
        {"category": "їжа", "date": "2026-10-30"},
        {"amount": "100.00", "date": "2026-10-30"},
        {"amount": "100.00", "category": "їжа"},
        {"amount": "invalid_number", "category": "їжа", "date": "2026-10-30"},
        {"amount": "NaN", "category": "їжа", "date": "2026-10-30"},
        {"amount": "Infinity", "category": "їжа", "date": "2026-10-30"},
        {"amount": 100.5, "category": "їжа", "date": "2026-10-30"},
        {"amount": True, "category": "їжа", "date": "2026-10-30"},
        {"amount": "10.00", "category": 123, "date": "2026-10-30"},
        {"amount": "10.00", "category": None, "date": "2026-10-30"},
        {"amount": "10.00", "category": "їжа", "date": "invalid-date"},
        {"amount": "10.00", "category": "їжа", "date": "2026-10-30", "id": "abc"},
        {"amount": "10.00", "category": "їжа", "date": "2026-10-30", "id": -1},
        {"amount": "10.00", "category": "їжа", "date": "2026-10-30", "id": True},
        {"amount": "10.00", "category": "їжа", "date": "2026-10-30", "car": 5},
        {
            "amount": "10.00",
            "category": "їжа",
            "date": "2026-10-30",
            "description": 123,
        },
    ],
)
def test_from_dict_raises_invalid_expense_data_error(invalid_data: Any) -> None:
    with pytest.raises(InvalidExpenseDataError):
        Expense.from_dict(invalid_data)
