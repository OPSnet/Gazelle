<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width">
    <meta name="referrer" content="none, no-referrer, same-origin" />
    <title><?=display_str($PageTitle)?></title>
    <link rel="shortcut icon" href="/favicon.ico" />
    <link rel="apple-touch-icon" href="/apple-touch-icon.png" />
    <link href="<?=STATIC_SERVER ?>/styles/public/style.css?v=<?=filemtime(SERVER_ROOT."/static/styles/public/style.css")?>" rel="stylesheet" type="text/css" />
<?php if (DEBUG_MODE) { ?>
    <script src="S<?=TATIC_SERVER?>/functions/jquery-migrate.js" type="text/javascript"></script>
<?php } ?>
    <script src="<?=STATIC_SERVER?>/functions/jquery.js" type="text/javascript"></script>
    <script src="<?=STATIC_SERVER?>/functions/script_start.js?v=<?=filemtime(SERVER_ROOT.'/public/static/functions/script_start.js')?>" type="text/javascript"></script>
    <script src="<?=STATIC_SERVER?>/functions/ajax.class.js?v=<?=filemtime(SERVER_ROOT.'/public/static/functions/ajax.class.js')?>" type="text/javascript"></script>
    <script src="<?=STATIC_SERVER?>/functions/cookie.class.js?v=<?=filemtime(SERVER_ROOT.'/public/static/functions/cookie.class.js')?>" type="text/javascript"></script>
    <script src="<?=STATIC_SERVER?>/functions/storage.class.js?v=<?=filemtime(SERVER_ROOT.'/public/static/functions/storage.class.js')?>" type="text/javascript"></script>
    <script src="<?=STATIC_SERVER?>/functions/global.js?v=<?=filemtime(SERVER_ROOT.'/public/static/functions/global.js')?>" type="text/javascript"></script>
</head>
<body>
<div id="maincontent">
