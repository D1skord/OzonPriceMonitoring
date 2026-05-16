---
name: ozon-price-monitoring-deploy
description: Use when working in the Ozon Price Monitoring project and the user asks to deploy, release, "раскатай", "отправляем и раскатываем", "выкати на prod", or run the production rollout flow.
---

# Ozon Price Monitoring Deploy

Используй этот skill только для проекта Ozon Price Monitoring.

## Правила

- Общайся с пользователем на русском, если он не попросил иначе.
- Не запускай deploy без явного подтверждения пользователя.
- Не раскрывай значения secrets из `.env*`, GitHub Secrets, Docker compose и CI logs.
- Не удаляй volumes с данными.
- Не трогай другие проекты на сервере.
- Не откатывай и не удаляй чужие незакоммиченные изменения.
- Если есть несвязанные изменения в worktree, остановись и уточни у пользователя, какие файлы входят в релиз.

## Production-схема

- GitHub repo: `D1skord/OzonPriceMonitoring`.
- Production domain: `pricemonitoring.vinichenko-ivan.ru`.
- Верхний nginx на VDS проксирует `pricemonitoring.vinichenko-ivan.ru` на `127.0.0.1:8083`.
- GitHub secret `NGINX_PORT` должен быть `8083`.
- Production project path: `/var/www/vinichenko/data/www/pricemonitoring.vinichenko-ivan.ru`.
- Production deploy использует `docker-compose.prod.yml`.
- На сервере старый `docker-compose` (не `docker compose`), поэтому всегда используй `docker-compose`.
- Основные контейнеры: `nginx`, `php`, `postgres`, `worker`, `scheduler`, `vk-bot`.
- `browser-login` - временный контейнер для ручной авторизации Ozon через noVNC, deploy workflow его не поднимает и останавливает перед выкладкой.

## Flow

1. Проверить состояние:
   - `git status --short --branch`;
   - убедиться, что в worktree нет несвязанных чужих правок.
2. Проверить изменения:
   - для PHP-файлов запустить синтаксис-проверку;
   - если менялся `docker-compose.prod.yml`, запустить `docker-compose --env-file .env.example -f docker-compose.prod.yml config --quiet`;
   - для широких изменений запустить `make test`.
3. После успешных проверок:
   - `git add` только релевантных файлов;
   - `git commit -m "..."`;
   - `git push origin main`.
4. Проверить GitHub Actions CI:
   - `gh run list --repo D1skord/OzonPriceMonitoring --workflow ci --limit 3`;
   - дождаться зеленого CI.
5. Запустить production deploy:
   - `gh workflow run deploy --repo D1skord/OzonPriceMonitoring`;
   - отслеживать результат через `gh run list --repo D1skord/OzonPriceMonitoring --workflow deploy --limit 3`.
6. После deploy проверить:
   - `curl -sI https://pricemonitoring.vinichenko-ivan.ru/`;
   - `curl -sI https://pricemonitoring.vinichenko-ivan.ru/admin/login`;
   - проверить контейнеры: `docker ps --format '{{.Names}}\t{{.Status}}' | grep ozonprices_prod`.

## Контейнеры и их роли

| Контейнер | Команда | Описание |
|-----------|---------|----------|
| nginx | - | Проксирует 8083→80 |
| php | - | Главный PHP-FPM |
| postgres | - | База данных |
| worker | `queue:work` | Обрабатывает jobs (парсинг) |
| scheduler | `schedule:work` | Запускает `wishlists:dispatch-due` каждую минуту |
| vk-bot | `vk:listen` | Long Poll для VK Bot |
| browser-login | `bash docker/browser-login/start.sh` | Временный noVNC + обычный Chromium для ручного входа в Ozon |

## Ozon Browser Login

Для ручной авторизации в Ozon не запускай браузер в `php` контейнере через `docker exec` и не используй `php artisan ozon:login-profile`.

Правильный production flow:

```bash
ssh root@82.146.43.174
cd /var/www/vinichenko/data/www/pricemonitoring.vinichenko-ivan.ru
docker-compose --env-file .env -p ozonprices_prod -f docker-compose.prod.yml up -d --no-build browser-login
```

Открыть:

```text
http://82.146.43.174:6080/vnc.html
```

После успешного входа закрыть внешний доступ:

```bash
docker-compose --env-file .env -p ozonprices_prod -f docker-compose.prod.yml stop browser-login
```

## Отчет пользователю

В финальном ответе кратко укажи:

- какие проверки прошли;
- какой commit был отправлен;
- какой GitHub Actions run прошел;
- прошел ли deploy;
- статус всех контейнеров.
