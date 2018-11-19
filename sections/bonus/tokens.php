<?php
authorize();

if (!preg_match('/^(token|other)-[123]$/', $Label, $match)) {
    error(403);
}

if ($match[1] === 'token') {
    if (!$Bonus->purchaseToken(G::$LoggedUser['ID'], $Label, G::$LoggedUser)) {
        error('Purchase not concluded.');
    }
} else {
    if (empty($_GET['user'])) {
        error('You have to enter a username to give tokens to.');
    }
    $ID = Users::ID_from_username(urldecode($_GET['user']));
    if (is_null($ID)) {
        error('Invalid username. Please select a valid user');
    } elseif ($ID == G::$LoggedUser['ID']) {
        error('You cannot give yourself tokens.');
    }
    if (!$Bonus->purchaseTokenOther(G::$LoggedUser['ID'], $ID, $Label, G::$LoggedUser)) {
        error('Purchase for other not concluded.');
    }
}

header('Location: bonus.php?complete=' . urlencode($Label));
