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

Compose-файлы разделены по окружениям:

- `docker-compose.yml` - локальная разработка.
- `docker-compose.test.yml` - изолированное окружение для CI и локальных тестов.
- `docker-compose.prod.yml` - production compose для VDS/deploy.

Основные dev/prod сервисы:

- `nginx` - HTTP entrypoint, локально обычно `http://127.0.0.1:8080`
- `php` - app container для artisan/test/manual commands
- `postgres` - PostgreSQL
- `worker` - `php artisan queue:work database`
- `scheduler` - `php artisan schedule:work`
- `browser-login` - профиль для ручного логина в Ozon через noVNC

Test compose содержит `php` и `postgres_test`; тестовые команды используют `.env.test`.

PHP runtime image общий: `ozonprices-php`. Worker, scheduler и browser-login используют тот же image, чтобы не плодить разные сборки одного Dockerfile.

PostgreSQL 18 хранит volume в `/var/lib/postgresql`, не меняй обратно на `/var/lib/postgresql/data`.

## Production deployment flow (from local to production)

### Пошаговый флоу раскатки фичи на production

**1. Локальная разработка и проверки:**
```bash
git status --short --branch           # убедиться что worktree чист
# ... сделать изменения в коде ...
make check                          # typecheck + tests + build
```

**2. Коммит и пуш:**
```bash
git add <changed-files>
git commit -m "описание изменений"
git push origin main
```

**3. Дождаться зеленого CI:**
```bash
gh run list --repo D1skord/OzonPriceMonitoring --workflow ci --limit 3
```

**4. Запустить deploy через GitHub Actions:**
```bash
gh workflow run deploy --repo D1skord/OzonPriceMonitoring
gh run list --repo D1skord/OzonPriceMonitoring --workflow deploy --limit 3
```

**5. Проверить что прод поднялся:**
```bash
# На сервере:
docker ps --format 'table {{.Names}}\t{{.Status}}' | grep ozonprices_prod

# Удаленно:
curl -sI https://pricemonitoring.vinichenko-ivan.ru/
curl -sI https://pricemonitoring.vinichenko-ivan.ru/admin/login
```

### Контейнеры production

| Контейнер | Команда | Описание |
|-----------|---------|----------|
| nginx | - | Проксирует 8083→80 |
| php | - | PHP-FPM |
| postgres | - | База данных |
| worker | `queue:work` | Обрабатывает jobs (парсинг Ozon) |
| scheduler | `schedule:work` | Каждую минуту запускает `wishlists:dispatch-due` |
| vk-bot | `vk:listen` | Long Poll для VK Bot |

### Production путь на сервере

```
/var/www/vinichenko/data/www/pricemonitoring.vinichenko-ivan.ru/
```

### Проверка логов проде

```bash
# На сервере:
docker logs ozonprices_prod_worker_1 --tail=20
docker logs ozonprices_prod_scheduler_1 --tail=20
docker logs ozonprices_prod_vk-bot_1 --tail=20
docker logs ozonprices_prod_php_1 --tail=20
```

## Авторизация в Ozon через браузер (для парсинга)

Для авторизации используется отдельный `browser-login` service. Он поднимает noVNC, Xvfb, x11vnc и обычный Chromium с общим Docker volume `ozon_browser_profile`. Ручной логин не запускается через Playwright.

### Запуск VNC-сессии для авторизации

**1. Поднять noVNC и браузер:**
```bash
# На сервере:
cd /var/www/vinichenko/data/www/pricemonitoring.vinichenko-ivan.ru

docker-compose --env-file .env -p ozonprices_prod -f docker-compose.prod.yml up -d --no-build browser-login
```

**2. Открыть в браузере:**
```
http://82.146.43.174:6080/vnc.html
```
Нажать Connect, авторизоваться в Ozon.

**3. После авторизации — остановить VNC:**
```bash
# На сервере:
docker-compose --env-file .env -p ozonprices_prod -f docker-compose.prod.yml stop browser-login
```

### Профиль браузера Ozon

```
/var/www/vinichenko/data/www/pricemonitoring.vinichenko-ivan.ru/storage/app/ozon-browser-profile/
```

Путь настраивается через `OZON_PROFILE_PATH` в `.env`.

### Ограничения и диагностика

- Chromium запускается с флагами `--no-sandbox --disable-dev-shm-usage` внутри контейнера.
- Если `6080` занят или открывается не тот браузер, проверь:

```bash
docker ps --format 'table {{.Names}}\t{{.Status}}\t{{.Ports}}'
```

## CI/CD

- GitHub Actions CI: `.github/workflows/ci.yml`, запускается на push в `main` и pull request.
- CI создает `.env` и `.env.test` из example-файлов, затем запускает `make test` и `make pint`.
- Deploy: `.github/workflows/deploy.yml`, запускается вручную через `workflow_dispatch`.
- Deploy создает production `.env` из GitHub Secrets, загружает его на VDS, обновляет `origin/main` и запускает `docker-compose.prod.yml`.
- Не запускать deploy без прямого подтверждения пользователя.

Текущая production-схема:

- GitHub repo: `D1skord/OzonPriceMonitoring`.
- Production domain: `pricemonitoring.vinichenko-ivan.ru`.
- Верхний nginx на VDS проксирует `pricemonitoring.vinichenko-ivan.ru` и `www.pricemonitoring.vinichenko-ivan.ru` на `127.0.0.1:8083`.
- GitHub secret `DOMAIN_NAME` должен быть `pricemonitoring.vinichenko-ivan.ru`.
- GitHub secret `APP_URL` должен быть `https://pricemonitoring.vinichenko-ivan.ru`.
- GitHub secret `NGINX_HOST` должен быть `127.0.0.1`.
- GitHub secret `NGINX_PORT` должен быть `8083`.
- GitHub secret `VDS_HOST` должен быть `82.146.43.174`.
- Production deploy использует `docker-compose.prod.yml`.
- На сервере может быть старый `docker-compose`, поэтому `docker-compose.prod.yml` держится совместимым с `version: "3.3"` и без `${VAR:-default}`.

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
docker compose --env-file .env.test -f docker-compose.test.yml exec -T php php artisan test
docker compose --env-file .env.test -f docker-compose.test.yml exec -T php vendor/bin/pint --test
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
