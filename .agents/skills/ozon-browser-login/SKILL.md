---
name: ozon-browser-login
description: Use when working in the Ozon Price Monitoring project and the user needs to log in to Ozon through the project browser profile, fix noVNC/browser-login issues, compare dev/prod Ozon login flows, or ensure the crawler uses the shared Ozon browser profile.
---

# Ozon Browser Login

Используй этот skill только для проекта Ozon Price Monitoring.

## Цель

Запустить ручной Chromium-login в Ozon через noVNC так, чтобы cookies/session сохранялись в общий Docker volume `ozon_browser_profile`, который затем используют `worker` и crawler.

Это не flow для обхода антибот-защиты. Не предлагай скрывать автоматизацию или ломать защиту сайта. Разрешено диагностировать различия окружения и запускать обычный ручной браузер для входа пользователя в собственный аккаунт.

## Правила

- Общайся с пользователем на русском, если он не попросил иначе.
- Все команды приложения запускай только через Docker Compose.
- Не запускай `php artisan`, `composer`, `vendor/bin/pint`, PHPUnit или Playwright на хосте.
- Не удаляй volumes, профили браузера, cookies и контейнеры без явного подтверждения.
- Не печатай содержимое `.env`.
- Не запускай production deploy без явного подтверждения.
- Если порт `6080` занят, сначала выясни, кто его держит; не останавливай чужой контейнер молча.

## Ментальная модель

- `browser-login` - временный сервис с noVNC, Xvfb, x11vnc и обычным Chromium.
- Chromium запускается из `docker/browser-login/start.sh`, не через Playwright.
- Профиль: `${OZON_PROFILE_PATH}` внутри volume `ozon_browser_profile`.
- После ручного входа `worker`/crawler должны читать тот же профиль.
- Playwright может использовать этот сохраненный профиль при обходе wishlist, но ручной логин должен выполняться обычным Chromium.

## Dev Flow

1. Проверить, что проект поднят:
   ```bash
   docker compose --env-file .env ps
   ```
2. Поднять login browser:
   ```bash
   docker compose --env-file .env --profile browser-login up -d --no-build browser-login
   ```
3. Открыть:
   ```text
   http://127.0.0.1:6080/vnc.html
   ```
4. Нажать `Connect`, войти в Ozon вручную, открыть `https://www.ozon.ru/my/favorites`.
5. После успешного входа остановить только login browser:
   ```bash
   docker compose --env-file .env stop browser-login
   ```

## Prod Flow

В production использовать сервис `browser-login` из `docker-compose.prod.yml`, но с project name `ozonprices_prod`. На VDS сейчас доступен старый `docker-compose 1.25.0`, поэтому для production-команд используй `docker-compose`, без `--profile`.

1. На сервере:
   ```bash
   cd /var/www/vinichenko/data/www/pricemonitoring.vinichenko-ivan.ru
   docker-compose --env-file .env -p ozonprices_prod -f docker-compose.prod.yml up -d --no-build browser-login
   ```
2. Открыть:
   ```text
   http://82.146.43.174:6080/vnc.html
   ```
3. Войти в Ozon вручную.
4. После входа остановить login browser:
   ```bash
   docker-compose --env-file .env -p ozonprices_prod -f docker-compose.prod.yml stop browser-login
   ```

## Диагностика

Проверить, какой контейнер держит noVNC:

```bash
docker ps --format 'table {{.Names}}\t{{.Status}}\t{{.Ports}}'
```

Правильный dev-контейнер:

```text
ozonprices-browser-login-1 ... 127.0.0.1:6080->6080/tcp
```

Правильный prod-контейнер:

```text
ozonprices_prod_browser-login_1 ... 0.0.0.0:6080->6080/tcp
```

Проверить процессы внутри:

```bash
docker compose --env-file .env exec -T browser-login sh -lc 'ps -eo pid,comm,args | grep -E "Xvfb|websockify|x11vnc|chromium" | grep -v grep'
```

Внутри должны быть `Xvfb`, `x11vnc`, `websockify` и `/usr/lib/chromium/chromium` с `--user-data-dir=/var/www/ozon-prices/storage/app/ozon-browser-profile`.

## Частые проблемы

- `6080` открывается, но Ozon ведет себя иначе: вероятно, это другой контейнер или старый временный noVNC. Сравни `docker ps` и `curl -I http://127.0.0.1:6080/`.
- `6080` занят: можно временно поднять dev flow на `NOVNC_PORT=6081`, но затем вернуть нормальный `6080`.
- Ozon открывается в `browser-login`, но crawler все равно получает блок: отдельно проверяй уже Playwright crawler, потому что ручной login browser и crawler - разные процессы.
- `browser-login` не стартует после изменения compose: проверить `docker compose --env-file .env config --quiet` локально или для prod `docker-compose --env-file .env -p ozonprices_prod -f docker-compose.prod.yml config --quiet`.
