<?
enforce_login();
if (!check_perms('site_upload')) {
	error(403);
}
if ($LoggedUser['DisableUpload']) {
	error('Your upload privileges have been revoked.');
}
// build the page
if($_REQUEST['action'] == 'take_log') {
	include(SERVER_ROOT.'/sections/upload/take_log.php');
}
if (!empty($_POST['submit'])) {
	include('upload_handle.php');
} else {
	include(SERVER_ROOT.'/sections/upload/upload.php');
}
?>
