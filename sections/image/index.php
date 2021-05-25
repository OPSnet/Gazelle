<?php

if (!check_perms('site_proxy_images')) {
    header('Content-type: image/png');
    Gazelle\Image::render('403 forbidden');
    exit;
}

$url = isset($_GET['i']) ? urldecode($_GET['i']) : null;
$key = 'imagev4_' . md5($url);

// use a while loop to allow early exit
while (($imageData = $Cache->get_value($key)) === false) {
    if (!preg_match(IMAGE_REGEXP, $url)) {
        $imageData = null;
        $error = 'bad parameters';
        break;
    }

    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL            => $url,
        CURLOPT_HEADER         => false,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_USERAGENT      => FAKE_USERAGENT,
    ]);
    if (defined('HTTP_PROXY')) {
        curl_setopt_array($curl, [
            CURLOPT_HTTPPROXYTUNNEL => true,
            CURLOPT_PROXY           => HTTP_PROXY,
        ]);
    }

    $imageData = curl_exec($curl);
    $rescode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);
    if ($rescode != 200) {
        $error = "HTTP $rescode";
        break;
    }

    $len = strlen($imageData);
    if (isset($_GET['c']) && $len > 0 && $len <= 262144) {
        $Cache->cache_value($key, $imageData, 86400 * 3);
    }
    break; // all good
}

if (!isset($error)) {
    $image = new Gazelle\Image($imageData);
    if ($image->error()) {
        $error = 'corrupt';
    }
    elseif ($image->invisible()) {
        $error = 'invisible';
    }
    elseif ($image->verysmall()) {
        $error = 'too small';
    }
}

if (isset($error)) {
    $Cache->delete_value($key);
    header('Content-type: image/png');
    Gazelle\Image::render($error);
    exit;
}

if (isset($_GET['type']) && isset($_GET['userid'])) {
    $userId = (int)$_GET['userid'];
    if ($userId < 1) {
        $Cache->delete_value($key);
        header('Content-type: image/png');
        Gazelle\Image::render('no such user');
        exit;
    }

    $usage  = $_GET['type'];
    switch($usage) {
        case 'avatar':
            $maxHeight = 400; // pixels
            $maxSizeKb = 256;
            break;
        case 'avatar2':
            $maxHeight = 400;
            $maxSizeKb = 256;
            break;
        case 'donoricon':
            $maxHeight = 100;
            $maxSizeKb = 64;
            break;
        default:
            $Cache->delete_value($key);
            header('Content-type: image/png');
            Gazelle\Image::render('bad image type');
            exit;
    }

    $sizeKb = strlen($imageData) / 1024;
    if ($sizeKb > $maxSizeKb || $image->height() > $maxHeight) {
        switch($usage) {
            case 'avatar':
                $imageType = 'avatar';
                $subject   = 'Your avatar has been automatically reset';
                $DB->prepared_query("
                    UPDATE users_info SET Avatar = '' WHERE UserID = ?  ", $userId
                );
                $Cache->delete_value("user_info_$userId");
                break;
            case 'avatar2':
                $imageType = 'second avatar';
                $subject   = 'Your second avatar has been automatically reset';
                $DB->prepared_query("
                    UPDATE donor_rewards SET SecondAvatar = '' WHERE UserID = ?  ", $userId
                );
                $Cache->delete_value("donor_info_$userId");
                break;
            case 'donoricon':
                $imageType = 'donor icon';
                $subject   = 'Your donor icon has been automatically reset';
                $DB->prepared_query("
                    UPDATE donor_rewards SET CustomIcon = '' WHERE UserID = ?  ", $userId
                );
                $Cache->delete_value("donor_info_$userId");
                break;
        }

        $sizeKb = number_format($sizeKb);
        $user = new \Gazelle\User($userId);
        $user->addStaffNote(
            ucfirst($imageType) . " $url reset automatically (Size: {$sizeKb}kB, Height: {$image->height()}px)."
        )->modify();
        (new Gazelle\Manager\User)->sendPM($userId, 0, $subject, $Twig->render('user/reset-avatar.twig', [
            'height'    => $maxHeight,
            'size_kb'   => $sizeKb,
            'type'      => $imageType,
            'url'       => $url,
        ]));
    }
}

header("Content-type: image/" . $image->type());
$image->display();
