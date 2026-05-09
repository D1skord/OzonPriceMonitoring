# AGENTS.md

Короткий onboarding для агентов, которые продолжают работу над проектом.

## Контекст проекта

Это Laravel MVP для мониторинга цен товаров из Ozon wishlist через VK-бота.

Поток данных:

1. Пользователь отправляет VK-боту ссылку на Ozon wishlist.
2. Приложение привязывает один активный wishlist к VK-пользователю.
3. Scheduler раз в несколько минут ставит due wishlists в database queue.
4. Worker запускает `CrawlWishlistJob`, открывает wishlist через Playwright с сохраненным Ozon profile, парсит товары и цены.
5. `WishlistSyncService` сохраняет products, snapshots, alerts и crawl runs.
6. Если цена стала новым историческим минимумом, `VkBotClient` отправляет алерт.

## Жесткое правило окружения

Все команды приложения выполняются только внутри Docker Compose.

Не запускай на хосте:

- `php artisan ...`
- `composer ...`
- `vendor/bin/pint ...`
- `vendor/bin/phpunit ...`
- Playwright/browser automation

Правильно:

```bash
docker compose --env-file .env exec -T php php artisan test
docker compose --env-file .env exec -T php vendor/bin/pint --test
docker compose --env-file .env exec -T php php artisan migrate --force
```

Для обычного запуска используй `--no-build`, чтобы Docker не подтягивал base images без явной причины:

```bash
docker compose --env-file .env up -d --no-build
```

Если локального образа нет, сначала проверь состояние:

```bash
docker compose --env-file .env ps -a
docker compose --env-file .env images
```

Потом уже осознанно выполняй build:

```bash
docker compose --env-file .env build php nginx
docker compose --env-file .env up -d --no-build
```

## Docker Compose

Основные сервисы:

- `nginx` - HTTP entrypoint, локально обычно `http://127.0.0.1:8080`
- `php` - app container для artisan/test/manual commands
- `postgres` - PostgreSQL
- `worker` - `php artisan queue:work database`
- `scheduler` - `php artisan schedule:work`
- `browser-login` - профиль для ручного логина в Ozon через noVNC

PHP runtime image общий: `ozonprices-php`. Worker, scheduler и browser-login используют тот же image, чтобы не плодить разные сборки одного Dockerfile.

PostgreSQL 18 хранит volume в `/var/lib/postgresql`, не меняй обратно на `/var/lib/postgresql/data`.

## Архитектурные правила

- Бизнес-логика живет в сервисах, не в Eloquent models и не в Filament resources.
- Parser strategy не пишет в БД, а возвращает DTO.
- Цены хранятся integer minor units: `499000` означает `4 990 ₽`.
- Первый обход wishlist создает baseline без alerts.
- Внешние ошибки пишутся в `crawl_runs` и видны в Filament.
- MVP не использует Redis/RabbitMQ, только Laravel database queue.
- Не добавляй Wildberries, web cabinet, external API, ML/analytics и ручное редактирование товаров без отдельного запроса.

## Ключевые места

- Models: `app/Models`
- Migrations: `database/migrations`
- Crawl job: `app/Jobs/CrawlWishlistJob.php`
- Services: `app/Services`
- Parser strategies: `app/Services/Parsing`
- VK listener command: `app/Console/Commands/VkListenCommand.php`
- Due wishlist dispatcher: `app/Console/Commands/DispatchDueWishlistsCommand.php`
- Playwright runner: `resources/playwright/ozon-wishlist-crawler.mjs`
- Filament resources: `app/Filament/Resources`
- Parser fixtures: `tests/Fixtures`

## Проверки перед сдачей

Минимальный набор:

```bash
docker compose --env-file .env exec -T php php artisan migrate --force
docker compose --env-file .env exec -T php php artisan filament:assets
docker compose --env-file .env exec -T php php artisan test
docker compose --env-file .env exec -T php vendor/bin/pint --test
```

Полезные smoke checks:

```bash
docker compose --env-file .env exec -T php php artisan route:list --except-vendor
docker compose --env-file .env exec -T php php artisan about --only=environment
curl --max-time 5 -I http://127.0.0.1:8080
curl --max-time 5 -I http://127.0.0.1:8080/admin/login
curl --max-time 5 -I http://127.0.0.1:8080/css/filament/filament/app.css
```

## Секреты

Не печатай содержимое `.env` в ответ пользователю. Для документации и примеров используй только `.env.example`.

## Когда нужна осторожность

- Не выполняй `migrate:fresh`, `down -v`, удаление volumes или prune без явного подтверждения пользователя.
- Не пересобирай образы через `up --build`, если цель просто запустить проект.
- Если Filament открыт без стилей, проверь `php artisan filament:assets` внутри Docker.
- Если Playwright не стартует после `composer install`, проверь, что внутри контейнера выполнено:

```bash
docker compose --env-file .env exec -T php vendor/bin/playwright-install --browsers
```
