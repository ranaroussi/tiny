<?php

/**
 * Tiny: PHP Framework
 * https://github.com/ranaroussi/tiny
 *
 * Copyright 2013-2024 Ran Aroussi (@aroussi)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     https://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 */

declare(strict_types=1);

class TinySSE
{
    private const HEADERS = [
        'Access-Control-Allow-Origin: *',
        'Access-Control-Allow-Credentials: true',
        'Cache-Control: no-store, no-cache, must-revalidate',
        'Connection: keep-alive',
        'Content-Type: text/event-stream',
        'X-Accel-Buffering: no' // ← disable buffering in nginx through headers
    ];

    /**
     * Initializes the Server-Sent Events (SSE) connection.
     *
     * This method sets up the necessary configurations for SSE:
     * - Manages session handling
     * - Configures PHP execution settings
     * - Sets appropriate HTTP headers for SSE
     * - Disables output compression
     * - Configures output buffering for immediate flushing
     *
     * @return void
     */
    public static function start(): void
    {

        if (session_id() === '') {
            session_start();
        }
        session_write_close();
        set_time_limit(0);
        ignore_user_abort(false);

        foreach (self::HEADERS as $header) {
            header($header);
        }

        if (function_exists('apache_setenv')) {
            apache_setenv('no-gzip', '1');
        }

        ini_set('zlib.output_compression', '0');
        ini_set('implicit_flush', '1');

        // Push data to the browser every "sleep"
        ob_implicit_flush(true);
        ob_start();
        while (ob_get_level() > 0) {
            ob_end_flush();
        }
        while (ob_get_length() > 0) {
            ob_end_clean();
        }
    }


    /**
     * Sends data to the client using Server-Sent Events (SSE) format.
     *
     * This method takes a string of data, formats it according to the SSE specification,
     * and sends it to the client. It also ensures that the data is immediately flushed
     * to the client.
     *
     * @param string $data The data to be sent to the client
     * @return void
     *
     * @uses TinySSE::flush() to ensure the data is immediately sent to the client
     *
     * Note: The data is sent with the "data:" prefix as per SSE specification.
     *       Any backslashes in the data are removed using stripslashes().
     */
    public function send(string $data): void
    {
        echo "data: " . stripslashes($data) . "\n\n";
        $this->flush();
    }

    /**
     * Flushes the output buffer and sends any buffered output to the client.
     *
     * This method ensures that any content in the PHP output buffer is sent
     * immediately to the client. It's particularly useful in SSE scenarios
     * where we want to ensure real-time data transmission.
     *
     * The method performs two main actions:
     * 1. If there's content in the output buffer (ob_get_level() > 0),
     *    it flushes that buffer using ob_flush().
     * 2. It then calls the PHP flush() function to push the data to the client.
     *
     * @return void
     */
    public function flush(): void
    {
        if (ob_get_level() > 0) {
            ob_flush();
        }
        flush();
    }


    /**
     * Streams PostgreSQL notifications using Server-Sent Events (SSE).
     *
     * This method sets up a continuous stream that listens for notifications on a specified
     * PostgreSQL channel. When a notification is received, it's immediately sent to the client
     * as a JSON-encoded message.
     *
     * @param string $channel The name of the PostgreSQL channel to listen on
     * @param int $sleep The number of seconds to wait between checks for notifications (default: 1)
     * @return void
     *
     * @uses TinySSE::start() to initialize the SSE connection
     * @uses tiny::db()->getPdo() to get the PDO connection
     * @uses TinySSE::send() to send data to the client
     * @uses TinySSE::flush() to ensure data is immediately sent
     *
     * Note: This method runs indefinitely until the connection is closed by the client or the server.
     */
    public function streamPostgres(string $channel, int $sleep = 1): void
    {
        /*
        -- run run this for every channel
        CREATE OR REPLACE FUNCTION public.<NOTIFY_CHANNEL>()
        RETURNS trigger
        AS $function$
        BEGIN
            PERFORM pg_notify('<CHANNEL_NAME>', row_to_json(NEW)::text);
            RETURN NULL;
        END;
        $function$
        LANGUAGE plpgsql;

        CREATE TRIGGER trigger_on_insert
            AFTER INSERT ON <MYTABLE>
            FOR EACH ROW
            EXECUTE PROCEDURE <NOTIFY_CHANNEL>();
        */
        self::start();

        $pdo = tiny::db()->getPdo();
        $pdo->exec('LISTEN ' . $pdo->quote($channel));

        while (true) {
            $result = $pdo->pgsqlGetNotify(PDO::FETCH_ASSOC, $sleep * 1000);
            if ($result) {
                $this->send(json_encode($result, JSON_UNESCAPED_SLASHES));
            }
            $this->flush();
        }
    }

    /**
     * Streams data from a cache using Server-Sent Events (SSE).
     *
     * This method sets up a stream that continuously checks a specified cache key
     * for new data. When data is found, it's sent to the client and then deleted
     * from the cache. This allows for real-time updates from the server to the client.
     *
     * @param string $key The cache key to monitor for new data
     * @param int $sleep The number of seconds to wait between cache checks (default: 1)
     * @return void
     *
     * @uses TinySSE::stream() to set up the SSE stream
     * @uses tiny::cache() to interact with the cache
     *
     * Note: To terminate the stream, send "[DONE]" as the cache value.
     */
    public function streamKey(string $key, int $sleep = 1): void
    {
        /*
        // usage - reader
        tiny::sse()->streamKey('KEY', 1);

        // usage - writer
        tiny::sse()->sendKey('KEY', 'VALUE');

        // usage - web client
        <script>
        sseInit('URL', (data) => {
            console.log(data);
        }, 'NAME (optional)');
        </script>
        */

        self::start();

        $m = tiny::cache();
        self::stream(function () use ($m, $key) {
            // to quit - send "[DONE]"
            $data = $m->get($key);
            if ($data) {
                // error_log($data); // ← for debugging
                $m->delete($key);
                return $data;
            }
            return null;
        }, $sleep);
    }

    /**
     * Pushes data to a specified cache key for Server-Sent Events (SSE).
     *
     * This method takes a key and data, encodes the data if it's not already a string,
     * and stores it in the cache using the provided key. This is typically used in
     * conjunction with streamKey() to facilitate real-time updates.
     *
     * @param string $key The cache key to store the data under
     * @param mixed $data The data to be stored. If not a string, it will be JSON encoded
     * @return void
     *
     * @uses tiny::cache() to get the cache instance
     * @uses json_encode() to convert non-string data to JSON
     *
     * Note: This method is designed to work with the SSE streaming system,
     * allowing data to be pushed for later retrieval and streaming to clients.
     */
    public function sendKey(string $key, mixed $data): void
    {
        $data = is_string($data) ? $data : json_encode($data, JSON_UNESCAPED_SLASHES);
        tiny::cache()->set($key, $data);
    }

    /**
     * Streams data using Server-Sent Events (SSE).
     *
     * This method sets up a continuous stream that repeatedly calls a provided function
     * to fetch data. When data is available, it's sent to the client. The stream
     * continues until the connection is aborted or the script is terminated.
     *
     * @param callable $func The function to call for fetching data
     * @param int $sleep The number of seconds to wait between function calls (default: 10)
     * @return void
     *
     * @uses TinySSE::start() to initialize the SSE stream
     * @uses TinySSE::send() to send data to the client
     * @uses TinySSE::flush() to flush the output buffer
     *
     * Note: The stream will automatically terminate if the connection is aborted,
     * sending a '[DONE]' message before exiting.
     */
    public function stream(callable $func, int $sleep = 10): void
    {
        self::start();

        while (true) {
            if (connection_aborted()) {
                $this->send('[DONE]');
                $this->flush();
                exit();
            }
            $data = $func();
            if ($data !== null) {
                $this->send($data);
            }
            sleep($sleep);
        }
    }
}
