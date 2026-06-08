<?php

declare(strict_types=1);

class OpenAIHelper
{
    private const DEFAULT_MODEL = "gpt-4o-mini";
    private const DEFAULT_MAX_TOKENS = 500;

    /**
     * Moderates the given content using OpenAI's GPT model.
     *
     * @param string $userPrompt The content to be moderated, containing type, text, and link.
     * @param string|null $systemPrompt Optional system prompt to be included in the message.
     * @param array $images Optional array of image URLs or paths to be included in the message.
     * @return mixed JSON encoded response containing moderation results or error information.
     */
    public static function sendMessage(
        string $userPrompt,
        ?string $systemPrompt = null,
        array $images = [],
        int $maxTokens = self::DEFAULT_MAX_TOKENS,
        string $model = self::DEFAULT_MODEL
    ): mixed {
        $payload = [
            "model" => $model,
            "max_tokens" => $maxTokens,
            "response_format" => ["type" => "json_object"],  // Force JSON output
            "messages" => []
        ];
        if ($systemPrompt) {
            $payload['messages'][] = [
                "role" => "system",
                "content" => [[
                    "type" => "text",
                    "text" => $systemPrompt,
                ]]
            ];
        }

        $content = [
            ["type" => "text", "text" => $userPrompt]
        ];
        foreach ($images as $image) {
            $content[] = [
                "type" => "image_url",
                "image_url" => [
                    "url" => self::processImage($image),
                    "detail" => "low"
                ]
            ];
        }
        $payload['messages'][] = [
            "role" => "user",
            "content" => $content
        ];

        // Use direct curl instead of tiny::http() to avoid body truncation bug
        $ch = curl_init('https://api.openai.com/v1/chat/completions');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . (@$_SERVER['TINY_OPENAI_API_KEY'] ?? '')
            ],
            CURLOPT_POSTFIELDS => json_encode($payload)
        ]);

        $body = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        $ch = null;

        // Log response details
        if ($curlError) {
            error_log("⚠️ Curl error: " . $curlError);
        } else {
            error_log("✅ Curl success. HTTP {$httpCode}, body length: " . strlen($body) . " bytes");
        }

        // Parse JSON from body
        $res = null;
        if ($body) {
            $res = json_decode($body, true);
            if ($res) {
                error_log("✅ Successfully parsed JSON from curl response");
                if (isset($res['choices'])) {
                    error_log("✅ Response has 'choices' key with " . count($res['choices']) . " choices");
                } else {
                    error_log("⚠️ Response missing 'choices' key. Top-level keys: " . implode(', ', array_keys($res)));
                }
            } else {
                error_log("⚠️ Failed to parse JSON: " . json_last_error_msg());
                error_log("Body preview: " . substr($body, 0, 500));
            }
        } else {
            error_log("⚠️ Empty response body from curl");
        }

        // Handle both array and object response structures
        $hasChoices = false;
        $content = null;

        if ($res) {
            if (is_array($res) && isset($res['choices'])) {
                $hasChoices = true;
                $content = $res['choices'][0]['message']['content'] ?? null;
            } else if (is_object($res) && property_exists($res, 'choices')) {
                $hasChoices = true;
                $content = $res->choices[0]->message->content ?? null;
            }
        }

        if ($hasChoices && $content) {
            error_log("✅ OpenAI content extracted: " . substr($content, 0, 200));
            $parsedContent = json_decode($content, true);
            if ($parsedContent) {
                error_log("✅ Content parsed as JSON successfully!");
                return json_encode([
                    'error' => false,
                    'data' => $parsedContent
                ]);
            } else {
                error_log("⚠️ Content is not valid JSON: " . json_last_error_msg());
            }
        }

        // Log error for debugging
        if (!$res) {
            error_log("OpenAI API returned null response");
        } else if ((is_array($res) && isset($res['error'])) || (is_object($res) && property_exists($res, 'error'))) {
            $error = is_array($res) ? $res['error'] : $res->error;
            error_log("OpenAI API error: " . json_encode($error));
        } else {
            error_log("⚠️ OpenAI response structure unexpected. Has choices: " . ($hasChoices ? 'yes' : 'no'));
        }

        return json_encode([
            'error' => true,
            'data' => [
                'status' => 'failed',
                'category' => 'unknown',
                'reasoning' => 'Invalid request',
                'description' => 'The request to the moderation API failed',
                'severity' => 0
            ]
        ]);

        return $res;
    }

    /**
     * Processes the given image for inclusion in the OpenAI API request.
     *
     * @param string $image The image URL or file path.
     * @return string The processed image URL or base64 encoded image data.
     */
    private static function processImage(string $image): string
    {
        if (!str_starts_with($image, 'http')) {
            $imageData = base64_encode(file_get_contents($image));
            return "data:image/jpeg;base64,{$imageData}";
        }
        return $image;
    }
}

// Usage:
// $result = tiny::openai()->sendMessage($userPrompt, $systemPrompt, $images, $maxTokens, $model);

tiny::registerHelper('openai', function () {
    return new OpenAIHelper();
});
