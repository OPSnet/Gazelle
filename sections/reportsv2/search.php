<?php
if (!check_perms('admin_reports')) {
    error(403);
}

View::show_header('Reports V2', 'reportsv2');

$report_name_cache = [];
foreach ($ReportCategories as $label => $key) {
    foreach (array_keys($Types[$label]) as $type) {
        $report_name_cache[$type] = $Types[$label][$type]['title'] . " ($key)";
    }
}

if (isset($_GET['report-type'])) {
    foreach ($_GET['report-type'] as $t) {
        if (array_key_exists($t, $report_name_cache)) {
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
}
if (isset($_GET['dt-until']) && preg_match('/(\d\d\d\d-\d\d-\d\d)/', $_GET['dt-until'], $match)) {
    $filter['dt-until'] = $match[1];
    $dt_until = $match[1];
}
if (isset($filter)) {
    $filter['page'] = (isset($_GET['page']) && preg_match('/(\d+)/', $_GET['page'], $match))
        ? $match[1] : 1;
    list ($Results, $Total) = \Gazelle\Report::search(G::$DB, $filter);
}

if (!isset($dt_from)) {
    $dt_from  = date('Y-m-d', strtotime(date('Y-m-d', strtotime(date('Y-m-d'))) . '-1 month'));
}
if (!isset($dt_until)) {
    $dt_until = date('Y-m-d');
}
?>

<div class="header">
    <h2>Search Reports</h2>
<?php include('header.php'); ?>
</div>

<?php
if (isset($Results)) {
    $Page  = max(1, isset($_GET['page']) ? intval($_GET['page']) : 1);
    $Pages = Format::get_pages($Page, $Total, TORRENTS_PER_PAGE);
?>
<div class="linkbox">
    <?= $Pages ?>
</div>
<div class="thin box pad">
    <table>
        <thead>
            <tr>
                <td>Report</td>
                <td>Uploaded&nbsp;by</td>
                <td>Reported&nbsp;by</td>
                <td>Handled&nbsp;by</td>
                <td>Torrent</td>
                <td>Report&nbsp;type</td>
                <td width="120px">Date&nbsp;reported</td>
            </tr>
        </thead>
        <tbody>
<?php
    $user_cache = [];

    foreach ($Results as $r) {
        if (!array_key_exists($r['UserID'], $user_cache)) {
            $user_cache[$r['UserID']] = Users::format_username($r['UserID']);
        }
        if (!array_key_exists($r['ReporterID'], $user_cache)) {
            $user_cache[$r['ReporterID']] = Users::format_username($r['ReporterID']);
        }
        if (!array_key_exists($r['ResolverID'], $user_cache)) {
            $user_cache[$r['ResolverID']] = $r['ResolverID']
                ? Users::format_username($r['ResolverID'])
                : '<i>unclaimed</i>';
        }
        if ($r['GroupID']) {
            $name = Artists::display_artists(Artists::get_artist($r['GroupID']))
                . sprintf('<a href=/torrents.php?id=%d&torrentid=%d#torrent%d>%s</a>',
                    $r['GroupID'], $r['TorrentID'], $r['TorrentID'], display_str($r['Name'])
                )
                . " [" . $r['Year'] . ']';
        }
        else {
            $name = $r['Name'];
        }
?>
            <tr>
                <td align="right"><a href="/reportsv2.php?view=report&id=<?= $r['ID'] ?>"><?= $r['ID'] ?></a></td>
                <td><?= $r['UserID'] ? $user_cache[$r['UserID']] : '<i>unknown</i>' ?></td>
                <td><?= $user_cache[$r['ReporterID']] ?></td>
                <td><?= $user_cache[$r['ResolverID']] ?></td>
                <td><?= $name ?></td>
                <td><?= $report_name_cache[$r['Type']] ?></td>
                <td><?= time_diff($r['ReportedTime']) ?></td>
            </tr>
<?php
    } ?>
        </tbody>
    </table>
</div>
<div class="linkbox">
    <?= $Pages ?>
</div>
<br />
<?php
} ?>

<div class="thin box pad">
    <form method="get" action="/reportsv2.php">
        <table>
            <tr>
                <td width="150px">Reported by</td>
                <td><input type="text" name="reporter" size="20" value="<?= isset($_GET['reporter']) ? $_GET['reporter'] : '' ?>" /></td>
            </tr>
            <tr>
                <td width="150px">Handled by</td>
                <td><input type="text" name="handler" size="20" value="<?= isset($_GET['handler']) ? $_GET['handler'] : '' ?>" /></td>
            </tr>
            <tr>
                <td width="150px">Uploaded by</td>
                <td><input type="text" name="uploader" size="20" value="<?= isset($_GET['uploader']) ? $_GET['uploader'] : '' ?>" /></td>
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
foreach ($report_name_cache as $key => $label) {
    $selected = array_key_exists('report-type', $_GET) && in_array($key, $_GET['report-type']) ? ' selected="selected"' : '';
?>
                        <option value="<?= $key ?>"<?= $selected ?>><?= $label ?></option>
<?php
} ?>
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
