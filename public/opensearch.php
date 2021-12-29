<?php

header('Content-type: application/opensearchdescription+xml');

require_once(__DIR__ . '/../lib/config.php');

$Type = in_array(($_GET['type'] ?? ''), ['torrents','artists','requests','forums','users','wiki','log'])
    ? $_GET['type']
    : 'artists';

echo '<?xml version="1.0" encoding="UTF-8"?>'; ?>

<OpenSearchDescription xmlns="http://a9.com/-/spec/opensearch/1.1/" xmlns:moz="http://www.mozilla.org/2006/browser/search/">
    <ShortName><?=SITE_NAME.' '.ucfirst($Type)?> </ShortName>
    <Description>Search <?=SITE_NAME?> for <?=ucfirst($Type)?></Description>
    <Developer></Developer>
    <Image width="16" height="16" type="image/x-icon<?= SITE_URL ?>/favicon.ico</Image>
<?php
switch ($Type) {
    case 'artists':
?>
    <Url type="text/html" method="get" template="<?=SITE_URL?>/artist.php?artistname={searchTerms}"></Url>
    <moz:SearchForm><?=SITE_URL?>/torrents.php?action=advanced</moz:SearchForm>
<?php
        break;
    case 'torrents':
?>
    <Url type="text/html" method="get" template="<?=SITE_URL?>/torrents.php?action=basic&amp;searchstr={searchTerms}"></Url>
    <moz:SearchForm><?=SITE_URL?>/torrents.php</moz:SearchForm>
<?php
        break;
    case 'requests':
?>
    <Url type="text/html" method="get" template="<?=SITE_URL?>/requests.php?search={searchTerms}"></Url>
    <moz:SearchForm><?=SITE_URL?>/requests.php</moz:SearchForm>
<?php
        break;
    case 'forums':
?>
    <Url type="text/html" method="get" template="<?=SITE_URL?>/forums.php?action=search&amp;search={searchTerms}"></Url>
    <moz:SearchForm><?=SITE_URL?>/forums.php?action=search</moz:SearchForm>
<?php
        break;
    case 'users':
?>
    <Url type="text/html" method="get" template="<?=SITE_URL?>/user.php?action=search&amp;search={searchTerms}"></Url>
    <moz:SearchForm><?=SITE_URL?>/user.php?action=search</moz:SearchForm>
<?php
        break;
    case 'wiki':
?>
    <Url type="text/html" method="get" template="<?=SITE_URL?>/wiki.php?action=search&amp;search={searchTerms}"></Url>
    <moz:SearchForm><?=SITE_URL?>/wiki.php?action=search</moz:SearchForm>
<?php
        break;
    case 'log':
?>
    <Url type="text/html" method="get" template="<?=SITE_URL?>/log.php?search={searchTerms}"></Url>
    <moz:SearchForm><?=SITE_URL?>/log.php</moz:SearchForm>
<?php
        break;
}
?>
    <Url type="application/opensearchdescription+xml" rel="self" template="<?=SITE_URL?>/opensearch.php?type=<?=$Type?>" />
    <Language>en-us</Language>
    <OutputEncoding>UTF-8</OutputEncoding>
    <InputEncoding>UTF-8</InputEncoding>
</OpenSearchDescription>
