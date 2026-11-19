from datetime import date
from decimal import Decimal

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

    view.show_menu()
    rendered = "\n".join(outputs)

    assert "Переглянути всі витрати" in rendered
    assert "Статистика за рік" in rendered
    assert "Додати" not in rendered
    assert "Видалити" not in rendered


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
