<?php

if (!isset($_GET['id'])) {
    header('Location: staffpm.php');
    exit;
}
$manager = new Gazelle\Manager\StaffPM;
$staffPM = $manager->findById((int)($_GET['id'] ?? 0));
if (is_null($staffPM)) {
    error(404);
}
$viewer = new Gazelle\User($LoggedUser['ID']);
if (!$staffPM->visible($viewer)) {
    error(403);
}
if ($staffPM->author()->id() === $viewer->id() && $staffPM->isUnread()) {
    // User is viewing their own unread conversation, set it to read
    $staffPM->markAsRead($viewer);
}
$userMan = new Gazelle\Manager\User;

View::show_header('Staff PM', 'staffpm,bbcode');
echo $Twig->render('staffpm/message.twig', [
    'common'      => $manager->commonAnswerList(),
    'heading'     => $manager->heading($viewer),
    'pm'          => $staffPM,
    'textarea'    => new TEXTAREA_PREVIEW('message', 'quickpost', '', 90, 10, false, false, true),
    'staff_level' => $userMan->staffLevelList(),
    'staff'       => $userMan->staffList(),
    'fls'         => $userMan->FLSList(),
    'viewer'      => $viewer,
]);
View::show_footer();
