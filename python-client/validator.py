import datetime

INCORRECT_DATE_FORMAT_ERROR = (
    "Некоректний формат або значення дати. Очікується YYYY-MM-DD або DD.MM.YYYY."
)

MAX_EXPENSE_AGE_YEARS = 100
DATE_FORMATS = (
    "%Y-%m-%d",
    "%d.%m.%Y",
)


class ValidationError(Exception):
    pass


def parse_month(raw: str) -> int:
    cleaned = raw.strip()
    if not cleaned.isdigit() or not 1 <= int(cleaned) <= 12:
        raise ValidationError("Місяць має бути числом від 1 до 12.")
    return int(cleaned)


def parse_year(raw: str) -> int:
    cleaned = raw.strip()
    if not cleaned.isdigit() or not 1900 <= int(cleaned) <= 2100:
        raise ValidationError("Рік має бути коректним чотиризначним числом.")
    return int(cleaned)


def parse_and_validate_date(
    date_str: str, today: datetime.date | None = None
) -> datetime.date:
    if not isinstance(date_str, str):
        raise TypeError("Потрібно передати рядок, що представляє дату.")

    cleaned_str = date_str.strip()

    if today is None:
        today = datetime.date.today()

    if not cleaned_str:
        return today

    parsed_date = None

    for fmt in DATE_FORMATS:
        try:
            parsed_date = datetime.datetime.strptime(cleaned_str, fmt).date()
            break
        except ValueError:
            continue

    if parsed_date is None:
        raise ValidationError(INCORRECT_DATE_FORMAT_ERROR)

    if parsed_date > today:
        raise ValidationError("Дата не може бути в майбутньому.")

    try:
        min_date = datetime.date(
            today.year - MAX_EXPENSE_AGE_YEARS, today.month, today.day
        )
    except ValueError:
        min_date = datetime.date(today.year - MAX_EXPENSE_AGE_YEARS, today.month, 28)

    if parsed_date < min_date:
        raise ValidationError(
            f"Дата не може бути старішою за {MAX_EXPENSE_AGE_YEARS} років."
        )

    return parsed_date
