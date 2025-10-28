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

        $httpResponse = tiny::http()->postJSON(
            url: 'https://api.openai.com/v1/chat/completions',
            json: $payload,
            options: [
                "headers" => [
                    "Authorization: Bearer " . (@$_SERVER['APP_OPENAI_API_KEY'] ?? '')
                ],
                "timeout" => 30  // 30 seconds for AI API calls
            ]
        );

        // Debug logging
        if (!$httpResponse || !isset($httpResponse->json)) {
            error_log("OpenAI HTTP request failed. Response: " . json_encode($httpResponse));
            error_log("HTTP Status: " . ($httpResponse->status ?? 'unknown'));
            error_log("HTTP Body: " . ($httpResponse->body ?? 'empty'));
        } else {
            // Log successful response for debugging
            error_log("OpenAI HTTP success. Status: " . ($httpResponse->status ?? 'unknown'));
            error_log("OpenAI Response Body (first 500 chars): " . substr($httpResponse->body ?? '', 0, 500));
            error_log("OpenAI Response JSON: " . json_encode($httpResponse->json));
        }

        $res = $httpResponse->json ?? null;

        // return $res;
        if ($res && is_object($res) && property_exists($res, 'choices')) {
            return json_encode([
                'error' => false,
                'data' => json_decode($res->choices[0]->message->content)
            ]);
        }

        // Log error for debugging
        if (!$res) {
            error_log("OpenAI API returned null response");
        } else if (is_object($res) && property_exists($res, 'error')) {
            error_log("OpenAI API error: " . json_encode($res->error));
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
