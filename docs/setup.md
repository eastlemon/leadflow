# Setup guide

Цель: после `git clone` за `5–10 минут` получить работающий dev-стенд
с прогнанными тестами и доступным Filament-админ-панелью.

Сейчас в репе **нет** ни `Containerfile`, ни `docker-compose`,
ни `podman-compose`. Проект рассчитан на **нативный запуск на хосте**:
PHP-FPM/CGI + MySQL + Redis + Horizon как обычные процессы. На
`vdska` (наш dev-VPS) так и работает, и это путь по умолчанию,
описанный в разделе [Native install](#native-install-ubuntu--debian).

Podman-вариант — в самом конце, как опция, которую мы можем
допилить. Если ты сидишь на нём постоянно — скажи, добавим
`Containerfile` + `podman-compose.yml` отдельным коммитом.

---

## TL;DR (native, Ubuntu 24.04)

```bash
# 1. Системные пакеты
sudo apt update
sudo apt install -y php8.3 php8.3-cli php8.3-{mbstring,xml,curl,zip,bcmath,intl,mysql,redis,sqlite3} \
                    php8.3-fpm php8.3-gd \
                    composer nodejs npm \
                    mysql-server redis-server \
                    nginx

# 2. Composer-зависимости
composer config -g repos.packagist composer https://repo.packagist.org   # см. подводные камни
composer install --no-interaction

# 3. .env и ключ
cp .env.example .env
php artisan key:generate
# заполни DB_* секцию (см. ниже)

# 4. Миграции + сид-админ
php artisan migrate --force
php artisan make:filament-user   # интерактивно создаст admin@leadflow.local

# 5. Тесты (БД = sqlite in-memory, MySQL/Redis не нужны)
php artisan test

# 6. Запуск
php artisan serve --host=0.0.0.0 --port=8000      # web
php artisan horizon                              # в другом терминале
```

Дальше: <http://localhost:8000/admin> — Filament-панель.

---

## Что нужно установить

| Пакет       | Минимум       | Зачем                                                |
|-------------|---------------|------------------------------------------------------|
| PHP         | 8.3+ (8.4 ОК) | рантайм Laravel 13                                   |
| Composer    | 2.6+          | зависимости                                          |
| Node.js     | 20+           | `npm run build` для Vite-ассетов Filament-панели     |
| MySQL       | 8.0+          | основное хранилище (MariaDB 10.6+ тоже подойдёт)     |
| Redis       | 6.0+          | `QUEUE_CONNECTION=redis` + `CACHE_STORE=redis`       |
| nginx       | любой         | боевой reverse proxy, в dev можно `artisan serve`    |

PHP-расширения (имена для `apt install php8.3-<имя>`):

```
cli fpm mbstring xml curl zip bcmath intl mysql redis sqlite3 gd
```

Опционально:

```
imagick       # для Excel-картинок в PhpSpreadsheet (не критично)
pcov          # покрытие тестов
xdebug        # дебаг (выключи в проде)
```

---

## Native install (Ubuntu / Debian)

Этот путь **протестирован на vdska** (Ubuntu 24.04, PHP 8.4) и
полностью совпадает с тем, что уже работает на нашем dev-VPS.

### 1. Системные пакеты

```bash
sudo apt update
sudo apt install -y software-properties-common ca-certificates lsb-release apt-transport-https
sudo add-apt-repository ppa:ondrej/php -y      # даёт php8.3/8.4 на Ubuntu 24.04
sudo apt update

sudo apt install -y \
  php8.3 php8.3-cli php8.3-fpm \
  php8.3-mbstring php8.3-xml php8.3-curl php8.3-zip php8.3-bcmath php8.3-intl \
  php8.3-mysql php8.3-redis php8.3-sqlite3 php8.3-gd \
  composer nodejs npm \
  mysql-server redis-server \
  nginx curl git unzip
```

Проверить:

```bash
php -v          # PHP 8.3.x
composer -V     # Composer 2.6+
node -v         # v20.x+
mysql --version # 8.0.x
redis-cli --version
```

### 2. MySQL: создать БД и пользователя

По политике vdska root-доступ по сети закрыт, и для каждого
приложения мы делаем отдельного `@127.0.0.1` пользователя с
сильным паролем. Здесь — то же самое.

```bash
sudo mysql <<'SQL'
CREATE DATABASE leadflow CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'leadflow'@'127.0.0.1' IDENTIFIED BY 'CHANGE_ME_STRONG_PASSWORD';
GRANT ALL PRIVILEGES ON leadflow.* TO 'leadflow'@'127.0.0.1';
FLUSH PRIVILEGES;
SQL
```

Сгенерируй пароль и положи его в `.env` (см. ниже). На проде
храни в secrets-manager, не в репе.

### 3. Redis

Ставится и стартует автоматически через systemd. Ничего
дополнительно не надо, по умолчанию слушает `127.0.0.1:6379`.

Проверить:

```bash
redis-cli ping    # PONG
```

### 4. Composer-зависимости

```bash
cd /var/www/leadflow       # или куда склонировал
composer install --no-interaction
```

> **⚠️ Подводный камень: composer-зеркало.**
> На vdska `composer config -g repos.packagist` указывает на
> Aliyun-зеркало, которое отстаёт от packagist на дни.
> Если `composer install` ругается, что пакет не найден или
> показывает старые версии — переключи:
>
> ```bash
> composer config -g repos.packagist composer https://repo.packagist.org
> ```
>
> Это разовая настройка, локально на хосте.

### 5. `.env`

```bash
cp .env.example .env
php artisan key:generate
```

Минимальный набор переменных, которые надо заполнить:

```dotenv
APP_NAME=LeadFlow
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000
APP_TIMEZONE=UTC

# --- Database ---
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=leadflow
DB_USERNAME=leadflow
DB_PASSWORD=CHANGE_ME_STRONG_PASSWORD      # тот, что в mysql-блоке

# --- Redis ---
REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
REDIS_DB=0
REDIS_CACHE_DB=1

# --- Queue / Cache ---
QUEUE_CONNECTION=redis
CACHE_STORE=redis
SESSION_DRIVER=database
BROADCAST_CONNECTION=log
FILESYSTEM_DISK=local

# --- Logging ---
LOG_CHANNEL=stack
LOG_STACK=single
LOG_LEVEL=debug

# --- Bank API (опционально) ---
# В dev пока пусто. Реальные ключи лежат в user_connects.tune
# (см. docs/scoring.md). Никакие bank_API_URL в .env не нужны.

# --- Skorozvon webhook (опционально) ---
# SKOROZVON_WEBHOOK_SECRET=
```

### 6. Миграции и админ

```bash
php artisan migrate --force
```

Создать админа для Filament-панели:

```bash
php artisan make:filament-user
```

Он спросит name / email / password. На vdska это
`admin@leadflow.local` / `admin` (только для локалки, **в проде
сразу сменить**).

### 7. NPM-ассеты (Filament + Vite)

Filament-панель использует Vite для CSS/JS. Один раз:

```bash
npm install
npm run build         # для prod-сборки
# или
npm run dev           # для dev с hot reload
```

Если `npm run build` не делал — панель откроется, но без стилей.

### 8. Запуск

В одном терминале:

```bash
php artisan serve --host=0.0.0.0 --port=8000
```

В другом:

```bash
php artisan horizon
```

Horizon читает `config/horizon.php` и стартует supervisor-процесс
с воркерами. Dashboard: <http://localhost:8000/horizon>.

Для cron-задач (Laravel scheduler, `routes/console.php`) — добавить
в crontab:

```cron
* * * * * cd /var/www/leadflow && php artisan schedule:run >> /dev/null 2>&1
```

### 9. nginx (опционально)

`artisan serve` годится для разработки. На проде / нескольких
разработчиках — nginx → php-fpm:

```nginx
server {
    listen 80;
    server_name leadflow.local;
    root /var/www/leadflow/public;

    index index.php;
    client_max_body_size 64M;     # для xlsx-аплоадов

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

### 10. Webhook endpoint (если нужен Skorozzon)

Маршрут `/webhooks/skorozvon` уже зарегистрирован в
`app/Webhooks/Skorozzon/SkorozzonWebhookProcessor.php`. Чтобы он
принимал реальные запросы, добавь в nginx отдельный `location`,
пробрасывающий на приложение, и сертификат (Let's Encrypt). На
vdska этот endpoint уже настроен на `imsg.rigroll.ru`.

---

## Верификация: всё ли работает

После всех шагов — sanity-check одним блоком:

```bash
cd /var/www/leadflow

# 1. Тесты. Должны быть зелёные, ничего не фейкаем.
php artisan test

# 2. Миграции в актуальном состоянии.
php artisan migrate:status

# 3. Очередь/Redis живые.
php artisan tinker --execute='dump(\Illuminate\Support\Facades\Redis::ping());'
# => "PONG"

# 4. Horizon поднялся.
php artisan horizon:status
# => "Horizon is running."

# 5. Скоринг-пайплайн строится.
php artisan tinker --execute='dump(app(\App\Scoring\ScoringConfigFactory::class)->forBank("alfa", []));'
# => ["config" => ScoringConfig { ... }, "rules" => [... 3 rules ...]]
```

Если что-то падает — смотри `storage/logs/laravel.log`.

В браузере: <http://localhost:8000/admin> (логин админа из шага 6).

---

## Что **не нужно** для dev

Эти куски есть в проекте, но в dev-сборке они не нужны и могут
сбить с толку:

- **`SKOROZVON_WEBHOOK_SECRET`** — если пусто, webhook-процессор
  принимает любую подпись (это by design для dev). На проде
  обязательно заполнить.
- **Реальные bank API ключи** — не хранятся в `.env`, лежат в
  `user_connects.tune`. Создаются через Filament-панель
  (UserConnectResource) или вручную. Для прогона тестов
  ключи тоже не нужны.
- **Horizon в супервизоре** — в проде да, в dev `php artisan
  horizon` в отдельном терминале достаточно.

---

## Podman-вариант (если нужен)

Сейчас в репе нет ни `Containerfile`, ни `podman-compose.yml`.
Варианты:

1. **Подожди следующий коммит** — я добавлю базовый
   `Containerfile` (php-fpm + extensions) + `podman-compose.yml`
   (app, nginx, mysql, redis, horizon, scheduler). Это
   стандартная схема, я подсмотрю в наш TellFax-овский
   `docker-compose.yml` и адаптирую.
2. **Сделай свой** — минимальный `Containerfile` это примерно
   15 строк на базе `docker.io/library/php:8.3-fpm-bookworm` плюс
   `apt install` нужных расширений и `composer install` в build
   stage. multi-stage build даст ~250 MB финальный образ.

### Разница Podman vs Docker (на что обратить внимание)

| Docker                            | Podman                                        |
|-----------------------------------|-----------------------------------------------|
| `docker compose`                  | `podman-compose` (отдельный пакет)            |
| Daemon (`dockerd`)                | Нет демона, форкается CLI                     |
| `docker0` bridge                  | `slirp4netns` / `netavark` (rootless по умолчанию) |
| `docker exec -it ... bash`        | `podman exec -it ... bash`                    |
| `docker compose logs -f`          | `podman-compose logs -f`                      |
| Volumes perms — `1000:1000`       | `--userns=keep-id` или `--uidmap`             |
| `DOCKER_HOST` env                 | Нет аналога, сокет через XDG_RUNTIME_DIR      |

Самые частые грабли при переходе:

- **Сокеты на хосте.** Podman по умолчанию слушает сокет
  `$XDG_RUNTIME_DIR/podman/podman.sock`. Убедись, что
  переменная экспортирована:
  `export XDG_RUNTIME_DIR=/run/user/$(id -u)`.
- **UID-маппинг в volume.** Если в compose указан
  `./src:/app:z` (relabel SELinux), в rootless podman это
  не нужно, но маппинг UID в контейнере и на хосте должен
  совпадать, иначе будешь ловить "permission denied" на
  `storage/` и `bootstrap/cache/`. Либо `--userns=keep-id`
  для bind-mount в dev, либо фиксированный UID в образе.
- **Нет daemon'а = `docker-compose` build watch не работает.**
  Для dev hot-reload используй `podman compose watch` (4.5+)
  или просто пересобирай руками.
- **Сеть.** `slirp4netns` медленнее `docker0`. Для dev ОК,
  в проде лучше `--net=host` или netavark bridge.

Если хочешь podman-путь сразу — скажи, и я добавлю
`Containerfile` + `podman-compose.yml` отдельным коммитом
(см. "Подводные камни" выше).
