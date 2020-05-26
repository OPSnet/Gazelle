<?php

enforce_login();

$siteLog = new \Gazelle\Manager\SiteLog($Debug);

View::show_header("Site log");

if (!empty($_GET['page']) && is_number($_GET['page'])) {
    $page = min(SPHINX_MAX_MATCHES / LOG_ENTRIES_PER_PAGE, (int)$_GET['page']);
    $offset = ($page - 1) * LOG_ENTRIES_PER_PAGE;
} else {
    $page = 1;
    $offset = 0;
}

$siteLog->load($page, $offset, $_GET['search'] ?? '');
?>
<div class="thin">
    <div class="header">
        <h2>Site log</h2>
    </div>
    <div class="box pad">
        <form class="search_form" name="log" action="" method="get">
            <table cellpadding="6" cellspacing="1" border="0" class="layout border" width="100%">
                <tr>
                    <td class="label"><strong>Search for:</strong></td>
                    <td>
                        <input type="search" name="search" size="60"<?=(!empty($_GET['search']) ? ' value="'.display_str($_GET['search']).'"' : '')?> />
                        &nbsp;
                        <input type="submit" value="Search log" />
                    </td>
                </tr>
            </table>
        </form>
    </div>

<?php
if ($siteLog->totalMatches() > LOG_ENTRIES_PER_PAGE) {
    $pages = Format::get_pages($page, $siteLog->totalMatches(), LOG_ENTRIES_PER_PAGE, 9);
?>
    <div class="linkbox">
        <?= $pages ?>
    </div>
<?php } ?>
    <table cellpadding="6" cellspacing="1" border="0" class="log_table border" id="log_table" width="100%">
        <tr class="colhead">
            <td style="width: 180px;"><strong>Time</strong></td>
            <td><strong>Message<? ($_GET['search'] ?? null) ? (' "' . $_GET['search'] . '"') : '' ?></strong></td>
        </tr>
<?php if ($siteLog->error()) { ?>
    <tr class="nobr"><td colspan="2">Search request failed (<?= $siteLog->errorMessage() ?>).</td></tr>
<?php
}
$row = 'a';
$count = 0;
foreach ($siteLog->next() as $event) {
    list($logId, $message, $logTime) = $event;
    ++$count;
    list ($color, $message) = $siteLog->colorize($message);
    $row = $row === 'a' ? 'b' : 'a';
?>
        <tr class="row<?= $row ?>" id="log_<?= $logId ?>">
            <td class="nobr">
                <?= time_diff($logTime) ?>
            </td>
            <td>
                <span<?= $color ? (' style="color:' . $color .';"') : '' ?>><?= $message ?></span>
            </td>
        </tr>
<?php
}
if (!$siteLog->error() && !$count) { ?>
    <tr class="nobr"><td colspan="2">Nothing found!</td></tr>
<?php } ?>
    </table>
<?php if ($siteLog->totalMatches() > LOG_ENTRIES_PER_PAGE) { ?>
    <div class="linkbox">
        <?= $pages ?>
    </div>
<?php  } ?>
</div>
<?php

View::show_footer();
