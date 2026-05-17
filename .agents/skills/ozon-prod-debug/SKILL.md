---
name: ozon-prod-debug
description: Use when working in the Ozon Price Monitoring project and the user asks to inspect production/VDS problems, "зайди на прод", "посмотри почему на проде не работает", check production logs, diagnose prod containers, VK bot, worker, scheduler, nginx, PHP, crawler, or Ozon parsing failures without deploying.
---

# Ozon Production Debug

Используй этот skill только для диагностики production проекта Ozon Price Monitoring. Это не deploy-flow: если пользователь просит выкатывать изменения, используй `ozon-price-monitoring-deploy`.

## Главные правила

- Общайся с пользователем на русском, если он не попросил иначе.
- Production диагностика по умолчанию read-only.
- Не печатай значения `.env`, GitHub Secrets, tokens, cookies, пароли, DSN и другие secrets.
- Не трогай другие проекты на сервере.
- Не запускай `deploy`, `git pull`, `git reset`, `docker-compose up/down`, `restart`, `migrate`, `queue:restart`, `vk:listen`, `browser-login up`, правки файлов или изменения `.env` без явного подтверждения пользователя.
- Не удаляй volumes, профили браузера, cookies, контейнеры, очереди или данные БД.
- Если нужно исправление с влиянием на production, сначала кратко объясни действие и риск, затем запроси подтверждение.

## Карта production

- SSH: `ssh root@82.146.43.174`
- Project path: `/var/www/vinichenko/data/www/pricemonitoring.vinichenko-ivan.ru`
- Domain: `https://pricemonitoring.vinichenko-ivan.ru`
- Host nginx проксирует domain на `127.0.0.1:8083`
- На VDS старый `docker-compose 1.25.0`, поэтому используй `docker-compose`, не `docker compose`.
- Compose:
  ```bash
  docker-compose --env-file .env -p ozonprices_prod -f docker-compose.prod.yml
  ```

Не относящиеся к этому проекту контейнеры и директории не трогать, например `sitemonitoringvinichenko-ivanru_*`, `mariadb-10.3` и другие проекты в `/var/www`.

## Контейнеры

| Контейнер | Роль |
|-----------|------|
| `ozonprices_prod_nginx_1` | HTTP внутри Docker, порт host `127.0.0.1:8083->80` |
| `ozonprices_prod_php_1` | PHP-FPM и artisan-команды |
| `ozonprices_prod_postgres_1` | PostgreSQL |
| `ozonprices_prod_worker_1` | `php artisan queue:work database`, crawler/jobs |
| `ozonprices_prod_scheduler_1` | `php artisan schedule:work`, постановка due wishlists |
| `ozonprices_prod_vk-bot_1` | `php artisan vk:listen`, единственный постоянный VK Long Poll consumer |
| `ozonprices_prod_browser-login_1` | Временный noVNC/Chromium для ручного Ozon login; обычно должен быть stopped |

## Базовый read-only чеклист

Сначала собери факты, затем делай выводы. Не dump'и огромные логи.

```bash
ssh root@82.146.43.174
cd /var/www/vinichenko/data/www/pricemonitoring.vinichenko-ivan.ru
git status --short --branch
git rev-parse --short HEAD
docker ps --format 'table {{.Names}}\t{{.Status}}\t{{.Ports}}' | grep ozonprices_prod
docker-compose --env-file .env -p ozonprices_prod -f docker-compose.prod.yml ps
curl -sI https://pricemonitoring.vinichenko-ivan.ru/
curl -sI https://pricemonitoring.vinichenko-ivan.ru/admin/login
curl -sI http://127.0.0.1:8083/
```

Если проблема общая, посмотри короткие хвосты логов:

```bash
docker logs ozonprices_prod_nginx_1 --tail=100
docker logs ozonprices_prod_php_1 --tail=100
docker logs ozonprices_prod_worker_1 --tail=100
docker logs ozonprices_prod_scheduler_1 --tail=100
docker logs ozonprices_prod_vk-bot_1 --tail=100
```

## Диагностика по симптомам

### Сайт не открывается

Проверь:

```bash
curl -sI https://pricemonitoring.vinichenko-ivan.ru/
curl -sI http://127.0.0.1:8083/
docker logs ozonprices_prod_nginx_1 --tail=100
docker logs ozonprices_prod_php_1 --tail=100
```

Если внешний domain не отвечает, но `127.0.0.1:8083` отвечает, проблема вероятнее в host nginx/DNS/TLS. Не меняй host nginx без подтверждения.

### Laravel/PHP ошибка

Проверь через контейнер `php`:

```bash
docker-compose --env-file .env -p ozonprices_prod -f docker-compose.prod.yml exec -T php php artisan about --only=environment
docker-compose --env-file .env -p ozonprices_prod -f docker-compose.prod.yml exec -T php sh -lc 'tail -n 120 storage/logs/laravel.log'
```

Для route-проблем:

```bash
docker-compose --env-file .env -p ozonprices_prod -f docker-compose.prod.yml exec -T php php artisan route:list --except-vendor
```

Передавай пользователю только нужные строки логов и не раскрывай secrets.

### Worker, crawler или Ozon parsing не работают

Проверь:

```bash
docker logs ozonprices_prod_worker_1 --tail=160
docker-compose --env-file .env -p ozonprices_prod -f docker-compose.prod.yml exec -T php php artisan queue:failed
```

Если нужно смотреть БД, делай только read-only `SELECT` и сообщи, что проверяешь. Не выполняй write/DDL/DML в production без отдельного явного запроса.

### Scheduler не ставит задачи

Проверь:

```bash
docker logs ozonprices_prod_scheduler_1 --tail=160
docker-compose --env-file .env -p ozonprices_prod -f docker-compose.prod.yml exec -T php php artisan schedule:list
```

Не запускай `schedule:work` вручную, пока production scheduler уже работает.

### VK bot не отвечает

Проверь:

```bash
docker logs ozonprices_prod_vk-bot_1 --tail=160
docker-compose --env-file .env -p ozonprices_prod -f docker-compose.prod.yml ps vk-bot
```

На production должен быть только один постоянный VK Long Poll consumer. Не запускай локальный `vk:listen` с production token параллельно с `ozonprices_prod_vk-bot_1`.

### Browser login торчит наружу

`browser-login` нужен только на время ручного входа в Ozon. Если пользователь не логинится прямо сейчас, он должен быть stopped.

Проверь:

```bash
docker ps --format 'table {{.Names}}\t{{.Status}}\t{{.Ports}}' | grep -E 'ozonprices_prod_browser-login|6080'
curl -sI --max-time 5 http://82.146.43.174:6080/
```

Не поднимай и не останавливай `browser-login` без подтверждения, если пользователь явно не попросил управлять login-сессией.

## Как докладывать результат

Отделяй факты от предположений:

- `Наблюдаю:` конкретные статусы контейнеров, HTTP-коды, короткие фрагменты логов.
- `Похоже:` вероятная причина, если она выводится из фактов.
- `Предлагаю:` самый маленький безопасный следующий шаг.

Если причина не найдена, честно скажи, какие проверки уже сделаны и какой следующий read-only источник фактов нужен.
