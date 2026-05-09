# Ozon Prices

MVP мониторинга цен Ozon wishlist через VK-бота.

Пользователь отправляет VK-боту ссылку на Ozon wishlist. Система хранит один активный wishlist на VK-пользователя, раз в 6 часов ставит обход в database queue, открывает wishlist через Playwright с сохраненным Ozon browser profile, парсит товары и цены, пишет snapshots и отправляет VK alert только если цена стала новым историческим минимумом.

## Стек

- PHP 8.4
- Laravel 13
- PostgreSQL 18
- Filament 5
- Laravel database queue
- VK Bot Long Poll
- playwright-php/playwright
- Docker Compose: `php`, `nginx`, `postgres`, `worker`, `scheduler`, optional `browser-login`

## Запуск

```bash
cp .env.example .env
docker compose --env-file .env up -d --build
docker compose --env-file .env exec -T php composer install -n
docker compose --env-file .env exec -T php vendor/bin/playwright-install --browsers
docker compose --env-file .env exec -T php php artisan key:generate --force
docker compose --env-file .env exec -T php php artisan filament:assets
docker compose --env-file .env exec -T php php artisan migrate --force
```

Приложение будет доступно на `http://127.0.0.1:8080`, админка Filament на `http://127.0.0.1:8080/admin`.

Создать первого администратора:

```bash
docker compose --env-file .env exec php php artisan make:filament-user --panel=admin
```

## Настройка VK

В `.env` заполнить:

```dotenv
VK_BOT_TOKEN=
VK_GROUP_ID=
VK_API_VERSION=5.199
```

Запуск Long Poll listener:

```bash
docker compose --env-file .env exec php php artisan vk:listen
```

Поддерживаемые команды бота:

- `/start`
- `/help`
- ссылка на Ozon wishlist
- `/items`
- `/item ID`

## Ozon browser profile

Crawler использует общий persistent profile:

```dotenv
OZON_PROFILE_PATH=/var/www/ozon-prices/storage/app/ozon-browser-profile
PLAYWRIGHT_BROWSERS_PATH=/ms-playwright
CHROME_PATH=/usr/bin/chromium
```

Для ручного логина в Ozon:

```bash
docker compose --env-file .env --profile browser-login up browser-login
```

Открыть noVNC: `http://127.0.0.1:6080`, зайти в Ozon вручную и оставить профиль сохраненным. Worker и ручной browser-login используют один Docker volume `ozon_browser_profile`.

## Очередь и расписание

Постоянные процессы уже описаны в `docker-compose.yml`:

- `worker`: `php artisan queue:work database --queue=default --sleep=3 --tries=1 --timeout=600`
- `scheduler`: `php artisan schedule:work`

Планировщик каждые 5 минут запускает `wishlists:dispatch-due`, который ставит due wishlists в database queue. Интервал следующего обхода задается:

```dotenv
CRAWL_INTERVAL_HOURS=6
```

Ручной запуск постановки due wishlists:

```bash
docker compose --env-file .env exec -T php php artisan wishlists:dispatch-due
```

## Архитектура

- Бизнес-логика находится в сервисах.
- Eloquent models держат связи и casts, без доменной логики.
- Parser strategy возвращает DTO и не пишет в БД.
- Сейчас реализована стратегия `ozon`; реестр стратегий готов к добавлению новых marketplace parser.
- Цены хранятся в minor units: `499000` означает `4 990 ₽`.
- Первый обход создает baseline без алертов.
- Внешние ошибки crawler пишутся в `crawl_runs` и видны в Filament.

Ключевые классы:

- `App\Services\Crawling\OzonWishlistCrawler`
- `App\Services\Parsing\OzonWishlistParserStrategy`
- `App\Services\AlertPolicy`
- `App\Services\Vk\VkBotClient`
- `App\Services\WishlistSyncService`
- `App\Jobs\CrawlWishlistJob`

`OzonWishlistCrawler` запускает небольшой Node Playwright runner из `resources/playwright/ozon-wishlist-crawler.mjs`, потому что для сохраненной Ozon-сессии нужен настоящий `chromium.launchPersistentContext()` с profile directory.

## Проверки

```bash
docker compose --env-file .env exec -T php php artisan test
docker compose --env-file .env exec -T php vendor/bin/pint --test
```

Если Filament login открылся без стилей, значит не опубликованы assets:

```bash
docker compose --env-file .env exec -T php php artisan filament:assets
```

## Полезные команды

```bash
docker compose --env-file .env ps
docker compose --env-file .env logs -f worker
docker compose --env-file .env logs -f scheduler
docker compose --env-file .env exec php bash
```
