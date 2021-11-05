<?php

$page = !empty($_GET['page']) ? (int) $_GET['page'] : 1;
$page = max(1, $page);
$limit = TORRENTS_PER_PAGE;
$offset = TORRENTS_PER_PAGE * ($page-1);

$header = new \Gazelle\Util\SortableTableHeader('hourlypoints', [
    'size'          => ['dbColumn' => 't.Size',        'defaultSort' => 'desc', 'text' => 'Size'],
    'seeders'       => ['dbColumn' => 'Seeders',       'defaultSort' => 'desc', 'text' => 'Seeders'],
    'seedtime'      => ['dbColumn' => 'SeedTime',      'defaultSort' => 'desc', 'text' => 'Seedtime'],
    'hourlypoints'  => ['dbColumn' => 'HourlyPoints',  'defaultSort' => 'desc', 'text' => 'BP/hour'],
    'dailypoints'   => ['dbColumn' => 'DailyPoints',   'defaultSort' => 'desc', 'text' => 'BP/day'],
    'weeklypoints'  => ['dbColumn' => 'WeeklyPoints',  'defaultSort' => 'desc', 'text' => 'BP/week'],
    'monthlypoints' => ['dbColumn' => 'MonthlyPoints', 'defaultSort' => 'desc', 'text' => 'BP/month'],
    'yearlypoints'  => ['dbColumn' => 'YearlyPoints',  'defaultSort' => 'desc', 'text' => 'BP/year'],
    'pointspergb'   => ['dbColumn' => 'PointsPerGB',   'defaultSort' => 'desc', 'text' => 'BP/GB/year'],
]);

$userMan = new Gazelle\Manager\User;
if (empty($_GET['userid'])) {
    $user = $Viewer;
    $ownProfile = true;
} else {
    if (!$Viewer->permitted('admin_bp_history')) {
        error(403);
    }
    $user = $userMan->findById((int)($_GET['userid'] ?? 0));
    $ownProfile = false;
}
if (is_null($user)) {
    error(404);
}
$userId = $user->id();
$bonus = new Gazelle\Bonus($user);

[$totalTorrents, $totalSize, $totalHourlyPoints, $totalDailyPoints, $totalWeeklyPoints, $totalMonthlyPoints, $totalYearlyPoints, $totalPointsPerGB
] = $bonus->userTotals();

$paginator = new Gazelle\Util\Paginator(TORRENTS_PER_PAGE, (int)($_GET['page'] ?? 1));
$paginator->setTotal($totalTorrents);

$Title = $ownProfile ? 'Your Bonus Points Rate' : ($user->username() . "'s Bonus Point Rate");
View::show_header($Title);
?>
<div class="header">
    <h2><?=$Title?></h2>
    <h3>Points: <?= number_format($user->bonusPointsTotal()) ?></h3>
</div>
<div class="linkbox">
    <a href="wiki.php?action=article&name=bonuspoints" class="brackets">About Bonus Points</a>
    <a href="bonus.php" class="brackets">Bonus Point Shop</a>
    <a href="bonus.php?action=history<?= $Viewer->permitted('admin_bp_history') && !$ownProfile ? "&amp;userid=$userId" : '' ?>" class="brackets">History</a>
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
    <?= $paginator->linkbox() ?>
</div>
<table>
    <thead>
    <tr class="colhead">
        <td>Torrent</td>
        <td class="nobr number_column"><?= $header->emit('size') ?></td>
        <td class="nobr"><?= $header->emit('seeders') ?></td>
        <td class="nobr"><?= $header->emit('seedtime') ?></td>
        <td class="nobr"><?= $header->emit('hourlypoints') ?></td>
        <td class="nobr"><?= $header->emit('dailypoints') ?></td>
        <td class="nobr"><?= $header->emit('weeklypoints') ?></td>
        <td class="nobr"><?= $header->emit('monthlypoints') ?></td>
        <td class="nobr"><?= $header->emit('yearlypoints') ?></td>
        <td class="nobr"><?= $header->emit('pointspergb') ?></td>
    </tr>
    </thead>
    <tbody>
<?php

if ($totalTorrents) {
    [$groupIDs, $torrentStats] = $bonus->userDetails($header->getOrderBy(), $header->getOrderDir(),
        $limit, $offset);
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
<?php } ?>
    </tbody>
</table>
<div class="linkbox">
    <?= $paginator->linkbox() ?>
</div>
<?php
View::show_footer();
