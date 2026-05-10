---
name: ozon-price-monitoring-deploy
description: Use when working in the Ozon Price Monitoring project and the user asks to deploy, release, "выкати на прод", "раскатай", or run the production rollout flow.
---

# Ozon Price Monitoring Deploy

Используй этот skill только для проекта Ozon Price Monitoring.

## Правила

- Общайся с пользователем на русском, если он не попросил иначе.
- Не запускай deploy без явного подтверждения пользователя.
- Не раскрывай значения secrets из `.env*`, GitHub Secrets, Docker compose и CI logs.
- Не запускай Laravel/PHP/Composer/Playwright команды на хосте: только внутри Docker Compose.
- Не выполняй `migrate:fresh`, `down -v`, удаление volumes, prune или пересборку через `up --build` без явного подтверждения.
- Не откатывай и не удаляй чужие незакоммиченные изменения.
- Если есть несвязанные изменения в worktree, остановись и уточни у пользователя, какие файлы входят в релиз.

## Production-схема

- GitHub repo: `D1skord/OzonPriceMonitoring`.
- Production domain: `pricemonitoring.vinichenko-ivan.ru`.
- Production project path: `/var/www/vinichenko/data/www/pricemonitoring.vinichenko-ivan.ru`.
- Верхний nginx на VDS проксирует `pricemonitoring.vinichenko-ivan.ru` и `www.pricemonitoring.vinichenko-ivan.ru` на `127.0.0.1:8083`.
- GitHub secret `NGINX_HOST` должен быть `127.0.0.1`.
- GitHub secret `NGINX_PORT` должен быть `8083`.
- Production deploy использует `docker-compose.prod.yml` и project name `ozonprices_prod`.
- На сервере может быть старый `docker-compose`, поэтому `docker-compose.prod.yml` должен оставаться совместимым с `version: "3.3"` и без `${VAR:-default}`.
- PostgreSQL не должен публиковать порт на host; наружу открыт только app nginx на `127.0.0.1:8083`.
- Services на проде: `nginx`, `php`, `postgres`, `worker`, `scheduler`.

## Flow

1. Проверить состояние:
   - `git status --short --branch`;
   - убедиться, что в worktree нет несвязанных чужих правок;
   - проверить, что в git не попали `.env`, `.env.test`, `.env.production`, `auth.json`, `vendor` и логи.
2. Проверить изменения:
   - `make test`;
   - `make pint`;
   - если менялись compose-файлы, проверить `docker compose --env-file .env -f docker-compose.yml config --quiet`, `docker compose --env-file .env.test -f docker-compose.test.yml config --quiet`, `docker compose --env-file .env -f docker-compose.prod.yml config --quiet`.
3. После успешных проверок:
   - `git add` только релевантных файлов;
   - `git commit -m "..."`;
   - `git push origin main`.
4. Проверить GitHub Actions:
   - `gh run list --repo D1skord/OzonPriceMonitoring --limit 5`;
   - найти CI run для текущего commit;
   - дождаться зеленого CI через `gh run watch <run_id> --repo D1skord/OzonPriceMonitoring --exit-status`.
5. Запустить deploy:
   - предпочтительно `gh workflow run Deploy --repo D1skord/OzonPriceMonitoring --ref main`, если `VDS_PASSWORD` заполнен реальным значением;
   - если GitHub Actions deploy недоступен, выполнять ручной SSH deploy через `root@82.146.43.174`, не печатая `.env`;
   - на сервере использовать `docker-compose --env-file .env -p ozonprices_prod -f docker-compose.prod.yml`, если `docker compose --env-file` не поддерживается;
   - перед Composer внутри контейнера выполнить `git config --global --add safe.directory /var/www/ozon-prices`;
   - при bind mount проблемах выровнять владельца production dir по `UID:GID` из `.env`.
6. После deploy проверить:
   - `curl --max-time 15 -I http://pricemonitoring.vinichenko-ivan.ru/`;
   - `curl --max-time 15 -k -I https://pricemonitoring.vinichenko-ivan.ru/`;
   - на сервере `curl --max-time 15 -I http://127.0.0.1:8083/`;
   - на сервере `curl --max-time 15 -I http://127.0.0.1:8083/admin/login`;
   - на сервере убедиться, что `127.0.0.1:8083` слушает compose-проект `ozonprices_prod`.

## Отчет пользователю

В финальном ответе кратко укажи:

- какие проверки прошли;
- какой commit был отправлен;
- какой GitHub Actions run прошел;
- прошел ли deploy;
- что вернули production smoke checks;
- какие secrets остались требующими ручного заполнения.
