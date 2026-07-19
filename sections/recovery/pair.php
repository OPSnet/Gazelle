<?php

/**
 * @phpstan-var \Gazelle\User $Viewer
 * @phpstan-var \Twig\Environment $Twig
 */

declare(strict_types=1);

namespace Gazelle;

if (!$Viewer->permitted('admin_recovery')) {
    Error403::error();
}

$prev     = false;
$prevId   = false;
$currId   = false;
$result   = false;
$confirm  = false;
$message  = false;
$recovery = new Manager\Recovery();

if (isset($_POST['curr']) && isset($_POST['prev'])) {
    authorize();
    $userMan  = new Manager\User();
    $currId = (int)trim($_POST['curr']);
    $curr = $userMan->findById($currId);
    if (is_null($curr)) {
        $message = "No current ID found for <tt>$currId</tt>.";
    } else {
        $prevId = (int)trim($_POST['prev']);
        $prev   = $recovery->findById($prevId);
        if (!$prev) {
            $message = "No previous ID found for <tt>$prevId</tt>.";
        } elseif ($Map = $recovery->isMapped($prevId)) {
            $user = $userMan->findById($Map[0]['ID']);
            $message = "Previous id $prevId already mapped to " . ($user ? $user->username() : "ID {$Map[0]['ID']}");
        } elseif ($Map = $recovery->isMappedLocal($currId)) {
            $message = $curr->username() . " is already mapped to previous id {$Map[0]['ID']}";
        } else {
            [$prev, $confirm] = $recovery->pairConfirmation($prevId, $currId);
            if (!($prev && $confirm)) {
                $message = "No database information to pair from $prevId to $currId";
            }
            $message = $recovery->mapToPrevious($curr, $prevId, $Viewer)
                ? $curr->username() . " has been successfully mapped to previous user {$confirm['Username']}."
                : "DB Error: could not map $currId to $prevId";
            $confirm = false;
        }
    }
}

echo $Twig->render('recovery/pair.twig', [
    'confirm' => $confirm,
    'curr_id' => $currId,
    'prev_id' => $prevId,
    'prev'    => $prev,
    'message' => $message,
    'viewer'  => $Viewer,
]);
