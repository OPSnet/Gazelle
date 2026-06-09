<?php
/** @phpstan-var \Gazelle\User $Viewer */
/** @phpstan-var \Twig\Environment $Twig */

declare(strict_types=1);

namespace Gazelle;

$manager = new Manager\StaffPM();
$staffPM = $manager->findById((int)($_GET['id'] ?? 0));
if (is_null($staffPM)) {
    Error404::error();
}
if (!$staffPM->visible($Viewer)) {
    Error403::error();
}
if ($staffPM->userId() === $Viewer->id && $staffPM->isUnread()) {
    // User is viewing their own unread conversation, set it to read
    $staffPM->markAsRead($Viewer);
}
$userMan = new Manager\User();

echo $Twig->render('staffpm/message.twig', [
    'author'      => $userMan->findById($staffPM->userId()),
    'common'      => $manager->commonAnswerList(),
    'heading'     => $manager->heading($Viewer),
    'pm'          => $staffPM,
    'textarea'    => new Util\Textarea('quickpost', '', 90, 10),
    'staff_level' => $userMan->staffClassList(),
    'staff'       => $userMan->staffList(),
    'fls'         => $userMan->flsList(),
    'viewer'      => $Viewer,
]);
