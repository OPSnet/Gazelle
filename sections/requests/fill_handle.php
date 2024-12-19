<?php
// phpcs:disable PSR1.Files.SideEffects.FoundWithSymbols

function print_or_return(string $message, int|null $error = null) {
    // this function is a crime against humanity
    if (defined('NO_AJAX_ERROR')) {
        return $message;
    } else {
        json_or_error($message, $error);
    }
}

/** @phpstan-var \Gazelle\User $Viewer */

if (!defined('AJAX')) {
    authorize();
}

$request = (new Gazelle\Manager\Request())->findById((int)$_REQUEST['requestid']);
if (is_null($request)) {
    error(404);
}

$error   = [];
$torrent = null;
$tgMan   = new Gazelle\Manager\Torrent();
$isAdmin = $Viewer->permitted('site_moderate_requests');
if (!empty($_REQUEST['torrentid'])) {
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

if (!empty($_REQUEST['user']) && $isAdmin) {
    $filler = (new Gazelle\Manager\User())->findByUsername($_REQUEST['user']);
    if (is_null($filler)) {
        $error[] = 'No such user to fill for!';
    }
} else {
    $filler = $Viewer;
}

array_push($error, ...$request->validate($torrent, $filler, $isAdmin));
if (count($error)) {
    echo print_or_return(implode('<br />', $error));
}

$request->fill($filler, $torrent);
if (defined('AJAX')) {
    $data = [
        'requestId'  => $request->id(),
        'torrentId'  => $torrent->id(),
        'fillerId'   => $filler->id(),
        'fillerName' => $filler->username(),
        'bounty'     => $request->bounty(),
    ];
    if ($_REQUEST['action'] === 'request_fill') {
        json_print('success', $data);
    } else {
        return $data;
    }
} else {
    header('Location: ' . $request->location());
}
