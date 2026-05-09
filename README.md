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

## Важное правило

Проект запускается и обслуживается через Docker Compose. Не запускай `php artisan`, `composer`, тесты и Playwright на хосте.

Правильно:

```bash
docker compose --env-file .env exec -T php php artisan test
```

Или через `make`:

```bash
make test
```

## Первый запуск

Скопировать env:

```bash
cp .env.example .env
```

Собрать образы, поднять контейнеры, установить зависимости, опубликовать Filament assets и применить миграции:

```bash
make build
```

То же самое без `make`:

```bash
docker compose --env-file .env build php nginx
docker compose --env-file .env up -d --no-build --remove-orphans
docker compose --env-file .env exec -T php composer install -n
docker compose --env-file .env exec -T php vendor/bin/playwright-install --browsers
docker compose --env-file .env exec -T php php artisan key:generate --force
docker compose --env-file .env exec -T php php artisan filament:assets
docker compose --env-file .env exec -T php php artisan migrate --force
```

После запуска:

- приложение: `http://127.0.0.1:8080`
- Filament admin: `http://127.0.0.1:8080/admin/login`
- noVNC для ручного логина в Ozon: `http://127.0.0.1:6080`

## Обычный запуск

Когда образы уже собраны:

```bash
make up
```

Или напрямую:

```bash
docker compose --env-file .env up -d --no-build
```

Проверить контейнеры:

```bash
make ps
```

Остановить:

```bash
make down
```

Не используй `docker compose down -v`, если не хочешь удалить PostgreSQL volume и сохраненный Ozon browser profile.

## Создание администратора

Создать пользователя для Filament:

```bash
make admin-create
```

Команда интерактивно спросит имя, email и пароль. Потом открыть:

```text
http://127.0.0.1:8080/admin/login
```

Если login page открылся без стилей, опубликовать assets:

```bash
make filament-assets
```

## Настройка VK

В `.env` заполнить:

```dotenv
VK_BOT_TOKEN=
VK_GROUP_ID=
VK_API_VERSION=5.199
```

Получить `VK_BOT_TOKEN` нужно в настройках сообщества VK. Long Poll должен быть включен для группы.

Запустить VK Long Poll listener:

```bash
make vk-listen
```

Или напрямую:

```bash
docker compose --env-file .env exec php php artisan vk:listen
```

Поддерживаемые команды бота:

- `/start`
- `/help`
- ссылка на Ozon wishlist
- `/items`
- `/item ID`

## Авторизация аккаунта Ozon в браузере

Crawler использует persistent browser profile. Это нужно, чтобы Playwright видел Ozon как уже авторизованный браузер.

Профиль хранится в Docker volume `ozon_browser_profile`. Внутри контейнера путь такой:

```dotenv
OZON_PROFILE_PATH=/var/www/ozon-prices/storage/app/ozon-browser-profile
PLAYWRIGHT_BROWSERS_PATH=/ms-playwright
CHROME_PATH=/usr/bin/chromium
```

Запустить контейнер для ручного логина:

```bash
make browser-login
```

Или напрямую:

```bash
docker compose --env-file .env --profile browser-login up browser-login
```

Дальше:

1. Открыть `http://127.0.0.1:6080`.
2. В noVNC нажать connect, если экран не подключился автоматически.
3. В открывшемся Chromium зайти на `https://www.ozon.ru`.
4. Авторизоваться в своем Ozon аккаунте.
5. Открыть wishlist вручную и убедиться, что товары видны.
6. Остановить `browser-login` через `Ctrl+C`.

После этого `worker` будет использовать тот же browser profile при обходе wishlist. Если Ozon разлогинит аккаунт или начнет показывать captcha, повторить ручной login flow.

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

Посмотреть логи:

```bash
docker compose --env-file .env logs -f worker
docker compose --env-file .env logs -f scheduler
```

## Как проверить весь flow

1. Поднять проект:

```bash
make up
```

2. Проверить админку:

```text
http://127.0.0.1:8080/admin/login
```

3. Авторизовать Ozon profile через `make browser-login`.

4. Настроить VK token/group id в `.env`.

5. Запустить listener:

```bash
make vk-listen
```

6. Написать боту ссылку на Ozon wishlist.

7. Проверить в Filament:

- `Users`
- `Wishlists`
- `Products`
- `Price Snapshots`
- `Alerts`
- `Crawl Runs`

Первый обход создает baseline без алертов. Алерт появится только если следующая цена будет новым историческим минимумом.

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
make test
make pint
```

Или напрямую:

```bash
docker compose --env-file .env exec -T php php artisan test
docker compose --env-file .env exec -T php vendor/bin/pint --test
```

Проверить HTTP:

```bash
curl --max-time 5 -I http://127.0.0.1:8080
curl --max-time 5 -I http://127.0.0.1:8080/admin/login
curl --max-time 5 -I http://127.0.0.1:8080/css/filament/filament/app.css
```

## Полезные команды

```bash
make help
make ps
make bash
make migrate
make test
make pint
make filament-assets
make admin-create
make browser-login
make vk-listen
```

Войти в PHP container:

```bash
docker compose --env-file .env exec php bash
```
