from collections.abc import Callable
from typing import Any

import pytest
import requests

from main import main
from tests.fakes import fake_get, make_response

CARS_PAYLOAD = [
    {"id": "toyota", "name": "Toyota", "country": "Japan"},
    {"id": "bmw", "name": "BMW", "country": "Germany"},
]


def routed_get(
    routes: dict[str, tuple[int, Any]], calls: list[str]
) -> Callable[..., requests.Response]:
    """Фейковий http_get: відповідь залежить від закінчення URL."""

    def _get(url: str, timeout: float) -> requests.Response:
        calls.append(url)
        for suffix, (status, payload) in routes.items():
            if url.endswith(suffix):
                return make_response(status, payload)
        raise AssertionError(f"unexpected url {url}")

    return _get


def feed_input(monkeypatch: pytest.MonkeyPatch, answers: list[str]) -> None:
    it = iter(answers)
    monkeypatch.setattr("builtins.input", lambda _="": next(it))


@pytest.mark.parametrize("interrupt_exc", [KeyboardInterrupt(), EOFError()])
def test_main_handles_ctrl_c_and_ctrl_d_gracefully(
    monkeypatch: pytest.MonkeyPatch,
    capsys: pytest.CaptureFixture[str],
    interrupt_exc: BaseException,
) -> None:
    monkeypatch.setenv("CAR_SLUG", "toyota")
    monkeypatch.setenv("API_URL", "http://api.test")

    def raise_interrupt(_: str = "") -> str:
        raise interrupt_exc

    monkeypatch.setattr("builtins.input", raise_interrupt)

    main(http_get=fake_get(payload=[]))

    captured = capsys.readouterr()
    assert "перервано" in captured.out.lower()


@pytest.mark.parametrize("interrupt_exc", [KeyboardInterrupt(), EOFError()])
def test_main_ctrl_c_and_ctrl_d_during_car_choice(
    monkeypatch: pytest.MonkeyPatch,
    capsys: pytest.CaptureFixture[str],
    interrupt_exc: BaseException,
) -> None:
    monkeypatch.delenv("CAR_SLUG", raising=False)

    def raise_interrupt(_: str = "") -> str:
        raise interrupt_exc

    monkeypatch.setattr("builtins.input", raise_interrupt)

    main(http_get=fake_get(payload=CARS_PAYLOAD))

    assert "перервано" in capsys.readouterr().out.lower()


def test_main_uses_api_url_and_car_slug_from_env(
    monkeypatch: pytest.MonkeyPatch,
) -> None:
    monkeypatch.setenv("CAR_SLUG", "toyota")
    monkeypatch.setenv("API_URL", "http://api.test/")
    monkeypatch.setattr("builtins.input", lambda _="": "0")
    calls: list[tuple[str, float]] = []

    main(http_get=fake_get(payload=[], calls=calls))

    assert calls == [("http://api.test/api/cars/toyota/expenses", 5.0)]


def test_main_car_slug_skips_catalog(monkeypatch: pytest.MonkeyPatch) -> None:
    monkeypatch.setenv("CAR_SLUG", "toyota")
    monkeypatch.setenv("API_URL", "http://api.test")
    feed_input(monkeypatch, ["0"])
    calls: list[str] = []

    main(http_get=routed_get({"/expenses": (200, [])}, calls))

    assert calls == ["http://api.test/api/cars/toyota/expenses"]


def test_main_interactive_choice_requests_catalog_then_expenses(
    monkeypatch: pytest.MonkeyPatch, capsys: pytest.CaptureFixture[str]
) -> None:
    monkeypatch.delenv("CAR_SLUG", raising=False)
    monkeypatch.setenv("API_URL", "http://api.test")
    feed_input(monkeypatch, ["2", "0"])  # друга машина, потім вихід із меню
    calls: list[str] = []
    routes: dict[str, tuple[int, Any]] = {
        "/api/cars": (200, CARS_PAYLOAD),
        "/api/cars/bmw/expenses": (200, []),
    }

    main(http_get=routed_get(routes, calls))

    assert calls == [
        "http://api.test/api/cars",
        "http://api.test/api/cars/bmw/expenses",
    ]
    assert "BMW" in capsys.readouterr().out


def test_main_catalog_error_is_shown_without_traceback(
    monkeypatch: pytest.MonkeyPatch, capsys: pytest.CaptureFixture[str]
) -> None:
    monkeypatch.delenv("CAR_SLUG", raising=False)
    monkeypatch.setenv("API_URL", "http://api.test")
    calls: list[tuple[str, float]] = []

    main(http_get=fake_get(exc=requests.ConnectionError(), calls=calls))

    assert "Немає з'єднання" in capsys.readouterr().out
    assert len(calls) == 1


@pytest.mark.parametrize("answer", ["0", ""])
def test_main_exit_at_car_choice_is_silent(
    monkeypatch: pytest.MonkeyPatch,
    capsys: pytest.CaptureFixture[str],
    answer: str,
) -> None:
    monkeypatch.delenv("CAR_SLUG", raising=False)
    monkeypatch.setenv("API_URL", "http://api.test")
    feed_input(monkeypatch, [answer])
    calls: list[str] = []

    main(http_get=routed_get({"/api/cars": (200, CARS_PAYLOAD)}, calls))

    assert calls == ["http://api.test/api/cars"]
    assert "[Помилка]" not in capsys.readouterr().out


def test_main_empty_catalog_exits_with_message(
    monkeypatch: pytest.MonkeyPatch, capsys: pytest.CaptureFixture[str]
) -> None:
    monkeypatch.delenv("CAR_SLUG", raising=False)
    feed_input(monkeypatch, [])

    main(http_get=fake_get(payload=[]))

    assert capsys.readouterr().out.strip()


def test_main_api_error_is_shown_not_raised(
    monkeypatch: pytest.MonkeyPatch, capsys: pytest.CaptureFixture[str]
) -> None:
    monkeypatch.setenv("CAR_SLUG", "nope")
    monkeypatch.setenv("API_URL", "http://api.test")

    main(http_get=fake_get(status=404))

    assert "Не знайдено" in capsys.readouterr().out
