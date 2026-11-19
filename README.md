# Car Expenses

Веб-сервіс обліку витрат на автомобілі на Symfony. Дані зберігаються в БД (Doctrine, SQLite) і доступні трьома шляхами:

- сайт на Twig (список машин, сторінка машини з витратами);
- адмінка EasyAdmin з CRUD для машин і витрат (лише `ROLE_ADMIN`);
- JSON API, з якого читає консольний Python-клієнт зі статистикою витрат.


## Вимоги

- PHP 8.4 або новіший з розширеннями `pdo_sqlite` і `sqlite3`;
- розширення `intl` бажане; без нього все працює (адмінка використовує власне поле дати);
- Composer;
- Python 3.10+ (лише для клієнта в `python-client/`).

## Встановлення

```bash
composer install
```

База даних — SQLite. За замовчуванням `.env` вказує на `var/data_<env>.db`. Щоб змінити шлях, створіть `.env.local`
(у git не потрапляє) і задайте `DATABASE_URL`, наприклад:

```
DATABASE_URL="sqlite:///%kernel.project_dir%/var/data.db"
```

Створіть таблиці:

```bash
php bin/console doctrine:migrations:migrate -n
```

## Дані й адміністратор

Імпорт машин із `data/cars.json` (повторний запуск оновлює записи за `slug`, дублікатів не створює):

```bash
php bin/console app:import-cars data/cars.json
```

Створення адміністратора (пароль запитується приховано, двічі; мінімум 8 символів; у командному рядку не передається):

```bash
php bin/console app:create-admin admin@example.com
```

## Запуск сервера

```bash
php -S 127.0.0.1:8000 -t public
# або, якщо встановлено Symfony CLI:
symfony serve
```

## Сторінки

| Метод | URL | Що це | Доступ |
|-------|-----|-------|--------|
| GET | `/` | редірект на `/cars` | усі |
| GET | `/cars` | список машин із кількістю й сумою витрат | усі |
| GET | `/cars/{slug}` | машина, витрати за категоріями, список витрат; 404, якщо немає | усі |
| GET, POST | `/login` | форма входу | усі |
| GET | `/logout` | вихід (потрібен CSRF-токен, посилання дає меню адмінки) | авторизовані |
| * | `/admin` | EasyAdmin: машини й витрати | `ROLE_ADMIN` |

## JSON API

Лише читання (GET). Відповіді мають `Content-Type: application/json`.

`GET /api/cars` — `id` це slug машини:

```json
[
  { "id": "toyota", "name": "Toyota", "country": "Japan" }
]
```

`GET /api/cars/{slug}/expenses` — витрати машини, `date` DESC, `id` DESC; `amount` завжди рядок із 2 знаками:

```json
[
  {
    "id": 1,
    "car": "toyota",
    "amount": "1250.00",
    "category": "пальне",
    "date": "2026-11-01",
    "description": "Повний бак"
  }
]
```

`description` може бути `null`.

Усі помилки під `/api/*` мають один формат `{"error": "<повідомлення>"}` і відповідний HTTP-статус:

| Статус | Коли | Тіло |
|--------|------|------|
| 404 | немає машини з таким slug | `{"error": "Машину не знайдено"}` |
| 404 | немає такого маршруту (`/api/nope`, `/api/cars/toyota`) | `{"error": "Не знайдено"}` |
| 405 | метод не GET (є заголовок `Allow`) | `{"error": "Метод не дозволений"}` |
| 500 | внутрішня помилка (деталі не розкриваються) | `{"error": "Внутрішня помилка сервера"}` |

Поза `/api` помилки лишаються звичайними HTML-сторінками.

## Python-клієнт

Консольний клієнт читає API і рахує статистику витрат (список, періоди, підсумки). Лише читає, нічого не змінює.

```bash
cd python-client
python -m venv .venv
source .venv/bin/activate        # Windows PowerShell: .venv\Scripts\Activate.ps1
pip install -e .
pip install pytest ruff mypy types-requests   # для розробки й перевірок

python main.py
```

- `API_URL` — адреса сервера, за замовчуванням `http://127.0.0.1:8000`
  (`API_URL=http://127.0.0.1:8000 python main.py`; у PowerShell: `$env:API_URL="http://127.0.0.1:8000"`).
- Машину клієнт пропонує вибрати зі списку з `/api/cars` (номер; `0` або Enter — вихід).
- `CAR_SLUG` (необов'язково) пропускає вибір: `CAR_SLUG=toyota python main.py`.

Перевірки клієнта:

```bash
cd python-client
pytest
ruff check .
mypy
```

## Тестування

```bash
php bin/phpunit

php bin/console lint:container
php bin/console lint:twig templates
php bin/console lint:yaml config
php bin/console doctrine:schema:validate --env=test
```

Тестова БД — SQLite з `.env.test`. Якщо тести скаржаться на `no such table`:

```bash
php bin/console --env=test doctrine:database:create --if-not-exists
php bin/console --env=test doctrine:migrations:migrate -n
```

Python-клієнт: `cd python-client && pytest && ruff check . && mypy` (мережі в тестах немає).

## Структура репозиторію

```
src/            Entity, Repository, Service, Dto, Command, EventListener, Twig, Controller (Api/, Admin/)
templates/      Twig-шаблони сайту, входу й адмінки
assets/         стилі й JS (AssetMapper)
config/         конфігурація Symfony
migrations/     міграції Doctrine
data/           cars.json для імпорту
tests/          PHPUnit: Unit, Functional, Command
python-client/  консольний клієнт (pytest, ruff, mypy)
```

## Примітки

- БД — SQLite; таблиці створюються лише міграціями.
- Грошові суми скрізь передаються рядками (`"1250.00"`), у Python це `Decimal`; `float` не використовується.
- Сайт не залежить від CDN: стилі й скрипти віддаються локально через AssetMapper.
- Секрети (`.env.local`) у git не потрапляють.
