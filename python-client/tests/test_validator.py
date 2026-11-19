import datetime
import re

import pytest

from validator import ValidationError, parse_and_validate_date

FIXED_TODAY = datetime.date(2026, 10, 28)
INCORRECT_DATE_FORMAT_ERROR = (
    "Некоректний формат або значення дати. Очікується YYYY-MM-DD або DD.MM.YYYY."
)
LEAP_TODAY_2000 = datetime.date(2000, 2, 29)


@pytest.mark.parametrize(
    "input_str, today, expected_output",
    [
        ("2023-01-01", FIXED_TODAY, datetime.date(2023, 1, 1)),
        ("01.01.2023", FIXED_TODAY, datetime.date(2023, 1, 1)),
        ("2023-1-1", FIXED_TODAY, datetime.date(2023, 1, 1)),
        ("1.1.2023", FIXED_TODAY, datetime.date(2023, 1, 1)),
        (" 01.01.2023 ", FIXED_TODAY, datetime.date(2023, 1, 1)),
        ("", FIXED_TODAY, FIXED_TODAY),
        ("28.10.2026", FIXED_TODAY, FIXED_TODAY),
        ("28.10.1926", FIXED_TODAY, datetime.date(1926, 10, 28)),
        ("28.02.1900", LEAP_TODAY_2000, datetime.date(1900, 2, 28)),
        ("29.02.2000", LEAP_TODAY_2000, LEAP_TODAY_2000),
    ],
)
def test_parse_and_validate_date_success(
    input_str: str, today: datetime.date, expected_output: datetime.date
) -> None:
    assert parse_and_validate_date(input_str, today=today) == expected_output


@pytest.mark.parametrize(
    "input_str, today, error_message",
    [
        ("01.01.23", FIXED_TODAY, INCORRECT_DATE_FORMAT_ERROR),
        ("2023-13-01", FIXED_TODAY, INCORRECT_DATE_FORMAT_ERROR),
        ("01.13.2023", FIXED_TODAY, INCORRECT_DATE_FORMAT_ERROR),
        ("31.02.2026", FIXED_TODAY, INCORRECT_DATE_FORMAT_ERROR),
        ("2026-02-31", FIXED_TODAY, INCORRECT_DATE_FORMAT_ERROR),
        ("29.02.2025", FIXED_TODAY, INCORRECT_DATE_FORMAT_ERROR),
        ("27/10/2026", FIXED_TODAY, INCORRECT_DATE_FORMAT_ERROR),
        ("abc", FIXED_TODAY, INCORRECT_DATE_FORMAT_ERROR),
        ("29.10.2026", FIXED_TODAY, "Дата не може бути в майбутньому."),
        ("27.10.1926", FIXED_TODAY, "Дата не може бути старішою за 100 років."),
        ("27.02.1900", LEAP_TODAY_2000, "Дата не може бути старішою за 100 років."),
    ],
)
def test_parse_and_validate_date_invalid(
    input_str: str, today: datetime.date, error_message: str
) -> None:
    with pytest.raises(ValidationError, match=re.escape(error_message)):
        parse_and_validate_date(input_str, today=today)


def test_parse_and_validate_date_wrong_type() -> None:
    with pytest.raises(
        TypeError, match="Потрібно передати рядок, що представляє дату."
    ):
        parse_and_validate_date(None)  # type: ignore[arg-type]
