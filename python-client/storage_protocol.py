from typing import Protocol

from expense import Expense


class StorageError(Exception):
    """Будь-яка помилка отримання/збереження даних (мережа, формат, сервер)."""


class ReadableStorage(Protocol):
    def load(self) -> list[Expense]: ...


class WritableStorage(Protocol):
    def save(self, expenses: list[Expense]) -> None: ...
