<?

if ($_GET['type']) {
	switch ($_GET['type']) {
		case 'posts':
			// Load post history page
			include('post_history.php');
			break;
		default:
			print json_encode(
				['status' => 'failure']
				);
	}
} else {
	print json_encode(
		['status' => 'failure']
		);
}

?>
