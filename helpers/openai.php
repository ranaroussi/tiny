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
        $payload['messages'] = [
            "role" => "user",
            "content" => [
                "type" => "text",
                "text" => $content
            ]
        ];

        $res = tiny::http()->postJSON(
            url: 'https://api.openai.com/v1/chat/completions',
            json: $payload,
            options: [
                "headers" => [
                    "Authorization: Bearer " . @$_SERVER['APP_OPENAI_API_KEY'] ?? ''
                ]
            ]
        )->json;

        // return $res;
        if (property_exists($res, 'choices')) {
            return json_encode([
                'error' => false,
                'data' => json_decode($res->choices[0]->message->content)
            ]);
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
