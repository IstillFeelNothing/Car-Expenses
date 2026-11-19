import pytest

from main import main
from tests.fakes import fake_get


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


def test_main_uses_api_url_and_car_slug_from_env(
    monkeypatch: pytest.MonkeyPatch,
) -> None:
    monkeypatch.setenv("CAR_SLUG", "toyota")
    monkeypatch.setenv("API_URL", "http://api.test/")
    monkeypatch.setattr("builtins.input", lambda _="": "0")
    calls: list[tuple[str, float]] = []

    main(http_get=fake_get(payload=[], calls=calls))

    assert calls == [("http://api.test/api/cars/toyota/expenses", 5.0)]


def test_main_without_car_slug_shows_error(
    monkeypatch: pytest.MonkeyPatch, capsys: pytest.CaptureFixture[str]
) -> None:
    monkeypatch.delenv("CAR_SLUG", raising=False)
    calls: list[tuple[str, float]] = []

    main(http_get=fake_get(payload=[], calls=calls))

    assert "CAR_SLUG" in capsys.readouterr().out
    assert calls == []


def test_main_api_error_is_shown_not_raised(
    monkeypatch: pytest.MonkeyPatch, capsys: pytest.CaptureFixture[str]
) -> None:
    monkeypatch.setenv("CAR_SLUG", "nope")
    monkeypatch.setenv("API_URL", "http://api.test")

    main(http_get=fake_get(status=404))

    assert "Не знайдено" in capsys.readouterr().out
