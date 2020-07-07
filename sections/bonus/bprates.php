<?php

use Gazelle\Util\SortableTableHeader;

$page = !empty($_GET['page']) ? intval($_GET['page']) : 1;
$page = max(1, $page);
$limit = TORRENTS_PER_PAGE;
$offset = TORRENTS_PER_PAGE * ($page-1);

$sortOrderMap =  [
    'size'          => ['t.Size',        'desc'],
    'seeders'       => ['Seeders',       'desc'],
    'seedtime'      => ['SeedTime',      'desc'],
    'hourlypoints'  => ['HourlyPoints',  'desc'],
    'dailypoints'   => ['DailyPoints',   'desc'],
    'weeklypoints'  => ['WeeklyPoints',  'desc'],
    'monthlypoints' => ['MonthlyPoints', 'desc'],
    'yearlypoints'  => ['YearlyPoints',  'desc'],
    'pointspergb'   => ['PointsPerGB',   'desc'],
];
$sortOrder = (!empty($_GET['order']) && isset($sortOrderMap[$_GET['order']])) ? $_GET['order'] : 'hourlypoints';
$orderBy = $sortOrderMap[$sortOrder][0];
$orderWay = (empty($_GET['sort']) || $_GET['sort'] == $sortOrderMap[$sortOrder][1])
    ? $sortOrderMap[$sortOrder][1]
    : SortableTableHeader::SORT_DIRS[$sortOrderMap[$sortOrder][1]];

if (!empty($_GET['userid'])) {
    if (!check_perms('admin_bp_history')) {
        error(403);
    }
    $userId = intval($_GET['userid']);
    $User = array_merge(Users::user_stats($_GET['userid']), Users::user_info($_GET['userid']), Users::user_heavy_info($_GET['userid']));
    if (empty($User)) {
        error(404);
    }
}
else {
    $userId = $LoggedUser['ID'];
    $User = $LoggedUser;
}

$Title = ($userId === $LoggedUser['ID']) ? 'Your Bonus Points Rate' : "{$User['Username']}'s Bonus Point Rate";
View::show_header($Title);

$Bonus = new \Gazelle\Bonus;

list($totalTorrents, $totalSize, $totalHourlyPoints, $totalDailyPoints, $totalWeeklyPoints, $totalMonthlyPoints, $totalYearlyPoints, $totalPointsPerGB)
    = $Bonus->userTotals($userId);
$pages = Format::get_pages($page, $totalTorrents, TORRENTS_PER_PAGE);

?>
<div class="header">
    <h2><?=$Title?></h2>
    <h3>Points: <?=number_format((int)$User['BonusPoints'])?></h3>
</div>
<div class="linkbox">
    <a href="wiki.php?action=article&name=bonuspoints" class="brackets">About Bonus Points</a>
    <a href="bonus.php" class="brackets">Bonus Point Shop</a>
    <a href="bonus.php?action=history<?= check_perms('admin_bp_history') && $userId != G::$LoggedUser['ID'] ? "&amp;userid=$userId" : '' ?>" class="brackets">History</a>
</div>
<table>
    <thead>
        <tr class="colhead">
            <td style="text-align: center;">Total Torrents</td>
            <td style="text-align: center;">Size</td>
            <td style="text-align: center;">BP/hour</td>
            <td style="text-align: center;">BP/day</td>
            <td style="text-align: center;">BP/week</td>
            <td style="text-align: center;">BP/month</td>
            <td style="text-align: center;">BP/year</td>
            <td style="text-align: center;" title="Bonus points per GB if seeded a year">BP/GB/year</td>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td style="text-align: center;"><?=$totalTorrents?></td>
            <td style="text-align: center;"><?=Format::get_size($totalSize)?></td>
            <td style="text-align: center;"><?=number_format($totalHourlyPoints, 2)?></td>
            <td style="text-align: center;"><?=number_format($totalDailyPoints, 2)?></td>
            <td style="text-align: center;"><?=number_format($totalWeeklyPoints, 2)?></td>
            <td style="text-align: center;"><?=number_format($totalMonthlyPoints, 2)?></td>
            <td style="text-align: center;"><?=number_format($totalYearlyPoints, 2)?></td>
            <td style="text-align: center;"><?=number_format($totalPointsPerGB, 2)?></td>
        </tr>
    </tbody>
</table>
<br />

<div class="linkbox">
    <?=$pages?>
</div>
<?php
$header = new SortableTableHeader([
    'size'          => 'Size',
    'seeders'       => 'Seeders',
    'seedtime'      => 'Seedtime',
    'hourlypoints'  => 'BP/hour',
    'dailypoints'   => 'BP/day',
    'weeklypoints'  => 'BP/week',
    'monthlypoints' => 'BP/month',
    'yearlypoints'  => 'BP/year',
    'pointspergb'   => 'BP/GB/year',
], $sortOrder, $orderWay);
?>
<table>
    <thead>
    <tr class="colhead">
        <td>Torrent</td>
        <td class="nobr number_column"><?= $header->emit('size',          $sortOrderMap['size'][1]) ?></td>
        <td class="nobr"><?= $header->emit('seeders',       $sortOrderMap['seeders'][1]) ?></td>
        <td class="nobr"><?= $header->emit('seedtime',      $sortOrderMap['seedtime'][1]) ?></td>
        <td class="nobr"><?= $header->emit('hourlypoints',  $sortOrderMap['hourlypoints'][1]) ?></td>
        <td class="nobr"><?= $header->emit('dailypoints',   $sortOrderMap['dailypoints'][1]) ?></td>
        <td class="nobr"><?= $header->emit('weeklypoints',  $sortOrderMap['weeklypoints'][1]) ?></td>
        <td class="nobr"><?= $header->emit('monthlypoints', $sortOrderMap['monthlypoints'][1]) ?></td>
        <td class="nobr"><?= $header->emit('yearlypoints',  $sortOrderMap['yearlypoints'][1]) ?></td>
        <td class="nobr"><?= $header->emit('pointspergb',   $sortOrderMap['pointspergb'][1]) ?></td>
    </tr>
    </thead>
    <tbody>
<?php

if ($totalTorrents) {
    list($groupIDs, $torrentStats) = $Bonus->userDetails($userId, $orderBy, $orderWay, $limit, $offset);
    $groups = Torrents::get_groups($groupIDs, true, true, false);
    foreach ($torrentStats as $stats) {
        $groupId = $stats['GroupID'];
        $groupYear = $groups[$groupId]['Year'];
        $torrents = isset($groups[$groupId]['Torrents']) ? $groups[$groupId]['Torrents'] : [];
        $Artists = $groups[$groupId]['Artists'];
        $ExtendedArtists = $groups[$groupId]['ExtendedArtists'];
        $VanityHouse = $groups[$groupId]['VanityHouse'];

        if (!empty($ExtendedArtists[1]) || !empty($ExtendedArtists[4]) || !empty($ExtendedArtists[5])) {
            unset($ExtendedArtists[2]);
            unset($ExtendedArtists[3]);
            $DisplayName = Artists::display_artists($ExtendedArtists);
        } elseif (!empty($Artists)) {
            $DisplayName = Artists::display_artists([1 => $Artists]);
        } else {
            $DisplayName = '';
        }
        $DisplayName .= '<a href="torrents.php?id=' . $groupId . '&amp;torrentid=' . $stats['ID'] . '" class="tooltip" title="View torrent" dir="ltr">' . $groups[$groupId]['Name'] . '</a>';
        if ($groupYear > 0) {
            $DisplayName .= " [$groupYear]";
        }
?>
    <tr>
        <td><?= $DisplayName ?></td>
        <td class="nobr number_column"><?= Format::get_size($stats['Size']) ?></td>
        <td class="number_column"><?= number_format($stats['Seeders']) ?></td>
        <td class="number_column"><?= convert_hours($stats['Seedtime'], 2) ?></td>
        <td class="number_column"><?= number_format($stats['HourlyPoints'], 3) ?></td>
        <td class="number_column"><?= number_format($stats['DailyPoints'], 3) ?></td>
        <td class="number_column"><?= number_format($stats['WeeklyPoints'], 3) ?></td>
        <td class="number_column"><?= number_format($stats['MonthlyPoints'], 2) ?></td>
        <td class="number_column"><?= number_format($stats['YearlyPoints'], 2) ?></td>
        <td class="number_column"><?= number_format($stats['PointsPerGB'], 2) ?></td>
    </tr>
<?php
    }
}
else {
?>
    <tr>
        <td colspan="9" style="text-align:center;">No torrents being seeded currently</td>
    </tr>
<?php
}
?>

    </tbody>
</table>
<div class="linkbox">
    <?=$pages?>
</div>
<?php
View::show_footer();
