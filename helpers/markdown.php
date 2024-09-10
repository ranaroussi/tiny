<?php

declare(strict_types=1);

class Markdown
{
    public const MARKDOWNLIB_VERSION = "1.6.0";

    public function transform(string $text): string
    {
        $text = $this->processCols($text);
        $text = $this->processTabs($text);
        $text = $this->processSyntaxHighlighting($text);
        $text = $this->processTabContent($text);
        $text = $this->processNoCodeTabs($text);
        $text = $this->processOembed($text);
        $text = $this->processPageBreak($text);
        $text = $this->processCenter($text);
        $text = $this->processToggles($text);
        $text = $this->processBoxes($text);
        $text = $this->processCards($text);
        $text = $this->processTOC($text);
        $text = $this->originalTransform($text);
        $text = $this->processCallouts($text);
        $text = $this->cleanupHtml($text);
        $text = $this->processBookmarks($text);

        return $text;
    }

    private function processCols(string $text): string
    {
        return preg_replace(
            '/(::::\s?cols=(\d))\R+((\w*|.|\R)+)\R+(::::\s?+\R?+)/m',
            "<div class=\"cols\" style=\"--md-cols:$2\">\n$3\n</div>",
            $text
        );
    }

    private function processTabs(string $text): string
    {
        $text = preg_replace(
            '/(::::\s?tabs-code-group)\R+((\w*|.|\R)+)\R+(::::\s?+\R?+)/m',
            "<div class=\"code-group\"><div class=\"md-tab-group\"><div class=\"md-tab-toolbar\"></div>\n$2\n</div></div>",
            $text
        );
        $text = preg_replace(
            '/(::::\s?tabs)\R+((\w*|.|\R)+)\R+(::::\s?+\R?+)/m',
            "<div class=\"md-tab-group\"><div class=\"md-tab-toolbar\"></div>\n$2\n</div>",
            $text
        );
        return preg_replace(
            '/((:::\s?tab (.*?)(\s?\{(.+)\})?)\R((\w*|.|\R)+)(\R:::\s?\R))/m',
            "<div class=\"md-tab\" data-tab-title=\"$3\" style=\"display:none\">\n$6\n</div>",
            $text
        );
    }

    private function processSyntaxHighlighting(string $text): string
    {
        $GLOBALS['mermaids'] = 0;
        return preg_replace_callback(
            '/```(\w+)(\s?(\{(.+)\})?)\R((\w*|.|\R)+)\R```/m',
            function ($matches) {
                $GLOBALS['mermaids']++;
                if ($matches[1] == 'mermaid') {
                    return '<pre class="mermaid-src fixed hidden invisible opacity-0" style="top:-100vh;left:-100vh;" data-mermaid-id="' . $GLOBALS['mermaids'] . '">' . $matches[5] . '</pre><pre><code class="mermaid" id="mermaid-' . $GLOBALS['mermaids'] . '" style="color:transparent">' . $matches[5] . '</code></pre>';
                } else {
                    return '<pre data-prismjs-copy-timeout=1000 data-line="' . $matches[4] . '" class="line-numbers"><code class="language-' . $matches[1] . '" data-highlight="' . $matches[4] . '">' . $matches[5] . '</code></pre>';
                }
            },
            $text
        );
    }

    private function processTabContent(string $text): string
    {
        return preg_replace_callback(
            '/(.*|\R?)(<div class="md-tab-content">\R?)((\w*|.|\R)+)\R?(<\/div><\/div>\R?)(.*|\R?)/m',
            function ($matches) {
                return $matches[1] . $matches[2] . $this->originalTransform($matches[3]) . $matches[4] . $matches[5] . $matches[6];
            },
            $text
        );
    }

    private function processNoCodeTabs(string $text): string
    {
        return preg_replace_callback(
            '/((<div class="md-tab" (.*?)>\R)(?!<pre>)((\w*|.|\R)+)\R(<\/div>))/m',
            function ($matches) {
                return $matches[2] . $this->originalTransform($matches[4]) . $matches[6];
            },
            $text
        );
    }

    private function processOembed(string $text): string
    {
        return preg_replace_callback(
            '/(.*?[^`])\[\>\]\((https|http):\/\/(.*?)\)(\s|$)/im',
            function ($matches) {
                $url = urldecode("$matches[2]://$matches[3]");
                $domain = str_replace("www.", "", parse_url($url, PHP_URL_HOST));
                $iframe_options = ' allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen ';

                return match ($domain) {
                    "asciinema.org" => $this->processAsciinema($matches, $url),
                    "vimeo.com" => $this->processVimeo($matches, $url, $iframe_options),
                    "loom.com" => $this->processLoom($matches, $url, $iframe_options),
                    "youtu.be", "youtube.com" => $this->processYoutube($matches, $url, $iframe_options),
                    default => "$matches[1] <oembed src=\"$matches[2]://$matches[3]\">$matches[2]://$matches[3]</oembed> $matches[4]",
                };
            },
            $text
        );
    }

    private function processAsciinema(array $matches, string $url): string
    {
        preg_match_all('/a\/(.*?)(\?(.*?))?$/m', $url, $parts, PREG_SET_ORDER, 0);
        $id = $parts[0][1] ?? '';
        $qs = $parts[0][2] ?? '';
        return $matches[1] . '<div class="oembed-wrapper" style="overflow-y:hidden"><div style="margin-bottom: -16px"><script id="asciicast-' . $id . '" src="https://asciinema.org/a/' . $id . '.js' . $qs . '" async></script></div></div>' . $matches[4];
    }

    private function processVimeo(array $matches, string $url, string $iframe_options): string
    {
        $player = preg_replace('/https:\/\/vimeo.com\/(\d+)\/(\w+)(\??)(\&?)/m', 'https://player.vimeo.com/video/$1?h=$2&', $url);
        return $matches[1] . '<div class="oembed-wrapper"><div style="position:relative;height:0;padding-bottom:56.25%"><iframe ' . $iframe_options . ' style="position:absolute;top:0;left:0;width:100%;height:100%;border:0;overflow:hidden" src="' . $player . '"></iframe></div></div>' . $matches[4];
    }

    private function processLoom(array $matches, string $url, string $iframe_options): string
    {
        return $matches[1] . '<div class="oembed-wrapper"><div style="position:relative;height:0;padding-bottom:56.25%"><iframe ' . $iframe_options . ' style="position:absolute;top:0;left:0;width:100%;height:100%;border:0;overflow:hidden" src="' . str_replace('share', 'embed', $url) . '?hideEmbedTopBar=true"></iframe></div></div>' . $matches[4];
    }

    private function processYoutube(array $matches, string $url, string $iframe_options): string
    {
        if (str_contains($url, 'youtube.com')) {
            preg_match_all('/v=(.*?)($|&|#)/m', $url, $parts, PREG_SET_ORDER, 0);
        } else {
            preg_match_all('/youtu.be\/(.*?)($|&|#)/m', $url, $parts, PREG_SET_ORDER, 0);
        }
        $video_id = $parts[0][1] ?? '';
        $embed_url = 'https://www.youtube.com/embed/' . $video_id . '?feature=oembed';
        return $matches[1] . '<div class="oembed-wrapper"><div style="position:relative;height:0;padding-bottom:56.25%"><iframe ' . $iframe_options . ' style="position:absolute;top:0;left:0;width:100%;height:100%;border:0;overflow:hidden" src="' . $embed_url . '"></iframe></div></div>' . $matches[4];
    }

    private function processPageBreak(string $text): string
    {
        return preg_replace(
            '/(^|\R)(~+)($|\R)/',
            '\n<div class="print-page-break"></div>\n',
            $text
        );
    }

    private function processCenter(string $text): string
    {
        return preg_replace('/->(.*?)<-/', '<center>$1</center>', $text);
    }

    private function processToggles(string $text): string
    {
        return preg_replace_callback(
            '/(\[\[toggle\s(.*?)\]\]\s?\R?((\w*|.|\R)+)\[\[\/toggle\]\])/m',
            function ($matches) {
                return "<details>\n<summary>$matches[2]</summary><div>" . $this->originalTransform($matches[3]) . "</div></details>\n";
            },
            $text
        );
    }

    private function processBoxes(string $text): string
    {
        return preg_replace_callback(
            '/((\[\[boxed(\s?(.*?))?\]\]\s?\R?((\w*|.|\R)+)\[\[\/boxed\]\]))/m',
            function ($matches) {
                return "<div class=\"boxed boxed-$matches[4]\">" . $this->originalTransform($matches[5]) . "</div>\n";
            },
            $text
        );
    }

    private function processCards(string $text): string
    {
        $text = preg_replace_callback(
            '/(\[\[card(\s(.*?))?\]\]\s?\R?((\w*|.|\R)+)\[\[\/card\]\])/m',
            function ($matches) {
                return "<div class=\"card\">\n" . ($matches[3] ? "<h4>$matches[3]</h4>" : '') . $this->originalTransform($matches[4]) . "</div>\n";
            },
            $text
        );
        return preg_replace_callback(
            '/\((.*?)\)<div class="card">((\n|.)*?)<\/div>(\s+|$)/m',
            function ($matches) {
                return "<a class=\"card\" href=\"$matches[1]\">$matches[2]</a>";
            },
            $text
        );
    }

    private function processTOC(string $text): string
    {
        return preg_replace_callback(
            '/\n(#{2,4})\s+(.*?)\n/m',
            function ($matches) {
                $matches[2] = trim(strip_tags($matches[2]));
                $hash = mb_strtolower(str_replace(' ', '-', preg_replace('/[^a-zA-Z0-9 -]/m', '', $matches[2])));
                if ($hash == 'up-next') {
                    return "\n$matches[1] $matches[2]";
                }
                if ($matches[1] == '##') {
                    return "\n<h2><a class=\"anchor inherit\" id=\"$hash\" href=\"#$hash\">$matches[2]</a></h2>\n";
                } elseif ($matches[1] == '###') {
                    return "\n<h3><a class=\"anchor\" id=\"$hash\" href=\"#$hash\">$matches[2]</a></h3>\n";
                } else {
                    return "\n<h4><a class=\"anchor\" id=\"$hash\" href=\"#$hash\">$matches[2]</a></h4>\n";
                }
            },
            $text
        );
    }

    private function processCallouts(string $text): string
    {
        $callouts = ['tip', 'note', 'info', 'warning', 'danger'];
        foreach ($callouts as $callout) {
            $text = preg_replace(
                '/(<p>\[\[' . $callout . '\]\]\s?\R((\w*|.|\R)+)\R\[\[\/' . $callout . '\]\]<\/p>)/m',
                "<div class=\"callout callout-$callout\">$2</div>\n",
                $text
            );
            $text = preg_replace(
                '/(<p>\[\[' . $callout . '\s(.*?)\]\]\s?\R((\w*|.|\R)+)\R\[\[\/' . $callout . '\]\]<\/p>)/m',
                "<div class=\"callout callout-$callout\">\n<h3>$2</h3>\n$3\n</div>\n",
                $text
            );
            $text = preg_replace(
                '/(<p>\[\[' . $callout . '\]\]<\/p>\s?\R((\w*|.|\R)+)\R<p>\[\[\/' . $callout . '\]\]<\/p>)/m',
                "<div class=\"callout callout-$callout\">$2</div>\n",
                $text
            );
            $text = preg_replace(
                '/(<p>\[\[' . $callout . '\s(.*?)\]\]<\/p>\s?\R((\w*|.|\R)+)\R<p>\[\[\/' . $callout . '\]\]<\/p>)/m',
                "<div class=\"callout callout-$callout\">\n<h3>$2</h3>\n$3\n</div>\n",
                $text
            );
        }
        return $text;
    }

    private function cleanupHtml(string $text): string
    {
        $replacements = [
            '<p><oembed' => '<oembed',
            '</oembed></p>' => '</oembed>',
            '<p><div' => '<div',
            '</div></p>' => '</div>',
            '<p><details>' => '<details>',
            '</summary></p>' => '</summary>',
            '<p></details></p>' => '</details>',
            '<p></p>' => '',
        ];
        return str_replace(array_keys($replacements), array_values($replacements), $text);
    }

    private function processBookmarks(string $text): string
    {
        $re = '/\[\+\] <a href="(.*)">(.*)<\/a>/m';
        $subst = '<svg viewBox="0 0 384 512" xmlns="http://www.w3.org/2000/svg" style="margin:-2px 6px 0 0; width:14px; height:14px; display:inline"><path fill="currentColor" d="m0 487.7v-439.7c0-26.5 21.5-48 48-48h48v322.1c0 12.8 14.2 20.4 24.9 13.3l71.1-47.4 71.1 47.4c10.6 7.1 24.9-.5 24.9-13.3v-322.1h48c26.5 0 48 21.5 48 48v439.7c0 13.4-10.9 24.3-24.3 24.3-5 0-9.9-1.5-14-4.4l-153.7-107.6-153.7 107.6c-4.1 2.9-9 4.4-14 4.4-13.4 0-24.3-10.9-24.3-24.3z"/><path fill="currentColor" d="m192 288-71.1 47.4c-10.6 7.1-24.9-.5-24.9-13.3v-322.1h192v322.1c0 12.8-14.2 20.4-24.9 13.3z" opacity=".4"/></svg><a href="$1">$2</a><br>';
        return preg_replace($re, $subst, $text);
    }

    public function originalTransform(string $text): string
    {
        // Implementation of the original transform method
        // (This method should contain the original Markdown parsing logic)
        return $text;
    }
}
