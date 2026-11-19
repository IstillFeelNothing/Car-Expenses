import json
from typing import Any

import requests

from api_client import HttpGet
from expense import Expense
from storage_protocol import StorageError


class FakeStorage:
    """Реалізація `ReadableStorage` для тестів: віддає список або кидає помилку."""

    def __init__(
        self,
        expenses: list[Expense] | None = None,
        load_error: StorageError | None = None,
    ) -> None:
        self.expenses = list(expenses) if expenses is not None else []
        self.load_error = load_error
        self.load_calls = 0

    def load(self) -> list[Expense]:
        self.load_calls += 1
        if self.load_error is not None:
            raise self.load_error
        return list(self.expenses)


def make_response(
    status: int = 200, payload: Any = None, body: bytes | None = None
) -> requests.Response:
    """Справжній `requests.Response` без мережі."""
    resp = requests.Response()
    resp.status_code = status
    resp.encoding = "utf-8"
    resp._content = (
        body if body is not None else json.dumps(payload, ensure_ascii=False).encode()
    )
    return resp


def fake_get(
    status: int = 200,
    payload: Any = None,
    body: bytes | None = None,
    exc: Exception | None = None,
    calls: list[tuple[str, float]] | None = None,
) -> HttpGet:
    """Фейковий `http_get`: записує (url, timeout) у `calls`."""

    def _get(url: str, timeout: float) -> requests.Response:
        if calls is not None:
            calls.append((url, timeout))
        if exc is not None:
            raise exc
        return make_response(status, payload, body)

    return _get
