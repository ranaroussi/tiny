<?php

declare(strict_types=1);


class Markdown
{
    public const MARKDOWNLIB_VERSION = "1.6.0";

    /**
     * Main transformation method that converts markdown to HTML.
     *
     * Processes text through multiple stages of markdown parsing:
     * 1. Custom syntax (cols, tabs, syntax highlighting, embeds, etc.)
     * 2. Standard markdown (via originalTransform)
     * 3. HTML cleanup and post-processing
     *
     * The order of operations is important as some processors depend on
     * output from previous processors.
     *
     * @param string|null $text The markdown text to convert
     * @return string|null The HTML output, or null/empty if input is invalid
     */
    public function transform(?string $text, bool $autoParseURLs = true, bool $autoParseToc = true): ?string
    {
        if (!$text) return '';

        // $text = ''; // use for debugigng

        // Ensure complex docs (e.g. large mermaid blocks) don't blow up regex rewrites
        if (function_exists('ini_set')) {
            $currentLimit = (int)ini_get('pcre.backtrack_limit');
            $targetLimit = 2000000;
            if ($currentLimit < $targetLimit) {
                @ini_set('pcre.backtrack_limit', (string)$targetLimit);
            }
        }

        $text = str_replace('\n', "\n", $text);
        $text = str_replace("\n1. ", "\n\n1. ", $text);
        $text = str_replace("\n\n- ", "\n\n\n- ", $text);
        $text = str_replace("\n```", "\n\n```", $text);
        $text = str_replace("```tsx", "```typescript", $text);
        $text = str_replace("```\n", "```\n\n", $text);

        $text = str_replace(' xmlns="http://www.w3.org/2000/svg"', "", $text);
        // $text = preg_replace('/\n\s+?- /m', "\n\n- ", $text);
        // $text = str_replace(":\n- ", ":\n\n- ", $text);
        // $text = str_replace("**\n- ", "**\n\n- ", $text);
        // ↓ only apply these to text not between ``` and ```
        $text = preg_replace_callback('/(```[\s\S]*?```)|([\s\S]+?)(?=(```|$))/', function ($m) {
            // If this is a fenced block, return as-is
            if (!empty($m[1])) return $m[1];

            $text = $m[2];
            $text = preg_replace('/\n\s+?- /m', "\n\n- ", $text);
            $text = str_replace(":\n- ", ":\n\n- ", $text);
            $text = str_replace("**\n- ", "**\n\n- ", $text);
            return $text;

        }, $text);

        $text = (string)$this->processCols($text);
        $text = (string)$this->processTabs($text);
        $text = (string)$this->processSyntaxHighlighting($text);
        $text = (string)$this->processTabContent($text);
        $text = (string)$this->processNoCodeTabs($text);
        $text = (string)$this->processOembed($text);
        $text = (string)$this->processPageBreak($text);
        $text = (string)$this->processCenter($text);
        $text = (string)$this->processBoxes($text);
        $text = (string)$this->processCards($text);
        if ($autoParseToc) {
            $text = (string)$this->processTOC($text);
        }

        $text = (string)$this->processCallouts($text);
        $text = (string)$this->processTables($text);
        $text = (string)$this->processSteps($text);
        $text = (string)$this->processDetails($text);
        $text = (string)$this->originalTransform($text);
        $text = (string)$this->cleanupHtml($text);

        if ($autoParseURLs) {
            $text = (string)$this->processURLs($text);
        }

        $text = (string)$this->processBookmarks($text);
        $text = (string)$this->processButtons($text);

        // replace `text` with <code>text</code>
        $text = preg_replace('/`(.*?)`/m', '<code>$1</code>', $text);

        // replace horizontal rules --- with <hr> outside pre/code blocks
        $parts = preg_split('/(<pre\b[^>]*>.*?<\/pre>|<code\b[^>]*>.*?<\/code>)/is', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        if ($parts !== false) {
            foreach ($parts as $index => $part) {
                if ($index % 2 === 0) {
                    $parts[$index] = str_replace("\n---\n", "\n<hr>\n", $part);
                }
            }
            $text = implode('', $parts);
        }

        // support checkboxes
        $text = str_replace('<li>[ ] ', '<li><input type="checkbox" style="margin-bottom: -2px; margin-right: 4px;" class="input"> ', $text);
        $text = str_replace('<li>[x] ', '<li><input type="checkbox" style="margin-bottom: -2px; margin-right: 4px;" class="input" checked> ', $text);
        // $text = str_replace('<p></div>', '</div><p>', $text);

        $text = str_replace('</p><br>', '</p>', $text);
        // $text = str_replace('<p></p>', '', $text);
        // $text = preg_replace('/\<p\>\s+?\<\/p\>/m', "", $text);


        // die($text);
        return $text;
    }

    /**
     * Processes column layout syntax in markdown.
     *
     * Converts custom column syntax (:::: cols=2) to HTML div elements
     * with CSS grid styling for multi-column layouts.
     *
     * @param string $text The markdown text containing column syntax
     * @return string|null The text with columns converted to HTML
     */
    private function processCols(string $text): ?string
    {
        // tiny::dd($text);

        return preg_replace(
            '/::::\s?cols=(\d)\R+([\s\S]+?)\R+::::/mu',
            "<div class=\"cols\" style=\"--md-cols:$1\">\n$2\n</div>\n",
            $text
        );
    }

    /**
     * Processes tab group syntax in markdown.
     *
     * Converts custom tab syntax ([[tabs]]...[[/tabs]]) with individual tabs
     * ([[tab Label]]...[[/tab]]) into HTML tab groups with navigation buttons
     * and content panels. First tab is active by default.
     *
     * @param string $text The markdown text containing tab syntax
     * @return string|null The text with tabs converted to HTML
     */
    private function processTabs(string $text): ?string
    {
        // Quick exit if no tabs marker found
        if (strpos($text, '[[tabs]]') === false) {
            return $text;
        }

        // replace each [[tabs]] ... [[/tabs]] block
        return preg_replace_callback('/\[\[tabs\]\](.*?)\[\[\/tabs\]\]/si', function ($m) {
            $block = $m[1];

            // find all [[tab LABEL]] ... [[/tab]] blocks
            if (!preg_match_all('/\[\[tab(?:\s+([^\]]+))?\]\](.*?)\[\[\/tab\]\]/si', $block, $tabs, PREG_SET_ORDER)) {
                // no inner tabs - return original block unchanged
                return $m[0];
            }

            $buttons = [];
            $panels  = [];
            $count   = 0;

            $firstTab = true;
            foreach ($tabs as $t) {
                $activeClass = $firstTab ? ' active-tab' : '';
                $firstTab = false;

                $count++;

                // label: everything after [[tab ...]]
                $rawLabel = isset($t[1]) ? trim($t[1]) : '';
                if ($rawLabel === '') {
                    $rawLabel = "tab-$count";
                }

                // slug for data-target/id: trim, spaces -> hyphen, collapse multiple hyphens
                $slug = strtolower(trim(preg_replace('/\s+/', '-', $rawLabel)));
                $slug = preg_replace('/-+/', '-', $slug);

                // button text: readable label (match your example: lowercase, remove spaces)
                $buttonText = strtolower(preg_replace('/\s+/', '', $rawLabel));

                // content stays exactly as-is
                $content = $t[2];

                // handle two code blocks inside a single tab
                $content = preg_replace_callback('/(```[\s\S]*?```)([\s\S]*?)(?=```[\s\S]*?```|$)(```[\s\S]*?```)?/',function ($matches) {
                    $before = $matches[1]; // first code block
                    $middle = $this->originalTransform($matches[2]); // <– modify this
                    $after  = $matches[3] ?? ''; // second code block
                    return $before . $middle . $after;
                }, $content);

                $buttons[] = '  <button class="' . $activeClass . '" type="button" data-target="' . htmlspecialchars($slug, ENT_QUOTES, 'UTF-8') . '">' .
                    htmlspecialchars($buttonText, ENT_QUOTES, 'UTF-8') . '</button>';

                $panels[]  = '<div class="' . $activeClass . '" data-id="' . htmlspecialchars($slug, ENT_QUOTES, 'UTF-8') . '">' . $content . '</div>';
            }

            // assemble html
            $html  = "<div class=\"tab-group\">\n";
            $html .= "<nav>\n" . implode("\n", $buttons) . "\n</nav>\n";
            $html .= implode("\n", $panels) . "\n";
            $html .= "</div>";

            return $html;
        }, $text);
    }

    /**
     * Processes code blocks with syntax highlighting.
     *
     * Converts fenced code blocks (```lang```) to HTML pre/code elements
     * with Prism.js classes for syntax highlighting. Supports:
     * - Optional language specifier
     * - Optional line highlighting ({1,3-5})
     * - Mermaid diagram rendering
     *
     * @param string $text The markdown text containing code blocks
     * @return string|null The text with code blocks converted to HTML
     */
    private function processSyntaxHighlighting(string $text): ?string
    {
        // Matches: ```lang{1,3-5}\nCODE\n```
        // - lang is optional
        // - {…} is optional and may be adjacent or spaced
        $pattern = '~^```(?P<lang>[a-zA-Z0-9_-]+)?(?:[ \t]*\{(?P<hl>[^\}]+)\})?\R(?P<code>[\s\S]*?)\R```~mu';

        return preg_replace_callback($pattern, function ($m) {
            static $mermaids = 0;

            $language     = $m['lang'] ?? '';
            $lineHighlight = isset($m['hl']) ? trim($m['hl']) : '';
            $code         = $m['code'];

            if ($language === 'mermaid') {
                $mermaids++;
                return '<pre class="mermaid-src fixed hidden invisible opacity-0" style="top:-100vh;left:-100vh;" data-mermaid-id="' . $mermaids . '">' . $code . '</pre>'
                    . '<pre><code class="mermaid" id="mermaid-' . $mermaids . '" style="color:transparent">' . trim(htmlspecialchars($code, ENT_NOQUOTES, 'UTF-8')) . '</code></pre>';
            }

            // Prism classes/attrs
            $langClass = $language ? 'language-' . $language : 'language-plaintext';
            $lineAttr  = $lineHighlight !== '' ? ' data-line="' . htmlspecialchars($lineHighlight, ENT_QUOTES, 'UTF-8') . '"' : '';
            $copyAttr  = ' data-prismjs-copy-timeout="1000"'; // optional: Prism copy-to-clipboard plugin
            $codeEsc   = htmlspecialchars($code, ENT_NOQUOTES, 'UTF-8');

            $codeEsc = trim($codeEsc, "\n");

            $codeEsc = str_replace("\n\n- ", "\n- ", $codeEsc);
            $codeEsc = str_replace("\n\n1. ", "\n1. ", $codeEsc);

            if (!$language) {
                return '<pre><code>' . $codeEsc . '</code></pre>';
            }
            if ($language == 'plain') {
                return '<pre class="w-fit"><code>' . $codeEsc . '</code></pre>';
            }
            return '<pre class="line-numbers"' . $lineAttr . $copyAttr . '>'
                . '<code class="' . $langClass . '">' . $codeEsc . '</code>'
                . '</pre>';
        }, $text);
    }

    /**
     * Processes tab content that needs markdown transformation.
     *
     * Applies markdown transformation to content inside tab divs that
     * were marked for processing. Used as a second pass after tab
     * structure is created.
     *
     * @param string $text The text containing tab content divs
     * @return string|null The text with tab content transformed
     */
    private function processTabContent(string $text): ?string
    {
        // Original regex (catastrophic backtracking on musl/Alpine):
        // '/(.*|\R?)(<div class="md-tab-content">\R?)((\w*|.|\R)+)\R?(<\/div><\/div>\R?)(.*|\R?)/mu'
        return preg_replace_callback(
            '/(.*|\R?)(<div class="md-tab-content">\R?)([\s\S]+?)\R?(<\/div><\/div>\R?)(.*|\R?)/mu',
            function ($matches) {
                return $matches[1] . $matches[2] . $this->originalTransform($matches[3]) . $matches[4] . $matches[5];
            },
            $text
        );
    }

    /**
     * Processes tab divs that don't contain code blocks.
     *
     * Applies markdown transformation to tabs that don't start with
     * code blocks. Code block tabs are preserved as-is to avoid double
     * processing.
     *
     * @param string $text The text containing tab divs
     * @return string|null The text with non-code tabs transformed
     */
    private function processNoCodeTabs(string $text): ?string
    {
        // Original regex (catastrophic backtracking on musl/Alpine):
        // '/((<div class="md-tab" (.*?)>\R)(?!<pre>)((\w*|.|\R)+)\R(<\/div>))/mu'
        return preg_replace_callback(
            '/((<div class="md-tab" (.*?)>\R)(?!<pre>)([\s\S]+?)\R(<\/div>))/mu',
            function ($matches) {
                return $matches[2] . $this->originalTransform($matches[4]) . $matches[5];
            },
            $text
        );
    }

    /**
     * Processes oEmbed links for embedded media content.
     *
     * Converts special syntax ([>](url)) to embedded media players for
     * supported services: YouTube, Vimeo, Loom, and Asciinema.
     * Unsupported URLs are converted to generic oembed tags.
     *
     * @param string $text The markdown text containing embed syntax
     * @return string|null The text with embeds converted to HTML
     */
    private function processOembed(string $text): ?string
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

    /**
     * Processes Asciinema recording embeds.
     *
     * Extracts the recording ID from the URL and generates an embed script
     * tag for Asciinema terminal recording playback.
     *
     * @param array $matches The regex match array from processOembed
     * @param string $url The Asciinema URL to embed
     * @return string|null The HTML for the embedded recording
     */
    private function processAsciinema(array $matches, string $url): ?string
    {
        preg_match_all('/a\/(.*?)(\?(.*?))?$/m', $url, $parts, PREG_SET_ORDER, 0);
        $id = $parts[0][1] ?? '';
        $qs = $parts[0][2] ?? '';
        return $matches[1] . '<div class="oembed-wrapper" style="overflow-y:hidden"><div style="margin-bottom: -16px"><script id="asciicast-' . $id . '" src="https://asciinema.org/a/' . $id . '.js' . $qs . '" async></script></div></div>' . $matches[4];
    }

    /**
     * Processes Vimeo video embeds.
     *
     * Converts Vimeo URLs to embedded iframe player with 16:9 aspect
     * ratio. Handles both standard and unlisted video URLs.
     *
     * @param array $matches The regex match array from processOembed
     * @param string $url The Vimeo URL to embed
     * @param string $iframe_options HTML attributes for the iframe
     * @return string|null The HTML for the embedded video
     */
    private function processVimeo(array $matches, string $url, string $iframe_options): ?string
    {
        $player = preg_replace('/https:\/\/vimeo.com\/(\d+)\/(\w+)(\??)(\&?)/m', 'https://player.vimeo.com/video/$1?h=$2&', $url);
        return $matches[1] . '<div class="oembed-wrapper"><div style="position:relative;height:0;padding-bottom:56.25%"><iframe ' . $iframe_options . ' style="position:absolute;top:0;left:0;width:100%;height:100%;border:0;overflow:hidden" src="' . $player . '"></iframe></div></div>' . $matches[4];
    }

    /**
     * Processes Loom video embeds.
     *
     * Converts Loom share URLs to embedded iframe player with 16:9
     * aspect ratio and hidden top bar for cleaner embedding.
     *
     * @param array $matches The regex match array from processOembed
     * @param string $url The Loom URL to embed
     * @param string $iframe_options HTML attributes for the iframe
     * @return string|null The HTML for the embedded video
     */
    private function processLoom(array $matches, string $url, string $iframe_options): ?string
    {
        return $matches[1] . '<div class="oembed-wrapper"><div style="position:relative;height:0;padding-bottom:56.25%"><iframe ' . $iframe_options . ' style="position:absolute;top:0;left:0;width:100%;height:100%;border:0;overflow:hidden" src="' . str_replace('share', 'embed', $url) . '?hideEmbedTopBar=true"></iframe></div></div>' . $matches[4];
    }

    /**
     * Processes YouTube video embeds.
     *
     * Converts YouTube URLs (both youtube.com and youtu.be) to embedded
     * iframe player with 16:9 aspect ratio. Extracts video ID from
     * various URL formats.
     *
     * @param array $matches The regex match array from processOembed
     * @param string $url The YouTube URL to embed
     * @param string $iframe_options HTML attributes for the iframe
     * @return string|null The HTML for the embedded video
     */
    private function processYoutube(array $matches, string $url, string $iframe_options): ?string
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

    /**
     * Processes page break syntax for printing.
     *
     * Converts tilde sequences (~~~) to HTML divs with print-page-break
     * class, useful for controlling page breaks in PDF exports.
     *
     * @param string $text The markdown text containing page break syntax
     * @return string|null The text with page breaks converted to HTML
     */
    private function processPageBreak(string $text): ?string
    {
        return preg_replace(
            '/(^|\R)(~+)($|\R)/u',
            '\n<div class="print-page-break"></div>\n',
            $text
        );
    }

    /**
     * Processes center alignment syntax.
     *
     * Converts custom syntax (->text<-) to HTML center tags for
     * centered content.
     *
     * @param string $text The markdown text containing center syntax
     * @return string|null The text with centered content wrapped in tags
     */
    private function processCenter(string $text): ?string
    {
        return preg_replace('/->(.*?)<-/', '<center>$1</center>', $text);
    }

    /**
     * Processes collapsible details/toggle syntax.
     *
     * Converts custom syntax ([[toggle Label]]...[[/toggle]]) to HTML
     * details/summary elements for collapsible content sections.
     *
     * @param string $text The markdown text containing toggle syntax
     * @return string|null The text with toggles converted to HTML
     */
    private function processDetails(string $text): ?string
    {
        // Original regex (catastrophic backtracking on musl/Alpine):
        // '/(\[\[toggle\s(.*?)\]\]\s?\R?((\w*|.|\R)+)\[\[\/toggle\]\])/mu'
        return preg_replace_callback(
            '/\[\[toggle\s(.*?)\]\]\s?\R?([\s\S]+?)\[\[\/toggle\]\]/mu',
            function ($matches) {
                return "<details>\n<summary>$matches[1]</summary><div>" . $this->originalTransform($matches[2]) . "</div></details>\n";
            },
            $text
        );
    }

    /**
     * Processes boxed content syntax.
     *
     * Converts custom syntax ([[boxed variant]]...[[/boxed]]) to HTML
     * divs with boxed styling. Optional variant adds additional classes.
     *
     * @param string $text The markdown text containing boxed syntax
     * @return string|null The text with boxes converted to HTML
     */
    private function processBoxes(string $text): ?string
    {
        // Original regex (catastrophic backtracking on musl/Alpine):
        // '/((\[\[boxed(\s?(.*?))?\]\]\s?\R?((\w*|.|\R)+)\[\[\/boxed\]\]))/mu'
        return preg_replace_callback(
            '/\[\[boxed(\s(.*?))?\]\]\s?\R?([\s\S]+?)\[\[\/boxed\]\]/mu',
            function ($matches) {
                return "<div class=\"boxed boxed-$matches[2]\">" . $this->originalTransform($matches[3]) . "</div>\n";
            },
            $text
        );
    }

    /**
     * Processes card component syntax.
     *
     * Converts custom syntax ([[card Title]]...[[/card]]) to HTML divs
     * with card styling. If preceded by a link, wraps the entire card
     * in an anchor tag.
     *
     * @param string $text The markdown text containing card syntax
     * @return string|null The text with cards converted to HTML
     */
    private function processCards(string $text): ?string
    {
        // Original regex (catastrophic backtracking on musl/Alpine):
        // '/(\[\[card(\s(.*?))?\]\]\s?\R?((\w*|.|\R)+)\[\[\/card\]\])/mu'
        $text = preg_replace_callback(
            '/\[\[card(\s(.*?))?\]\]\s?\R?([\s\S]+?)\[\[\/card\]\]/mu',
            function ($matches) {
                return "<div class=\"card\">\n" . ($matches[2] ? "<h4>$matches[2]</h4>" : '') . $this->originalTransform($matches[3]) . "</div>\n";
            },
            $text
        );
        if (!$text || !str_contains($text, '<div class="card">')) {
            return $text . '';
        }

        return preg_replace_callback(
            '/\((.*?)\)<div class="card">((\n|.)*?)<\/div>(\s+|$)/m',
            function ($matches) {
                return "<a class=\"card\" href=\"$matches[1]\">$matches[2]</a>\n\n";
            },
            $text
        );
    }

    /**
     * Processes step-by-step list syntax.
     *
     * Converts custom syntax ([[steps]]...[[/steps]]) with individual steps
     * ([[step Title]]...[[/step]]) into HTML ordered lists with step styling.
     * Steps are automatically numbered in the output.
     *
     * @param string $text The markdown text containing steps syntax
     * @return string|null The text with steps converted to HTML
     */
    private function processSteps(string $text): ?string
    {
        // Quick exit if no steps marker found
        if (strpos($text, '[[steps]]') === false) {
            return $text;
        }

        // replace each [[steps]] ... [[/steps]] block
        return preg_replace_callback('/\[\[steps\]\](.*?)\[\[\/steps\]\]/si', function ($m) {
            $block = $m[1];

            // find all [[step LABEL]] ... [[/step]] blocks
            if (!preg_match_all('/\[\[step(?:\s+([^\]]+))?\]\](.*?)\[\[\/step\]\]/si', $block, $steps, PREG_SET_ORDER)) {
                // no inner steps - return original block unchanged
                return $m[0];
            }

            $items = [];
            $count = 0;

            foreach ($steps as $s) {
                $count++;

                // title: everything after [[step ...]]
                $title = isset($s[1]) ? trim($s[1]) : "Step $count";

                // content is processed through originalTransform to convert markdown to HTML
                $content = trim($s[2]);

                // $content = sre
                $content = $this->originalTransform($content);

                // Apply emphasis markup outside of inline code spans only.
                $segments = preg_split(
                    '/(<code\b[^>]*>.*?<\/code>)/is',
                    $content,
                    -1,
                    PREG_SPLIT_DELIM_CAPTURE
                );
                if ($segments !== false) {
                    foreach ($segments as &$segment) {
                        if (preg_match('/^<code\b/i', $segment)) {
                            continue;
                        }
                        $segment = preg_replace('/\*\*(.*?)\*\*/s', '<strong>$1</strong>', $segment);
                        $segment = preg_replace('/\*|_(.*?)\*|_/s', '<em>$1</em>', $segment);
                    }
                    unset($segment);
                    $content = implode('', $segments);
                } else {
                    $content = preg_replace('/\*\*(.*?)\*\*/s', '<strong>$1</strong>', $content);
                    $content = preg_replace('/\*|_(.*?)\*|_/s', '<em>$1</em>', $content);
                }

                $items[] = '<li class="step">' . "\n" .
                          '  <h3 class="step-title">' . $title . '</h3>' . "\n" .
                          $content .
                          '</li>';
            }

            // assemble html
            $html  = '<ol class="steps">' . "\n";
            $html .= implode("\n", $items) . "\n";
            $html .= '</ol>';

            return $html;
        }, $text);
    }

    /**
     * Processes table of contents anchor generation for headers.
     *
     * Converts headers (##, ###, ####) to anchored header tags with IDs
     * derived from the header text. Creates linkable anchors for
     * documentation navigation. Special case: "up-next" headers are
     * not anchored.
     *
     * @param string $text The markdown text containing headers
     * @return string|null The text with headers converted to anchored HTML
     */
    private function processTOC(string $text): ?string
    {
        $pattern = '/\n(#{2,4})\s+(.*?)\n/m';
        $replacer = function ($matches) {
            if (preg_match('/<a\\b/i', $matches[2]) || preg_match('/\\[[^\\]]+\\]\\([^\\)]+\\)/', $matches[2])) {
                return "\n$matches[1] $matches[2]\n";
            }
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
        };

        // Avoid altering headings inside code/pre blocks.
        $parts = preg_split('/(<pre\\b[^>]*>.*?<\\/pre>|<code\\b[^>]*>.*?<\\/code>)/is', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        if ($parts === false || count($parts) === 1) {
            return preg_replace_callback($pattern, $replacer, $text);
        }

        foreach ($parts as $index => $part) {
            if (preg_match('/^<pre\\b/i', $part) || preg_match('/^<code\\b/i', $part)) {
                continue;
            }
            $replaced = preg_replace_callback($pattern, $replacer, $part);
            $parts[$index] = $replaced === null ? $part : $replaced;
        }

        return implode('', $parts);
    }

    /**
     * Processes callouts in markdown text.
     *
     * Converts markdown callout syntax (e.g., `> [!info] This is an info callout`)
     * to HTML callout elements with appropriate classes.
     *
     * @param string $text The markdown text containing callouts
     * @return string|null The text with callouts converted to HTML, or null if input is invalid
     */
    private function processCallouts(string $text): ?string
    {
        $pattern = '/^[ \t]*>\s*\[\!(\w+)\][^\r\n]*\R((?:^[ \t]*>.*(?:\R|$))+)/miu';

        return preg_replace_callback($pattern, function ($m) {
            $type = strtolower($m[1]);

            // Optional: normalize to a known set and map aliases as needed
            $aliases = [
                'info' => 'info',
                'tip' => 'tip',
                'important' => 'important',
                'warning' => 'warning',
                'caution' => 'warning',
                'special' => 'special',
                'note' => 'note',
                'danger' => 'danger',
            ];
            $type = $aliases[$type] ?? 'note';

            // Remove the leading `>` from body lines
            $inner = preg_replace('/^[ \t]*>(.*)/m', '$1', $m[2]);

            $inner = preg_replace('/\n\s+?- /m', "\n- ", $inner);
            $inner = str_replace(":\n- ", ":\n\n- ", $inner);
            $inner = str_replace(":**\n- ", ":**\n\n- ", $inner);
            $inner = trim($this->originalTransform($inner), "\r\n");

            // replace newlines with double newlines
            // $inner = preg_replace('/\R+/', "\n\n", $inner);

            return "\n\n" .'<!-- callout class="callout callout-' . $type . '" -->' . "\n\n"
                . $inner . "\n\n<!-- /callout -->\n\n";
        }, $text);
    }

    /**
     * Converts markdown tables to HTML table elements.
     *
     * Processes markdown table syntax (pipe-delimited rows with separator
     * line) and converts them to properly formatted HTML tables. Supports:
     * - Multiple columns with header rows
     * - Column alignment via separator row syntax (:---, ---:, :---:)
     * - Wrapping tables in div.table-wrapper for styling
     *
     * Uses a line-by-line scanning approach to identify table boundaries
     * and preserve non-table content. Processes tables inline without
     * affecting surrounding markdown content.
     *
     * @param string $text The markdown text containing potential tables
     * @return string|null The text with markdown tables converted to HTML,
     *                     or null if input is invalid
     */
    private function processTables(string $text): ?string
    {
        // Quick bail-out: look for a header line and a separator line nearby
        // This is a cheap check before splitting or scanning to avoid
        // unnecessary processing if no tables are present.
        // Pattern matches: optional whitespace + pipe + content + newline +
        // whitespace + pipe + separator dashes (at least 3)
        if (!preg_match('/^\s*\|.*\R\s*\|[ :\-]{3,}/mu', $text)) {
            return $text;
        }

        // Split text into individual lines for processing
        // \R matches any Unicode newline sequence (CR, LF, CRLF, etc.)
        $lines = preg_split('/\R/u', $text);
        // Output array to collect processed lines and HTML tables
        $out   = [];
        // Current line index during scanning
        $i     = 0;
        // Total number of lines to process
        $n     = count($lines);

        // Helper closures kept inside to satisfy "single function" requirement
        /**
         * Splits a table row line into individual cell values.
         *
         * Helper closure that processes a single row line, removing
         * leading/trailing pipes and splitting on pipe delimiters.
         * Handles optional whitespace around pipes.
         *
         * @param string $row The row line to split (e.g., "| col1 | col2 |")
         * @return array Array of trimmed cell values
         */
        $splitRow = static function (string $row): array {
            // Remove leading/trailing whitespace from entire row
            $row = trim($row);
            // Remove leading pipe if present (markdown tables start with |)
            $row = preg_replace('/^\|/', '', $row); // strip one leading |
            // Remove trailing pipe if present (markdown tables end with |)
            $row = preg_replace('/\|$/', '', $row); // strip one trailing |
            // Return empty array if row is empty after processing
            if ($row === '') return [];
            // Split on pipe character with optional surrounding whitespace
            // This handles variations like "|col1|col2|" or "| col1 | col2 |"
            $cells = preg_split('/\s*\|\s*/', $row);
            // Trim whitespace from each cell value to normalize content
            return array_map('trim', $cells);
        };

        /**
         * Checks if a line is a valid markdown table row.
         *
         * Validates that the line starts with optional whitespace
         * followed by a pipe character, indicating it's part of a table.
         * The line must contain at least one pipe delimiter.
         *
         * @param string $line The line to check
         * @return bool True if line appears to be a table row
         */
        $isRow = static function (string $line): bool {
            // Pattern matches lines that start with optional spaces + '|'
            // and have at least one more pipe or content
            // Pattern: optional whitespace, pipe, any content, optional pipe,
            // optional whitespace
            return (bool)preg_match('/^\s*\|.*\|?\s*$/', $line);
        };

        /**
         * Checks if a line is a markdown table separator row.
         *
         * Validates that the line contains dashes with optional colons
         * indicating column alignment. Valid formats:
         * - '---' (default alignment)
         * - ':---' (left alignment)
         * - '---:' (right alignment)
         * - ':---:' (center alignment)
         *
         * @param string $line The line to check
         * @return bool True if line is a valid separator row
         */
        $isSep = static function (string $line) use ($splitRow): bool {
            // Separator must be made of dashes with optional colons
            // Remove trailing pipe and whitespace for parsing
            $line = rtrim(trim($line), '|');
            // Add pipe back for consistent parsing with splitRow helper
            $cells = $splitRow($line . '|'); // reuse splitter
            // Return false if no cells found (invalid separator)
            if (!$cells) return false;
            // Check each cell contains only dashes and optional colons
            // Pattern: optional colon at start, 3+ dashes, optional colon at end
            foreach ($cells as $c) {
                if (!preg_match('/^:?-{3,}:?$/', trim($c))) {
                    return false;
                }
            }
            return true;
        };

        /**
         * Parses alignment values from separator row cells.
         *
         * Converts separator cell syntax to CSS alignment values.
         * Mapping:
         * - ':---:' or ':--:' -> 'center'
         * - '---:' or '--:' -> 'right'
         * - ':---' or ':--' -> 'left'
         * - '---' or '--' -> null (default)
         *
         * @param array $sepCells Array of separator cell strings
         * @return array Array of alignment values ('left', 'right',
         *               'center', or null)
         */
        $parseAlign = static function (array $sepCells): array {
            $out = [];
            foreach ($sepCells as $cell) {
                // Trim whitespace from cell before checking colon positions
                $cell = trim($cell);
                // Check for left alignment colon (colon at start)
                $left  = ($cell !== '' && $cell[0] === ':');
                // Check for right alignment colon (colon at end)
                $right = ($cell !== '' && substr($cell, -1) === ':');
                // Determine alignment based on colon positions
                // Both colons present = center alignment
                if ($left && $right) $out[] = 'center';
                // Only right colon = right alignment
                elseif ($right)      $out[] = 'right';
                // Only left colon = left alignment
                elseif ($left)       $out[] = 'left';
                // No colons = default alignment (null, browser default)
                else                 $out[] = null;
            }
            return $out;
        };

        // Main processing loop: scan lines to find and convert tables
        // Iterate through all lines, identifying table blocks and converting
        // them to HTML while preserving non-table content
        while ($i < $n) {
            // Check if current line is a table row AND next line is a separator
            // If conditions not met, copy line as-is and continue to next line
            // This preserves all non-table content in the original format
            if (! $isRow($lines[$i]) || $i + 1 >= $n || ! $isSep($lines[$i + 1])) {
                $out[] = $lines[$i++];
                continue;
            }

            // We have a valid table: collect header, separator, and data rows
            // Extract header row (first row of table, contains column names)
            $headerLine = $lines[$i++];
            // Extract separator row (defines column alignment via dashes/colons)
            $sepLine    = $lines[$i++];

            // Collect all data rows following the separator
            // Continue collecting while remaining lines are valid table rows
            // Stops when encountering a non-table line or end of input
            $rowLines = [];
            while ($i < $n && $isRow($lines[$i])) {
                $rowLines[] = $lines[$i++];
            }

            // Parse table structure
            // Split header row into individual column headers
            $headers = $splitRow($headerLine);
            // Parse alignment from separator row cells
            $aligns  = $parseAlign($splitRow($sepLine));
            // Ensure alignment array matches header count
            // Pad with null if separator has fewer cells than headers
            $aligns  = array_pad($aligns, count($headers), null);

            // Build HTML table structure
            // Start table HTML with wrapper div for styling and table element
            $html = "<div class=\"table-wrapper\"><table class=\"table\">\n<thead>\n<tr>";
            // Generate header cells with alignment attributes
            foreach ($headers as $idx => $h) {
                // Add text-align style attribute if alignment is specified
                // If alignment is null, no style attribute is added (browser default)
                $attr = $aligns[$idx] ? ' style="text-align:' . $aligns[$idx] . '"' : '';
                // Escape header text to prevent XSS and wrap in <th> tag
                $h = $this->originalTransform(htmlspecialchars($h, ENT_QUOTES, 'UTF-8'));
                $h = preg_replace('/<p>(.*?)<\/p>/i', '$1', $h);
                $html .= "<th{$attr}>" . $h . "</th>";
            }
            // Close header row and start table body section
            $html .= "</tr>\n</thead>\n<tbody>\n";

            // Process each data row in the table
            foreach ($rowLines as $row) {
                // Split row into individual cells
                $cols = $splitRow($row);
                // Ensure cell count matches header count
                // Pad with empty strings if row has fewer cells than headers
                $cols = array_pad($cols, count($headers), '');
                // Truncate if row has more cells than headers
                // Extra cells are ignored to maintain table structure
                $cols = array_slice($cols, 0, count($headers));

                // Start new table row
                $html .= "<tr>";
                // Generate data cells with alignment attributes
                foreach ($cols as $idx => $c) {
                    // Add text-align style attribute if alignment is specified
                    // Uses same alignment as corresponding header column
                    $attr = $aligns[$idx] ? ' style="text-align:' . $aligns[$idx] . '"' : '';
                    // Escape cell text to prevent XSS and wrap in <td> tag
                    $c = $this->originalTransform(htmlspecialchars($c, ENT_QUOTES, 'UTF-8'));
                    $c = preg_replace('/<p>(.*?)<\/p>/i', '$1', $c);
                    $html .= "<td{$attr}>" . $c . "</td>";
                }
                // Close table row
                $html .= "</tr>\n";
            }
            // Close table body, table element, and wrapper div
            $html .= "</tbody>\n</table></div>";

            // Add completed table HTML to output array
            $out[] = $html;
        }

        // Join all processed lines and tables back into single string
        // Newlines are preserved to maintain document structure
        return implode("\n", $out);
    }

    /**
     * Cleans up HTML output after markdown processing.
     *
     * Fixes common HTML issues introduced during markdown processing:
     * - Processes inline code backticks
     * - Converts callout comment markers to div elements
     * - Removes unwanted paragraph tags around block elements
     * - Removes empty paragraph tags
     *
     * @param string $text The HTML text that needs cleanup
     * @return string|null The cleaned HTML text
     */
    private function cleanupHtml(string $text): ?string
    {
        // Fix inline code
        $text = preg_replace('/`(.*?)`/m', '<code>$1</code>', $text);

        // Fix callouts
        $text = preg_replace('/<!-- callout class="callout callout-(.*?)" -->/m', '<div class="callout callout-$1">', $text);
        $text = preg_replace('/<!-- \/callout -->/m', '</div>', $text);

        // Remove empty tags like <p></p> or <h2></h2>.
        $text = preg_replace('/<([a-z][a-z0-9]*)\b[^>]*>(\s+?)<\/\1>/i', '', $text);
        $text = preg_replace('/<([a-z][a-z0-9]*)\b[^>]*><\/div><\/\1>/i', '</div>', $text);
        $text = preg_replace('/<li><p>(.*)<\/p><\/li>/i', '<li>$1</li>', $text);

        $text = str_replace('</p><br>', '</p>', $text);

        // Remove <p> tags around card elements
        $text = str_replace('<p><a class="card"', '<a class="card"', $text);
        $text = str_replace('<p></a></p>', '</a>', $text);
        $text = preg_replace('/<a class="card" href="(.*?)"><\/p>/', '<a class="card" href="$1">', $text);

        return $text;

        // $replacements = [
        //     '<p><oembed' => '<oembed',
        //     '</oembed></p>' => '</oembed>',
        //     '<p><div' => '<div',
        //     '</div></p>' => '</div>',
        //     '<p><details>' => '<details>',
        //     '<p><summary>' => '<summary>',
        //     '</summary></p>' => '</summary>',
        //     '<p></details></p>' => '</details>',
        //     '</details></p>' => '</details>',
        // ];

        // return str_replace(array_keys($replacements), array_values($replacements), $text);
    }

    /**
     * Auto-links plain URLs and email addresses.
     *
     * Converts plain text URLs (http://, https://, www.) and email addresses
     * to clickable links. Protects URLs in backtick code spans and <code> tags
     * by temporarily replacing them with placeholders before processing.
     *
     * @param string $text The HTML text to process
     * @return string|null The text with auto-linked URLs and emails
     */
    private function processURLs(string $text): ?string
    {
        // Step 1: Protect code spans (backticks and <code> tags) by replacing with placeholders
        $codeSpans = [];
        $codeIndex = 0;

        // Protect backtick code spans first
        $text = preg_replace_callback(
            '/`([^`]+)`/',
            function($matches) use (&$codeSpans, &$codeIndex) {
                $placeholder = "___CODE_SPAN_{$codeIndex}___";
                $codeSpans[$placeholder] = $matches[0]; // Store full match with backticks
                $codeIndex++;
                return $placeholder;
            },
            $text
        );

        // Protect existing <code> tags
        $text = preg_replace_callback(
            '/<code[^>]*>.*?<\/code>/is',
            function($matches) use (&$codeSpans, &$codeIndex) {
                $placeholder = "___CODE_SPAN_{$codeIndex}___";
                $codeSpans[$placeholder] = $matches[0];
                $codeIndex++;
                return $placeholder;
            },
            $text
        );

        // Step 2: Auto-link emails that aren't already linked
        $text = preg_replace_callback(
            '/(?<!href=")(?<!>)\b([a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,})\b(?![^<]*<\/a>)/i',
            function($matches) {
                $email = $matches[1];
                return '<a href="mailto:' . htmlspecialchars($email, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($email, ENT_QUOTES, 'UTF-8') . '</a>';
            },
            $text
        );

        // Step 3: Auto-link URLs (http://, https://)
        $text = preg_replace_callback(
            '/(?<!href=")(?<!src=")(?<!">)\b(https?:\/\/[^\s<>"]+)(?![^<]*<\/a>)/i',
            function($matches) {
                $url = $matches[1];
                // Remove trailing punctuation that's likely not part of the URL
                $url = rtrim($url, '.,;:!?)');
                return '<a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '</a>';
            },
            $text
        );

        // Step 4: Auto-link www. URLs (without protocol)
        $text = preg_replace_callback(
            '/(?<!href=")(?<!">)(?<![a-zA-Z0-9])\b(www\.[^\s<>"]+)(?![^<]*<\/a>)/i',
            function($matches) {
                $url = $matches[1];
                // Remove trailing punctuation
                $url = rtrim($url, '.,;:!?)');
                return '<a href="http://' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '</a>';
            },
            $text
        );

        // Step 5: Restore protected code spans
        foreach ($codeSpans as $placeholder => $original) {
            $text = str_replace((string)$placeholder, $original, $text);
        }

        return $text;
    }

    /**
     * Processes bookmark syntax with icons.
     *
     * Converts custom bookmark syntax ([+] link) to links with a
     * bookmark icon. Useful for highlighting saved/bookmarked items in
     * documentation.
     *
     * @param string $text The HTML text containing bookmark links
     * @return string|null The text with bookmark icons added
     */
    private function processBookmarks(string $text): ?string
    {
        $re = '/\[\+\] <a href="(.*)">(.*)<\/a>(.*?)\n/m';
        $subst = '<svg viewBox="0 0 384 512" xmlns="http://www.w3.org/2000/svg" style="margin:-2px 6px 0 0; width:14px; height:14px; display:inline"><path fill="currentColor" d="m0 487.7v-439.7c0-26.5 21.5-48 48-48h48v322.1c0 12.8 14.2 20.4 24.9 13.3l71.1-47.4 71.1 47.4c10.6 7.1 24.9-.5 24.9-13.3v-322.1h48c26.5 0 48 21.5 48 48v439.7c0 13.4-10.9 24.3-24.3 24.3-5 0-9.9-1.5-14-4.4l-153.7-107.6-153.7 107.6c-4.1 2.9-9 4.4-14 4.4-13.4 0-24.3-10.9-24.3-24.3z"/><path fill="currentColor" d="m192 288-71.1 47.4c-10.6 7.1-24.9-.5-24.9-13.3v-322.1h192v322.1c0 12.8-14.2 20.4-24.9 13.3z" opacity=".4"/></svg><a href="$1">$2</a>$3<br>';
        return preg_replace($re, $subst, $text);
    }

    /**
     * Processes buttons syntax.
     *
     * Converts custom bookmark syntax ([>] link / [>>] link) to links
     * button class.
     *
     * @param string $text The HTML text containing bookmark links
     * @return string|null The text with bookmark icons added
     */
    private function processButtons(string $text): ?string
    {
        $re = '/\[\>\] <a href="(.*)">(.*)<\/a>(.*?)\n/m';
        $subst = '<a href="$1" class="btn">$2</a>$3<br>';
        $text = preg_replace($re, $subst, $text);

        $re = '/\[\>\>\] <a href="(.*)">(.*)<\/a>(.*?)\n/m';
        $subst = '<a href="$1" class="btn-secondary">$2</a>$3<br>';
        $text = preg_replace($re, $subst, $text);

        $re = '/\[\[\>\]\] <a href="(.*)">(.*)<\/a>(.*?)\n/m';
        $subst = '<a href="$1" class="btn-outline">$2</a>$3<br>';
        $text = preg_replace($re, $subst, $text);

        return $text;
    }

    /**
     * Static helper method for transforming markdown to HTML.
     *
     * Creates or reuses a parser instance and transforms the text.
     * This method maintains a static parser list to avoid recreating
     * instances. Supports derived classes via late static binding.
     *
     * Note: This appears to be legacy API. Current codebase uses the
     * helper registration pattern instead.
     *
     * @param string $text The markdown text to transform
     * @return string The transformed HTML output
     */
    public static function defaultTransform($text)
    {
        # Initialize the parser and return the result of its transform method.
        # This will work fine for derived classes too.

        # Take parser class on which this function was called.
        $parser_class = \get_called_class();

        # Try to take parser from the static parser list
        static $parser_list;
        $parser = &$parser_list[$parser_class];

        # Create the parser if not already set
        if (!$parser) {
            $parser = new $parser_class();
        }

        # Transform text using parser.
        return $parser->transform($text . '');
    }

    ### Configuration Variables ###

    # Change to ">" for HTML output.
    public $empty_element_suffix = " />";
    public $tab_width = 4;

    # Change to `true` to disallow markup or entities.
    public $no_markup = false;
    public $no_entities = false;

    # Predefined urls and titles for reference links and images.
    public $predef_urls = array();
    public $predef_titles = array();

    # Optional filter function for URLs
    public $url_filter_func = null;

    # Optional header id="" generation callback function.
    public $header_id_func = null;

    # Optional function for converting code block content to HTML
    public $code_block_content_func = null;

    # Class attribute to toggle "enhanced ordered list" behaviour
    # setting this to true will allow ordered lists to start from the index
    # number that is defined first.  For example:
    # 2. List item two
    # 3. List item three
    #
    # becomes
    # <ol start="2">
    # <li>List item two</li>
    # <li>List item three</li>
    # </ol>
    public $enhanced_ordered_list = false;

    ### Parser Implementation ###

    # Regex to match balanced [brackets].
    # Needed to insert a maximum bracked depth while converting to PHP.
    protected $nested_brackets_depth = 6;
    protected $nested_brackets_re;

    protected $nested_url_parenthesis_depth = 4;
    protected $nested_url_parenthesis_re;

    # Table of hash values for escaped characters:
    protected $escape_chars = '\`*_{}[]()>#+-.!';
    protected $escape_chars_re;

    /**
     * Constructs the Markdown parser and initializes variables.
     *
     * Initializes regex patterns for nested brackets and parentheses,
     * prepares italics/bold patterns, sets up escape character regex,
     * and sorts processing gamuts by priority.
     */
    public function __construct()
    {
        # Constructor function. Initialize appropriate member variables.

        $this->_initDetab();
        $this->prepareItalicsAndBold();

        $this->nested_brackets_re =
            str_repeat('(?>[^\[\]]+|\[', $this->nested_brackets_depth) .
            str_repeat('\])*', $this->nested_brackets_depth);

        $this->nested_url_parenthesis_re =
            str_repeat('(?>[^()\s]+|\(', $this->nested_url_parenthesis_depth) .
            str_repeat('(?>\)))*', $this->nested_url_parenthesis_depth);

        $this->escape_chars_re = '[' . preg_quote($this->escape_chars) . ']';

        # Sort document, block, and span gamut in ascendent priority order.
        asort($this->document_gamut);
        asort($this->block_gamut);
        asort($this->span_gamut);
    }

    # Internal hashes used during transformation.
    protected $urls = array();
    protected $titles = array();
    protected $html_hashes = array();

    # Status flag to avoid invalid nesting.
    protected $in_anchor = false;

    /**
     * Initializes parser state before transformation.
     *
     * Called at the start of each transformation to reset internal
     * state. Clears URL and title hashes, initializes with predefined
     * values, and resets anchor nesting flag.
     */
    protected function setup()
    {
        # Called before the transformation process starts to setup parser
        # states.

        # Clear global hashes.
        $this->urls = $this->predef_urls;
        $this->titles = $this->predef_titles;
        $this->html_hashes = array();

        $this->in_anchor = false;
    }

    /**
     * Cleans up parser state after transformation.
     *
     * Called after transformation completes to free memory. Clears
     * internal URL, title, and HTML hash arrays.
     */
    protected function teardown()
    {
        # Called after the transformation process to clear any variable
        # which may be taking up memory unnecessarly.

        $this->urls = array();
        $this->titles = array();
        $this->html_hashes = array();
    }

    /**
     * Processes standard markdown syntax (original markdown spec).
     *
     * Handles the core markdown-to-HTML transformation including:
     * - Text normalization (BOM removal, line ending standardization)
     * - Tab-to-space conversion
     * - HTML block hashing
     * - Link definitions stripping
     * - Block-level elements (headers, lists, code blocks, etc.)
     *
     * This is called after custom syntax processing in transform().
     *
     * @param string $text The markdown text to process
     * @return string|null The HTML output
     */
    public function originalTransform(string $text): ?string
    {
        # Main function. Performs some preprocessing on the input text
        # and pass it through the document gamut.

        $this->setup();

        $text = trim($text);

        # Remove UTF-8 BOM and marker character in input, if present.
        $text = preg_replace('{^\xEF\xBB\xBF|\x1A}', '', $text);

        # Standardize line endings:
        #   DOS to Unix and Mac to Unix
        $text = preg_replace('{\r\n?}', "\n", $text);

        # Make sure $text ends with a couple of newlines:
        $text .= "\n\n";

        // Protect pre blocks so Markdown doesn't alter their contents.
        $text = preg_replace_callback(
            '/<pre\b[^>]*>[\s\S]*?<\/pre>/i',
            function ($matches) {
                return $this->hashBlock($matches[0]);
            },
            $text
        );

        # Convert all tabs to spaces.
        $text = $this->detab($text);

        # Turn block-level HTML blocks into hash entries
        $text = $this->hashHTMLBlocks($text);

        // Protect inline code spans so emphasis doesn't touch their contents.
        $text = $this->hashInlineCodeSpans($text);

        # Strip any lines consisting only of spaces and tabs.
        # This makes subsequent regexen easier to write, because we can
        # match consecutive blank lines with /\n+/ instead of something
        # contorted like /[ ]*\n+/ .
        $text = preg_replace('/^[ ]+$/m', '', $text);

        # Run document gamut methods.
        foreach ($this->document_gamut as $method => $priority) {
            $text = $this->$method($text);
        }

        $this->teardown();

        return $text . "\n";
    }

    protected $document_gamut = array(
        # Strip link definitions, store in hashes.
        "stripLinkDefinitions" => 20,
        "runBasicBlockGamut" => 30,
    );

    protected function stripLinkDefinitions($text)
    {
        #
        # Strips link definitions from text, stores the URLs and titles in
        # hash references.
        #
        $less_than_tab = $this->tab_width - 1;

        # Link defs are in the form: ^[id]: url "optional title"
        $text = preg_replace_callback(
            '{
                            ^[ ]{0,' . $less_than_tab . '}\[(.+)\][ ]?:	# id = $1
                              [ ]*
                              \n?				# maybe *one* newline
                              [ ]*
                            (?:
                              <(.+?)>			# url = $2
                            |
                              (\S+?)			# url = $3
                            )
                              [ ]*
                              \n?				# maybe one newline
                              [ ]*
                            (?:
                                (?<=\s)			# lookbehind for whitespace
                                ["(]
                                (.*?)			# title = $4
                                [")]
                                [ ]*
                            )?	# title is optional
                            (?:\n+|\Z)
            }xm',
            array($this, '_stripLinkDefinitions_callback'),
            $text
        );

        return $text;
    }

    private function hashInlineCodeSpans(string $text): string
    {
        return preg_replace_callback(
            '/(?<!\\\\)(`+)(.+?)\\1(?!`)/s',
            function ($matches) {
                return $this->makeCodeSpan($matches[2]);
            },
            $text
        );
    }

    protected function _stripLinkDefinitions_callback($matches)
    {
        $link_id = strtolower($matches[1]);
        $url = $matches[2] == '' ? $matches[3] : $matches[2];
        $this->urls[$link_id] = $url;
        $this->titles[$link_id] = &$matches[4];

        return ''; # String that will replace the block
    }

    protected function hashHTMLBlocks($text)
    {
        if ($this->no_markup) {
            return $text;
        }

        $less_than_tab = $this->tab_width - 1;

        # Hashify HTML blocks:
        # We only want to do this for block-level HTML tags, such as headers,
        # lists, and tables. That's because we still want to wrap <p>s around
        # "paragraphs" that are wrapped in non-block-level tags, such as anchors,
        # phrase emphasis, and spans. The list of tags we're looking for is
        # hard-coded:
        #
        # *  List "a" is made of tags which can be both inline or block-level.
        #    These will be treated block-level when the start tag is alone on
        #    its line, otherwise they're not matched here and will be taken as
        #    inline later.
        # *  List "b" is made of tags which are always block-level;
        #
        $block_tags_a_re = 'ins|del';
        $block_tags_b_re = 'p|div|h[1-6]|blockquote|pre|table|dl|ol|ul|address|' .
            'script|noscript|style|form|fieldset|iframe|math|svg|' .
            'article|section|nav|aside|hgroup|header|footer|' .
            'figure';

        # Regular expression for the content of a block tag.
        $nested_tags_level = 4;
        $attr = '
            (?>				# optional tag attributes
              \s			# starts with whitespace
              (?>
                [^>"/]+		# text outside quotes
              |
                /+(?!>)		# slash not followed by ">"
              |
                "[^"]*"		# text inside double quotes (tolerate ">")
              |
                \'[^\']*\'	# text inside single quotes (tolerate ">")
              )*
            )?
            ';
        $content =
            str_repeat('
                (?>
                  [^<]+			# content without tag
                |
                  <\2			# nested opening tag
                    ' . $attr . '	# attributes
                    (?>
                      />
                    |
                      >', $nested_tags_level) .    # end of opening tag
            '.*?' .                    # last level nested tag content
            str_repeat(
                '
                      </\2\s*>	# closing nested tag
                    )
                  |
                    <(?!/\2\s*>	# other tags with a different name
                  )
                )*',
                $nested_tags_level
            );
        $content2 = str_replace('\2', '\3', $content);

        # First, look for nested blocks, e.g.:
        # 	<div>
        # 		<div>
        # 		tags for inner block must be indented.
        # 		</div>
        # 	</div>
        #
        # The outermost tags must start at the left margin for this to match, and
        # the inner nested divs must be indented.
        # We need to do this before the next, more liberal match, because the next
        # match will start at the first `<div>` and stop at the first `</div>`.
        $text = preg_replace_callback(
            '{(?>
            (?>
                (?<=\n)			# Starting on its own line
                |				# or
                \A\n?			# the at beginning of the doc
            )
            (						# save in $1

              # Match from `\n<tag>` to `</tag>\n`, handling nested tags
              # in between.

                        [ ]{0,' . $less_than_tab . '}
                        <(' . $block_tags_b_re . ')# start tag = $2
                        ' . $attr . '>			# attributes followed by > and \n
                        ' . $content . '		# content, support nesting
                        </\2>				# the matching end tag
                        [ ]*				# trailing spaces/tabs
                        (?=\n+|\Z)	# followed by a newline or end of document

            | # Special version for tags of group a.

                        [ ]{0,' . $less_than_tab . '}
                        <(' . $block_tags_a_re . ')# start tag = $3
                        ' . $attr . '>[ ]*\n	# attributes followed by >
                        ' . $content2 . '		# content, support nesting
                        </\3>				# the matching end tag
                        [ ]*				# trailing spaces/tabs
                        (?=\n+|\Z)	# followed by a newline or end of document

            | # Special case just for <hr />. It was easier to make a special
              # case than to make the other regex more complicated.

                        [ ]{0,' . $less_than_tab . '}
                        <(hr)				# start tag = $2
                        ' . $attr . '			# attributes
                        /?>					# the matching end tag
                        [ ]*
                        (?=\n{2,}|\Z)		# followed by a blank line or end of document

            | # Special case for standalone HTML comments:

                    [ ]{0,' . $less_than_tab . '}
                    (?s:
                        <!-- .*? -->
                    )
                    [ ]*
                    (?=\n{2,}|\Z)		# followed by a blank line or end of document

            | # PHP and ASP-style processor instructions (<? and <%)

                    [ ]{0,' . $less_than_tab . '}
                    (?s:
                        <([?%])			# $2
                        .*?
                        \2>
                    )
                    [ ]*
                    (?=\n{2,}|\Z)		# followed by a blank line or end of document

            )
            )}Sxmi',
            array($this, '_hashHTMLBlocks_callback'),
            $text
        );

        return $text;
    }

    protected function _hashHTMLBlocks_callback($matches)
    {
        $text = $matches[1];
        $key = $this->hashBlock($text);

        return "\n\n$key\n\n";
    }

    protected function hashPart($text, $boundary = 'X')
    {
        #
        # Called whenever a tag must be hashed when a function insert an atomic
        # element in the text stream. Passing $text to through this function gives
        # a unique text-token which will be reverted back when calling unhash.
        #
        # The $boundary argument specify what character should be used to surround
        # the token. By convension, "B" is used for block elements that needs not
        # to be wrapped into paragraph tags at the end, ":" is used for elements
        # that are word separators and "X" is used in the general case.
        #
        # Swap back any tag hash found in $text so we do not have to `unhash`
        # multiple times at the end.
        $text = $this->unhash($text);

        # Then hash the block.
        static $i = 0;
        $key = "$boundary\x1A" . ++$i . $boundary;
        $this->html_hashes[$key] = $text;

        return $key; # String that will replace the tag.
    }

    protected function hashBlock($text)
    {
        #
        # Shortcut function for hashPart with block-level boundaries.
        #
        return $this->hashPart($text, 'B');
    }

    protected $block_gamut = array(
        #
        # These are all the transformations that form block-level
        # tags like paragraphs, headers, and list items.
        #
        "doHeaders" => 10,
        "doHorizontalRules" => 20,

        "doLists" => 40,
        "doCodeBlocks" => 50,
        "doBlockQuotes" => 60,
    );

    protected function runBlockGamut($text)
    {
        #
        # Run block gamut tranformations.
        #
        # We need to escape raw HTML in Markdown source before doing anything
        # else. This need to be done for each block, and not only at the
        # begining in the Markdown function since hashed blocks can be part of
        # list items and could have been indented. Indented blocks would have
        # been seen as a code block in a previous pass of hashHTMLBlocks.
        $text = $this->hashHTMLBlocks($text);

        return $this->runBasicBlockGamut($text);
    }

    protected function runBasicBlockGamut($text)
    {
        #
        # Run block gamut tranformations, without hashing HTML blocks. This is
        # useful when HTML blocks are known to be already hashed, like in the first
        # whole-document pass.
        #
        foreach ($this->block_gamut as $method => $priority) {
            $text = $this->$method($text);
        }

        # Finally form paragraph and restore hashed blocks.
        $text = $this->formParagraphs($text);

        return $text;
    }

    protected function doHorizontalRules($text)
    {
        # Do Horizontal Rules:
        return preg_replace(
            '{
                ^[ ]{0,3}	# Leading space
                ([-*_])		# $1: First marker
                (?>			# Repeated marker group
                    [ ]{0,2}	# Zero, one, or two spaces.
                    \1			# Marker character
                ){2,}		# Group repeated at least twice
                [ ]*		# Tailing spaces
                $			# End of line.
            }mx',
            "\n" . $this->hashBlock("<hr$this->empty_element_suffix") . "\n",
            $text
        );
    }

    protected $span_gamut = array(
        #
        # These are all the transformations that occur *within* block-level
        # tags like paragraphs, headers, and list items.
        #
        # Process character escapes, code spans, and inline HTML
        # in one shot.
        "parseSpan" => -30,

        # Process anchor and image tags. Images must come first,
        # because ![foo][f] looks like an anchor.
        "doImages" => 10,
        "doAnchors" => 20,

        # Make links out of things like `<https://example.com/>`
        # Must come after doAnchors, because you can use < and >
        # delimiters in inline links like [this](<url>).
        "doAutoLinks" => 30,
        "encodeAmpsAndAngles" => 40,

        "doItalicsAndBold" => 50,
        "doHardBreaks" => 60,
    );

    protected function runSpanGamut($text)
    {
        #
        # Run span gamut tranformations.
        #
        foreach ($this->span_gamut as $method => $priority) {
            $text = $this->$method($text);
        }

        return $text;
    }

    protected function doHardBreaks($text)
    {
        # Do hard breaks:
        return preg_replace_callback(
            '/ {2,}\n/',
            array($this, '_doHardBreaks_callback'),
            $text
        );
    }

    protected function _doHardBreaks_callback($matches)
    {
        return $this->hashPart("<br$this->empty_element_suffix\n");
    }

    protected function doAnchors($text)
    {
        #
        # Turn Markdown link shortcuts into XHTML <a> tags.
        #
        if ($this->in_anchor) {
            return $text;
        }
        $this->in_anchor = true;

        #
        # First, handle reference-style links: [link text] [id]
        #
        $text = preg_replace_callback(
            '{
            (					# wrap whole match in $1
              \[
                (' . $this->nested_brackets_re . ')	# link text = $2
              \]

              [ ]?				# one optional space
              (?:\n[ ]*)?		# one optional newline followed by spaces

              \[
                (.*?)		# id = $3
              \]
            )
            }xs',
            array($this, '_doAnchors_reference_callback'),
            $text
        );

        #
        # Next, inline-style links: [link text](url "optional title")
        #
        $text = preg_replace_callback(
            '{
            (				# wrap whole match in $1
              \[
                (' . $this->nested_brackets_re . ')	# link text = $2
              \]
              \(			# literal paren
                [ \n]*
                (?:
                    <(.+?)>	# href = $3
                |
                    (' . $this->nested_url_parenthesis_re . ')	# href = $4
                )
                [ \n]*
                (			# $5
                  ([\'"])	# quote char = $6
                  (.*?)		# Title = $7
                  \6		# matching quote
                  [ \n]*	# ignore any spaces/tabs between closing quote and )
                )?			# title is optional
              \)
            )
            }xs',
            array($this, '_doAnchors_inline_callback'),
            $text
        );

        #
        # Last, handle reference-style shortcuts: [link text]
        # These must come last in case you've also got [link text][1]
        # or [link text](/foo)
        #
        $text = preg_replace_callback(
            '{
            (					# wrap whole match in $1
              \[
                ([^\[\]]+)		# link text = $2; can\'t contain [ or ]
              \]
            )
            }xs',
            array($this, '_doAnchors_reference_callback'),
            $text
        );

        $this->in_anchor = false;

        return $text;
    }

    protected function _doAnchors_reference_callback($matches)
    {
        $whole_match = $matches[1];
        $link_text = $matches[2];
        $link_id = &$matches[3];

        if ($link_id == "") {
            # for shortcut links like [this][] or [this].
            $link_id = $link_text;
        }

        # lower-case and turn embedded newlines into spaces
        $link_id = strtolower($link_id);
        $link_id = preg_replace('{[ ]?\n}', ' ', $link_id);

        if (isset($this->urls[$link_id])) {
            $url = $this->urls[$link_id];
            $url = $this->normalizeRelativeUrl($url);
            $url = $this->encodeURLAttribute($url);

            $result = "<a href=\"$url\"";
            if (isset($this->titles[$link_id])) {
                $title = $this->titles[$link_id];
                $title = $this->encodeAttribute($title);
                $result .= " title=\"$title\"";
            }

            $link_text = $this->runSpanGamut($link_text);
            $result .= ">$link_text</a>";
            $result = $this->hashPart($result);
        } else {
            $result = $whole_match;
        }

        return $result;
    }

    protected function _doAnchors_inline_callback($matches)
    {
        $whole_match = $matches[1];
        $link_text = $this->runSpanGamut($matches[2]);
        $url = $matches[3] == '' ? $matches[4] : $matches[3];
        $title = &$matches[7];

        // if the URL was of the form <s p a c e s> it got caught by the HTML
        // tag parser and hashed. Need to reverse the process before using the URL.
        $unhashed = $this->unhash($url);
        if ($unhashed != $url) {
            $url = preg_replace('/^<(.*)>$/', '\1', $unhashed);
        }

        $url = $this->encodeURLAttribute($url);

        $result = "<a href=\"$url\"";
        if (isset($title)) {
            $title = $this->encodeAttribute($title);
            $result .= " title=\"$title\"";
        }

        $link_text = $this->runSpanGamut($link_text);
        $result .= ">$link_text</a>";

        return $this->hashPart($result);
    }

    protected function normalizeRelativeUrl($url)
    {
        if ($url === '' || $url === '.' || $url === '..') {
            return $url;
        }
        if ($url[0] === '#' || $url[0] === '/' || $url[0] === '?') {
            return $url;
        }
        if (strpos($url, '//') === 0) {
            return $url;
        }
        if (preg_match('/^[a-z][a-z0-9+.-]*:/i', $url)) {
            return $url;
        }
        if (preg_match('/^\.\.?\//', $url)) {
            return $url;
        }

        return './' . $url;
    }

    protected function doImages($text)
    {
        #
        # Turn Markdown image shortcuts into <img> tags.
        #
        #
        # First, handle reference-style labeled images: ![alt text][id]
        #
        $text = preg_replace_callback(
            '{
            (				# wrap whole match in $1
              !\[
                (' . $this->nested_brackets_re . ')		# alt text = $2
              \]

              [ ]?				# one optional space
              (?:\n[ ]*)?		# one optional newline followed by spaces

              \[
                (.*?)		# id = $3
              \]

            )
            }xs',
            array($this, '_doImages_reference_callback'),
            $text
        );

        #
        # Next, handle inline images:  ![alt text](url "optional title")
        # Don't forget: encode * and _
        #
        $text = preg_replace_callback(
            '{
            (				# wrap whole match in $1
              !\[
                (' . $this->nested_brackets_re . ')		# alt text = $2
              \]
              \s?			# One optional whitespace character
              \(			# literal paren
                [ \n]*
                (?:
                    <(\S*)>	# src url = $3
                |
                    (' . $this->nested_url_parenthesis_re . ')	# src url = $4
                )
                [ \n]*
                (			# $5
                  ([\'"])	# quote char = $6
                  (.*?)		# title = $7
                  \6		# matching quote
                  [ \n]*
                )?			# title is optional
              \)
            )
            }xs',
            array($this, '_doImages_inline_callback'),
            $text
        );

        return $text;
    }

    protected function _doImages_reference_callback($matches)
    {
        $whole_match = $matches[1];
        $alt_text = $matches[2];
        $link_id = strtolower($matches[3]);

        if ($link_id == "") {
            $link_id = strtolower($alt_text); # for shortcut links like ![this][].
        }

        $alt_text = $this->encodeAttribute($alt_text);
        if (isset($this->urls[$link_id])) {
            $url = $this->encodeURLAttribute($this->urls[$link_id]);
            $result = "<img loading=\"lazy\" src=\"$url\" alt=\"$alt_text\"";
            if (isset($this->titles[$link_id])) {
                $title = $this->titles[$link_id];
                $title = $this->encodeAttribute($title);
                $result .= " title=\"$title\"";
            }
            $result .= $this->empty_element_suffix;
            $result = $this->hashPart($result);
        } else {
            # If there's no such link ID, leave intact:
            $result = $whole_match;
        }

        return $result;
    }

    protected function _doImages_inline_callback($matches)
    {
        $whole_match = $matches[1];
        $alt_text = $matches[2];
        $url = $matches[3] == '' ? $matches[4] : $matches[3];
        $title = &$matches[7];

        $alt_text = $this->encodeAttribute($alt_text);
        $url = $this->encodeURLAttribute($url);
        $result = "<img loading=\"lazy\" src=\"$url\" alt=\"$alt_text\"";
        if (isset($title)) {
            $title = $this->encodeAttribute($title);
            $result .= " title=\"$title\""; # $title already quoted
        }
        $result .= $this->empty_element_suffix;

        return $this->hashPart($result);
    }

    protected function doHeaders($text)
    {
        # Setext-style headers:
        #	  Header 1
        #	  ========
        #
        #	  Header 2
        #	  --------
        #
        $text = preg_replace_callback(
            '{ ^(.+?)[ ]*\n(=+|-+)[ ]*\n+ }mx',
            array($this, '_doHeaders_callback_setext'),
            $text
        );

        # atx-style headers:
        #	# Header 1
        #	## Header 2
        #	## Header 2 with closing hashes ##
        #	...
        #	###### Header 6
        #
        $text = preg_replace_callback(
            '{
                ^(\#{1,6})	# $1 = string of #\'s
                [ ]*
                (.+?)		# $2 = Header text
                [ ]*
                \#*			# optional closing #\'s (not counted)
                \n+
            }xm',
            array($this, '_doHeaders_callback_atx'),
            $text
        );

        return $text;
    }

    protected function _doHeaders_callback_setext($matches)
    {
        # Terrible hack to check we haven't found an empty list item.
        if ($matches[2] == '-' && preg_match('{^-(?: |$)}', $matches[1])) {
            return $matches[0];
        }

        // Getting the first character in the appropriate match
        $level = $matches[2][0] == '=' ? 1 : 2;

        # id attribute generation
        $idAtt = $this->_generateIdFromHeaderValue($matches[1]);

        $block = "<h$level$idAtt>" . $this->runSpanGamut($matches[1]) . "</h$level>";

        return "\n" . $this->hashBlock($block) . "\n\n";
    }

    protected function _doHeaders_callback_atx($matches)
    {

        # id attribute generation
        $idAtt = $this->_generateIdFromHeaderValue($matches[2]);

        $level = strlen($matches[1]);
        $block = "<h$level$idAtt>" . $this->runSpanGamut($matches[2]) . "</h$level>";

        return "\n" . $this->hashBlock($block) . "\n\n";
    }

    protected function _generateIdFromHeaderValue($headerValue)
    {

        # if a header_id_func property is set, we can use it to automatically
        # generate an id attribute.
        #
        # This method returns a string in the form id="foo", or an empty string
        # otherwise.
        if (!is_callable($this->header_id_func)) {
            return "";
        }
        $idValue = call_user_func($this->header_id_func, $headerValue);
        if (!$idValue) {
            return "";
        }

        return ' id="' . $this->encodeAttribute($idValue) . '"';
    }

    protected function doLists($text)
    {
        #
        # Form HTML ordered (numbered) and unordered (bulleted) lists.
        #
        $less_than_tab = $this->tab_width - 1;

        # Re-usable patterns to match list item bullets and number markers:
        $marker_ul_re = '[*+-]';
        $marker_ol_re = '\d+[\.]';

        $markers_relist = array(
            $marker_ul_re => $marker_ol_re,
            $marker_ol_re => $marker_ul_re,
        );

        foreach ($markers_relist as $marker_re => $other_marker_re) {
            # Re-usable pattern to match any entirel ul or ol list:
            $whole_list_re = '
                (								# $1 = whole list
                  (								# $2
                    ([ ]{0,' . $less_than_tab . '})	# $3 = number of spaces
                    (' . $marker_re . ')			# $4 = first list item marker
                    [ ]+
                  )
                  (?s:.+?)
                  (								# $5
                      \z
                    |
                      \n{2,}
                      (?=\S)
                      (?!						# Negative lookahead for another list item marker
                        [ ]*
                        ' . $marker_re . '[ ]+
                      )
                    |
                      (?=						# Lookahead for another kind of list
                        \n
                        \3						# Must have the same indentation
                        ' . $other_marker_re . '[ ]+
                      )
                  )
                )
            '; // mx

            # We use a different prefix before nested lists than top-level lists.
            # See extended comment in _ProcessListItems().

            if ($this->list_level) {
                $text = preg_replace_callback(
                    '{
                        ^
                        ' . $whole_list_re . '
                    }mx',
                    array($this, '_doLists_callback'),
                    $text
                );
            } else {
                $text = preg_replace_callback(
                    '{
                        (?:(?<=\n)\n|\A\n?) # Must eat the newline
                        ' . $whole_list_re . '
                    }mx',
                    array($this, '_doLists_callback'),
                    $text
                );
            }
        }

        return $text;
    }

    protected function _doLists_callback($matches)
    {
        # Re-usable patterns to match list item bullets and number markers:
        $marker_ul_re = '[*+-]';
        $marker_ol_re = '\d+[\.]';
        $marker_any_re = "(?:$marker_ul_re|$marker_ol_re)";
        $marker_ol_start_re = '[0-9]+';

        $list = $matches[1];
        $list_type = preg_match("/$marker_ul_re/", $matches[4]) ? "ul" : "ol";

        $marker_any_re = ($list_type == "ul" ? $marker_ul_re : $marker_ol_re);

        $list .= "\n";
        $result = $this->processListItems($list, $marker_any_re);

        $ol_start = 1;
        if ($this->enhanced_ordered_list) {
            # Get the start number for ordered list.
            if ($list_type == 'ol') {
                $ol_start_array = array();
                $ol_start_check = preg_match("/$marker_ol_start_re/", $matches[4], $ol_start_array);
                if ($ol_start_check) {
                    $ol_start = $ol_start_array[0];
                }
            }
        }

        if ($ol_start > 1 && $list_type == 'ol') {
            $result = $this->hashBlock("<$list_type start=\"$ol_start\">\n" . $result . "</$list_type>");
        } else {
            $result = $this->hashBlock("<$list_type>\n" . $result . "</$list_type>");
        }

        return "\n" . $result . "\n\n";
    }

    protected $list_level = 0;

    protected function processListItems($list_str, $marker_any_re)
    {
        #
        #	Process the contents of a single ordered or unordered list, splitting it
        #	into individual list items.
        #
        # The $this->list_level global keeps track of when we're inside a list.
        # Each time we enter a list, we increment it; when we leave a list,
        # we decrement. If it's zero, we're not in a list anymore.
        #
        # We do this because when we're not inside a list, we want to treat
        # something like this:
        #
        #		I recommend upgrading to version
        #		8. Oops, now this line is treated
        #		as a sub-list.
        #
        # As a single paragraph, despite the fact that the second line starts
        # with a digit-period-space sequence.
        #
        # Whereas when we're inside a list (or sub-list), that line will be
        # treated as the start of a sub-list. What a kludge, huh? This is
        # an aspect of Markdown's syntax that's hard to parse perfectly
        # without resorting to mind-reading. Perhaps the solution is to
        # change the syntax rules such that sub-lists must start with a
        # starting cardinal number; e.g. "1." or "a.".

        $this->list_level++;

        # trim trailing blank lines:
        $list_str = preg_replace("/\n{2,}\\z/", "\n", $list_str);

        $list_str = preg_replace_callback(
            '{
            (\n)?							# leading line = $1
            (^[ ]*)							# leading whitespace = $2
            (' . $marker_any_re . '				# list marker and space = $3
                (?:[ ]+|(?=\n))	# space only required if item is not empty
            )
            ((?s:.*?))						# list item text   = $4
            (?:(\n+(?=\n))|\n)				# tailing blank line = $5
            (?= \n* (\z | \2 (' . $marker_any_re . ') (?:[ ]+|(?=\n))))
            }xm',
            array($this, '_processListItems_callback'),
            $list_str
        );

        $this->list_level--;

        return $list_str;
    }

    protected function _processListItems_callback($matches)
    {
        $item = $matches[4];
        $leading_line = &$matches[1];
        $leading_space = &$matches[2];
        $marker_space = $matches[3];
        $tailing_blank_line = &$matches[5];

        if (
            $leading_line || $tailing_blank_line ||
            preg_match('/\n{2,}/', $item)
        ) {
            # Replace marker with the appropriate whitespace indentation
            $item = $leading_space . str_repeat(' ', strlen($marker_space)) . $item;
            $item = $this->runBlockGamut($this->outdent($item) . "\n");
        } else {
            # Recursion for sub-lists:
            $item = $this->doLists($this->outdent($item));
            $item = preg_replace('/\n+$/', '', $item);
            $item = $this->runSpanGamut($item);
        }

        return "<li>" . $item . "</li>\n";
    }

    protected function doCodeBlocks($text)
    {
        #
        #	Process Markdown `<pre><code>` blocks.
        #
        $text = preg_replace_callback(
            '{
                (?:\n\n|\A\n?)
                (	            # $1 = the code block -- one or more lines, starting with a space/tab
                  (?>
                    [ ]{' . $this->tab_width . '}  # Lines must start with a tab or a tab-width of spaces
                    .*\n+
                  )+
                )
                ((?=^[ ]{0,' . $this->tab_width . '}\S)|\Z)	# Lookahead for non-space at line-start, or end of doc
            }xm',
            array($this, '_doCodeBlocks_callback'),
            $text
        );

        return $text;
    }

    protected function _doCodeBlocks_callback($matches)
    {
        $codeblock = $matches[1];

        $codeblock = $this->outdent($codeblock);
        if ($this->code_block_content_func) {
            $codeblock = call_user_func($this->code_block_content_func, $codeblock, "");
        } else {
            $codeblock = htmlspecialchars($codeblock, ENT_NOQUOTES, 'UTF-8');
        }

        # trim leading newlines and trailing newlines
        $codeblock = preg_replace('/\A\n+|\n+\z/', '', $codeblock);

        $codeblock = "<pre><code>$codeblock\n</code></pre>";

        return "\n\n" . $this->hashBlock($codeblock) . "\n\n";
    }

    protected function makeCodeSpan($code)
    {
        #
        # Create a code span markup for $code. Called from handleSpanToken.
        #
        // $code = htmlspecialchars(trim($code), ENT_NOQUOTES, 'UTF-8');
        $code = trim($code);
        return $this->hashPart("<code>$code</code>");
    }

    protected $em_relist = array(
        '' => '(?:(?<!\*)\*(?!\*)|(?<!_)_(?!_))(?![\.,:;]?\s)',
        '*' => '(?<![\s*])\*(?!\*)',
        '_' => '(?<![\s_])_(?!_)',
    );
    protected $strong_relist = array(
        '' => '(?:(?<!\*)\*\*(?!\*)|(?<!_)__(?!_))(?![\.,:;]?\s)',
        '**' => '(?<![\s*])\*\*(?!\*)',
        '__' => '(?<![\s_])__(?!_)',
    );
    protected $em_strong_relist = array(
        '' => '(?:(?<!\*)\*\*\*(?!\*)|(?<!_)___(?!_))(?![\.,:;]?\s)',
        '***' => '(?<![\s*])\*\*\*(?!\*)',
        '___' => '(?<![\s_])___(?!_)',
    );
    protected $em_strong_prepared_relist;

    protected function prepareItalicsAndBold()
    {
        #
        # Prepare regular expressions for searching emphasis tokens in any
        # context.
        #
        foreach ($this->em_relist as $em => $em_re) {
            foreach ($this->strong_relist as $strong => $strong_re) {
                # Construct list of allowed token expressions.
                $token_relist = array();
                if (isset($this->em_strong_relist["$em$strong"])) {
                    $token_relist[] = $this->em_strong_relist["$em$strong"];
                }
                $token_relist[] = $em_re;
                $token_relist[] = $strong_re;

                # Construct master expression from list.
                $token_re = '{(' . implode('|', $token_relist) . ')}';
                $this->em_strong_prepared_relist["$em$strong"] = $token_re;
            }
        }
    }

    protected function doItalicsAndBold($text)
    {
        $token_stack = array('');
        $text_stack = array('');
        $em = '';
        $strong = '';
        $tree_char_em = false;

        while (1) {
            #
            # Get prepared regular expression for seraching emphasis tokens
            # in current context.
            #
            $token_re = $this->em_strong_prepared_relist["$em$strong"];

            #
            # Each loop iteration search for the next emphasis token.
            # Each token is then passed to handleSpanToken.
            #
            $parts = preg_split($token_re, $text, 2, PREG_SPLIT_DELIM_CAPTURE);
            $text_stack[0] .= $parts[0];
            $token = &$parts[1];
            $text = &$parts[2];

            if (empty($token)) {
                # Reached end of text span: empty stack without emitting.
                # any more emphasis.
                while ($token_stack[0]) {
                    $text_stack[1] .= array_shift($token_stack);
                    $text_stack[0] .= array_shift($text_stack);
                }

                break;
            }

            $token_len = strlen($token);
            if ($tree_char_em) {
                # Reached closing marker while inside a three-char emphasis.
                if ($token_len == 3) {
                    # Three-char closing marker, close em and strong.
                    array_shift($token_stack);
                    $span = array_shift($text_stack);
                    $span = $this->runSpanGamut($span);
                    $span = "<strong><em>$span</em></strong>";
                    $text_stack[0] .= $this->hashPart($span);
                    $em = '';
                    $strong = '';
                } else {
                    # Other closing marker: close one em or strong and
                    # change current token state to match the other
                    $token_stack[0] = str_repeat($token[0], 3 - $token_len);
                    $tag = $token_len == 2 ? "strong" : "em";
                    $span = $text_stack[0];
                    $span = $this->runSpanGamut($span);
                    $span = "<$tag>$span</$tag>";
                    $text_stack[0] = $this->hashPart($span);
                    $$tag = ''; # $$tag stands for $em or $strong
                }
                $tree_char_em = false;
            } elseif ($token_len == 3) {
                if ($em) {
                    # Reached closing marker for both em and strong.
                    # Closing strong marker:
                    for ($i = 0; $i < 2; ++$i) {
                        $shifted_token = array_shift($token_stack);
                        $tag = strlen($shifted_token) == 2 ? "strong" : "em";
                        $span = array_shift($text_stack);
                        $span = $this->runSpanGamut($span);
                        $span = "<$tag>$span</$tag>";
                        $text_stack[0] .= $this->hashPart($span);
                        $$tag = ''; # $$tag stands for $em or $strong
                    }
                } else {
                    # Reached opening three-char emphasis marker. Push on token
                    # stack; will be handled by the special condition above.
                    $em = $token[0];
                    $strong = "$em$em";
                    array_unshift($token_stack, $token);
                    array_unshift($text_stack, '');
                    $tree_char_em = true;
                }
            } elseif ($token_len == 2) {
                if ($strong) {
                    # Unwind any dangling emphasis marker:
                    if (strlen($token_stack[0]) == 1) {
                        $text_stack[1] .= array_shift($token_stack);
                        $text_stack[0] .= array_shift($text_stack);
                    }
                    # Closing strong marker:
                    array_shift($token_stack);
                    $span = array_shift($text_stack);
                    $span = $this->runSpanGamut($span);
                    $span = "<strong>$span</strong>";
                    $text_stack[0] .= $this->hashPart($span);
                    $strong = '';
                } else {
                    array_unshift($token_stack, $token);
                    array_unshift($text_stack, '');
                    $strong = $token;
                }
            } else {
                # Here $token_len == 1
                if ($em) {
                    if (strlen($token_stack[0]) == 1) {
                        # Closing emphasis marker:
                        array_shift($token_stack);
                        $span = array_shift($text_stack);
                        $span = $this->runSpanGamut($span);
                        $span = "<em>$span</em>";
                        $text_stack[0] .= $this->hashPart($span);
                        $em = '';
                    } else {
                        $text_stack[0] .= $token;
                    }
                } else {
                    array_unshift($token_stack, $token);
                    array_unshift($text_stack, '');
                    $em = $token;
                }
            }
        }

        return $text_stack[0];
    }

    protected function doBlockQuotes($text)
    {
        $text = preg_replace_callback(
            '/
              (								# Wrap whole match in $1
                (?>
                  ^[ ]*>[ ]?			# ">" at the start of a line
                    .+\n					# rest of the first line
                  (.+\n)*					# subsequent consecutive lines
                  \n*						# blanks
                )+
              )
            /xm',
            array($this, '_doBlockQuotes_callback'),
            $text
        );

        return $text;
    }

    protected function _doBlockQuotes_callback($matches)
    {
        $bq = $matches[1];
        # trim one level of quoting - trim whitespace-only lines
        $bq = preg_replace('/^[ ]*>[ ]?|^[ ]+$/m', '', $bq);
        $bq = $this->runBlockGamut($bq);        # recurse

        $bq = preg_replace('/^/m', "  ", $bq);
        # These leading spaces cause problem with <pre> content,
        # so we need to fix that:
        $bq = preg_replace_callback(
            '{(\s*<pre>.+?</pre>)}sx',
            array($this, '_doBlockQuotes_callback2'),
            $bq
        );

        return "\n" . $this->hashBlock("<blockquote>\n$bq\n</blockquote>") . "\n\n";
    }

    protected function _doBlockQuotes_callback2($matches)
    {
        $pre = $matches[1];
        $pre = preg_replace('/^  /m', '', $pre);

        return $pre;
    }

    protected function formParagraphs($text)
    {
        #
        #	Params:
        #		$text - string to process with html <p> tags
        #
        # Strip leading and trailing lines:
        $text = preg_replace('/\A\n+|\n+\z/', '', $text);

        $grafs = preg_split('/\n{2,}/', $text, -1, PREG_SPLIT_NO_EMPTY);
        $block_tag_re = '(?:p|div|h[1-6]|blockquote|pre|table|dl|ol|ul|li|address|' .
            'script|noscript|style|form|fieldset|iframe|math|svg|' .
            'article|section|nav|aside|hgroup|header|footer|' .
            'figure|figcaption|hr|details|summary|oembed|main|video|audio|canvas)';
        $block_tag_pattern = '/<\/?(?:' . $block_tag_re . ')\b/i';

        #
        # Wrap <p> tags and unhashify HTML blocks
        #
        foreach ($grafs as $key => $value) {
            if (!preg_match('/^B\x1A[0-9]+B$/', $value)) {
                if (preg_match($block_tag_pattern, $value) || preg_match('/B\x1A[0-9]+B/', $value)) {
                    $grafs[$key] = $this->unhash($value);
                    continue;
                }
                # Is a paragraph.
                $value = $this->runSpanGamut($value);
                $value = preg_replace('/^([ ]*)/', "<p>", $value);
                $value .= "</p>";
                $grafs[$key] = $this->unhash($value);
            } else {
                # Is a block.
                # Modify elements of @grafs in-place...
                $graf = $value;
                $block = $this->html_hashes[$graf];
                $graf = $block;
                $grafs[$key] = $graf;
            }
        }

        return implode("\n\n", $grafs);
    }

    protected function encodeAttribute($text)
    {
        #
        # Encode text for a double-quoted HTML attribute. This function
        # is *not* suitable for attributes enclosed in single quotes.
        #
        $text = $this->encodeAmpsAndAngles($text);
        $text = str_replace('"', '&quot;', $text);

        return $text;
    }

    protected function encodeURLAttribute($url, &$text = null)
    {
        #
        # Encode text for a double-quoted HTML attribute containing a URL,
        # applying the URL filter if set. Also generates the textual
        # representation for the URL (removing mailto: or tel:) storing it in $text.
        # This function is *not* suitable for attributes enclosed in single quotes.
        #
        if ($this->url_filter_func) {
            $url = call_user_func($this->url_filter_func, $url);
        }

        if (preg_match('{^mailto:}i', $url)) {
            $url = $this->encodeEntityObfuscatedAttribute($url, $text, 7);
        } elseif (preg_match('{^tel:}i', $url)) {
            $url = $this->encodeAttribute($url);
            $text = substr($url, 4);
        } else {
            $url = $this->encodeAttribute($url);
            $text = $url;
        }

        return $url;
    }

    protected function encodeAmpsAndAngles($text)
    {
        #
        # Smart processing for ampersands and angle brackets that need to
        # be encoded. Valid character entities are left alone unless the
        # no-entities mode is set.
        #
        if ($this->no_entities) {
            $text = str_replace('&', '&amp;', $text);
        } else {
            # Ampersand-encoding based entirely on Nat Irons's Amputator
            # MT plugin: <http://bumppo.net/projects/amputator/>
            $text = preg_replace(
                '/&(?!#?[xX]?(?:[0-9a-fA-F]+|\w+);)/',
                '&amp;',
                $text
            );
        }
        # Encode remaining <'s
        $text = str_replace('<', '&lt;', $text);

        return $text;
    }

    protected function doAutoLinks($text)
    {
        $text = preg_replace_callback(
            '{<((https?|ftp|dict|tel):[^\'">\s]+)>}i',
            array($this, '_doAutoLinks_url_callback'),
            $text
        );

        # Email addresses: <address@domain.foo>
        $text = preg_replace_callback(
            '{
            <
            (?:mailto:)?
            (
                (?:
                    [-!#$%&\'*+/=?^_`.{|}~\w\x80-\xFF]+
                |
                    ".*?"
                )
                \@
                (?:
                    [-a-z0-9\x80-\xFF]+(\.[-a-z0-9\x80-\xFF]+)*\.[a-z]+
                |
                    \[[\d.a-fA-F:]+\]	# IPv4 & IPv6
                )
            )
            >
            }xi',
            array($this, '_doAutoLinks_email_callback'),
            $text
        );

        return $text;
    }

    protected function _doAutoLinks_url_callback($matches)
    {
        $url = $this->encodeURLAttribute($matches[1], $text);
        $link = "<a href=\"$url\">$text</a>";

        return $this->hashPart($link);
    }

    protected function _doAutoLinks_email_callback($matches)
    {
        $addr = $matches[1];
        $url = $this->encodeEntityObfuscatedAttribute("mailto:" . $addr, $text, 7);
        $link = "<a href=\"$url\">$text</a>";

        return $this->hashPart($link);
    }

    protected function encodeEntityObfuscatedAttribute($addr, &$text = null, $skip = 0)
    {
        #
        # Input: an email address, e.g. "foo@example.com"
        #
        # Output: the email address as a mailto link, with each character
        #	of the address encoded as either a decimal or hex entity, in
        #	the hopes of foiling most address harvesting spam bots. E.g.:
        #
        #	<a href="&#x6D;&#97;&#105;&#108;&#x74;&#111;:&#102;&#111;&#111;&#64;&#101;
        #	   x&#x61;&#109;&#x70;&#108;&#x65;&#x2E;&#99;&#111;&#109;">&#102;&#111;&#111;
        #	   &#64;&#101;x&#x61;&#109;&#x70;&#108;&#x65;&#x2E;&#99;&#111;&#109;</a>
        #
        # Based by a filter by Matthew Wickline, posted to BBEdit-Talk.
        # With some optimizations by Milian Wolff. Forced encoding of HTML
        # attribute values to accomodate XML-minded markup parsers (like
        # XHTML Validator, work by J. Siarto).
        #
        $length = strlen($addr);
        $encoded_addr = '';
        $current_char = $skip;
        $next_char = null;

        # Randomize the character encoding method on every second character
        # This improves the encoding randomness while still ensuring the
        # obfuscation doesn't make the string unreadable.
        $is_entity = false;

        for ($i = $skip; $i < $length; $i++) {
            # Perform a random encoding on half the characters
            # Leave the other half unencoded
            $char = $addr[$i];

            # Use HTML entity encoding for characters that need it
            if (ord($char) > 127 || $char == '@' || $char == '.') {
                if (rand(0, 1)) {
                    $encoded_char = sprintf('&#x%X;', ord($char));
                } else {
                    $encoded_char = sprintf('&#%d;', ord($char));
                }
            } else {
                $encoded_char = $char;
            }

            $encoded_addr .= $encoded_char;
        }

        # Generate the obfuscated text from the encoded address
        $text = $encoded_addr;

        # Strip "mailto:" from the encoded string, if it starts with it
        if (preg_match('{^&#109;&#97;&#105;&#108;&#116;&#111;&#58;}i', $encoded_addr)) {
            $encoded_addr = preg_replace('{^&#109;&#97;&#105;&#108;&#116;&#111;&#58;}i', '', $encoded_addr);
            $text = $encoded_addr;
        }

        return $encoded_addr;
    }

    protected function parseSpan($text)
    {
        #
        # Parsing span elements, including code spans, character escapes,
        # and inline HTML tags.
        #
        $output = '';

        $span_re = '{
                (
                    \\\\ ' . $this->escape_chars_re . '
                |
                    (?<![`\\\\])
                    `+						# code span marker
            ' . (!$this->no_markup ? '
                |
                    <!--.*?-->		# comment
                |
                    <\?.*?\?> | <%.*?%>	# processing instruction
                |
                    <[!$]?[-a-zA-Z0-9:_]+	# regular tags
                    (?>
                        \s
                        (?>[^"\'>]+|"[^"]*"|\'[^\']*\')*
                    )?
                    >
                |
                    <[-a-zA-Z0-9:_]+\s*/> # xml-style empty tag
                |
                    </[-a-zA-Z0-9:_]+\s*> # closing tag
            ' : '') . '
                )
                }xs';

        while (1) {
            # Each loop iteration process one of the following tags:
            # - Code span: `code`
            # - Escaped char: \char
            # - Inline HTML: <tag>
            #
            # as well as other characters until the next tag.
            #
            $parts = preg_split($span_re, $text, 2, PREG_SPLIT_DELIM_CAPTURE);

            # Process everything before the match
            if ($parts[0] != "") {
                $output .= $parts[0];
            }

            # If no match, we've processed everything
            if (!isset($parts[1])) {
                break;
            }

            # Process the matched tag/span
            $tag = $parts[1];

            if ($tag[0] == "\\") {
                # Escaped character
                $output .= $this->hashPart(substr($tag, 1));
            } elseif ($tag[0] == "`") {
                # Code span
                # Search for end marker and create the span
                if (preg_match('/^(' . preg_quote($tag) . ')(.+?)\1(?!`)/s', $parts[2], $matches)) {
                    # Matched end marker
                    $parts[2] = substr($parts[2], strlen($matches[0]));
                    $output .= $this->makeCodeSpan($matches[2]);
                } else {
                    # No match, so no code span, just output the marker
                    $output .= $tag;
                }
            } elseif ($tag[0] == "<") {
                # HTML tag or comment
                $output .= $this->hashPart($tag);
            }

            # Continue with the rest of the text
            $text = $parts[2];
        }

        return $output;
    }

    protected $predef_attr = array();

    protected function doExtraAttributes($tag_name, $attr, $defaultIdValue = null, &$classes = null)
    {
        #
        # Parse attributes caught by the beforehand parsing of extractions
        # and return the HTML-formatted list of attributes.
        #
        # Currently supported attributes are .class and #id.
        #
        if (empty($attr) && !$defaultIdValue && empty($this->predef_attr[$tag_name])) {
            return "";
        }

        # Split on components
        preg_match_all('/
            (?:
                \.([^\#\[\]\.]+)        # .class
            |
                \#([^\#\[\]\.]+)        # #id
            |
                (?<!\\\)                # ignore escaped square brackets
                \[
                    (?>
                        [^\[\]\\]+      # any non-special character
                    |
                        \\.             # escaped character
                    |
                        (?R)            # recursion
                    )*
                \]
            )
        /x', $attr, $matches);
        $elements = $matches[0];
        $classes = array();
        $attributes = array();
        $id = false;

        foreach ($elements as $element) {
            if ($element[0] == '.') {
                # .class
                $classes[] = substr($element, 1);
            } elseif ($element[0] == '#') {
                # #id
                $id = substr($element, 1);
            } elseif ($element[0] == '[') {
                # [attributes]
                # Strip brackets and parse for attribute pairs
                $subelements = substr($element, 1, -1);
                $subelements = preg_split('/(?<=[^\\\])\s+/', $subelements);

                foreach ($subelements as $subelement) {
                    if (strpos($subelement, '=') !== false) {
                        # Attribute with value
                        list($attr_name, $attr_value) = explode('=', $subelement, 2);
                        $attr_value = trim($attr_value, '"\'');
                        $attr_value = str_replace('\]', ']', $attr_value);
                        $attr_value = str_replace('\"', '"', $attr_value);
                        $attr_value = str_replace("\'", "'", $attr_value);
                        $attributes[$attr_name] = $attr_value;
                    } else {
                        # Boolean attribute
                        $attributes[$subelement] = $subelement;
                    }
                }
            }
        }

        # Check for predefined attributes for this tag
        if (isset($this->predef_attr[$tag_name])) {
            $predefined = $this->predef_attr[$tag_name];
            if (isset($predefined['~'])) {
                $classes = array_merge($classes, $predefined['~']);
            }
            foreach ($predefined as $key => $value) {
                if ($key != '~') {
                    $attributes[$key] = $value;
                }
            }
        }

        # Resolve default id
        if (!$id && $defaultIdValue) {
            $id = $defaultIdValue;
        }

        # Build attribute string
        $attr_str = "";
        if (!empty($id)) {
            $attr_str .= ' id="' . $this->encodeAttribute($id) . '"';
        }
        if (!empty($classes)) {
            $attr_str .= ' class="' . $this->encodeAttribute(implode(" ", $classes)) . '"';
        }
        foreach ($attributes as $key => $value) {
            $attr_str .= ' ' . $key . '="' . $this->encodeAttribute($value) . '"';
        }

        return $attr_str;
    }

    protected function unhash($text)
    {
        #
        # Swap back in all the tags hashed by _HashHTMLBlocks.
        #
        return preg_replace_callback('/(.)\x1A[0-9]+\1/', array($this, '_unhash_callback'), $text);
    }

    protected function _unhash_callback($matches)
    {
        return $this->html_hashes[$matches[0]];
    }

    # These are the tab expansion functions used by detab and outdent.
    protected $prestr = '';
    protected $poststr = '';

    protected function _initDetab()
    {
        #
        # Initialize the filter. This is only required once.
        #
        $this->prestr = str_repeat(' ', $this->tab_width);
        $this->poststr = '';
    }

    protected function detab($text)
    {
        #
        # Replace tabs with the appropriate amount of spaces.
        #
        $text = preg_replace_callback('/^.*\t.*$/m', array($this, '_detab_callback'), $text);

        return $text;
    }

    protected function _detab_callback($matches)
    {
        $line = $matches[0];
        $strlen = 0;        # String length to the detabbed line.

        # Split in blocks delemited by tab characters.
        $blocks = explode("\t", $line);

        # Add each block to the output, with a tab in between.
        $line = $blocks[0];
        unset($blocks[0]);    # Do not add first block twice.
        foreach ($blocks as $block) {
            # Calculate the amount of spaces to add to the line.
            $amount = $this->tab_width - ($strlen % $this->tab_width);
            $strlen += $amount + strlen($block);
            $line .= str_repeat(" ", $amount) . $block;
        }

        return $line;
    }

    protected function outdent($text)
    {
        #
        # Remove one level of line-leading tabs or spaces
        #
        return preg_replace('/^(\t|[ ]{1,' . $this->tab_width . '})/m', '', $text);
    }
}

tiny::registerHelper('markdown', function () {
    return new Markdown();
});
