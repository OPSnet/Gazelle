<?php

use Gazelle\Inbox;

$recipient = (new Gazelle\Manager\User)->findById((int)$_GET['toid']);
if (is_null($recipient)) {
    error(404);
}
if ($Viewer->disablePm() && !isset($StaffIDs[$recipient->id()])) {
    error(403);
}
if (empty($Return) && $recipient->id() == $Viewer->id()) {
    error('You cannot start a conversation with yourself!');
    header('Location: ' . Inbox::getLinkQuick('inbox', $Viewer->option('ListUnreadPMsFirst') ?? false, Inbox::RAW));
}

View::show_header('Compose message', ['js' => 'inbox,bbcode,jquery.validate,form_validate']);
echo $Twig->render('inbox/compose.twig', [
    'auth'      => $Viewer->auth(),
    'body'      => $Body ?? '',
    'subject'   => $Subject ?? '',
    'recipient' => $recipient,
]);
View::show_footer();
