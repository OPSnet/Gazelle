<?php
if (!check_perms('site_view_flow')) {
    error(403);
}
View::show_header('Torrents');

if (!$TorrentStats = $Cache->get_value('new_torrent_stats')) {
    $NumUsers = Users::get_enabled_users_count();
    $DB->query("SELECT count(*), coalesce(sum(Size), 0), coalesce(sum(FileCount), 0) FROM torrents");
    list($TorrentCount, $TotalSize, $TotalFiles) = $DB->next_record();

    $DB->query("SELECT count(*), coalesce(sum(Size), 0), coalesce(sum(FileCount), 0) FROM torrents WHERE Time > now() - INTERVAL 1 DAY");
    list($DayNum, $DaySize, $DayFiles) = $DB->next_record();

    $DB->query("SELECT count(*), coalesce(sum(Size), 0), coalesce(sum(FileCount), 0) FROM torrents WHERE Time > now() - INTERVAL 1 WEEK");
    list($WeekNum, $WeekSize, $WeekFiles) = $DB->next_record();

    $DB->query("SELECT count(*), coalesce(sum(Size), 0), coalesce(sum(FileCount), 0) FROM torrents WHERE Time > now() - INTERVAL 30 DAY");
    list($MonthNum, $MonthSize, $MonthFiles) = $DB->next_record();

    $DB->query("SELECT count(*), coalesce(sum(Size), 0), coalesce(sum(FileCount), 0) FROM torrents WHERE Time > now() - INTERVAL 120 DAY");
    list($QuartNum, $QuartSize, $QuartFiles) = $DB->next_record();

    $Cache->cache_value('new_torrent_stats',
        [
            $TorrentCount, $TotalSize, $TotalFiles, $NumUsers,
            $DayNum, $DaySize, $DayFiles,
            $WeekNum, $WeekSize, $WeekFiles,
            $MonthNum, $MonthSize, $MonthFiles,
            $QuartNum, $QuartSize, $QuartFiles,
        ], 3600);
} else {
        list(
            $TorrentCount, $TotalSize, $TotalFiles, $NumUsers,
            $DayNum, $DaySize, $DayFiles,
            $WeekNum, $WeekSize, $WeekFiles,
            $MonthNum, $MonthSize, $MonthFiles,
            $QuartNum, $QuartSize, $QuartFiles,
        ) = $TorrentStats;
    }

    ?>
    <div class="thin">
        <div class="box">
            <div class="head">Overall stats</div>
            <div class="pad">
                <table>
                <tr>
                    <td>Total torrents:</td><td class="number_column"><?=number_format($TorrentCount)?></td>
                    <td style="padding-left:50px">Mean torrents per user:</td><td class="number_column"><?=number_format($TorrentCount / $NumUsers)?></td>
                    <td style="padding-left:50px">Mean files per torrent:</td><td class="number_column"><?=number_format($TotalFiles / $TorrentCount)?></td>
                </tr>
                <tr>
                    <td>Total size:</td><td class="number_column"><?=Format::get_size($TotalSize)?></td>
                    <td style="padding-left:50px">Mean torrent size:</td><td class="number_column"><?=Format::get_size($TotalSize / $TorrentCount)?></td>
                    <td style="padding-left:50px">Mean filesize:</td><td class="number_column"><?=Format::get_size($TotalSize / $TotalFiles)?></td>
                </tr>
                <tr>
                    <td>Total files:</td><td class="number_column"><?=number_format($TotalFiles)?></td>
                </tr>
                </table>
            </div>
        </div>
        <br />
        <div class="box">
            <div class="head">Upload frequency</div>
            <div class="pad">
                <table>
                    <tr>
                    <th></th>
                    <th>Today</th>
                    <th>This week</th>
                    <th>Per day this week</th>
                    <th>This month</th>
                    <th>Per day this month</th>
                    <th>This quarter</th>
                    <th>Per day this quarter</th>
                    </tr>

                    <tr>
                    <th>Torrents</th>
                    <td class="number_column"><?=number_format($DayNum)?></td>
                    <td class="number_column"><?=number_format($WeekNum)?></td>
                    <td class="number_column"><?=number_format($WeekNum / 7)?></td>
                    <td class="number_column"><?=number_format($MonthNum)?></td>
                    <td class="number_column"><?=number_format($MonthNum / 30)?></td>
                    <td class="number_column"><?=number_format($QuartNum)?></td>
                    <td class="number_column"><?=number_format($QuartNum / 120)?></td>
                    </tr>

                    <tr>
                    <th>Size</th>
                    <td class="number_column"><?=Format::get_size($DaySize)?></td>
                    <td class="number_column"><?=Format::get_size($WeekSize)?></td>
                    <td class="number_column"><?=Format::get_size($WeekSize / 7)?></td>
                    <td class="number_column"><?=Format::get_size($MonthSize)?></td>
                    <td class="number_column"><?=Format::get_size($MonthSize / 30)?></td>
                    <td class="number_column"><?=Format::get_size($QuartSize)?></td>
                    <td class="number_column"><?=Format::get_size($QuartSize / 120)?></td>
                    </tr>

                    <tr>
                    <th>Files</th>
                    <td class="number_column"><?=number_format($DayFiles)?></td>
                    <td class="number_column"><?=number_format($WeekFiles)?></td>
                    <td class="number_column"><?=number_format($WeekFiles / 7)?></td>
                    <td class="number_column"><?=number_format($MonthFiles)?></td>
                    <td class="number_column"><?=number_format($MonthFiles / 30)?></td>
                    <td class="number_column"><?=number_format($QuartFiles)?></td>
                    <td class="number_column"><?=number_format($QuartFiles / 120)?></td>
                    </tr>
                </table>
        </div>
    </div>
</div>
<?php
View::show_footer();
