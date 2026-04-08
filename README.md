# Slot Reservation System

REST API для бронирования слотов с поддержкой идемпотентности, оптимистичных блокировок и кэширования.

## Описание

Тестовое задание: реализация системы бронирования временных слотов на базе Laravel 11.

**Основные возможности:**
- Просмотр доступности слотов с кэшированием (Redis)
- Создание брони (hold) с идемпотентностью по UUID-ключу
- Подтверждение и отмена брони
- Защита от оверселла через транзакции с `SELECT FOR UPDATE`

**Стек:** PHP 8.4, Laravel 12, MySQL 8, Redis, Nginx, Docker

---

## Структура базы данных

### `slots`
| Поле        | Тип              | Описание                        |
|-------------|------------------|---------------------------------|
| `id`        | bigint PK        | Идентификатор слота             |
| `capacity`  | unsigned bigint  | Общая вместимость               |
| `remaining` | unsigned bigint  | Оставшееся количество мест      |

### `holds`
| Поле              | Тип              | Описание                              |
|-------------------|------------------|---------------------------------------|
| `id`              | bigint PK        | Идентификатор брони                   |
| `slot_id`         | FK → slots       | Слот                                  |
| `status`          | varchar(32)      | `held` / `confirmed` / `cancelled`    |
| `idempotency_key` | uuid unique      | Ключ идемпотентности                  |
| `expires_at`      | timestamp        | Время истечения брони (hold + 5 мин)  |

---

## Запуск через Docker

### 1. Клонировать репозиторий и настроить окружение

```bash
cp .env.example .env
```

Отредактировать `.env` — настроить подключение к БД и Redis:

```dotenv
DB_CONNECTION=mysql
DB_HOST=db
DB_PORT=3306
DB_DATABASE=laravel
DB_USERNAME=laravel
DB_PASSWORD=root

CACHE_STORE=redis
REDIS_HOST=redis
REDIS_PORT=6379
```

### 2. Собрать образы и запустить контейнеры

```bash
docker compose build
docker compose up -d
# или через make:
make up
```

### 3. Установить зависимости Composer

```bash
docker compose exec app composer install
```

### 4. Сгенерировать ключ приложения

```bash
docker compose exec app php artisan key:generate
```

### 5. Выполнить миграции

```bash
docker compose exec app php artisan migrate
# или:
make migrate
```

### 6. Заполнить тестовыми данными (10 слотов со случайной вместимостью 1–10)

```bash
docker compose exec app php artisan db:seed
# или:
make seed
```

### Сервисы после запуска

| Сервис   | Адрес                  |
|----------|------------------------|
| API      | http://localhost:8080/ |
| MySQL    | localhost:3306         |
| Redis    | localhost:6389         |

---

## Команды Makefile

```bash
make up       # Запустить контейнеры
make down     # Остановить контейнеры
make restart  # Перезапустить контейнеры
make migrate  # Выполнить миграции
make seed     # Заполнить тестовыми данными
make cache    # Очистить кэш
make shell    # Открыть bash в контейнере app
make help     # Показать все команды
```

---

## API

Базовый URL: `http://localhost:8080/`

### Эндпоинты

| Метод    | URL                        | Описание                      |
|----------|----------------------------|-------------------------------|
| `GET`    | `/slots/availability`      | Список слотов с остатками     |
| `POST`   | `/slots/{id}/hold`         | Создать бронь                 |
| `POST`   | `/holds/{id}/confirm`      | Подтвердить бронь             |
| `DELETE` | `/holds/{id}`              | Отменить бронь                |

### Заголовки

| Заголовок         | Обязателен | Описание                                  |
|-------------------|------------|-------------------------------------------|
| `Idempotency-Key` | Да (hold)  | UUID v4 — ключ идемпотентности для `/hold`|
| `Content-Type`    | Нет        | `application/json`                        |

### Статусы ответов

| Код  | Описание                                              |
|------|-------------------------------------------------------|
| 200  | Успешный запрос (возврат существующей брони по ключу) |
| 201  | Бронь создана                                         |
| 409  | Конфликт: оверселл, неверный статус, истёкшая бронь   |
| 400  | Неверный или отсутствующий `Idempotency-Key`          |
| 404  | Слот или бронь не найдены                             |
| 500  | Внутренняя ошибка                                     |

---

## Примеры curl-запросов

### Получить доступность слотов

```bash
curl -s http://localhost:8080/slots/availability | jq
```

Ответ:
```json
[
  {"slot_id": 1, "capacity": 5, "remaining": 5},
  {"slot_id": 2, "capacity": 3, "remaining": 3}
]
```

---

### Создать бронь

```bash
curl -s -X POST http://localhost:8080/slots/1/hold \
  -H "Idempotency-Key: 550e8400-e29b-41d4-a716-446655440000" \
  | jq
```

Ответ `201 Created`:
```json
{
  "id": 1,
  "slot_id": 1,
  "status": "held",
  "idempotency_key": "550e8400-e29b-41d4-a716-446655440000",
  "expires_at": "2026-04-08T16:15:00.000000Z",
  "created_at": "2026-04-08T16:10:00.000000Z",
  "updated_at": "2026-04-08T16:10:00.000000Z"
}
```

---

### Повторный запрос с тем же ключом (идемпотентность)

Тот же запрос с тем же `Idempotency-Key` вернёт существующую бронь без создания новой:

```bash
curl -s -X POST http://localhost:8080/slots/1/hold \
  -H "Idempotency-Key: 550e8400-e29b-41d4-a716-446655440000" \
  | jq
```

Ответ `200 OK` — та же запись, новая не создаётся:
```json
{
  "id": 1,
  "slot_id": 1,
  "status": "held",
  "idempotency_key": "550e8400-e29b-41d4-a716-446655440000",
  "expires_at": "2026-04-08T16:15:00.000000Z",
  "created_at": "2026-04-08T16:10:00.000000Z",
  "updated_at": "2026-04-08T16:10:00.000000Z"
}
```

---

### Подтвердить бронь

```bash
curl -s -X POST http://localhost:8080/holds/1/confirm | jq
```

Ответ `200 OK`:
```json
{
  "id": 1,
  "slot_id": 1,
  "status": "confirmed",
  "idempotency_key": "550e8400-e29b-41d4-a716-446655440000",
  "expires_at": "2026-04-08T16:15:00.000000Z",
  "created_at": "2026-04-08T16:10:00.000000Z",
  "updated_at": "2026-04-08T16:11:00.000000Z"
}
```

---

### Отменить бронь

```bash
curl -s -X DELETE http://localhost:8080/holds/1 | jq
```

Ответ `200 OK`:
```json
{
  "id": 1,
  "slot_id": 1,
  "status": "cancelled",
  "idempotency_key": "550e8400-e29b-41d4-a716-446655440000",
  "expires_at": "2026-04-08T16:15:00.000000Z",
  "created_at": "2026-04-08T16:10:00.000000Z",
  "updated_at": "2026-04-08T16:12:00.000000Z"
}
```

---

### Конфликт при оверселле (все места заняты)

Если `remaining = 0`, попытка создать бронь вернёт ошибку:

```bash
curl -s -X POST http://localhost:8080/slots/1/hold \
  -H "Idempotency-Key: 123e4567-e89b-12d3-a456-426614174000" \
  | jq
```

Ответ `409 Conflict`:
```json
{
  "error": true,
  "message": "Slot remaining cannot be less than 0"
}
```

---

### Конфликт при подтверждении уже подтверждённой / отменённой брони

```bash
curl -s -X POST http://localhost:8080/holds/1/confirm | jq
```

Ответ `409 Conflict`:
```json
{
  "error": true,
  "message": "Hold not in held status"
}
```

---

## Прогрев кэша

Ключ `slots:availability` заполняется автоматически при первом обращении к `/slots/availability` и хранится в Redis 5–15 секунд. После истечения TTL следующий запрос снова обратится к БД и обновит кэш.

### Автоматический прогрев по расписанию

В `bootstrap/app.php` настроен планировщик Laravel: каждые 5 минут с 10:00 до 22:00 вызывается `SlotService::getAvailability()`, которая обновляет ключ `slots:availability` в Redis.

**Запустить планировщик (демон, блокирующий процесс):**

```bash
docker compose exec app php artisan schedule:work
# или:
make schedule-work
```

**Для продакшена** рекомендуется запускать `schedule:run` через системный cron каждую минуту:

```cron
* * * * * docker compose -f /path/to/docker-compose.yml exec -T app php artisan schedule:run >> /dev/null 2>&1
```

**Разовый запуск** (удобно для отладки):

```bash
docker compose exec app php artisan schedule:run
# или:
make schedule
```

**Просмотр расписания:**

```bash
docker compose exec app php artisan schedule:list
```

Вывод покажет задачу `cache:warm-slots` с выражением `*/5 * * * *` и ограничением `between 10:00-22:00`.

### Ручной прогрев через curl

```bash
curl -s http://localhost:8080/slots/availability > /dev/null
```

### Ручной прогрев через artisan tinker

```bash
docker compose exec app php artisan tinker
```

```php
app(\App\Service\SlotService::class)->getAvailability();
```

### Проверить наличие ключа в Redis

```bash
docker compose exec redis redis-cli get slots:availability
```

Если ключ существует, вернётся JSON со списком слотов. Если истёк — вернётся `(nil)`.

### Сбросить кэш вручную

```bash
docker compose exec redis redis-cli del slots:availability
# или через artisan:
docker compose exec app php artisan cache:clear
```

---

## Поведение системы

- **Идемпотентность:** повторный POST `/slots/{id}/hold` с тем же `Idempotency-Key` (UUID) возвращает существующую бронь, не создавая дубликат.
- **Блокировка от оверселла:** уменьшение `remaining` происходит только при подтверждении (`confirm`), а не при создании hold. Оба этапа защищены транзакцией с `SELECT FOR UPDATE`.
- **Истечение брони:** hold действителен 5 минут. Подтверждение после истечения вернёт `409`.
- **Кэш доступности:** список слотов кэшируется в Redis на 5–15 секунд. Инвалидируется при подтверждении или отмене брони.
