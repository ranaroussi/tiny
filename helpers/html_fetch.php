<?php

declare(strict_types=1);

use League\HTMLToMarkdown\HtmlConverter;

function get_remote_url(string $url): array
{
    try {
        $content = tiny::requests()->get($url)->text;
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => $e->getMessage(),
        ];
    }

    $title = extract_title($content) ?? $url;
    $content = clean_content($content);

    $converter = new HtmlConverter();
    return [
        'success' => true,
        'data' => [
            'url' => $url,
            'title' => $title,
            'text' => $converter->convert($content),
        ],
    ];
}

function extract_title(string $content): ?string
{
    if (preg_match('/<title>(.*?)<\/title>/is', $content, $matches)) {
        return trim($matches[1]);
    }
    return null;
}

function clean_content(string $content): string
{
    $remove_tags = ['head', 'script', 'style', 'nav', 'header', 'footer'];
    $content = preg_replace(
        array_map(fn($tag) => "#<{$tag}(.*?)>(.*?)</{$tag}>#is", $remove_tags),
        '',
        $content
    );

    $allowed_tags = '<h1><h2><h3><h4><h5><h6><p><a><img><table><tr><td><th><tbody><thead><tfoot><ul><ol><li><dl><dt><dd><pre><code><blockquote><hr><address><br><small><strong><b><sub><sup><u><em><i><mark>';
    return strip_tags($content, $allowed_tags);
}
