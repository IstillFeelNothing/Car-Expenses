from typing import Any

import pytest

from car import Car, InvalidCarDataError


def test_from_dict_maps_id_to_slug() -> None:
    car = Car.from_dict({"id": "toyota", "name": "Toyota", "country": "Japan"})

    assert car == Car(slug="toyota", name="Toyota", country="Japan")


def test_from_dict_ignores_extra_fields() -> None:
    car = Car.from_dict({"id": "bmw", "name": "BMW", "country": "DE", "x": 1})

    assert car.slug == "bmw"


def test_car_is_frozen() -> None:
    car = Car(slug="a", name="A", country="B")

    with pytest.raises(AttributeError):
        car.name = "C"  # type: ignore[misc]


@pytest.mark.parametrize("missing", ["id", "name", "country"])
def test_from_dict_missing_field(missing: str) -> None:
    data = {"id": "toyota", "name": "Toyota", "country": "Japan"}
    del data[missing]

    with pytest.raises(InvalidCarDataError, match=missing):
        Car.from_dict(data)


@pytest.mark.parametrize("field", ["id", "name", "country"])
@pytest.mark.parametrize("bad", [1, None, 1.5, ["x"], "", "   "])
def test_from_dict_bad_field_value(field: str, bad: Any) -> None:
    data = {"id": "toyota", "name": "Toyota", "country": "Japan", field: bad}

    with pytest.raises(InvalidCarDataError, match=field):
        Car.from_dict(data)


def test_from_dict_non_dict() -> None:
    with pytest.raises(InvalidCarDataError):
        Car.from_dict("toyota")  # type: ignore[arg-type]
