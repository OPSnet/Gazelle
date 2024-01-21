<?php

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Color\Color;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Writer\PngWriter;

if (session_status() === PHP_SESSION_NONE) {
    session_start(['read_and_close' => true]);
}

$valid = true;
$auth = new RobThree\Auth\TwoFactorAuth;
if (!empty($_SESSION['private_key'])) {
    $secret = $_SESSION['private_key'];
    if (isset($_POST['2fa'])) {
        if ($auth->verifyCode($secret, trim($_POST['2fa']), 2)) {
            header('Location: user.php?action=2fa&do=complete&userid=' . $Viewer->id());
            exit;
        }
        $valid = false;
    }
} else {
    $secret = $auth->createSecret();
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION['private_key'] = $secret;
    session_write_close();
}

$qrCode = Builder::create()
    ->writer(new PngWriter())
    ->writerOptions([])
    ->data('otpauth://totp/' . SITE_NAME . "?secret=$secret")
    ->encoding(new Encoding('UTF-8'))
    ->errorCorrectionLevel(ErrorCorrectionLevel::High)
    ->size(400)
    ->margin(20)
    ->foregroundColor(new Color(0, 0, 0, 0))
    ->backgroundColor(new Color(255, 255, 255, 0))
    ->build();

echo $Twig->render('user/2fa/configure.twig', [
    'valid'    => $valid,
    'qrcode'   => $qrCode,
    'secret'   => $secret,
    'user_id'  => $Viewer->id(),
    'utc_time' => gmdate('Y-m-d H:i:s', time()),
]);
