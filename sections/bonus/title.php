<?php

if (isset($_GET['Remove']) && $_GET['Remove'] === 'true') {
	G::$DB->query("UPDATE users_main SET Title='' WHERE ID={$ID}");
	G::$Cache->delete_value("user_info_{$ID}");
	G::$Cache->delete_value("user_stats_{$ID}");
}
elseif (isset($_GET['confirm'])) {
	if (!isset($_GET['Title'])) {
		error(403);
	}
	$Option = (isset($_GET['BBCode']) && $_GET['BBCode'] === 'true') ? 'title_bbcode' : 'title_nobbcode';
	$Price = $Options[$Option]['Price'];
	if ($Price > G::$LoggedUser['BonusPoints']) {
		error('You cannot afford this item');
	}
	if (!isset($_GET['BBCode']) || $_GET['BBCode'] !== 'true') {
		$_GET['Title'] = Text::strip_bbcode($_GET['Title']);
	}
	$ID = G::$LoggedUser['ID'];
	G::$DB->query("UPDATE users_main SET Title='".db_string($_GET['Title'])."', BonusPoints=BonusPoints - {$Price} WHERE ID={$ID}");
	G::$Cache->delete_value("user_info_{$ID}");
	G::$Cache->delete_value("user_stats_{$ID}");
}

// Show the confirmation page with a preview of the page