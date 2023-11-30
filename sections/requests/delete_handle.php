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
$title = $request->text();
if ($request->userId() !== $Viewer->id()) {
    $user = (new Gazelle\Manager\User)->findById($request->userId());
    if ($user) {
        $user->inbox()->createSystem(
            'A request you created has been deleted',
            "The request \"$title\" was deleted by [url=" . $Viewer->url() . "]"
                . $Viewer->username() . "[/url] for the reason: [quote]{$reason}[/quote]"
        );
    }
}
$requestId = $request->id();
$request->remove();

(new Gazelle\Log)->general("Request $requestId ($title) was deleted by user "
    . $Viewer->label() . " for the reason: $reason"
);

header('Location: requests.php');
