<?php

use Gazelle\Inbox;

$ToID = (int)$_GET['toid'];
if (!$ToID) {
    error(404);
}
if (!empty($LoggedUser['DisablePM']) && !isset($StaffIDs[$ToID])) {
    error(403);
}
if (empty($Return)) {
    if ($ToID == $Viewer->id()) {
        error('You cannot start a conversation with yourself!');
        header('Location: ' . Inbox::getLinkQuick('inbox', $LoggedUser['ListUnreadPMsFirst'] ?? false, Inbox::RAW));
    }
}

$Username = $DB->scalar("
    SELECT Username FROM users_main WHERE ID = ?
    ", $ToID
);
if (!$Username) {
    error(404);
}

View::show_header('Compose', ['js' => 'inbox,bbcode,jquery.validate,form_validate']);
echo $Twig->render('inbox/compose.twig', [
    'auth'     => $Viewer->auth(),
    'body'     => $Body ?? '',
    'subject'  => $Subject ?? '',
    'toid'     => $ToID,
    'username' => $Username,
]);
View::show_footer();
