[Home](../readme.md) | [Getting Started](../getting-started) | [Core Concepts](../core-concepts) | [Helpers](../helpers) | [Extensions](../extensions) | [Repo](https://github.com/ranaroussi/tiny)

# ClickHouse

The ClickHouse extension provides a thin client for [ClickHouse](https://clickhouse.com) — useful for analytics workloads, event logs, and high-volume time-series data.

It's exposed via `tiny::clickhouse()` and is a singleton per request.

## Configuration

```env
TINY_CLICKHOUSE_HOST=clickhouse.example.com
TINY_CLICKHOUSE_PORT=8443
TINY_CLICKHOUSE_USERNAME=default
TINY_CLICKHOUSE_PASSWORD=…
TINY_CLICKHOUSE_HTTPS=true
TINY_CLICKHOUSE_TIMEOUT=30
```

These are read once on first call to `tiny::clickhouse()`.

## API

```php
$ch = tiny::clickhouse();

// Run a SELECT, get rows as JSONEachRow by default
$rows = $ch->select("SELECT count() FROM events WHERE event_date = today()");

// Insert a single row
$ch->insert('events', [
    'event_id'   => bin2hex(random_bytes(8)),
    'event_name' => 'page_view',
    'user_id'    => 42,
    'event_time' => date('Y-m-d H:i:s'),
]);

// Batch insert
$ch->batchInsert(
    'events',
    ['event_id', 'event_name', 'user_id', 'event_time'],
    [
        ['e1', 'page_view',  1, '2024-03-15 10:00:00'],
        ['e2', 'page_view',  2, '2024-03-15 10:00:01'],
        ['e3', 'add_to_cart', 1, '2024-03-15 10:00:05'],
    ]
);

// Update (mutation)
$ch->update('events', ['user_id' => 99], "user_id = 42");

// Raw query (returns the HTTP response)
$ch->query("OPTIMIZE TABLE events FINAL");

// Schema helpers
$ch->tableExists('events');                   // bool
$cols = $ch->getTableColumns('events');       // ['name' => 'type', …]
```

## Format selection

`select()` accepts a second argument for the output format (default `JSONEachRow`):

```php
$ch->select("SELECT * FROM events LIMIT 10", 'JSONEachRow');
$ch->select("SELECT * FROM events LIMIT 10", 'TSVWithNames');
```

## Typical patterns

### Event ingestion

```php
class Analytics
{
    public function track(string $event, int $userId, array $props = []): void
    {
        tiny::clickhouse()->insert('events', [
            'event_id'   => bin2hex(random_bytes(8)),
            'event_name' => $event,
            'user_id'    => $userId,
            'props'      => json_encode($props),
            'event_time' => date('Y-m-d H:i:s.u'),
        ]);
    }
}
```

### Aggregations for dashboards

```php
$daily = tiny::clickhouse()->select("
    SELECT
        toDate(event_time) AS day,
        event_name,
        count() AS n
    FROM events
    WHERE event_time >= today() - 30
    GROUP BY day, event_name
    ORDER BY day DESC
");
```

### Buffered writes

For high-frequency events, batch in your app and flush periodically:

```php
class EventBuffer
{
    private array $buffer = [];

    public function add(array $row): void
    {
        $this->buffer[] = $row;
        if (count($this->buffer) >= 1000) {
            $this->flush();
        }
    }

    public function flush(): void
    {
        if (!$this->buffer) return;
        tiny::clickhouse()->batchInsert(
            'events',
            array_keys($this->buffer[0]),
            array_map('array_values', $this->buffer)
        );
        $this->buffer = [];
    }
}
```

## Best practices

1. **Use `batchInsert()` for high-volume writes.** ClickHouse loves big batches.
2. **Pre-create tables and partitions** — the extension doesn't try to manage schema.
3. **Don't query ClickHouse for transactional data.** It's not OLTP; latencies and consistency models differ.
4. **Set a reasonable `TINY_CLICKHOUSE_TIMEOUT`.** Long aggregations can take seconds.
5. **For HTTPS endpoints with self-signed certs**, you may need to disable cert verification in the underlying client; check your operations team's CA setup first.
