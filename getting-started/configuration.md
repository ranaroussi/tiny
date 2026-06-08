[Home](../readme.md) | [Getting Started](../getting-started) | [Core Concepts](../core-concepts) | [Helpers](../helpers) | [Extensions](../extensions) | [Repo](https://github.com/ranaroussi/tiny)

# Configuration

Tiny is configured entirely through environment variables, loaded from `.env.<env>` files at boot. The convention is the **`TINY_*` prefix** for framework- and integration-level settings.

## How configuration is loaded

`env.php` defines a single constant:

```php
<?php
const ENV = 'local'; // local | dev | stage | prod
```

`bootstrap.php` reads `ENV` and loads the matching dotenv file from the parent directory:

```
my-app/
├── env.php             # ← defines ENV
├── .env.local          # ← used when ENV=local
├── .env.prod           # ← used when ENV=prod
└── tiny/
```

Variables are loaded into `$_SERVER` and `getenv()`. Set them directly in the server environment in production if you prefer (Docker, systemd, etc.).

## Framework variables

### Core

| Variable | Default | Description |
|---|---|---|
| `TINY_APP_DIR` | `app` | Directory name of your application (relative to project root) |
| `TINY_DIR` | `tiny` | Directory name of the framework |
| `TINY_HOMEPAGE` | `home` | Controller name for `/` |
| `TINY_STATIC_DIR` | `static` | Static assets folder (or CDN URL) |
| `TINY_TIMEZONE` | `UTC` | PHP timezone |
| `TINY_MINIFY_OUTPUT` | `false` | Minify HTML output |
| `TINY_DEBUG_WHITELIST` | `*` | Comma-separated IPs allowed to see `dd()/dump()` output (`*` = all) |
| `TINY_LOG_FILE` | (system tmp) | Where `tiny::log()` writes |
| `TINY_LOG_LEVEL` | `info` | `debug` enables verbose logging |
| `TINY_CALC_TIMER` | `true` | Include request timer in debug output |

> **Path overrides** (rarely needed; auto-detected):
> `TINY_APP_PATH`, `TINY_PATH`, `TINY_PUBLIC_PATH`, `TINY_URL_PATH`, `TINY_CMS_PATH`, `TINY_COOKIE_DOMAIN`, `TINY_COOKIE_PATH`.

### Helper autoloading

| Variable | Description |
|---|---|
| `TINY_AUTOLOAD_HELPERS` | Comma-separated helper names to autoload, or `*` for all |

```env
TINY_AUTOLOAD_HELPERS=stripe,mailgun,utils
# or:
TINY_AUTOLOAD_HELPERS=*
```

### Database

| Variable | Default | Description |
|---|---|---|
| `TINY_DB_AUTOCONNECT` | `true` | Connect to DB on boot |
| `TINY_DB_TYPE` | – | `mysql` / `pgsql` / `postgres` / `postgresql` / `sqlite` |
| `TINY_DB_HOST` | `localhost` | |
| `TINY_DB_PORT` | (3306 / 5432) | |
| `TINY_DB_NAME` | `tiny` | |
| `TINY_DB_USER` | `root` | |
| `TINY_DB_PASS` | – | |
| `TINY_DB_PERSISTENT` | `false` | Use PDO persistent connections |
| `TINY_DB_SSL_MODE` | – | PostgreSQL SSL mode |
| `TINY_DB_SQLITE_FILE` | – | Path to SQLite file (sqlite only) |
| `TINY_DB_SQLITE_SCHEMA` | – | Path to schema.sql, applied on first creation |

### Cache

| Variable | Default | Description |
|---|---|---|
| `TINY_CACHE_DISABLED` | `false` | Bypass the cache (useful for local dev) |
| `TINY_CACHE_PREFIX` | – | Prefix all keys |
| `TINY_MEMCACHED_HOST` | `localhost` | When using Memcached engine |
| `TINY_MEMCACHED_PORT` | `11211` | |

> Tiny uses **APCu** by default. Pass `'memcached'` to `tiny::cache('memcached')` to opt into Memcached.

### Cookies & sessions

| Variable | Default | Description |
|---|---|---|
| `TINY_COOKIE_DOMAIN` | – | Cookie domain |
| `TINY_COOKIE_PATH` | `/` | Cookie path |
| `TINY_COOKIE_TTL` | `31536000` | Default cookie lifetime in seconds |

### ClickHouse

| Variable | Default | Description |
|---|---|---|
| `TINY_CLICKHOUSE_HOST` | – | |
| `TINY_CLICKHOUSE_PORT` | `8443` | |
| `TINY_CLICKHOUSE_USERNAME` | – | |
| `TINY_CLICKHOUSE_PASSWORD` | – | |
| `TINY_CLICKHOUSE_HTTPS` | `false` | |
| `TINY_CLICKHOUSE_TIMEOUT` | `30` | |

### Cypher (encryption)

| Variable | Default | Description |
|---|---|---|
| `TINY_CRYPTO_ALGO` | `aes-256-cbc` | OpenSSL cipher |
| `TINY_CRYPTO_SECRET` | – | Encryption key |
| `TINY_CRYPTO_TTL` | – | Default token lifetime |

### Geo

| Variable | Default | Description |
|---|---|---|
| `TINY_GEO_GEOIP2_CITY_PATH` | – | Path to GeoIP2-City.mmdb |
| `TINY_GEO_SUPPORTED_CURRENCIES` | – | Comma-separated allowed currencies |
| `TINY_GEO_UNSUPPORTED_COUNTRIES` | – | Comma-separated country codes to block |

### CMS

| Variable | Default | Description |
|---|---|---|
| `TINY_CMS_PATH` | `app/cms` | Where markdown content lives |
| `TINY_CMS_REBUILD_TOKEN` | – | Bearer token for rebuild endpoint |

## Integration variables

### Stripe

```env
TINY_STRIPE_PK=pk_test_…
TINY_STRIPE_SK=sk_test_…
TINY_STRIPE_VERSION=2023-10-16
TINY_STRIPE_WEBHOOK_SIGNATURE=whsec_…
TINY_STRIPE_MULTI_CURRENCY=false
```

### S3 / Spaces

```env
TINY_S3_REGION=nyc3
TINY_S3_ENDPOINT=https://nyc3.digitaloceanspaces.com
TINY_S3_BUCKET=my-bucket
TINY_S3_KEY=…
TINY_S3_SECRET=…
TINY_S3_CDN=https://cdn.example.com
TINY_S3_CDN_ID=…                    # for purging
TINY_S3_REDUCED_REDUNDANCY=false
TINY_S3_PATH_PREFIX=
TINY_S3_CLOUDFRONT_DISTRIBUTION=    # if using CloudFront
```

### Mailgun

```env
TINY_MAILGUN_API_KEY=…
TINY_MAILGUN_DOMAIN=m.example.com
TINY_MAILGUN_FROM_ADDRESS=noreply@example.com
TINY_MAILGUN_FROM_NAME="Example"
```

### Twilio

```env
TINY_TWILIO_ACCOUNT_SID=…
TINY_TWILIO_AUTH_TOKEN=…
TINY_TWILIO_MESSAGE_SERVICE_ID=…
TINY_TWILIO_FROM_NUMBER=+1…
```

### Mixpanel

```env
TINY_MIXPANEL_PROJECT_TOKEN=…
TINY_MIXPANEL_ENABLED=true
```

### Sentry (in your `html/index.php`)

```env
SENTRY_DSN=https://…
SENTRY_SAMPLE_RATE=1.0
SENTRY_FRONTEND=
```

## App-specific variables

Application-level settings should use the `APP_*` prefix (no `TINY_`):

```env
APP_SERVER_NAME=example.com
APP_RPC_TOKEN=…
APP_FEATURE_FLAG_X=true
```

These don't collide with framework variables and are easy to grep.

## Per-environment files

Create one file per environment:

```
.env.local      # development on your machine
.env.dev        # shared dev server
.env.stage      # staging
.env.prod       # production
```

`env.php` selects which one to load. Switch by changing the `ENV` constant or by setting the `ENV` server variable directly.

## Verification

After changing config, view the resolved values with the debugger:

```php
tiny::dd($_SERVER['TINY_DB_TYPE'], $_SERVER['TINY_AUTOLOAD_HELPERS']);
```

(Make sure your IP is in `TINY_DEBUG_WHITELIST`.)
