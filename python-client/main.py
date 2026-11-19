import os

import requests

from api_client import ApiClient, HttpGet
from api_storage import ApiCarCatalog, ApiStorage
from app import ExpenseApp
from repository import ExpenseRepository
from storage_protocol import StorageError
from view import ConsoleView

DEFAULT_API_URL = "http://127.0.0.1:8000"


def _resolve_car_slug(client: ApiClient, view: ConsoleView) -> str | None:
    """CAR_SLUG з env (без запиту каталогу) або інтерактивний вибір із `/api/cars`."""
    env_slug = os.environ.get("CAR_SLUG", "").strip()
    if env_slug:
        return env_slug

    try:
        cars = ApiCarCatalog(client).load_cars()
    except StorageError as exc:
        view.show_error(str(exc))
        return None

    car = view.choose_car(cars)
    if car is None:
        return None
    view.show_message(f"Обрано: {car.name} ({car.country})")
    return car.slug


def main(http_get: HttpGet = requests.get) -> None:
    view = ConsoleView()
    api_url = os.environ.get("API_URL", DEFAULT_API_URL)
    client = ApiClient(api_url, http_get=http_get)

    try:
        car_slug = _resolve_car_slug(client, view)
        if car_slug is None:
            return

        storage = ApiStorage(client, car_slug)
        repo = ExpenseRepository(storage)
        ExpenseApp(repo=repo, view=view).run()
    except (KeyboardInterrupt, EOFError):
        view.show_message("\nРоботу перервано. До зустрічі!")


if __name__ == "__main__":
    main()
