from typing import Any, Protocol

from car import Car
from expense import Expense


class StorageError(Exception):
    """Будь-яка помилка отримання/збереження даних (мережа, формат, сервер)."""


class ReadableStorage(Protocol):
    def load(self) -> list[Expense]: ...


class CarCatalog(Protocol):
    def load_cars(self) -> list[Car]: ...


class ExpenseSource(Protocol):
    """Те, що `ExpenseApp` потребує від репозиторію витрат."""

    def load(self) -> None: ...

    def get_all(self) -> list[Expense]: ...


class JsonClient(Protocol):
    """Те, що сховища потребують від HTTP-клієнта."""

    def get_json(self, path: str) -> Any: ...
