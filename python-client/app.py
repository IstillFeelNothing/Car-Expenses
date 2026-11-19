from collections.abc import Callable
from dataclasses import dataclass
from datetime import date, timedelta

from calculator import (
    filter_by_period,
    get_day_period,
    get_month_period,
    get_week_period,
    get_year_period,
    group_by_category,
    total_expenses,
)
from storage_protocol import ExpenseSource, StorageError
from validator import parse_month, parse_year
from view import format_date
from view_protocol import AppView


@dataclass(frozen=True)
class MenuItem:
    key: str
    label: str
    handler: Callable[[], None]


class ExpenseApp:
    def __init__(
        self,
        repo: ExpenseSource,
        view: AppView,
        today_fn: Callable[[], date] = date.today,
    ) -> None:
        self._repo = repo
        self._view = view
        self._today_fn = today_fn
        self._menu_items: tuple[MenuItem, ...] = (
            MenuItem("1", "Переглянути всі витрати", self._list_expenses),
            MenuItem("2", "Статистика за сьогодні", self._stats_today),
            MenuItem(
                "3", "Статистика за тиждень (останні 7 днів)", self._stats_week
            ),
            MenuItem("4", "Статистика за місяць", self._stats_month),
            MenuItem("5", "Статистика за рік", self._stats_year),
        )
        self._commands: dict[str, Callable[[], None]] = {
            item.key: item.handler for item in self._menu_items
        }

    def run(self) -> None:
        if not self._init_storage():
            return

        while True:
            self._view.show_menu([(i.key, i.label) for i in self._menu_items])
            choice = self._view.prompt("Оберіть дію: ")

            if choice == "0":
                self._view.show_message("До зустрічі!")
                break

            action = self._commands.get(choice)
            if action is None:
                self._view.show_error("Невідома команда. Оберіть пункт із меню.")
                continue

            action()

    def _init_storage(self) -> bool:
        try:
            self._repo.load()
        except StorageError as exc:
            self._view.show_error(str(exc))
            return False
        return True

    def _list_expenses(self) -> None:
        self._view.show_expenses(self._repo.get_all())

    def _stats_today(self) -> None:
        today = self._today_fn()
        start, end = get_day_period(today)
        self._show_period_stats(f"Витрати за {format_date(today)}", start, end)

    def _stats_week(self) -> None:
        today = self._today_fn()
        week_start = today - timedelta(days=6)
        start, end = get_week_period(week_start)
        self._show_period_stats(
            f"Витрати за 7 днів ({format_date(start)} – {format_date(today)})",
            start,
            end,
        )

    def _stats_month(self) -> None:
        year = self._view.ask("Рік (напр. 2026): ", parse_year)
        month = self._view.ask("Місяць (1–12): ", parse_month)
        start, end = get_month_period(year, month)
        self._show_period_stats(f"Витрати за {month:02d}.{year}", start, end)

    def _stats_year(self) -> None:
        year = self._view.ask("Рік (напр. 2026): ", parse_year)
        start, end = get_year_period(year)
        self._show_period_stats(f"Витрати за {year} рік", start, end)

    def _show_period_stats(self, title: str, start: date, end: date) -> None:
        filtered = filter_by_period(self._repo.get_all(), start, end)
        total = total_expenses(filtered)
        by_cat = group_by_category(filtered)
        self._view.show_summary(title, total, by_cat)
