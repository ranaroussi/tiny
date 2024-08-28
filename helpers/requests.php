<?php

class Requests
{

    private $headers = [];

    public function __construct($default_headers = null)
    {
        if ($default_headers) {
            $this->headers = $default_headers;
        }
    }

    public function getRedirectTarget($url)
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_NOBODY, 1);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1); // follow redirects
        curl_setopt($ch, CURLOPT_AUTOREFERER, 1); // set referer on redirect
        curl_exec($ch);
        $target = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        curl_close($ch);

        if ($target) {
            return $target;
        }

        return false;
    }

    // -------------------------------------------------------------------
    // shortcuts
    public function get(
        $url,
        $data = null,
        $json = false,
        $headers = null,
        $final_url = false,
        $interface = null,
        $ssl = null
    ) {
        return $this->request(
            'GET',
            $url,
            $data,
            $json,
            $headers,
            $final_url,
            $interface,
            $ssl
        );
    }

    public function post(
        $url,
        $data = null,
        $json = false,
        $headers = null,
        $final_url = false,
        $interface = null,
        $ssl = null
    ) {
        return $this->request(
            'POST',
            $url,
            $data,
            $json,
            $headers,
            $final_url,
            $interface,
            $ssl
        );
    }

    public function put(
        $url,
        $data = null,
        $json = false,
        $headers = null,
        $final_url = false,
        $interface = null,
        $ssl = null
    ) {
        return $this->request(
            'PUT',
            $url,
            $data,
            $json,
            $headers,
            $final_url,
            $interface,
            $ssl
        );
    }

    public function patch(
        $url,
        $data = null,
        $json = false,
        $headers = null,
        $final_url = false,
        $interface = null,
        $ssl = null
    ) {
        return $this->request(
            'PATCH',
            $url,
            $data,
            $json,
            $headers,
            $final_url,
            $interface,
            $ssl
        );
    }

    public function delete(
        $url,
        $data = null,
        $json = false,
        $headers = null,
        $final_url = false,
        $interface = null,
        $ssl = null
    ) {
        return $this->request(
            'DELETE',
            $url,
            $data,
            $json,
            $headers,
            $final_url,
            $interface,
            $ssl
        );
    }

    // -------------------------------------------------------------------

    private function request(
        $method,
        $url,
        $data = null,
        $json = null,
        $headers = null,
        $final_url = false,
        $interface = null,
        $ssl = null
    ) {
        /*
        syntac options:
        tiny::requests()->get(PARAMS);

        # php8
        tiny::requests()->get(
        url: 'https://example.com/path',
        ssl: [
        'cert' => './cert.pem',
        'key' => './key.pem'
        ]
        );

        tiny::requests->get([
        'url' => 'https://example.com/path',
        'ssl' => [
        'cert' => './cert.pem',
        'key' => './key.pem'
        ]
        );

        tiny::requests->get('https://example.com/path', [
        'ssl' => [
        'cert' => './cert.pem',
        'key' => './key.pem'
        ]
        );

        tiny::requests([
        'method' => 'GET',
        'url' => 'https://example.com/path',
        'ssl' => [
        'cert' => './cert.pem',
        'key' => './key.pem'
        ]
        );
         */
        // accept array with all params in url or data
        if (is_array($url)) {
            $original_url = $url;
            foreach ($url as $key => $value) {
                if (in_array($key, ['url', 'data', 'json', 'headers', 'final_url', 'interface', 'ssl'])) {
                    extract($url, EXTR_IF_EXISTS);
                    if ($url == $original_url) {
                        $url = null;
                    }
                    break;
                }
            }
        } else if (is_array($data)) {
            $original_data = $data;
            foreach ($data as $key => $value) {
                if (in_array($key, ['data', 'json', 'headers', 'final_url', 'interface', 'ssl'])) {
                    extract($data, EXTR_IF_EXISTS);
                    if ($data == $original_data) {
                        $data = null;
                    }
                    break;
                }
            }
        }

        // tiny::debug($json);
        return $this->curl(
            strtoupper($method),
            $url,
            $data,
            $json,
            $headers,
            $final_url,
            $interface,
            $ssl
        );
    }

    // -------------------------------------------------------------------

    private function curl(
        $method,
        $url,
        $data = null,
        $json = null,
        $headers = null,
        $final_url = false,
        $interface = null,
        $ssl = null
    ) {

        /*
        $ssl = [
        'cert' => 'path to certificate',
        'key' => 'path to key file',
        'keypass' => 'password',
        ]
         */
        $headers = array_merge($this->headers, ($headers) ? $headers : []);
        if ($json) {
            if (is_array($json) || is_object($json)) {
                $json = json_encode($json);
            }
            $headers[] = 'Content-Type: application/json';
            $headers[] = 'Content-Length: ' . strlen($json);
        }

        $send_data = '';
        if (is_array($data) && !empty($data)) {
            foreach ($data as $k => $v) {
                $send_data .= $k . '=' . $v . '&';
            }
        }

        if (strtoupper($method) == 'GET') {
            if (!$json && $send_data) {
                $url .= (strstr($url, '?')) ? '&' : '?';
                $url .= $send_data;
            }
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));

        if ($json || $send_data) {
            // curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, ($json) ? $json : $send_data);
        }

        if ($headers) {
            // tiny::debug($headers, 0);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array_unique($headers));
        }

        if ($interface) {
            curl_setopt($ch, CURLOPT_INTERFACE, $interface);
        }

        $ssl_verify_peer = 1;
        if ($ssl) {
            $cert = $ssl['cert'] ?? null;
            $key = $ssl['key'] ?? null;
            $keypass = $ssl['keypass'] ?? null;
            $ssl_verify_peer = $ssl['verify'] ?? 1;

            if ($cert) {
                curl_setopt($ch, CURLOPT_SSLCERT, $cert);
                if (str_ends_with($cert, '.pem')) {
                    curl_setopt($ch, CURLOPT_SSLCERTTYPE, 'PEM');
                }
            }
            if ($key) {
                curl_setopt($ch, CURLOPT_SSLKEY, $key);
                if (str_ends_with($key, '.pem')) {
                    curl_setopt($ch, CURLOPT_SSLKEYTYPE, 'PEM');
                }
                if ($keypass) {
                    curl_setopt($ch, CURLOPT_KEYPASSWD, $keypass);
                }
            }
        }

        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $ssl_verify_peer);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 60);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);

        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_HEADER, 1); // RETURN HTTP HEADERS?
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); // RETURN THE CONTENTS OF THE CALL?
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/112.0.0.0 Safari/537.36');

        $curl_data = curl_exec($ch);
        if ($final_url) {
            $curl_data = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL); // get last URL followed
        }
        curl_close($ch);
        // tiny::debug( curl_getinfo($ch) );

        if ($errno = curl_errno($ch)) {
            $error_message = curl_strerror($errno);
            return (object)array(
                'reason' => "$error_message (cURL #$errno)",
                'status' => '0 FAILED',
                'status_code' => 0,
                'headers' => [],
                'text' => "$error_message",
                'content' => "$error_message",
                'json' => '{"error":"' . $error_message . '"}'
            );
            echo "cURL error ({$errno}):\n {$error_message}";
        }

        return $this->objectify($curl_data);
    }

    // -------------------------------------------------------------------

    private function objectify($data)
    {
        $res = [
            'reason' => '',
            'status' => '',
            'status_code' => null,
            'headers' => [],
            'text' => '',
            'content' => '',
        ];

        if ($data === null) {
            return $res;
        }

        [$headers, $res['text']] = explode("\r\n\r\n", $data, 2) + [null, ''];
        $res['headers'] = [$headers];

        // Handle redirect headers
        if (strpos($res['text'], 'HTTP/') === 0) {
            [$additionalHeaders, $res['text']] = explode("\r\n\r\n", $res['text'], 2) + [null, ''];
            $res['headers'][] = $additionalHeaders;
        }

        $statusLine = strtok($res['headers'][count($res['headers']) - 1], "\n");
        $statusParts = explode(' ', $statusLine, 3);

        $res['status'] = $statusParts[1] ?? '';
        $res['status_code'] = (int)$res['status'];
        $res['reason'] = $statusParts[2] ?? '';
        $res['content'] = $res['text'];

        try {
            $res['json'] = json_decode($res['text'] . '');
        } catch (Exception $e) {
            $res['json'] = (object)array('error' => 'Not a JSON format');
        }

        if (count($res['headers']) == 1 && $res['headers'][0] == null) {
            $res['headers'] = [];
        }

        return (object)$res;
    }
}
