<?php

if (!$Viewer->permitted('site_proxy_images')) {
    header('Content-type: image/png');
    Gazelle\Image::render('403 forbidden');
    exit;
}

$url = isset($_GET['i']) ? urldecode($_GET['i']) : null;
$key = 'img2_' . md5($url);

$imageData = $Cache->get_value($key);
if ($imageData !== false) {
    $image = new Gazelle\Image($imageData);
} else {
    if (!preg_match(IMAGE_REGEXP, $url)) {
        header('Content-type: image/png');
        Gazelle\Image::render('bad url');
        exit;
    }

    $curl = new Gazelle\Util\Curl;
    if (!$curl->fetch($url)) {
        header('Content-type: image/png');
        Gazelle\Image::render("HTTP " . $curl->responseCode());
        exit;
    }

    $imageData = $curl->result();
    $image = new Gazelle\Image($imageData);
    if ($image->error()) {
        header('Content-type: image/png');
        Gazelle\Image::render('corrupt');
        exit;
    } elseif ($image->invisible()) {
        header('Content-type: image/png');
        Gazelle\Image::render('invisible');
        exit;
    } elseif ($image->verysmall()) {
        header('Content-type: image/png');
        Gazelle\Image::render('too small');
        exit;
    } else {
        $len = strlen($imageData);
        if (isset($_GET['c']) && $len > 0 && $len <= 262144) {
            $Cache->cache_value($key, $imageData, 86400 * 3);
        }
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
            $db = Gazelle\DB::DB();
            switch($usage) {
                case 'avatar':
                    $imageType = 'avatar';
                    $subject   = 'Your avatar has been automatically reset';
                    $db->prepared_query("
                        UPDATE users_info SET Avatar = '' WHERE UserID = ?  ", $userId
                    );
                    $Cache->delete_value("u_$userId");
                    break;
                case 'avatar2':
                    $imageType = 'second avatar';
                    $subject   = 'Your second avatar has been automatically reset';
                    $db->prepared_query("
                        UPDATE donor_rewards SET SecondAvatar = '' WHERE UserID = ?  ", $userId
                    );
                    $Cache->delete_value("donor_info_$userId");
                    break;
                case 'donoricon':
                    $imageType = 'donor icon';
                    $subject   = 'Your donor icon has been automatically reset';
                    $db->prepared_query("
                        UPDATE donor_rewards SET CustomIcon = '' WHERE UserID = ?  ", $userId
                    );
                    $Cache->delete_value("donor_info_$userId");
                    break;
                default:
                    error(0);
            }

            $sizeKb = number_format($sizeKb);
            $user = new \Gazelle\User($userId);
            $user->addStaffNote(
                ucfirst($imageType) . " $url reset automatically (Size: {$sizeKb}kB, Height: {$image->height()}px)."
            )->modify();
            (new Gazelle\Manager\User)->sendPM($userId, 0, $subject, $Twig->render('user/reset-avatar.twig', [
                'height'    => $maxHeight,
                'size_kb'   => $maxSizeKb,
                'type'      => $imageType,
                'url'       => $url,
            ]));
        }
    }
}

header("Content-type: image/" . $image->type());
$image->display();
