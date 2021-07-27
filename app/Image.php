<?php

namespace Gazelle;

class Image {
    protected $image;
    protected $height;
    protected $width;
    protected $type;

    public function __construct(string $data) {
        $this->image = imagecreatefromstring($data);
        if ($this->image) {
            [$this->height, $this->width, $this->type] = getimagesizefromstring($data);
        } else {
            [$this->height, $this->width, $this->type] = [0, 0, 0];
        }
    }

    function height(): int {
        return $this->height;
    }

    function width(): int {
        return $this->width;
    }

    function display() {
        switch($this->type) {
            case IMG_BMP:  return imagebmp($this->image);
            case IMG_GIF:  return imagegif($this->image);
            case IMG_JPG:  return imagejpeg($this->image, null, 90);
            case IMG_PNG:  return imagepng($this->image);
            case IMG_WEBP: return imagewebp($this->image);
            case IMG_XPM:  return imagexbm($this->image, null);
        }
    }

    function type(): string {
        switch($this->type) {
            case IMG_BMP:  return 'bmp';
            case IMG_GIF:  return 'gif';
            case IMG_JPG:  return 'jpg';
            case IMG_PNG:  return 'png';
            case IMG_WEBP: return 'webp';
            case IMG_XPM:  return 'xpm';
            default:       return 'error';
        }
    }

    public function error(): bool {
        return $this->type === 0;
    }

    public function invisible(): bool {
        $count = imagecolorstotal($this->image);
        if ($count == 0) {
            return false;
        }
        $alpha = 0;
        for ($i = 0; $i < $count; ++$i) {
            $color = imagecolorsforindex($this->image, $i);
            $alpha += $color['alpha'];
        }
        return $alpha / $count == 127;
    }

    public function verysmall(): bool {
        return $this->height * $this->width <= 256;
    }

    /**
     * Build and emit an image containing a simple text message.
     */
    public static function render(string $text) {
        $font = realpath(__DIR__ . '/../fonts/VERDANAB.TTF');
        $pointSize = 40.0;
        while (true) {
            [$left,, $right] = imageftbbox($pointSize, 0, $font, $text);
            $width = $right - $left;
            if ($width < 200) {
                break;
            }
            // too wide, but now we know what point size will make it fit
            $pointSize /= ($width + 10) / 200;
        }

        $image = imagecreatetruecolor(200, 200);
        $foreground = imagecolorallocate($image, 0x1f, 0xd5, 0x4f);
        $background = imagecolorallocate($image, 0x05, 0x14, 0x01);
        imagefill($image, 0, 0, $background);
        imagefttext($image, $pointSize, 0, (200 - $width) / 2, 120, $foreground, $font, $text);
        imagepng($image);
        imagedestroy($image);
    }

    /**
     * Debugging responses that return images can be tricky,
     * you cannot var_dump() your way out. Use this function
     * to create simple pngs that are stored in a temp
     * directory, and then you can have a look afterwards.
     */
    public static function debug(string $text) {
        ob_start();
        self::render($text);
        $data = ob_get_contents();
        ob_end_clean();
        $out = fopen(TMPDIR . "/$text.png", 'wb');
        fputs($out, $data);
        fclose($out);
    }
}
