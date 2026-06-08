[Home](../readme.md) | [Getting Started](../getting-started) | [Core Concepts](../core-concepts) | [Helpers](../helpers) | [Extensions](../extensions) | [Repo](https://github.com/ranaroussi/tiny)

# Helpers

Helpers are integration modules and utility classes living in `tiny/helpers/`. Unlike extensions, helpers are **not** auto-loaded — you opt in by setting `TINY_AUTOLOAD_HELPERS` in your env file:

```env
# Load specific helpers
TINY_AUTOLOAD_HELPERS=stripe,mailgun,utils

# Or load all helpers
TINY_AUTOLOAD_HELPERS=*
```

Once loaded, each helper is exposed as `tiny::<name>()` and returns a singleton instance.

```php
$payment = tiny::stripe()->charge([...]);
$url     = tiny::spaces()->upload('bucket', 'key', $bytes);
$loc     = tiny::geos()->getLocation();
```

## Catalog

### Payments & storage

| Helper | Accessor | Purpose | Detailed docs |
|---|---|---|---|
| Stripe | `tiny::stripe()` | Payments, subscriptions, customers, webhook signatures | [stripe.md](stripe.md) |
| Spaces / S3 | `tiny::spaces()` | DigitalOcean Spaces / S3-compatible storage; signed URLs, CDN purging | [spaces.md](spaces.md) |
| Invoice | `tiny::invoice()` | Generates PDF invoices |  |

### Email

| Helper | Accessor | Purpose |
|---|---|---|
| Mailgun | `tiny::mailgun()` | Send via Mailgun |
| Sendgrid | `tiny::sendgrid()` | Send via SendGrid |
| Email | `tiny::emailUtils()` | Generic email helper (formatting, parsing) |
| EmailValidator | `tiny::emailValidator($email)` | Validates emails, including a disposable-domain blacklist |

### Marketing & analytics

| Helper | Accessor | Purpose |
|---|---|---|
| HubSpot | `tiny::hubspot()` | CRM sync |
| Customer.io | `tiny::customerio()` | Behavioural messaging |
| Encharge | `tiny::encharge()` | Marketing automation |
| GetResponse | `tiny::getresponse()` | Email marketing |
| Mixpanel | `tiny::mixpanel()` | Event analytics |
| Userflow | `tiny::userflow()` | Product tours |
| Logsnag | `tiny::logsnag()` | Event notifications |

### Messaging

| Helper | Accessor | Purpose |
|---|---|---|
| Twilio | `tiny::twilio()` | SMS / voice |
| Vonage | `tiny::vonage()` | SMS / voice (formerly Nexmo) |

### Auth & identity

| Helper | Accessor | Purpose | Detailed docs |
|---|---|---|---|
| OAuth | `tiny::oauth()` | OAuth providers (Google, GitHub, etc.) | [oauth.md](oauth.md) |
| Avatar | `tiny::avatar()` | Generate identicon-style avatars |  |
| Cypher | `tiny::cypher()` | AES-256 encryption with TTL |  |
| UUID | `tiny::uuidUtils()` | UUID v3 / v4 / v5 generation and validation |  |

### Geo

| Helper | Accessor | Purpose | Detailed docs |
|---|---|---|---|
| Geos | `tiny::geos()` | GeoIP2 lookup, country/currency rules | [geos.md](geos.md) |

### Files & media

| Helper | Accessor | Purpose |
|---|---|---|
| DocReader | `tiny::docReader()` | Read text from PDF/DOCX/etc. |
| Markdown | `tiny::markdown()` | GFM-flavoured markdown with extensions |
| OpenGraph | `tiny::opengraph()` | Parse / emit Open Graph metadata, generate OG images |
| HTML | `tiny::html()` | HTML manipulation helpers |
| Caddy | `tiny::caddy()` | Generate Caddyfile snippets |

### Utilities

| Helper | Accessor | Purpose | Detailed docs |
|---|---|---|---|
| Utils | `tiny::utils()` | String / array / date helpers | [utils.md](utils.md) |
| Rate limiter | `tiny::rateLimiter($name, $reqs, $secs)` | Cache-backed request throttling | [rate-limiter.md](rate-limiter.md) |
| Shell | `tiny::shell()` | Safe shell command execution |  |

> **Rate limiter accessor signature.** Unlike the other helpers, `rateLimiter` takes constructor arguments on every call — they're forwarded to the underlying `RateLimiter` class. Usage:
>
> ```php
> $rl = tiny::rateLimiter('api', 10, 1);   // 10 requests per second
> $rl->add(1000, 3600);                    // plus 1000 per hour
> if (!$rl->check($_SERVER['REMOTE_ADDR'])) {
>     return $response->sendJSON(['error' => 'Too many requests'], 429);
> }
> ```

## Configuration

Each helper reads its credentials from environment variables. Convention is `TINY_<HELPER>_<KEY>`:

```env
TINY_STRIPE_PK=pk_test_…
TINY_STRIPE_SK=sk_test_…
TINY_STRIPE_WEBHOOK_SIGNATURE=whsec_…

TINY_MAILGUN_API_KEY=…
TINY_MAILGUN_DOMAIN=m.example.com
TINY_MAILGUN_FROM_ADDRESS=noreply@example.com

TINY_S3_REGION=nyc3
TINY_S3_ENDPOINT=https://nyc3.digitaloceanspaces.com
TINY_S3_BUCKET=my-bucket
TINY_S3_KEY=…
TINY_S3_SECRET=…
TINY_S3_CDN=https://cdn.example.com
```

See [Configuration reference](../getting-started/configuration.md) for the full list.

## Custom helpers

Two ways to add your own:

### 1. Drop a file in `tiny/helpers/`

```php
<?php
// tiny/helpers/myservice.php
declare(strict_types=1);

class TinyMyservice
{
    public function ping(): bool { return true; }
}
```

Add `TINY_AUTOLOAD_HELPERS=myservice` (or `*`) to your env and call `tiny::myservice()->ping()`.

### 2. Register a factory at runtime

For project-specific helpers you don't want in the framework folder, register a factory closure (typically from `app/common.php`):

```php
<?php
tiny::registerHelper('analytics', function () {
    return new \App\Services\Analytics($_SERVER['TINY_ANALYTICS_TOKEN']);
});

tiny::registerHelper('mailer', function () {
    return new \App\Services\Mailer();
});
```

Subsequent calls to `tiny::analytics()` return the singleton built by the closure. Arguments passed to the accessor are forwarded to the factory on first call.

## Best practices

1. **Only autoload what you use.** `TINY_AUTOLOAD_HELPERS=*` is convenient but adds cold-start cost.
2. **Wrap third-party calls with caching** where possible — APIs are slow and rate-limited.
3. **Don't pass secrets through user-controlled input.** Helper credentials should always come from env.
4. **Build domain-specific facades on top of helpers.** Don't call `tiny::stripe()` directly from a controller; wrap it in a service that knows your business rules.
