<?php
/** @phpstan-var \Gazelle\User $Viewer */

declare(strict_types=1);

namespace Gazelle;

authorize();

$request = new Manager\Request()->findById((int)$_POST['id']);
if (is_null($request)) {
    Error404::error();
}
if ($Viewer->id !== $request->userId() && !$Viewer->permitted('site_moderate_requests')) {
    Error403::error();
}

$reason = trim($_POST['reason']);
$title = $request->text();
if ($request->userId() !== $Viewer->id) {
    $user = new Manager\User()->findById($request->userId());
    if ($user) {
        $user->inbox()->createSystem(
            'A request you created has been deleted',
            "The request \"$title\" was deleted by [url={$Viewer->url()}]{$Viewer->username()}[/url] for the reason: [quote]{$reason}[/quote]"
        );
    }
}
$requestId = $request->id;
$request->remove();

$Viewer->logger()->general(
    "Request $requestId ($title) was deleted by user {$Viewer->label()} for the reason: $reason"
);

header('Location: requests.php');
