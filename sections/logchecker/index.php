<?
enforce_login();
if (!empty($_REQUEST['action'])) {
	switch ($_REQUEST['action']) {
		case 'upload':
			require(SERVER_ROOT.'/sections/logchecker/takeupload.php');
			break;
		case 'takeupload':
			require('takeupload.php');
			break;
		case 'missinglogupload':
			require('missinglogupload.php');
			break;
                case 'snatched':
                        require('snatched.php');
                        break;
                case 'update':
                        require('update.php');
                        break;
		default:
			error(0);
	}
} else {
	require(SERVER_ROOT.'/sections/logchecker/upload.php');
}
?>
