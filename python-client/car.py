from dataclasses import dataclass
from typing import Any


class InvalidCarDataError(Exception):
    pass


def _required_str(data: dict[str, Any], key: str) -> str:
    if key not in data:
        raise InvalidCarDataError(f"Обов'язкове поле '{key}' відсутнє.")
    value = data[key]
    if not isinstance(value, str):
        raise InvalidCarDataError(
            f"Поле '{key}' повинно бути рядком, отримано: {type(value)}"
        )
    if not value.strip():
        raise InvalidCarDataError(f"Поле '{key}' не може бути порожнім.")
    return value.strip()


@dataclass(frozen=True)
class Car:
    slug: str
    name: str
    country: str

    @classmethod
    def from_dict(cls, data: dict[str, Any]) -> "Car":
        """Створює машину з елемента `GET /api/cars` (`id` — це slug)."""
        if not isinstance(data, dict):
            raise InvalidCarDataError("Дані машини повинні бути словником.")

        return cls(
            slug=_required_str(data, "id"),
            name=_required_str(data, "name"),
            country=_required_str(data, "country"),
        )
