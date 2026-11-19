from datetime import date
from decimal import Decimal

import pytest

from car import Car
from expense import Expense
from validator import parse_and_validate_date
from view import ConsoleView, format_amount


def test_format_amount_ukrainian_locale_with_nbsp() -> None:
    assert format_amount(Decimal("1250.5")) == "1 250,50 грн"
    assert format_amount(Decimal("0")) == "0,00 грн"
    assert format_amount(Decimal("1000000.99")) == "1 000 000,99 грн"


def test_view_ask_retries_until_valid_input() -> None:
    inputs = iter(["абв", "31.12.2999", "14.11.2026"])
    outputs: list[str] = []
    view = ConsoleView(
        input_fn=lambda _: next(inputs),
        output_fn=outputs.append,
    )

    result = view.ask(
        "Дата: ", lambda raw: parse_and_validate_date(raw, today=date(2026, 11, 14))
    )

    assert result == date(2026, 11, 14)
    assert len(outputs) == 2


def test_view_show_menu_lists_only_read_actions() -> None:
    outputs: list[str] = []
    view = ConsoleView(input_fn=lambda _: "", output_fn=outputs.append)

    view.show_menu(
        [("1", "Переглянути всі витрати"), ("5", "Статистика за рік")]
    )
    rendered = "\n".join(outputs)

    assert "Переглянути всі витрати" in rendered
    assert "Статистика за рік" in rendered
    assert "Додати" not in rendered
    assert "Видалити" not in rendered


def test_view_show_menu_renders_items_and_exit_exactly() -> None:
    outputs: list[str] = []
    view = ConsoleView(input_fn=lambda _: "", output_fn=outputs.append)

    view.show_menu([("1", "Перше"), ("2", "Друге")])

    assert outputs == ["\n=== Трекер витрат ===", "1. Перше", "2. Друге", "0. Вихід"]


def test_view_show_expenses_truncates_long_category_and_formats_date() -> None:
    outputs: list[str] = []
    view = ConsoleView(input_fn=lambda _: "", output_fn=outputs.append)

    long_category = "дуже_довга_назва_категорії_яка_не_поміщається_в_колонку"
    expenses = [
        Expense(
            expense_id=1,
            amount=Decimal("1250.5"),
            category=long_category,
            date=date(2026, 11, 14),
        )
    ]

    view.show_expenses(expenses)
    rendered = "\n".join(outputs)

    assert "14.11.2026" in rendered
    assert "1 250,50 грн" in rendered
    assert "…" in rendered
    assert long_category not in rendered


def test_view_show_expenses_empty_list() -> None:
    outputs: list[str] = []
    view = ConsoleView(input_fn=lambda _: "", output_fn=outputs.append)

    view.show_expenses([])

    assert any("порожній" in line.lower() for line in outputs)


CARS = [
    Car(slug="toyota", name="Toyota", country="Japan"),
    Car(slug="bmw", name="BMW", country="Germany"),
]


def make_view(inputs: list[str]) -> tuple[ConsoleView, list[str]]:
    it = iter(inputs)
    outputs: list[str] = []
    return ConsoleView(input_fn=lambda _: next(it), output_fn=outputs.append), outputs


def test_choose_car_returns_selected_and_lists_all() -> None:
    view, outputs = make_view(["2"])

    assert view.choose_car(CARS) == CARS[1]
    rendered = "\n".join(outputs)
    assert "Toyota" in rendered and "Japan" in rendered
    assert "BMW" in rendered and "Germany" in rendered


@pytest.mark.parametrize("bad", ["abc", "3", "-1", "1.5", "99"])
def test_choose_car_retries_on_bad_input(bad: str) -> None:
    view, outputs = make_view([bad, "1"])

    assert view.choose_car(CARS) == CARS[0]
    assert any("[Помилка]" in line for line in outputs)


@pytest.mark.parametrize("exit_input", ["0", "", "  "])
def test_choose_car_exit_returns_none(exit_input: str) -> None:
    view, _ = make_view([exit_input])

    assert view.choose_car(CARS) is None


def test_choose_car_empty_list_shows_message_and_returns_none() -> None:
    view, outputs = make_view([])

    assert view.choose_car([]) is None
    assert outputs
