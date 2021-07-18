<?php
if (!check_perms('admin_reports')) {
    error(403);
}

$reportMan = new Gazelle\Manager\ReportV2;
$Types = $reportMan->types();
$categories = $reportMan->categories();
$reportNameCache = [];
foreach ($categories as $label => $key) {
    foreach (array_keys($Types[$label]) as $type) {
        $reportNameCache[$type] = $Types[$label][$type]['title'] . " ($key)";
    }
}

$filter = [];
if (isset($_GET['report-type'])) {
    foreach ($_GET['report-type'] as $t) {
        if (array_key_exists($t, $reportNameCache)) {
            $filter['report-type'][] = $t;
        }
    }
}
foreach(['reporter', 'handler', 'uploader'] as $role) {
    if (isset($_GET[$role]) && preg_match('/([\w.-]+)/', $_GET[$role], $match)) {
        $filter[$role] = $match[1];
    }
}
if (isset($_GET['torrent'])) {
    if (preg_match('/^\s*(\d+)\s*$/', $_GET['torrent'], $match)) {
        $filter['torrent'] = $match[1];
    }
    elseif (preg_match('#^https?://[^/]+/torrents\.php.*torrentid=(\d+)#', $_GET['torrent'], $match)) {
        $filter['torrent'] = $match[1];
    }
}
if (isset($_GET['group'])) {
    if (preg_match('/^\s*(\d+)\s*$/', $_GET['group'], $match)) {
        $filter['group'] = $match[1];
    }
    elseif (preg_match('#^https?://[^/]+/torrents\.php.*[?&]id=(\d+)#', $_GET['group'], $match)) {
        $filter['group'] = $match[1];
    }
}
if (isset($_GET['dt-from']) && preg_match('/(\d\d\d\d-\d\d-\d\d)/', $_GET['dt-from'], $match)) {
    $filter['dt-from'] = $match[1];
    $dt_from = $match[1];
} else {
    $dt_from  = date('Y-m-d', strtotime(date('Y-m-d', strtotime(date('Y-m-d'))) . '-1 month'));
}
if (isset($_GET['dt-until']) && preg_match('/(\d\d\d\d-\d\d-\d\d)/', $_GET['dt-until'], $match)) {
    $filter['dt-until'] = $match[1];
    $dt_until = $match[1];
} else {
    $dt_until = date('Y-m-d');
}

$paginator = new Gazelle\Util\Paginator(TORRENTS_PER_PAGE, (int)($_GET['page'] ?? 1));
$userMan = new Gazelle\Manager\User;

if ($filter) {
    $reportMan->setSearchFilter($filter)->setUserManager($userMan);
    try {
        $paginator->setTotal($reportMan->searchTotal());
    } catch (Gazelle\Exception\ResourceNotFoundException $e) {
        error("User not found: " . $e->getMessage());
    }
    $page = $reportMan->searchPage($paginator->limit(), $paginator->offset());
}
View::show_header('Reports V2', ['js' => 'reportsv2']);
?>

<div class="header">
    <h2>Search Reports</h2>
<?php require_once('header.php'); ?>
</div>

<?php
if ($filter) {
    echo $paginator->linkbox();
?>
<div class="thin box pad">
    <table>
        <thead>
            <tr>
                <td>Report</td>
                <td class="nowrap">Uploaded by</td>
                <td class="nowrap">Reported by</td>
                <td class="nowrap">Resolved by</td>
                <td>Torrent</td>
                <td class="nowrap">Report type</td>
                <td class="nowrap" width="120px">Date reported</td>
            </tr>
        </thead>
        <tbody>
<?php
    foreach ($page as $r) {
        if (!$r['GroupID']) {
            $name = $r['Name'];
        } else {
            $name = Artists::display_artists(Artists::get_artist($r['GroupID']))
                . sprintf('<a href=/torrents.php?id=%d&torrentid=%d#torrent%d>%s</a>',
                    $r['GroupID'], $r['TorrentID'], $r['TorrentID'], display_str($r['Name'])
                )
                . " [" . $r['Year'] . ']';
        }
?>
            <tr>
                <td align="right"><a href="/reportsv2.php?view=report&id=<?= $r['ID'] ?>"><?= $r['ID'] ?></a></td>
                <td><?= $r['uploader_username'] ?: '<i>unknown</i>' ?></td>
                <td><?= $r['reporter_username'] ?></td>
                <td><?= $r['resolver_username'] ?: '<i>unclaimed</i>' ?></td>
                <td><?= $name ?></td>
                <td class="nowrap"><?= $reportNameCache[$r['Type']] ?></td>
                <td><?= time_diff($r['ReportedTime']) ?></td>
            </tr>
<?php } ?>
        </tbody>
    </table>
</div>
<?= $paginator->linkbox() ?>
<br />
<?php } ?>

<div class="thin box pad">
    <form method="get" action="/reportsv2.php">
        <table>
            <tr>
                <td width="150px">Uploaded by</td>
                <td><input type="text" name="uploader" size="20" value="<?= isset($_GET['uploader']) ? $_GET['uploader'] : '' ?>" /></td>
            </tr>
            <tr>
                <td width="150px">Reported by</td>
                <td><input type="text" name="reporter" size="20" value="<?= isset($_GET['reporter']) ? $_GET['reporter'] : '' ?>" /></td>
            </tr>
            <tr>
                <td width="150px">Handled by</td>
                <td><input type="text" name="handler" size="20" value="<?= isset($_GET['handler']) ? $_GET['handler'] : '' ?>" /></td>
            </tr>
            <tr>
                <td width="150px">Single Torrent</td>
                <td><input type="text" name="torrent" size="80" value="<?= isset($_GET['torrent']) ? $_GET['torrent'] : '' ?>" /></td>
            </tr>
            <tr>
                <td width="150px">Torrent Group</td>
                <td><input type="text" name="group" size="80" value="<?= isset($_GET['group']) ? $_GET['group'] : '' ?>" /></td>
            </tr>
            <tr>
                <td width="150px">Report Type</td>
                <td>
                    <select multiple="multiple" size="8" name="report-type[]">
                        <option value="0">Don't Care</option>
<?php
foreach ($reportNameCache as $key => $label) {
    $selected = array_key_exists('report-type', $_GET) && in_array($key, $_GET['report-type']) ? ' selected="selected"' : '';
?>
                        <option value="<?= $key ?>"<?= $selected ?>><?= $label ?></option>
<?php } ?>
                    </select>
                </td>
            </tr>
            <tr>
                <td width="150px">Created</td>
                <td>
                    From <input type="text" name="dt-from" size="10" value="<?= $dt_from ?>" /> and until <input type="text" name="dt-until" size="10" value="<?= $dt_until ?>" />
                </td>
            </tr>
            <tr>
                <td colspan="2">
                    <input type="hidden" name="action" value="search" />
                    <input type="submit" value="Search reports" />
                </td>
            </tr>
        </table>
    </form>
</div>
<?php
View::show_footer();
