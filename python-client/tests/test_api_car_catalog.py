from typing import Any

import pytest
import requests

from api_client import ApiClient
from api_storage import ApiCarCatalog
from car import Car
from storage_protocol import CarCatalog, StorageError
from tests.fakes import fake_get

VALID: dict[str, Any] = {"id": "toyota", "name": "Toyota", "country": "Japan"}


def make_catalog(
    calls: list[tuple[str, float]] | None = None, **kwargs: Any
) -> ApiCarCatalog:
    return ApiCarCatalog(
        ApiClient("http://api.test", http_get=fake_get(calls=calls, **kwargs))
    )


def test_load_cars_converts_json() -> None:
    cars = make_catalog(
        payload=[VALID, {"id": "bmw", "name": "BMW", "country": "Germany"}]
    ).load_cars()

    assert cars == [
        Car(slug="toyota", name="Toyota", country="Japan"),
        Car(slug="bmw", name="BMW", country="Germany"),
    ]


def test_load_cars_requests_cars_endpoint() -> None:
    calls: list[tuple[str, float]] = []

    make_catalog(calls=calls, payload=[]).load_cars()

    assert calls == [("http://api.test/api/cars", 5.0)]


def test_load_cars_empty_list() -> None:
    assert make_catalog(payload=[]).load_cars() == []


def test_catalog_satisfies_protocol() -> None:
    catalog: CarCatalog = make_catalog(payload=[])

    assert catalog.load_cars() == []


def test_load_cars_non_list_raises() -> None:
    with pytest.raises(StorageError, match="очікується список"):
        make_catalog(payload={"error": "x"}).load_cars()


@pytest.mark.parametrize(
    "bad", ["str", {"id": "x"}, {**VALID, "name": ""}, {**VALID, "id": 5}]
)
def test_load_cars_broken_record_has_number(bad: Any) -> None:
    with pytest.raises(StorageError, match="№2"):
        make_catalog(payload=[VALID, bad]).load_cars()


def test_load_cars_404() -> None:
    with pytest.raises(StorageError, match="Не знайдено"):
        make_catalog(status=404, payload={}).load_cars()


def test_load_cars_500() -> None:
    with pytest.raises(StorageError, match="500"):
        make_catalog(status=500, payload={}).load_cars()


def test_load_cars_connection_error() -> None:
    with pytest.raises(StorageError, match="Немає з'єднання"):
        make_catalog(exc=requests.ConnectionError()).load_cars()


def test_load_cars_timeout() -> None:
    with pytest.raises(StorageError, match="вчасно"):
        make_catalog(exc=requests.Timeout()).load_cars()
