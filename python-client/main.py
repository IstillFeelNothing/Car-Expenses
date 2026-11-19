import os

import requests

from api_client import ApiClient, HttpGet
from api_storage import ApiStorage
from app import ExpenseApp
from repository import ExpenseRepository
from view import ConsoleView

DEFAULT_API_URL = "http://127.0.0.1:8000"


def main(http_get: HttpGet = requests.get) -> None:
    view = ConsoleView()

    car_slug = os.environ.get("CAR_SLUG", "").strip()
    if not car_slug:
        view.show_error(
            "Не задано змінну середовища CAR_SLUG (slug машини, напр. toyota)."
        )
        return

    api_url = os.environ.get("API_URL", DEFAULT_API_URL)
    client = ApiClient(api_url, http_get=http_get)
    storage = ApiStorage(client, car_slug)
    repo = ExpenseRepository(storage)
    app = ExpenseApp(repo=repo, view=view)

    try:
        app.run()
    except (KeyboardInterrupt, EOFError):
        view.show_message("\nРоботу перервано. До зустрічі!")


if __name__ == "__main__":
    main()
