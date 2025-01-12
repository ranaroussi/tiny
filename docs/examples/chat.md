# Real-Time Chat Example

This example demonstrates how to build a real-time chat application using Tiny PHP Framework's SSE extension and database integration.

## Project Structure

```
/your-project
├── app/
│   ├── controllers/
│   │   ├── chat.php
│   │   └── messages.php
│   ├── models/
│   │   ├── message.php
│   │   └── room.php
│   └── views/
│       └── chat/
│           ├── index.php
│           └── room.php
├── public/
│   └── js/
│       └── chat.js
└── database/
    └── migrations/
        └── 001_create_chat_tables.php
```

## Database Migration

```php
<?php
// database/migrations/001_create_chat_tables.php

return [
    'up' => "
        CREATE TABLE rooms (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE messages (
            id INT AUTO_INCREMENT PRIMARY KEY,
            room_id INT NOT NULL,
            user_id INT NOT NULL,
            content TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (room_id) REFERENCES rooms(id),
            FOREIGN KEY (user_id) REFERENCES users(id)
        );
    ",
    'down' => "
        DROP TABLE messages;
        DROP TABLE rooms;
    "
];
```

## Chat Controller

```php
<?php
// app/controllers/chat.php

class Chat extends TinyController
{
    private $roomModel;
    private $messageModel;

    public function __construct()
    {
        // Require authentication
        if (!tiny::isAuthenticated()) {
            return tiny::response()->redirect('/login');
        }

        $this->roomModel = tiny::model('room');
        $this->messageModel = tiny::model('message');
    }

    // Show chat rooms
    public function get($request, $response)
    {
        $rooms = $this->roomModel->all();
        return $response->render('chat/index', ['rooms' => $rooms]);
    }

    // Show specific room
    public function room($request, $response)
    {
        $roomId = $request->path->id;
        $room = $this->roomModel->find($roomId);

        if (!$room) {
            return $response->redirect('/chat');
        }

        // Get recent messages
        $messages = $this->messageModel->getRecent($roomId, 50);

        return $response->render('chat/room', [
            'room' => $room,
            'messages' => $messages
        ]);
    }

    // SSE endpoint for real-time updates
    public function stream($request, $response)
    {
        $roomId = $request->query->room;

        // Initialize SSE
        $sse = tiny::sse();
        $sse->start();

        // Send initial connection ID
        $sse->send([
            'type' => 'connected',
            'id' => $sse->getId()
        ]);

        // Subscribe to room channel
        $sse->subscribe("chat.room.$roomId", function($data) use ($sse) {
            $sse->send($data);
        });

        // Keep connection alive
        $sse->keepAlive(30);
    }
}
```

## Message Controller

```php
<?php
// app/controllers/messages.php

class Messages extends TinyController
{
    private $model;

    public function __construct()
    {
        $this->model = tiny::model('message');
    }

    // Send message
    public function post($request, $response)
    {
        $data = $request->body(true);

        // Validate input
        if (empty($data['content']) || empty($data['room_id'])) {
            return $response->sendJSON([
                'error' => 'Invalid message data'
            ], 400);
        }

        // Create message
        $message = $this->model->create([
            'room_id' => $data['room_id'],
            'user_id' => tiny::user()->id,
            'content' => $data['content']
        ]);

        // Broadcast to room
        tiny::sse()->broadcast("chat.room.{$data['room_id']}", [
            'type' => 'message',
            'data' => [
                'id' => $message->id,
                'content' => $message->content,
                'user' => tiny::user()->name,
                'timestamp' => $message->created_at
            ]
        ]);

        return $response->sendJSON(['success' => true]);
    }
}
```

## Chat View

```php
<!-- app/views/chat/room.php -->
<?php tiny::layout()->extend('default') ?>

<?php tiny::layout()->section('content') ?>
    <div class="chat-container">
        <div class="chat-messages" id="messages">
            <?php foreach ($messages as $message): ?>
                <div class="message">
                    <strong><?= $message->user->name ?>:</strong>
                    <span><?= $message->content ?></span>
                    <small><?= tiny::utils()->timeAgo($message->created_at) ?></small>
                </div>
            <?php endforeach ?>
        </div>

        <form id="messageForm" class="chat-form">
            <input type="text" id="messageInput" placeholder="Type your message...">
            <button type="submit">Send</button>
        </form>
    </div>

    <script>
        const roomId = <?= $room->id ?>;
        const userId = <?= tiny::user()->id ?>;
    </script>
    <script src="/js/chat.js"></script>
<?php tiny::layout()->endSection() ?>
```

## JavaScript Client

```javascript
// public/js/chat.js

class Chat {
    constructor(roomId) {
        this.roomId = roomId;
        this.messageForm = document.getElementById('messageForm');
        this.messageInput = document.getElementById('messageInput');
        this.messagesContainer = document.getElementById('messages');

        this.initSSE();
        this.initEventListeners();
    }

    initSSE() {
        this.eventSource = new EventSource(`/chat/stream?room=${this.roomId}`);

        this.eventSource.onmessage = (event) => {
            const data = JSON.parse(event.data);

            if (data.type === 'message') {
                this.addMessage(data.data);
            }
        };

        this.eventSource.onerror = () => {
            console.error('SSE connection failed');
            this.reconnect();
        };
    }

    initEventListeners() {
        this.messageForm.onsubmit = (e) => {
            e.preventDefault();
            this.sendMessage();
        };
    }

    async sendMessage() {
        const content = this.messageInput.value.trim();
        if (!content) return;

        try {
            const response = await fetch('/messages', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify({
                    room_id: this.roomId,
                    content: content
                })
            });

            if (response.ok) {
                this.messageInput.value = '';
            }
        } catch (error) {
            console.error('Failed to send message:', error);
        }
    }

    addMessage(message) {
        const div = document.createElement('div');
        div.className = 'message';
        div.innerHTML = `
            <strong>${message.user}:</strong>
            <span>${message.content}</span>
            <small>${this.formatTime(message.timestamp)}</small>
        `;

        this.messagesContainer.appendChild(div);
        this.scrollToBottom();
    }

    scrollToBottom() {
        this.messagesContainer.scrollTop = this.messagesContainer.scrollHeight;
    }

    formatTime(timestamp) {
        return new Date(timestamp).toLocaleTimeString();
    }

    reconnect() {
        setTimeout(() => {
            this.initSSE();
        }, 5000);
    }
}

// Initialize chat
document.addEventListener('DOMContentLoaded', () => {
    new Chat(roomId);
});
```

## Features Demonstrated

- Real-time messaging
- Server-Sent Events
- Database integration
- User authentication
- Message broadcasting
- Error handling
- Reconnection logic
- Message persistence

## Best Practices

1. **Real-Time Communication**
   - Use SSE for real-time updates
   - Implement reconnection logic
   - Handle connection errors
   - Optimize message delivery

2. **Security**
   - Authenticate users
   - Validate input
   - Protect against XSS
   - Use CSRF protection

3. **Performance**
   - Limit message history
   - Implement pagination
   - Cache active rooms
   - Optimize queries

4. **User Experience**
   - Show typing indicators
   - Display read receipts
   - Auto-scroll messages
   - Handle offline state
