<?php

declare(strict_types=1);

/**
 * @throws ImagickException
 */
class Avatar
{
    /**
     * @throws ImagickException
     */
    private function generateImageMask(string $src, string $bg, string $fg, string $target): ?string
    {
        $im = new Imagick();
        $svg = file_get_contents($src);
        $svg = str_replace('fill:#FFFFFF;', 'fill:' . $bg . ';', $svg);
        $svg = str_replace('fill:#000000;', 'fill:' . $fg . ';', $svg);
        $im->readImageBlob($svg);
        $im->setImageFormat("png24");
        $im->resizeImage(128, 128, Imagick::FILTER_LANCZOS, 1);

        if ($target == 'base64') {
            $buffer = $im->getImageBlob();
            return 'data:image/png;base64,' . base64_encode($buffer);
        }
        $im->writeImage($target);
        $im->clear();
        return null;
    }

    /**
     * @throws ImagickException
     */
    public function generate(string $bg = '#5189fb', string $fg = '#ffffff'): ?string
    {
        $src = tiny::config()->app_path . '/html/' . tiny::config()->static_dir . '/img/agent.svg';
        return $this->generateImageMask($src, $bg, $fg, 'base64');
    }
}
// $src = tiny::config()->app_path . '/html/'. tiny::config()->static_dir .'/img/agent.svg';
// tiny::avatar()->generate($src, '#ff0000', '#ffff00', '/Users/ran/Desktop/avatar2.png');
// echo tiny::avatar()->generate($src, '#ff0000', '#ffff00', 'base64');


tiny::registerHelper('avatar', function() {
    return new Avatar();
});

// tiny::avatar()->generate(...);
