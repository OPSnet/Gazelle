<?php
enforce_login();

if (G::$LoggedUser['DisablePoints']) {
	error('Your points have been disabled.');
}

if (isset($_GET['action'])) {
	switch ($_GET['action']) {
		case 'bprates':
			require_once(SERVER_ROOT . '/sections/bonus/bprates.php');
			break;
		case 'title':
			require_once(SERVER_ROOT . '/sections/bonus/title.php');
			break;
		case 'tokens':
			require_once(SERVER_ROOT . '/sections/bonus/tokens.php');
			break;
		default:
			require_once(SERVER_ROOT . '/sections/bonus/store.php');
			break;
	}
}
else {
	require_once(SERVER_ROOT . '/sections/bonus/store.php');
}
