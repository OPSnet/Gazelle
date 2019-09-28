<?
if (!check_perms('site_view_flow')) {
    error(403);
}
View::show_header('Torrents');

if (!$TorrentStats = $Cache->get_value('new_torrent_stats')) {
    $DB->query(" SELECT COUNT(*), SUM(Size), SUM(FileCount) FROM torrents");
    list($TorrentCount, $TotalSize, $TotalFiles) = $DB->next_record();
    $NumUsers = Users::get_enabled_users_count();
    $DB->query("SELECT COUNT(*), SUM(Size), SUM(FileCount) FROM torrents WHERE Time > now() - INTERVAL 1 DAY)");
    list($DayNum, $DaySize, $DayFiles) = $DB->next_record();
    $DB->query("SELECT COUNT(*), SUM(Size), SUM(FileCount) FROM torrents WHERE Time > now() - INTERVAL 1 WEEK)");
    list($WeekNum, $WeekSize, $WeekFiles) = $DB->next_record();
    $DB->query("SELECT COUNT(*), SUM(Size), SUM(FileCount) FROM torrents WHERE Time > now() - INTERVAL 1 MONTH)");
    list($MonthNum, $MonthSize, $MonthFiles) = $DB->next_record();
    $Cache->cache_value('new_torrent_stats', array($TorrentCount, $TotalSize, $TotalFiles,
                        $NumUsers, $DayNum, $DaySize, $DayFiles,
                        $WeekNum, $WeekSize, $WeekFiles, $MonthNum,
                        $MonthSize, $MonthFiles), 3600);
} else {
    list($TorrentCount, $TotalSize, $TotalFiles, $NumUsers, $DayNum, $DaySize, $DayFiles,
        $WeekNum, $WeekSize, $WeekFiles, $MonthNum, $MonthSize, $MonthFiles) = $TorrentStats;
}

?>
<div class="thin">
    <div class="box">
        <div class="head">Overall stats</div>
        <div class="pad">
            <ul class="stats nobullet">
                <li><strong>Total torrents: </strong><?=number_format($TorrentCount)?></li>
                <li><strong>Total size: </strong><?=Format::get_size($TotalSize)?></li>
                <li><strong>Total files: </strong><?=number_format($TotalFiles)?></li>
                <br />
                <li><strong>Mean torrents per user: </strong><?=number_format($TorrentCount / $NumUsers)?></li>
                <li><strong>Mean torrent size: </strong><?=Format::get_size($TotalSize / $TorrentCount)?></li>
                <li><strong>Mean files per torrent: </strong><?=number_format($TotalFiles / $TorrentCount)?></li>
                <li><strong>Mean filesize: </strong><?=Format::get_size($TotalSize / $TotalFiles)?></li>
            </ul>
        </div>
    </div>
    <br />
    <div class="box">
        <div class="head">Upload frequency</div>
        <div class="pad">
            <ul class="stats nobullet">
                <li><strong>Torrents today: </strong><?=number_format($DayNum)?></li>
                <li><strong>Size today: </strong><?=Format::get_size($DaySize)?></li>
                <li><strong>Files today: </strong><?=number_format($DayFiles)?></li>
                <br />
                <li><strong>Torrents this week: </strong><?=number_format($WeekNum)?></li>
                <li><strong>Size this week: </strong><?=Format::get_size($WeekSize)?></li>
                <li><strong>Files this week: </strong><?=number_format($WeekFiles)?></li>
                <br />
                <li><strong>Torrents per day this week: </strong><?=number_format($WeekNum / 7)?></li>
                <li><strong>Size per day this week: </strong><?=Format::get_size($WeekSize / 7)?></li>
                <li><strong>Files per day this week: </strong><?=number_format($WeekFiles / 7)?></li>
                <br />
                <li><strong>Torrents this month: </strong><?=number_format($MonthNum)?></li>
                <li><strong>Size this month: </strong><?=Format::get_size($MonthSize)?></li>
                <li><strong>Files this month: </strong><?=number_format($MonthFiles)?></li>
                <br />
                <li><strong>Torrents per day this month: </strong><?=number_format($MonthNum / 30)?></li>
                <li><strong>Size per day this month: </strong><?=Format::get_size($MonthSize / 30)?></li>
                <li><strong>Files per day this month: </strong><?=number_format($MonthFiles / 30)?></li>
            </ul>
        </div>
    </div>
</div>
<?
View::show_footer();
?>
