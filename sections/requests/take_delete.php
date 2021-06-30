<?php

authorize();

$request = (new Gazelle\Manager\Request)->findById((int)$_POST['id']);
if (is_null($request)) {
    error(404);
}
if ($Viewer->id() != $request->userId() && !$Viewer->permitted('site_moderate_requests')) {
    error(403);
}

$reason = trim($_POST['reason']);
$title = $request->fullTitle();
if ($request->userId() !== $Viewer->id()) {
    (new Gazelle\Manager\User)->sendPM($request->userId(), 0,
        'A request you created has been deleted',
        "The request \"$title\" was deleted by [url=user.php?id=" . $Viewer->id() . "]"
            . $Viewer->username() . "[/url] for the reason: [quote]{$reason}[/quote]"
    );
}
$requestId = $request->id();
$request->remove();

(new Gazelle\Log)->general("Request $requestId ($title) was deleted by user "
    . $Viewer->label() . " for the reason: $reason"
);

header('Location: requests.php');
