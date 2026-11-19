import datetime
from decimal import Decimal
from typing import Any

import pytest
import requests

from api_client import ApiClient
from api_storage import ApiStorage
from expense import Expense
from storage_protocol import ReadableStorage, StorageError
from tests.fakes import fake_get

VALID_ITEM: dict[str, Any] = {
    "id": 1,
    "car": "toyota",
    "amount": "1250.00",
    "category": "пальне",
    "date": "2026-11-01",
    "description": "Повний бак",
}


def make_storage(
    slug: str = "toyota", calls: list[tuple[str, float]] | None = None, **kwargs: Any
) -> ApiStorage:
    return ApiStorage(
        ApiClient("http://api.test", http_get=fake_get(calls=calls, **kwargs)), slug
    )


def test_load_converts_json_to_expenses() -> None:
    storage = make_storage(
        payload=[VALID_ITEM, {**VALID_ITEM, "id": 2, "description": None}]
    )

    expenses = storage.load()

    assert expenses == [
        Expense(
            amount=Decimal("1250.00"),
            category="пальне",
            date=datetime.date(2026, 11, 1),
            description="Повний бак",
            expense_id=1,
            car="toyota",
        ),
        Expense(
            amount=Decimal("1250.00"),
            category="пальне",
            date=datetime.date(2026, 11, 1),
            description="",
            expense_id=2,
            car="toyota",
        ),
    ]
    assert isinstance(expenses[0].amount, Decimal)


def test_load_empty_list() -> None:
    assert make_storage(payload=[]).load() == []


def test_load_requests_expenses_endpoint_of_slug() -> None:
    calls: list[tuple[str, float]] = []

    make_storage(slug="land rover/x", calls=calls, payload=[]).load()

    assert calls[0][0] == "http://api.test/api/cars/land%20rover%2Fx/expenses"


def test_api_storage_is_readable_storage_without_save() -> None:
    storage: ReadableStorage = make_storage(payload=[])

    assert not hasattr(storage, "save")


@pytest.mark.parametrize(
    "bad_item",
    [
        "not a dict",
        {**VALID_ITEM, "amount": "abc"},
        {**VALID_ITEM, "amount": 12.5},
        {**VALID_ITEM, "date": "01.11.2026"},
        {**VALID_ITEM, "category": None},
        {**VALID_ITEM, "id": "x"},
        {k: v for k, v in VALID_ITEM.items() if k != "amount"},
    ],
)
def test_load_broken_record_raises_storage_error(bad_item: Any) -> None:
    storage = make_storage(payload=[VALID_ITEM, bad_item])

    with pytest.raises(StorageError, match="№2"):
        storage.load()


def test_load_non_list_payload_raises_storage_error() -> None:
    with pytest.raises(StorageError, match="очікується список"):
        make_storage(payload={"error": "Car not found"}).load()


def test_load_propagates_not_found() -> None:
    with pytest.raises(StorageError, match="Не знайдено"):
        make_storage(status=404, payload={"error": "Car not found"}).load()


def test_load_propagates_connection_error() -> None:
    with pytest.raises(StorageError, match="Немає з'єднання"):
        make_storage(exc=requests.ConnectionError()).load()
