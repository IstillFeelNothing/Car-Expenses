from collections.abc import Callable
from typing import Any

import requests

from storage_protocol import StorageError

HttpGet = Callable[..., requests.Response]


class ApiClient:
    def __init__(
        self,
        base_url: str,
        http_get: HttpGet = requests.get,
        timeout: float = 5.0,
    ) -> None:
        self._base = base_url.rstrip("/")
        self._get = http_get
        self._timeout = timeout

    def get_json(self, path: str) -> Any:
        url = f"{self._base}{path}"
        try:
            resp = self._get(url, timeout=self._timeout)
        except requests.Timeout as err:
            raise StorageError("Сервер не відповів вчасно") from err
        except requests.ConnectionError as err:
            raise StorageError(f"Немає з'єднання з {self._base}") from err
        except requests.RequestException as err:
            raise StorageError(f"Помилка запиту до {self._base}: {err}") from err

        if resp.status_code == 404:
            raise StorageError(f"Не знайдено: {path}")
        if resp.status_code != 200:
            raise StorageError(f"Помилка сервера: {resp.status_code}")

        try:
            return resp.json()
        except ValueError as err:
            raise StorageError("Сервер повернув невалідний JSON") from err
