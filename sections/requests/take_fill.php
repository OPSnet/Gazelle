<?php

function print_or_return($JsonMsg, $Error = null) {
    if (defined('NO_AJAX_ERROR')) {
        return $JsonMsg;
    } else {
        json_or_error($JsonMsg, $Error);
    }
}

if (!defined('AJAX')) {
    authorize();
}

$request = (new Gazelle\Manager\Request)->findByid((int)$_REQUEST['requestid']);
if (is_null($request)) {
    error(404);
}

$error = [];
$torrent = null;
$tgMan = new Gazelle\Manager\Torrent;
if (!empty($_REEQUEST['torrentid'])) {
    $torrent = $tgMan->findById((int)$_REQUEST['torrentid']);
} else {
    if (empty($_REQUEST['link'])) {
        $error[] = print_or_return('You forgot to supply a link to the filling torrent');
    } else {
        if (preg_match(TORRENT_REGEXP, $_REQUEST['link'], $match)) {
            $torrent = $tgMan->findById((int)$match['id']);
        } else {
            $error[] = print_or_return('Your link does not appear to be valid (use the [PL] button to obtain the correct URL).');
        }
    }
}
if (is_null($torrent)) {
    $error[] = print_or_return('could not determine torrentid', 404);
}
if (!empty($_REQUEST['user']) && $Viewer->permitted('site_moderate_requests')) {
    $filler = (new Gazelle\Manager\User)->findByUsername($_REQUEST['user']);
    if (is_null($filler)) {
        $error[] = 'No such user to fill for!';
    }
} else {
    $filler = $Viewer;
}
if ($torrent->uploadGracePeriod()
    && $torrent->uploader()->id() !== $filler->id()
    && !$Viewer->permitted('site_moderate_requests')
) {
    $error[] = "There is a one hour grace period for new uploads to allow the uploader ("
        . $torrent->uploader()->username() . ") to fill the request.";
}

array_push($error, ...$request->validate($torrent));
if (count($error)) {
    echo print_or_return($error, implode('<br />', $error));
}

$request->fill($filler, $torrent);
if (defined('AJAX')) {
    $data = [
        'requestId'  => $request->id(),
        'torrentId'  => $torrent->id(),
        'fillerId'   => $filler->id(),
        'fillerName' => $filler->username(),
        'bounty'     => $requeset->bounty(),
    ];
    if ($_REQUEST['action'] === 'request_fill') {
        json_print('success', $data);
    } else {
        return $data;
    }
} else {
    header('Location: ' . $request->location());
}
