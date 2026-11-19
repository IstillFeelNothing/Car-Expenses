from urllib.parse import quote

from car import Car, InvalidCarDataError
from expense import Expense, InvalidExpenseDataError
from storage_protocol import CarCatalog, JsonClient, ReadableStorage, StorageError


class ApiStorage(ReadableStorage):
    """Читає витрати однієї машини з `GET /api/cars/{slug}/expenses`."""

    def __init__(self, client: JsonClient, car_slug: str) -> None:
        self._client = client
        self._car_slug = car_slug

    def load(self) -> list[Expense]:
        slug = quote(self._car_slug, safe="")
        payload = self._client.get_json(f"/api/cars/{slug}/expenses")
        if not isinstance(payload, list):
            raise StorageError("Сервер повернув неочікуваний формат: очікується список")

        expenses: list[Expense] = []
        for index, item in enumerate(payload, start=1):
            try:
                expenses.append(Expense.from_dict(item))
            except InvalidExpenseDataError as err:
                raise StorageError(f"Некоректний запис витрати №{index}: {err}") from err
        return expenses


class ApiCarCatalog(CarCatalog):
    """Читає список машин із `GET /api/cars`."""

    def __init__(self, client: JsonClient) -> None:
        self._client = client

    def load_cars(self) -> list[Car]:
        payload = self._client.get_json("/api/cars")
        if not isinstance(payload, list):
            raise StorageError("Сервер повернув неочікуваний формат: очікується список")

        cars: list[Car] = []
        for index, item in enumerate(payload, start=1):
            try:
                cars.append(Car.from_dict(item))
            except InvalidCarDataError as err:
                raise StorageError(f"Некоректний запис машини №{index}: {err}") from err
        return cars
