import pytest
import requests

from api_client import ApiClient
from storage_protocol import StorageError
from tests.fakes import fake_get


def test_get_json_returns_parsed_payload_on_200() -> None:
    calls: list[tuple[str, float]] = []
    client = ApiClient(
        "http://api.test/", http_get=fake_get(payload=[{"id": "toyota"}], calls=calls)
    )

    assert client.get_json("/api/cars") == [{"id": "toyota"}]
    assert calls == [("http://api.test/api/cars", 5.0)]


def test_get_json_passes_custom_timeout() -> None:
    calls: list[tuple[str, float]] = []
    client = ApiClient(
        "http://api.test", http_get=fake_get(payload=[], calls=calls), timeout=1.5
    )

    client.get_json("/x")

    assert calls == [("http://api.test/x", 1.5)]


def test_get_json_404_raises_storage_error() -> None:
    client = ApiClient("http://api.test", http_get=fake_get(status=404))

    with pytest.raises(StorageError, match="Не знайдено"):
        client.get_json("/api/cars/nope/expenses")


@pytest.mark.parametrize("status", [500, 503, 401, 204])
def test_get_json_non_200_raises_storage_error(status: int) -> None:
    client = ApiClient("http://api.test", http_get=fake_get(status=status))

    with pytest.raises(StorageError, match=f"Помилка сервера: {status}"):
        client.get_json("/api/cars")


def test_get_json_connection_error_raises_storage_error() -> None:
    client = ApiClient(
        "http://api.test", http_get=fake_get(exc=requests.ConnectionError("boom"))
    )

    with pytest.raises(StorageError, match="Немає з'єднання з http://api.test"):
        client.get_json("/api/cars")


def test_get_json_timeout_raises_storage_error() -> None:
    client = ApiClient("http://api.test", http_get=fake_get(exc=requests.Timeout()))

    with pytest.raises(StorageError, match="не відповів вчасно"):
        client.get_json("/api/cars")


def test_get_json_connect_timeout_is_reported_as_timeout() -> None:
    client = ApiClient(
        "http://api.test", http_get=fake_get(exc=requests.ConnectTimeout())
    )

    with pytest.raises(StorageError, match="не відповів вчасно"):
        client.get_json("/api/cars")


def test_get_json_other_request_exception_raises_storage_error() -> None:
    client = ApiClient(
        "http://api.test", http_get=fake_get(exc=requests.TooManyRedirects("loop"))
    )

    with pytest.raises(StorageError, match="Помилка запиту"):
        client.get_json("/api/cars")


def test_get_json_invalid_json_raises_storage_error() -> None:
    client = ApiClient("http://api.test", http_get=fake_get(body=b"<html>oops</html>"))

    with pytest.raises(StorageError, match="невалідний JSON"):
        client.get_json("/api/cars")
