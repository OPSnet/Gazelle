<?php
if (!function_exists('imagettftext')) {
    die('Captcha requires both the GD library and the FreeType library.');
}

function get_font() {
    return __DIR__ . '/../../fonts/' . CAPTCHA_FONT[mt_rand(0, count(CAPTCHA_FONT) - 1)];
}

function make_captcha_img() {
    $Length = 6;
    $ImageHeight = 75;
    $ImageWidth = 300;

    $Chars = 'abcdefghjkmprstuvwxyzABCDEFGHJKLMPQRSTUVWXY23456789';
    $CaptchaString = '';

    for ($i = 0; $i < $Length; $i++) {
        $CaptchaString .= $Chars[mt_rand(0,strlen($Chars) - 1)];
    }

    for ($x = 0; $x < $Length; $x++) {
        $FontDisplay[$x]['size'] = mt_rand(24, 32);
        $FontDisplay[$x]['top'] = mt_rand($FontDisplay[$x]['size'] + 5, $ImageHeight - ($FontDisplay[$x]['size'] / 2));
        $FontDisplay[$x]['angle'] = mt_rand(-30, 30);
        $FontDisplay[$x]['font'] = get_font();
    }

    $Img = imagecreatetruecolor($ImageWidth, $ImageHeight);
    $BGImg = imagecreatefrompng(__DIR__.'/../../captcha/' . CAPTCHA_BG[mt_rand(0, count(CAPTCHA_BG) - 1)]);
    imagecopymerge($Img, $BGImg, 0, 0, 0, 0, 300, 75, 50);

    $ForeColor = imagecolorallocatealpha($Img, 255, 255, 255, 65);

    for ($i = 0; $i < strlen($CaptchaString); $i++) {
        $CharX = (($ImageWidth / $Length) * ($i + 1)) - (($ImageWidth / $Length) * 0.75);
        imagettftext($Img,$FontDisplay[$i]['size'], $FontDisplay[$i]['angle'], $CharX,
                        $FontDisplay[$i]['top'], $ForeColor,
                        $FontDisplay[$i]['font'], $CaptchaString[$i]
                    );
    }

    header('Content-type: image/png');
    imagepng($Img);
    imagedestroy($Img);

    return $CaptchaString;
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$_SESSION['captcha'] = make_captcha_img();
