from expense import Expense
from storage_protocol import ReadableStorage


class RepositoryNotInitializedError(RuntimeError):
    pass


class ExpenseRepository:
    def __init__(self, storage: ReadableStorage) -> None:
        self._storage = storage
        self._expenses: list[Expense] = []
        self._is_loaded: bool = False

    def load(self) -> None:
        """Завантажує витрати зі сховища. `StorageError` не перехоплюється."""
        self._expenses = list(self._storage.load())
        self._is_loaded = True

    def get_all(self) -> list[Expense]:
        self._ensure_loaded()
        return list(self._expenses)

    def get_by_id(self, expense_id: int) -> Expense | None:
        self._ensure_loaded()
        for expense in self._expenses:
            if expense.expense_id == expense_id:
                return expense
        return None

    def _ensure_loaded(self) -> None:
        if not self._is_loaded:
            raise RepositoryNotInitializedError(
                "Репозиторій не ініціалізовано. Викличте load() перед роботою з даними."
            )
