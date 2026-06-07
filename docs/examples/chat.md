[Home](../readme.md) | [Getting Started](../getting-started) | [Core Concepts](../core-concepts) | [Helpers](../helpers) | [Extensions](../extensions) | [Repo](https://github.com/ranaroussi/tiny)

# Real-time chat (SSE)

A minimal multi-room chat that pushes new messages to connected browsers using Server-Sent Events. Producer (POST `/messages`) writes to the database and publishes through `tiny::sse()->sendKey()`; consumer (GET `/chat/stream?room=N`) streams them with `tiny::sse()->streamKey()`.

This is the simplest pattern — one cache key per room. For higher throughput, see the [PostgreSQL `LISTEN/NOTIFY` variant in the SSE docs](../extensions/sse.md#postgresql-listennotify).

## Project structure

```
my-app/
├── app/
│   ├── controllers/
│   │   ├── chat.php             # /chat        and /chat/stream
│   │   └── messages.php         # POST /messages
│   ├── models/
│   │   └── message.php
│   └── views/
│       └── chat/
│           ├── index.php
│           └── room.php
├── html/static/js/chat.js
└── migrations/
    └── 20240101_chat.php
```

## Tables

```php
<?php
// migrations/20240101_chat.php
class Chat extends TinyMigration
{
    public function up(): void
    {
        tiny::db()->execute("
            CREATE TABLE rooms (
                id          INT AUTO_INCREMENT PRIMARY KEY,
                name        VARCHAR(255) NOT NULL,
                created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
        tiny::db()->execute("
            CREATE TABLE messages (
                id          INT AUTO_INCREMENT PRIMARY KEY,
                room_id     INT NOT NULL,
                user_id     INT NOT NULL,
                content     TEXT NOT NULL,
                created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_room (room_id, created_at)
            )
        ");
    }
    public function down(): void
    {
        tiny::db()->execute("DROP TABLE IF EXISTS messages");
        tiny::db()->execute("DROP TABLE IF EXISTS rooms");
    }
}
```

## Model

`app/models/message.php`:

```php
<?php

class MessageModel extends TinyModel
{
    public function recent(int $roomId, int $limit = 50): array
    {
        $stmt = tiny::db()->getPdo()->prepare("
            SELECT m.id, m.content, m.created_at,
                   u.id AS user_id, u.name AS user_name
            FROM messages m
            JOIN users u ON u.id = m.user_id
            WHERE m.room_id = :room
            ORDER BY m.id DESC
            LIMIT :limit
        ");
        $stmt->bindValue('room',  $roomId, \PDO::PARAM_INT);
        $stmt->bindValue('limit', $limit,  \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_OBJ);
    }

    public function create(int $roomId, int $userId, string $content): object
    {
        $id = tiny::db()->insert('messages', [
            'room_id' => $roomId,
            'user_id' => $userId,
            'content' => $content,
        ]);

        $stmt = tiny::db()->getPdo()->prepare("
            SELECT m.id, m.content, m.created_at,
                   u.id AS user_id, u.name AS user_name
            FROM messages m
            JOIN users u ON u.id = m.user_id
            WHERE m.id = :id
        ");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch(\PDO::FETCH_OBJ);
    }
}
```

## Chat controller

`app/controllers/chat.php`:

```php
<?php

class Chat extends TinyController
{
    public function get($request, $response)
    {
        // /chat/stream?room=42 → SSE endpoint
        if ($request->path->section === 'stream') {
            return $this->stream($request, $response);
        }

        // /chat/<roomId> → room page
        if ($request->path->section) {
            $roomId = (int)$request->path->section;
            $room   = tiny::db()->getOne('rooms', ['id' => $roomId]);
            if (!$room) {
                return tiny::controller('404', true);
            }
            $messages = array_reverse(tiny::model('message')->recent($roomId));
            return $response->render('chat/room', [
                'room'     => $room,
                'messages' => $messages,
            ]);
        }

        // /chat → room list
        $rooms = tiny::db()->getAll('rooms', '*', 'name ASC');
        $response->render('chat/index', ['rooms' => $rooms]);
    }

    private function stream($request, $response)
    {
        $roomId = (int)$request->params('room');
        if (!$roomId) {
            return $response->sendJSON(['error' => 'room required'], 400);
        }

        // Stream from a per-room cache key.
        tiny::sse()->streamKey("chat:room:$roomId", sleep: 1);
    }
}
```

## Message controller

`app/controllers/messages.php`:

```php
<?php

class Messages extends TinyController
{
    public function post($request, $response)
    {
        if (!$request->isValidCSRF()) {
            return $response->sendJSON(['error' => 'invalid csrf token'], 403);
        }

        $body    = $request->body(true);
        $roomId  = (int)($body['room_id'] ?? 0);
        $content = trim($body['content'] ?? '');

        if (!$roomId || $content === '') {
            return $response->sendJSON(['error' => 'room_id and content required'], 400);
        }
        if (mb_strlen($content) > 2000) {
            return $response->sendJSON(['error' => 'content too long'], 422);
        }

        $message = tiny::model('message')->create($roomId, tiny::user()->id, $content);

        // Publish to anyone subscribed to this room.
        tiny::sse()->sendKey("chat:room:$roomId", [
            'id'         => (int)$message->id,
            'content'    => $message->content,
            'user_id'    => (int)$message->user_id,
            'user_name'  => $message->user_name,
            'created_at' => $message->created_at,
        ]);

        $response->sendJSON(['ok' => true, 'id' => (int)$message->id]);
    }
}
```

Two notes on the SSE pattern:

- `sendKey()` writes the encoded payload to the cache and `streamKey()` reads + deletes it. **Each message is delivered to exactly one consumer.** That's fine for testing with a single tab; for multiple subscribers per room, switch to PostgreSQL `LISTEN/NOTIFY` (see [SSE extension docs](../extensions/sse.md)) or fan-out by writing one key per subscriber.
- Under PHP-FPM, every SSE connection holds a worker. Use [Swoole](../extensions/swoole.md) or [FrankenPHP](../getting-started/runtime-modes.md) if you expect more than a handful of concurrent rooms.

## Views

`app/views/chat/index.php`:

```php
<?php Layout::main(['title' => 'Chat rooms']); ?>

    <h1>Rooms</h1>
    <ul>
        <?php foreach ($rooms as $room): ?>
            <li><a href="/chat/<?= (int)$room->id ?>"><?= htmlspecialchars($room->name) ?></a></li>
        <?php endforeach ?>
    </ul>

<?php Layout::main(); ?>
```

`app/views/chat/room.php`:

```php
<?php Layout::main(['title' => $room->name]); ?>

    <meta name="csrf-token" content="<?= htmlspecialchars($_SESSION['csrf_token'] ?? tiny::csrf()->generate()) ?>">

    <h1><?= htmlspecialchars($room->name) ?></h1>

    <ul id="messages">
        <?php foreach ($messages as $m): ?>
            <li data-id="<?= (int)$m->id ?>">
                <strong><?= htmlspecialchars($m->user_name) ?></strong>:
                <?= htmlspecialchars($m->content) ?>
                <small><?= htmlspecialchars($m->created_at) ?></small>
            </li>
        <?php endforeach ?>
    </ul>

    <form id="message-form">
        <input id="content" maxlength="2000" placeholder="Type a message…" required>
        <button type="submit">Send</button>
    </form>

    <script>
        window.ROOM_ID = <?= (int)$room->id ?>;
    </script>
    <script src="/static/js/chat.js" defer></script>

<?php Layout::main(); ?>
```

## Client

`html/static/js/chat.js`:

```js
const csrfToken = document.querySelector('meta[name=csrf-token]').content;
const list      = document.getElementById('messages');
const form      = document.getElementById('message-form');
const input     = document.getElementById('content');

// Subscribe.
const es = new EventSource(`/chat/stream?room=${window.ROOM_ID}`);
es.onmessage = ({ data }) => {
    if (data === '[DONE]') return es.close();
    const m = JSON.parse(data);
    if (list.querySelector(`li[data-id="${m.id}"]`)) return;  // de-dup
    const li = document.createElement('li');
    li.dataset.id = m.id;
    li.innerHTML = `<strong></strong>: <span></span> <small></small>`;
    li.querySelector('strong').textContent = m.user_name;
    li.querySelector('span').textContent   = m.content;
    li.querySelector('small').textContent  = m.created_at;
    list.appendChild(li);
    list.scrollTop = list.scrollHeight;
};

// Send.
form.addEventListener('submit', async (e) => {
    e.preventDefault();
    const content = input.value.trim();
    if (!content) return;

    const res = await fetch('/messages', {
        method:  'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': csrfToken,
        },
        body: JSON.stringify({
            room_id:    window.ROOM_ID,
            content,
            csrf_token: csrfToken,
        }),
    });

    if (res.ok) input.value = '';
});
```

## Things to try next

- **Typing indicators** — use a separate cache key (`chat:room:42:typing`) and `streamKey` from the client.
- **History pagination** — add `/chat/<id>?before=<message_id>` and have the client prepend.
- **Multiple subscribers per room** — switch the consumer to `streamPostgres()` and trigger a Postgres `NOTIFY` from `MessageModel::create()`.
- **Presence** — write a heartbeat key per user that expires after 30s; render online users in the sidebar.
- **Markdown** — pipe `content` through `tiny::markdown()->render()` before broadcasting.
