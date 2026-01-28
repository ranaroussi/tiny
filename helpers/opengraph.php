<?php
declare(strict_types=1);

/**
 * OpenGraphImage Class
 *
 * Generates social media preview images with customizable title and description text
 * overlaid on a template image. Supports multiple output formats and quality settings.
 */
class OpenGraphImage
{
    // Base configuration
    private string $basePath;
    private string $templateImage;
    private int $leftOffset;
    private int $topOffset;
    private int $textBoxWidth;

    // Title configuration
    private string $titleText;
    private string $title;
    private float $titleFontSize;
    private int $titleBaseTopOffset;
    private float $titleLineHeight;
    private string $titleFont;
    private array $titleColor;
    private int $titleMaxChars;

    // Description configuration (all optional)
    private ?string $descriptionText;
    private ?string $description = null;
    private ?float $descriptionFontSize = null;
    private ?int $descriptionTopOffset = null;
    private ?float $descriptionLineHeight = null;
    private ?string $descriptionFont = null;
    private ?array $descriptionColor = null;
    private ?int $descriptionMaxChars = null;

    /**
     * Initialize with base path for assets
     */
    public function __construct(string $basePath)
    {
        $this->basePath = $basePath;
    }

    /**
     * Set the template image and text positioning
     */
    public function setTemplate(string $image, int $leftOffset, int $topOffset, int $textBoxWidth): void
    {
        // Construct full image path and validate
        $image = $this->basePath . '/' . $image;
        if (!file_exists($image)) {
            throw new Exception('Template image not found');
        }
        $image = realpath($image);
        if ($image === false) {
            throw new Exception('Could not resolve template image path');
        }

        $this->templateImage = $image;
        $this->leftOffset = $leftOffset;
        $this->topOffset = $topOffset;
        $this->textBoxWidth = $textBoxWidth;
    }

    /**
     * Set the main title text
     */
    public function setTitle(string $text)
    {
        $this->titleText = mb_convert_encoding(trim(str_replace(' ', ' ', $text)), 'UTF-8', 'auto');
    }

    /**
     * Configure title text styling and positioning
     */
    public function setTitleOptions(
        float $fontSize = 37,
        float $lineHeight = 1.5,
        array $colorRGB = [0, 0, 0],
        string $font = 'inter-bold.ttf',
    ): void {
        if (!isset($this->titleText)) {
            throw new Exception('Please use `setTitle()` before configuring the title');
        }

        // Store title configuration
        $this->titleFontSize = $fontSize;
        $this->titleLineHeight = $lineHeight;
        $this->titleFont = $this->basePath . '/' . $font;
        $this->titleColor = $colorRGB;

        // Calculate text wrapping based on font size
        $this->titleMaxChars = (int)round($this->textBoxWidth / $fontSize * 1.5);
        $this->title = wordwrap($this->titleText, $this->titleMaxChars, "\n", true);

        // Adjust vertical position based on description presence
        $this->titleBaseTopOffset = $this->topOffset;
        if ($this->description === null) {
            $this->titleBaseTopOffset += (int)($fontSize * 1.5);
        }
    }

    /**
     * Set optional description text
     */
    public function setDescription(string $text)
    {
        $this->descriptionText = mb_convert_encoding(trim(str_replace(' ', ' ', $text)), 'UTF-8', 'auto');
    }

    /**
     * Configure description text styling and positioning
     */
    public function setDescriptionOptions(
        float $fontSize = 20,
        float $lineHeight = 1.8,
        array $colorRGB = [0, 0, 0],
        string $font = 'inter-medium.ttf',
        int $descriptionTopOffset = 10,
    ): void {
        if (!isset($this->descriptionText)) {
            throw new Exception('Please use `setDescription()` before configuring the description');
        }

        // Store description configuration
        $this->descriptionFontSize = $fontSize;
        $this->descriptionLineHeight = $lineHeight;
        $this->descriptionFont = $this->basePath . '/' . $font;
        $this->descriptionColor = $colorRGB;
        $this->descriptionTopOffset = $descriptionTopOffset;

        // Calculate text wrapping based on font size
        $this->descriptionMaxChars = (int)round($this->textBoxWidth / $fontSize * 1.5);
        $this->description = wordwrap($this->descriptionText, $this->descriptionMaxChars, "\n", true);
        $this->titleBaseTopOffset = $this->topOffset;

        $this->descriptionMaxChars += 10;
    }

    /**
     * Convenience method to save rendered image
     */
    public function save(string $file_path, int $quality = 80): void
    {
        $this->render($quality, $file_path);
    }

    /**
     * Render the image with configured text overlays
     */
    public function render(int $quality = 80, ?string $file_path = null, ?string $format = 'webp'): void
    {
        // Create image from template
        $template = @imagecreatefrompng($this->templateImage);
        if (!$template) {
            throw new RuntimeException('Failed to create image from template');
        }

        // Enable transparency support
        imagealphablending($template, true);
        imagesavealpha($template, true);

        // Render title text
        $titleColor = imagecolorallocate($template, ...$this->titleColor);
        $titleLines = (int)ceil(strlen($this->title) / $this->titleMaxChars);

        // Calculate vertical positioning
        $topOffset = ($this->titleBaseTopOffset - ($titleLines - 1.75) * $this->titleFontSize);

        // Adjust spacing for single line titles with short descriptions
        if ($titleLines === 1 && $this->description !== null && strlen($this->description) < $this->descriptionFontSize * 1.5) {
            $topOffset += $this->titleFontSize / 2;
        }

        // Render title and get bottom position
        $bottomOffset = $this->renderText(
            $template,
            $this->title,
            $this->titleFontSize,
            $this->titleLineHeight,
            $topOffset,
            $titleColor,
            $this->titleFont
        );

        // Render description if present
        if ($this->description !== null) {
            $descriptionColor = imagecolorallocate($template, ...$this->descriptionColor);

            // Calculate max description length based on title lines
            $truncate = ($titleLines === 1) ? $this->descriptionMaxChars * 3 : $this->descriptionMaxChars * 2 - 5;

            // Truncate description if needed
            if (strlen($this->description) > $truncate) {
                $lastChar = $this->description[$truncate] ?? '';
                $this->description = substr($this->description, 0, $truncate) . ($lastChar === '.' ? '.' : '…');
            }

            // Render description text
            $this->renderText(
                $template,
                $this->description,
                $this->descriptionFontSize,
                $this->descriptionLineHeight,
                $bottomOffset + $this->descriptionTopOffset,
                $descriptionColor,
                $this->descriptionFont
            );
        }

        // Output image
        if ($file_path) {
            $extension = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
            $quality = match ($extension) {
                'jpg', 'jpeg' => imagejpeg($template, $file_path, $quality),
                'png' => imagepng($template, $file_path, (int)($quality * 0.09)),
                'webp' => imagewebp($template, $file_path, $quality),
                'gif' => imagegif($template, $file_path),
                default => throw new RuntimeException('Unsupported image format: ' . $extension)
            };
        } else {
            header('Content-type: image/webp');
            imagewebp($template, null, $quality);
        }
    }

    /**
     * Helper function to render text with proper positioning
     * Returns the Y position after rendering for stacking multiple text blocks
     */
    private function renderText(
        $image,
        string $text,
        float $fontSize,
        float $lineHeight,
        float $y,
        $color,
        string $font
    ): float {
        $lines = explode("\n", mb_convert_encoding($text, 'UTF-8', 'auto'));
        $lineCount = count($lines);
        $angle = 0;

        foreach ($lines as $i => $line) {
            imagettftext(
                $image,
                $fontSize,
                $angle,
                (int)$this->leftOffset,
                (int)($y + ($i * $fontSize * $lineHeight)),
                $color,
                $font,
                $line
            );
        }

        return $y + ($lineCount * $fontSize * $lineHeight);
    }
}

tiny::registerHelper('opengraph', function($baseDir = 'ogfiles') {
    return new OpenGraphImage(tiny::$config->app_path . '/static/' . $baseDir);
});
