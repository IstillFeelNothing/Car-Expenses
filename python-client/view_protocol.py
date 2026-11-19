from collections.abc import Callable, Iterable, Sequence
from decimal import Decimal
from typing import Protocol, TypeVar

from expense import Expense

T = TypeVar("T")


class AppView(Protocol):
    """Те, що `ExpenseApp` потребує від представлення."""

    def show_message(self, message: str) -> None: ...

    def show_error(self, message: str) -> None: ...

    def show_menu(self, items: Sequence[tuple[str, str]]) -> None: ...

    def prompt(self, text: str) -> str: ...

    def ask(self, prompt: str, parser: Callable[[str], T]) -> T: ...

    def show_expenses(self, expenses: Iterable[Expense]) -> None: ...

    def show_summary(
        self, title: str, total: Decimal, by_category: dict[str, Decimal]
    ) -> None: ...
