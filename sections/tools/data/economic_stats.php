<?php
/*
Tools necessary for economic management
1. Current overall stats (!economy)
2. Statistical traffic trends in a graph
    a. All time / 1 year (whichever is smaller)
    b. 1 month
    c. 1 week
    d. 1 day
3. Freeleech analysis
    a. total download average during freeleech vs. normal conditions
    b. total stats of a freeleech - uploaded torrents, upload amount, download amount, snatches, etc.
4. Traffic trends over an account's life, on average
    a. at one week, one month, whatever (selectable range in weeks) - averages (up/down/ratio)
    b. given a selected timespan, average ratio (users who are 4-5 months old have X ratio)
    c. average date at which >50% of accounts with ratios >1 reach 1.0 and never dip below, stockpiling buffer
5. Raw numbers
    a. total torrents, seeders, leechers
    b. average seeds/leechs per torrent
    c. average snatches/user
    d. average seeding torrents/user
    e. users on ratio watch
6. Distribution graph of seedership vs. torrent percentage
    a. graph showing that the top 1% of torrents has 50% of seeders or whatever the numbers might be
7. Effects of economic changes
    a. number of users changed by ratio being changed
    b. project effects with intelligent mathematical analysis of a 24, 48 or 72 hour freeleech
*/
if (!check_perms('site_view_flow')) {
    error(403);
}

View::show_header('Economy');
$Eco = new \Gazelle\Stats\Economic;
$totalEnabled = $Eco->get('totalEnabled');
$totalPeerUsers = $Eco->get('totalPeerUsers');
$totalTorrents = $Eco->get('totalTorrents');

?>
<div class="thin">
    <div class="box">
        <div class="head">Overall stats</div>
        <div class="pad">
            <ul class="stats nobullet">
                <li><strong>Total upload: </strong><?= Format::get_size($Eco->get('totalUpload')) ?></li>
                <li><strong>Total download: </strong><?= Format::get_size($Eco->get('totalDownload')) ?></li>
                <li><strong>Total buffer: </strong><?= Format::get_size($Eco->get('totalUpload') - $Eco->get('totalDownload')) ?></li>
                <br />
                <li><strong>Mean ratio: </strong><?= Format::get_ratio_html($Eco->get('totalUpload'), $Eco->get('totalDownload')) ?></li>
                <li><strong>Mean upload: </strong><?= Format::get_size($Eco->get('totalUpload') / $totalEnabled) ?></li>
                <li><strong>Mean download: </strong><?= Format::get_size($Eco->get('totalDownload') / $totalEnabled) ?></li>
                <li><strong>Mean buffer: </strong><?= Format::get_size(($Eco->get('totalUpload') - $Eco->get('totalDownload')) / $totalEnabled) ?></li>
                <br />
                <li><strong>Total request bounty: </strong><?= Format::get_size($Eco->get('totalBounty')) ?></li>
                <li><strong>Available request bounty: </strong><?= Format::get_size($Eco->get('availableBounty')) ?></li>
            </ul>
        </div>
    </div>
    <br />
    <div class="box">
        <div class="head">Swarms and snatches</div>
        <div class="pad">
            <table>
            <tr>
            <td style="vertical-align:top;" width="50%">
            <ul class="stats nobullet">
                <li><strong>Total seeders: </strong><?= number_format($Eco->get('totalSeeders')) ?></li>
                <li><strong>Total leechers: </strong><?= number_format($Eco->get('totalLeechers')) ?></li>
                <li><strong>Total peers: </strong><?= number_format($Eco->get('totalSeeders') + $Eco->get('totalLeechers')) ?></li>
                <li><strong>Total snatches: </strong><?= number_format($Eco->get('totalOverallSnatches')) ?></li>
                <li><strong>Seeder/leecher ratio: </strong><?= Format::get_ratio_html($Eco->get('totalSeeders'), $Eco->get('totalLeechers')) ?></li>
                <li><strong>Seeder/snatch ratio: </strong><?= Format::get_ratio_html($Eco->get('totalSeeders'), $Eco->get('totalOverallSnatches')) ?></li>
                <br />
                <li><strong>Total users in at least 1 swarm: </strong><?= number_format($totalPeerUsers) ?></li>
                <li><strong>Mean seeding per user in at least 1 swarm: </strong><?= number_format($totalPeerUsers ? $Eco->get('totalSeeders') / $totalPeerUsers : 0, 2) ?></li>
                <li><strong>Mean leeching per user in at least 1 swarm: </strong><?= number_format($totalPeerUsers ? $Eco->get('totalLeechers') / $totalPeerUsers : 0, 2) ?></li>
                <li><strong>Mean snatches per user in at least 1 swarm: </strong><?= number_format($totalPeerUsers ? $Eco->get('totalSnatches') / $totalPeerUsers : 0, 2) ?></li>
            </ul>
            </td>
            <td style="vertical-align:top;" width="50%">
            <ul class="stats nobullet">
                <li><strong>Mean seeders per torrent: </strong><?= number_format($totalTorrents ? $Eco->get('totalSeeders') / $totalTorrents : 0, 2) ?></li>
                <li><strong>Mean leechers per torrent: </strong><?= number_format($totalTorrents ? $Eco->get('totalLeechers') / $totalTorrents : 0, 2) ?></li>
                <li><strong>Mean snatches per torrent: </strong><?= number_format($totalTorrents ? $Eco->get('totalSnatches') / $totalTorrents : 0, 2) ?></li>
                <br />
                <li><strong>Mean seeding per user: </strong><?= number_format($Eco->get('totalSeeders') / $totalEnabled, 2) ?></li>
                <li><strong>Mean leeching per user: </strong><?= number_format($Eco->get('totalLeechers') / $totalEnabled, 2) ?></li>
                <li><strong>Mean snatches per user: </strong><?= number_format($Eco->get('totalOverallSnatches') / $totalEnabled, 2) ?></li>
            </ul>
            </td>
            </tr>
            </table>
        </div>
    </div>
</div>
<?php
View::show_footer();
