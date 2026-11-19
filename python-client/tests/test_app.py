from collections.abc import Callable, Iterable, Sequence
from datetime import date
from decimal import Decimal
from typing import TypeVar

import pytest

from app import ExpenseApp
from expense import Expense
from repository import ExpenseRepository
from storage_protocol import StorageError
from tests.fakes import FakeStorage
from view import ConsoleView

FIXED_TODAY = date(2026, 11, 14)
T = TypeVar("T")


def make_expense(amount: str, category: str, day: date, expense_id: int) -> Expense:
    return Expense(
        amount=Decimal(amount),
        category=category,
        date=day,
        expense_id=expense_id,
        car="toyota",
    )


def run_app(storage: FakeStorage, user_inputs: list[str]) -> str:
    inputs = iter(user_inputs)
    outputs: list[str] = []
    view = ConsoleView(input_fn=lambda _: next(inputs), output_fn=outputs.append)
    app = ExpenseApp(
        repo=ExpenseRepository(storage), view=view, today_fn=lambda: FIXED_TODAY
    )
    app.run()
    return "\n".join(outputs)


@pytest.fixture
def storage() -> FakeStorage:
    return FakeStorage(
        [
            make_expense("100.00", "пальне", date(2026, 11, 14), 1),
            make_expense("250.00", "ремонт", date(2026, 11, 8), 2),
            make_expense("999.00", "страховка", date(2026, 11, 1), 3),
        ]
    )


def test_app_lists_expenses(storage: FakeStorage) -> None:
    rendered = run_app(storage, ["1", "0"])

    assert "100,00 грн" in rendered
    assert "Ремонт" in rendered
    assert "До зустрічі" in rendered


def test_app_lists_empty_expenses() -> None:
    rendered = run_app(FakeStorage(), ["1", "0"])

    assert "порожній" in rendered.lower()


def test_app_stats_today_and_week_use_injected_today_fn(storage: FakeStorage) -> None:
    rendered = run_app(storage, ["2", "3", "0"])

    assert "Витрати за 14.11.2026" in rendered
    assert "100,00 грн" in rendered
    assert "Витрати за 7 днів (08.11.2026 – 14.11.2026)" in rendered
    assert "350,00 грн" in rendered
    assert "999,00 грн" not in rendered


def test_app_stats_month_asks_year_and_month_with_retry(storage: FakeStorage) -> None:
    rendered = run_app(storage, ["4", "abc", "2026", "13", "11", "0"])

    assert "Рік має бути коректним" in rendered
    assert "Місяць має бути числом від 1 до 12." in rendered
    assert "Витрати за 11.2026" in rendered
    assert "1 349,00 грн" in rendered


def test_app_stats_year(storage: FakeStorage) -> None:
    rendered = run_app(storage, ["5", "2025", "5", "2026", "0"])

    assert "Витрати за 2025 рік" in rendered
    assert "Витрат за цей період немає." in rendered
    assert "Витрати за 2026 рік" in rendered
    assert "1 349,00 грн" in rendered


def test_app_menu_shows_exactly_registered_commands(storage: FakeStorage) -> None:
    outputs: list[str] = []
    view = ConsoleView(input_fn=lambda _: "0", output_fn=outputs.append)
    app = ExpenseApp(repo=ExpenseRepository(storage), view=view)

    app.run()

    menu_keys = [
        line.split(". ", 1)[0] for line in outputs if line[:1].isdigit()
    ]
    assert menu_keys[-1] == "0"
    assert sorted(menu_keys[:-1]) == sorted(app._commands)
    assert menu_keys[:-1] == ["1", "2", "3", "4", "5"]


def test_app_menu_output_is_unchanged(storage: FakeStorage) -> None:
    outputs: list[str] = []
    view = ConsoleView(input_fn=lambda _: "0", output_fn=outputs.append)

    ExpenseApp(repo=ExpenseRepository(storage), view=view).run()

    assert outputs[:7] == [
        "\n=== Трекер витрат ===",
        "1. Переглянути всі витрати",
        "2. Статистика за сьогодні",
        "3. Статистика за тиждень (останні 7 днів)",
        "4. Статистика за місяць",
        "5. Статистика за рік",
        "0. Вихід",
    ]


def test_app_unknown_command_shows_error(storage: FakeStorage) -> None:
    rendered = run_app(storage, ["9", "0"])

    assert "Невідома команда" in rendered


def test_app_removed_edit_commands_are_unknown(storage: FakeStorage) -> None:
    rendered = run_app(storage, ["6", "0"])

    assert "Невідома команда" in rendered


def test_app_load_error_shows_message_and_exits_without_prompt() -> None:
    storage = FakeStorage(load_error=StorageError("Немає з'єднання з http://api"))

    def fail_if_called(_: str) -> str:
        pytest.fail("input() не мав викликатися при помилці завантаження")

    outputs: list[str] = []
    view = ConsoleView(input_fn=fail_if_called, output_fn=outputs.append)

    ExpenseApp(repo=ExpenseRepository(storage), view=view).run()

    assert outputs == ["[Помилка] Немає з'єднання з http://api"]
    assert storage.load_calls == 1


class StubSource:
    """Структурна реалізація `ExpenseSource` без `ExpenseRepository`."""

    def __init__(self, expenses: list[Expense]) -> None:
        self._expenses = expenses

    def load(self) -> None:
        return None

    def get_all(self) -> list[Expense]:
        return list(self._expenses)


class StubView:
    """Структурна реалізація `AppView` без `ConsoleView`: пише події в журнал."""

    def __init__(self, inputs: list[str]) -> None:
        self._inputs = iter(inputs)
        self.events: list[tuple[str, object]] = []

    def show_message(self, message: str) -> None:
        self.events.append(("message", message))

    def show_error(self, message: str) -> None:
        self.events.append(("error", message))

    def show_menu(self, items: Sequence[tuple[str, str]]) -> None:
        self.events.append(("menu", len(items)))

    def prompt(self, text: str) -> str:
        return next(self._inputs)

    def ask(self, prompt: str, parser: Callable[[str], T]) -> T:
        return parser(next(self._inputs))

    def show_expenses(self, expenses: Iterable[Expense]) -> None:
        self.events.append(("expenses", [e.expense_id for e in expenses]))

    def show_summary(
        self, title: str, total: Decimal, by_category: dict[str, Decimal]
    ) -> None:
        self.events.append(("summary", total))


def test_app_works_with_structural_source_and_view() -> None:
    source = StubSource(
        [
            make_expense("100.00", "пальне", date(2026, 11, 14), 1),
            make_expense("50.00", "мийка", date(2026, 3, 1), 2),
        ]
    )
    view = StubView(["1", "5", "2026", "0"])

    ExpenseApp(repo=source, view=view, today_fn=lambda: FIXED_TODAY).run()

    assert ("expenses", [1, 2]) in view.events
    assert ("summary", Decimal("150.00")) in view.events
    assert view.events[-1] == ("message", "До зустрічі!")
