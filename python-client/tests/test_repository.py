import datetime
from decimal import Decimal

import pytest

from expense import Expense
from repository import ExpenseRepository, RepositoryNotInitializedError
from storage_protocol import StorageError
from tests.fakes import FakeStorage


def make_expense(expense_id: int | None, amount: str = "100.00") -> Expense:
    return Expense(
        amount=Decimal(amount),
        category="пальне",
        date=datetime.date(2026, 11, 12),
        expense_id=expense_id,
        car="toyota",
    )


def test_methods_raise_before_load() -> None:
    repo = ExpenseRepository(FakeStorage())

    assert issubclass(RepositoryNotInitializedError, RuntimeError)
    with pytest.raises(RepositoryNotInitializedError):
        repo.get_all()
    with pytest.raises(RepositoryNotInitializedError):
        repo.get_by_id(1)


def test_get_all_returns_loaded_expenses() -> None:
    items = [make_expense(1), make_expense(2, "45.50")]
    repo = ExpenseRepository(FakeStorage(items))
    repo.load()

    assert repo.get_all() == items


def test_get_all_returns_copy() -> None:
    repo = ExpenseRepository(FakeStorage([make_expense(1)]))
    repo.load()

    repo.get_all().clear()

    assert len(repo.get_all()) == 1


def test_get_by_id_found_and_missing() -> None:
    first, second = make_expense(1), make_expense(2, "45.50")
    repo = ExpenseRepository(FakeStorage([first, second]))
    repo.load()

    assert repo.get_by_id(2) == second
    assert repo.get_by_id(999) is None


def test_load_empty_storage() -> None:
    repo = ExpenseRepository(FakeStorage())
    repo.load()

    assert repo.get_all() == []


def test_load_propagates_storage_error() -> None:
    repo = ExpenseRepository(FakeStorage(load_error=StorageError("Сервер лежить")))

    with pytest.raises(StorageError, match="Сервер лежить"):
        repo.load()
    with pytest.raises(RepositoryNotInitializedError):
        repo.get_all()


def test_reload_replaces_data() -> None:
    storage = FakeStorage([make_expense(1)])
    repo = ExpenseRepository(storage)
    repo.load()

    storage.expenses = [make_expense(5)]
    repo.load()

    assert [e.expense_id for e in repo.get_all()] == [5]
