<?php
global $LoggedUser, $SSL;
define('FOOTER_FILE',SERVER_ROOT.'/design/publicfooter.php');
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
<head>
    <title><?=display_str($PageTitle)?></title>
    <meta http-equiv="X-UA-Compatible" content="chrome=1; IE=edge" />
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <meta name="referrer" content="none, no-referrer, same-origin" />
    <link rel="shortcut icon" href="favicon.ico" />
    <link rel="apple-touch-icon" href="/apple-touch-icon.png" />
    <meta name="viewport" content="width=device-width; initial-scale=1.0;" />
    <link href="<?=STATIC_SERVER ?>styles/public/style.css?v=<?=filemtime(SERVER_ROOT."/static/styles/public/style.css")?>" rel="stylesheet" type="text/css" />
    <script src="<?=STATIC_SERVER?>functions/jquery.js" type="text/javascript"></script>
    <?=(DEBUG_MODE || check_perms('site_debug')) ? '<script src="' . STATIC_SERVER . 'functions/jquery-migrate.js" type="text/javascript"></script>' : ''?>
    <script src="<?=STATIC_SERVER?>functions/script_start.js?v=<?=filemtime(SERVER_ROOT.'/public/static/functions/script_start.js')?>" type="text/javascript"></script>
    <script src="<?=STATIC_SERVER?>functions/ajax.class.js?v=<?=filemtime(SERVER_ROOT.'/public/static/functions/ajax.class.js')?>" type="text/javascript"></script>
    <script src="<?=STATIC_SERVER?>functions/cookie.class.js?v=<?=filemtime(SERVER_ROOT.'/public/static/functions/cookie.class.js')?>" type="text/javascript"></script>
    <script src="<?=STATIC_SERVER?>functions/storage.class.js?v=<?=filemtime(SERVER_ROOT.'/public/static/functions/storage.class.js')?>" type="text/javascript"></script>
    <script src="<?=STATIC_SERVER?>functions/global.js?v=<?=filemtime(SERVER_ROOT.'/public/static/functions/global.js')?>" type="text/javascript"></script>
</head>
<body>
<div id="maincontent">
