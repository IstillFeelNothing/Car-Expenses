from urllib.parse import quote

from api_client import ApiClient
from expense import Expense, InvalidExpenseDataError
from storage_protocol import ReadableStorage, StorageError


class ApiStorage(ReadableStorage):
    """Читає витрати однієї машини з `GET /api/cars/{slug}/expenses`."""

    def __init__(self, client: ApiClient, car_slug: str) -> None:
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
