<?php
if (!check_perms('site_view_flow')) {
    error(403);
}
$stats = new Gazelle\Stats\Torrent;
View::show_header('Torrents');
?>
<div class="thin">
    <div class="box">
        <div class="head">Overall stats</div>
        <div class="pad">
            <table>
            <tr>
                <td>Total torrents:</td><td class="number_column"><?= number_format($stats->torrentCount()) ?></td>
                <td style="padding-left:50px">Mean torrents per user:</td><td class="number_column"><?=
                    number_format($stats->torrentCount() / $stats->totalUsers()) ?></td>
                <td style="padding-left:50px">Mean files per torrent:</td><td class="number_column"><?=
                    number_format($stats->totalFiles() / $stats->torrentCount())?></td>
            </tr>
            <tr>
                <td>Total size:</td><td class="number_column"><?= Format::get_size($stats->totalSize()) ?></td>
                <td style="padding-left:50px">Mean torrent size:</td><td class="number_column"><?=
                    Format::get_size($stats->totalSize() / $stats->torrentCount())?></td>
                <td style="padding-left:50px">Mean filesize:</td><td class="number_column"><?=
                    Format::get_size($stats->totalSize() / $stats->totalFiles()) ?></td>
            </tr>
            <tr>
                <td>Total files:</td><td class="number_column"><?= number_format($stats->totalFiles()) ?></td>
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
                <td class="number_column"><?= number_format($stats->amount('day')) ?></td>
                <td class="number_column"><?= number_format($stats->amount('week')) ?></td>
                <td class="number_column"><?= number_format($stats->amount('week')) ?></td>
                <td class="number_column"><?= number_format($stats->amount('month')) ?></td>
                <td class="number_column"><?= number_format($stats->amount('month') / 30) ?></td>
                <td class="number_column"><?= number_format($stats->amount('quarter')) ?></td>
                <td class="number_column"><?= number_format($stats->amount('quarter') / 120) ?></td>
                </tr>

                <tr>
                <th>Size</th>
                <td class="number_column"><?= Format::get_size($stats->size('day')) ?></td>
                <td class="number_column"><?= Format::get_size($stats->size('week')) ?></td>
                <td class="number_column"><?= Format::get_size($stats->size('week') / 7) ?></td>
                <td class="number_column"><?= Format::get_size($stats->size('month')) ?></td>
                <td class="number_column"><?= Format::get_size($stats->size('month') / 30) ?></td>
                <td class="number_column"><?= Format::get_size($stats->size('quarter')) ?></td>
                <td class="number_column"><?= Format::get_size($stats->size('quarter') / 120) ?></td>
                </tr>

                <tr>
                <th>Files</th>
                <td class="number_column"><?= number_format($stats->files('day')) ?></td>
                <td class="number_column"><?= number_format($stats->files('week')) ?></td>
                <td class="number_column"><?= number_format($stats->files('week') / 7) ?></td>
                <td class="number_column"><?= number_format($stats->files('month')) ?></td>
                <td class="number_column"><?= number_format($stats->files('month') / 30) ?></td>
                <td class="number_column"><?= number_format($stats->files('quarter')) ?></td>
                <td class="number_column"><?= number_format($stats->files('quarter') / 120) ?></td>
                </tr>
            </table>
        </div>
    </div>

    <br />
    <div class="box">
        <div class="head">Content Analysis</div>
        <div class="pad">
            <table>
                <tr>
                <th width="33%">Formats</th>
                <th width="33%">Media</th>
                <th width="34%">Categories</th>
                </tr>
                <tr>

                <td style="vertical-align: top;"><table>
<?php foreach ($stats->format() as $f) { ?>
                    <tr><td><?= $f[0] ?: '<i>Grand</i>' ?></td>
                    <td><?= $f[1] ?: '<i>Total</i>' ?></td>
                    <td class="number_column"><?= number_format($f[2]) ?></td>
                    </tr>
<?php } ?>
                </table></td>

                <td style="vertical-align: top;"><table>
<?php foreach ($stats->media() as $m) { ?>
                    <tr><td><?= $m[0] ?: '<i>Total</it>' ?></td>
                    <td class="number_column"><?= number_format($m[1]) ?></td>
                    </tr>
<?php } ?>
                </table></td>

                <td style="vertical-align: top;"><table>
<?php foreach ($stats->category() as $c) { ?>
                    <tr><td><?= CATEGORY[$c[0] - 1] ?></td>
                    <td class="number_column"><?= number_format($c[1]) ?></td>
                    </tr>
<?php } ?>
                </table></td>

                </tr>
                <tr>
                <th width="33%">Added in last month</th>
                <th width="33%">&nbsp;</th>
                <th width="34%">&nbsp;</th>
                </tr>
                <tr>

                <td style="vertical-align: top;"><table>
<?php foreach ($stats->formatMonth() as $f) { ?>
                    <tr><td><?= $f[0] ?: '<i>Grand</i>' ?></td>
                    <td><?= $f[1] ?: '<i>Total</i>' ?></td>
                    <td class="number_column"><?= number_format($f[2]) ?></td>
                    </tr>
<?php } ?>
                </table></td>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
                </tr>
            </table>
        </div>
    </div>

</div>
<?php
View::show_footer();
