<?php

if (!$Viewer->permitted('users_mod')) {
    error(403);
}

$userMan = new Gazelle\Manager\User();
$amount = (int)($_POST['numtokens'] ?? 0);
$flAdded = false;
$flCleared = false;
if (isset($_POST['addtokens'])) {
    authorize();
    if ($amount < 1) {
        error('Please enter a valid number of tokens.');
    }
    $flAdded = $userMan->addMassTokens($amount, isset($_POST['allowleechdisabled']));
} elseif (isset($_POST['cleartokens'])) {
    authorize();
    if ($amount < 0) {
        error('Please enter a valid number of tokens.');
    }
    $flCleared = $userMan->clearMassTokens($amount, isset($_POST['allowleechdisabled']), isset($_POST['onlydrop']));
}

echo $Twig->render('admin/freeleech-tokens.twig', [
    'amount'         => $amount,
    'auth'           => $Viewer->auth(),
    'fl_added'       => $flAdded,
    'fl_cleared'     => $flCleared,
    'leech_disabled' => $_POST['allowleechdisabled'] ?? true,
    'only_drop'      => $_POST['onlydrop'] ?? false,
]);
