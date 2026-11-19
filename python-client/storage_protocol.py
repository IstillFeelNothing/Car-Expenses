from typing import Protocol

from car import Car
from expense import Expense


class StorageError(Exception):
    """Будь-яка помилка отримання/збереження даних (мережа, формат, сервер)."""


class ReadableStorage(Protocol):
    def load(self) -> list[Expense]: ...


class CarCatalog(Protocol):
    def load_cars(self) -> list[Car]: ...
