<?php

use Endroid\QrCode\QrCode;
use Endroid\QrCode\ErrorCorrectionLevel;

if (session_status() === PHP_SESSION_NONE) {
    session_start(['read_and_close' => true]);
}

$valid = true;
$auth = new RobThree\Auth\TwoFactorAuth;
if (!empty($_SESSION['private_key'])) {
    $secret = $_SESSION['private_key'];
    if (isset($_POST['2fa'])) {
        var_dump([$secret, $_POST]);
        if ($auth->verifyCode($secret, trim($_POST['2fa']), 2)) {
            header('Location: user.php?action=2fa&do=complete&userid=' . $userId);
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

$qrCode = new QrCode('otpauth://totp/' . SITE_NAME . "?secret=$secret");
$qrCode->setSize(400);
$qrCode->setMargin(20);
$qrCode->setErrorCorrectionLevel(ErrorCorrectionLevel::HIGH());
$qrCode->setForegroundColor(['r' =>   0, 'g' =>   0, 'b' =>   0, 'a' => 0]);
$qrCode->setBackgroundColor(['r' => 255, 'g' => 255, 'b' => 255, 'a' => 0]);

View::show_header('Two-factor Authentication');

echo $Twig->render('user/2fa/configure.twig', [
    'valid'    => $valid,
    'qrcode'   => $qrCode,
    'secret'   => $secret,
    'user_id'  => $LoggedUser['ID'],
    'utc_time' => gmdate('Y-m-d H:i:s', time()),
]);
View::show_footer();
