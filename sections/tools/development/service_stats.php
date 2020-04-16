<?php
if (!check_perms('site_debug') || !check_perms('admin_clear_cache')) {
    error(403);
}
if (isset($_POST['global_flush'])) {
    authorize();
    $Cache->flush();
}

$DB->query('SHOW GLOBAL STATUS');
$DBStats = $DB->to_array('Variable_name');

$DB->query('SHOW GLOBAL VARIABLES');
$DBVars = $DB->to_array('Variable_name');

$MemStats = $Cache->getStats();
$MemStats = $MemStats[array_keys($MemStats)[0]];

View::show_header("Service Stats");

if (check_perms('site_database_specifics')) { ?>
<div class="linkbox">
    <a href="tools.php?action=database_specifics" class="brackets">Database specifics</a>
</div>
<?php } ?>

<div class="permissions">
    <div class="permission_container">
        <table>
            <tr class="colhead">
                <td colspan="2">Service</td>
            </tr>
            <tr><td colspan="2"><strong>Threads (Active)</strong></td></tr>
            <tr>
                <td>Cache:</td>
                <td><?= number_format($MemStats['threads']) ?> <span style="float: right;">(<?= number_format($MemStats['threads'] / $MemStats['max_connections'], 1) ?>%)</span></td>
            </tr>
            <tr>
                <td<?php if ($DBStats['Threads_connected']['Value'] / $DBStats['Threads_created']['Value'] > 0.7) { echo ' class="invalid" '; } ?>>Database:</td>
                <td><?= number_format($DBStats['Threads_created']['Value']) ?> <span style="float: right;">(<?= number_format(($DBStats['Threads_connected']['Value'] / $DBStats['Threads_created']['Value']) * 100, 1) ?>%)</span></td>
            </tr>
            <tr>
                <td>DB peak connections:</td>
                <td><?= number_format($DBStats['Max_used_connections']['Value']) ?> <span style="float: right;">(<?= number_format($DBStats['Max_used_connections']['Value'] / $DBVars['max_connections']['Value'], 1) ?>%)</span></td>
            </tr>
            <tr><td colspan="2"></td></tr>
            <tr><td colspan="2"><strong>Connections</strong></td></tr>
            <tr>
                <td>Cache current:</td>
                <td><?= number_format($MemStats['curr_connections']) ?></td>
            </tr>
            <tr>
                <td>Cache total:</td>
                <td><?= number_format($MemStats['total_connections']) ?></td>
            </tr>
            <tr>
                <td>Database total:</td>
                <td><?= number_format($DBStats['Connections']['Value']) ?></td>
            </tr>
            <tr><td colspan="2"></td></tr>
            <tr><td colspan="2"><strong>Booted</strong></td></tr>
            <tr>
                <td>Cache:</td>
                <td><?= time_diff(time() - $MemStats['uptime']) ?></td>
            </tr>
            <tr>
                <td>Database:</td>
                <td><?= time_diff(time() - $DBStats['Uptime']['Value']) ?></td>
            </tr>
            <tr><td colspan="2"></td></tr>
            <tr><td colspan="2"><strong>Special</strong></td></tr>
            <tr>
                <td>Cache Current Index:</td>
                <td><?= number_format($MemStats['curr_items']) ?></span></td>
            </tr>
            <tr>
                <td>Cache Total Index:</td>
                <td><?= number_format($MemStats['total_items']) ?></span></td>
            </tr>
            <tr>
                <td<?php if ($MemStats['bytes'] / $MemStats['limit_maxbytes'] > 0.85) { echo ' class="tooltip invalid" title="Evictions begin when storage exceeds 85%" '; } ?>>Cache Storage:</td>
                <td><?= Format::get_size($MemStats['bytes']) ?> <span style="float: right;"><?= number_format($MemStats['bytes'] / $MemStats['limit_maxbytes'], 5) ?>%</span></td>
            </tr>
            <tr>
                <td>Cache cold moves:</td>
                <td><?= number_format($MemStats['moves_to_cold']) ?><span style="float: right;"><?= number_format($MemStats['moves_to_cold'] / $MemStats['uptime'], 5) ?>/s</span></td>
            </tr>
            <tr>
                <td>Cache warm moves:</td>
                <td><?= number_format($MemStats['moves_to_warm']) ?><span style="float: right;"><?= number_format($MemStats['moves_to_warm'] / $MemStats['uptime'], 5) ?>/s</span></td>
            </tr>
            <tr>
                <td>Cache moves within LRU:</td>
                <td><?= number_format($MemStats['moves_within_lru']) ?><span style="float: right;"><?= number_format($MemStats['moves_within_lru'] / $MemStats['uptime'], 5) ?>/s</span></td>
            </tr>
            <tr><td colspan="2"></td></tr>
            <tr><td colspan="2"><strong>Utilities</strong></td></tr>
            <tr>
                <td>Cache:</td>
                <td>
                    <form class="delete_form" name="cache" action="" method="post">
                        <input type="hidden" name="action" value="service_stats" />
                        <input type="hidden" name="auth" value="<?=$LoggedUser['AuthKey']?>" />
                        <input type="hidden" name="global_flush" value="1" />
                        <input type="submit" value="Manual Flush" />
                    </form>
                </td>
            </tr>
            <tr>
                <td<?php if ($MemStats['cmd_flush'] > $MemStats['uptime'] / 7 * 24 * 3600) { echo ' class="tooltip invalid" title="Flushing the cache on a regular basis defeats the benefits of it, look into using cache transactions, or deletes instead of global flushing where possible." '; } ?>>Total flushes:</td>
                <td><?= number_format($MemStats['cmd_flush']) ?></td>
            </tr>
            <tr>
                <td>Flush ratio:</td>
                <td><?= number_format($MemStats['cmd_flush'] / $MemStats['uptime'],5) ?>/s</td>
            </tr>
        </table>
    </div>
    <div class="permission_container">
        <table>
            <tr class="colhead">
                <td colspan="2">Activity</td>
            </tr>
            <tr><td colspan="2"><strong>Total reads</strong></td></tr>
            <tr>
                <td>Cache:</td>
                <td><?= number_format($MemStats['cmd_get']) ?></td>
            </tr>
            <tr>
                <td>Database:</td>
                <td><?= number_format($DBStats['Com_select']['Value']) ?></td>
            </tr>
            <tr><td colspan="2"><strong>Total writes</strong></td></tr>
            <tr>
                <td>Cache:</td>
                <td><?= number_format($MemStats['cmd_set']) ?></td>
            </tr>
            <tr>
                <td>Database:</td>
                <td><?= number_format($DBStats['Com_insert']['Value'] + $DBStats['Com_update']['Value']) ?></td>
            </tr>
            <tr><td colspan="2"></td></tr>
            <tr><td colspan="2"><strong>Get/Select (Success)</strong></td></tr>
            <tr>
                <td<?php if ($MemStats['get_hits'] / $MemStats['cmd_get'] < 0.7) { echo ' class="invalid" '; } ?>>Cache:</td>
                <td><?= number_format($MemStats['get_hits']) ?> <span style="float: right;">(<?= number_format(($MemStats['get_hits'] / $MemStats['cmd_get']) * 100, 3);?>%)</span></td>
            </tr>
            <tr>
                <td>Database:</td>
                <td><?= number_format($DBStats['Com_select']['Value']) ?> <span style="float: right;">(100.000%)</span></td>
            </tr>
            <tr><td colspan="2"><strong>Set/Insert (Success)</strong></td></tr>
            <tr>
                <td>Cache:</td>
                <td><?= number_format($MemStats['cmd_set']) ?> <span style="float: right;">(100.000%)</span></td>
            </tr>
            <tr>
                <td>Database:</td>
                <td><?= number_format($DBStats['Com_insert']['Value']) ?> <span style="float: right;">(100.000%)</span></td>
            </tr>
            <tr>
                <td<?php if ($MemStats['incr_hits']/($MemStats['incr_hits'] + $MemStats['incr_misses']) < 0.7) { echo ' class="invalid" '; } ?>>Cache increment:</td>
                <td><?= number_format($MemStats['incr_hits']) ?> <span style="float: right;">(<?= number_format(($MemStats['incr_hits'] / ($MemStats['incr_hits'] + $MemStats['incr_misses'])) * 100, 3);?>%)</span></td>
            </tr>
            <tr>
                <td<?php if ($MemStats['decr_hits'] / ($MemStats['decr_hits'] + $MemStats['decr_misses']) < 0.7) { echo ' class="invalid" '; } ?>>Cache decrement:</td>
                <td><?= number_format($MemStats['decr_hits']) ?> <span style="float: right;">(<?= number_format(($MemStats['decr_hits'] / ($MemStats['decr_hits'] + $MemStats['decr_misses'])) * 100, 3);?>%)</span></td>
            </tr>
            <tr><td colspan="2"><strong>CAS/Update (Success)</strong></td></tr>
            <tr>
                <td<?php if ($MemStats['cas_hits'] > 0 && $MemStats['cas_hits'] / ($MemStats['cas_hits'] + $MemStats['cas_misses']) < 0.7) { echo ' class="tooltip invalid" title="More than 30% of the issued CAS commands were unnecessarily wasting time and resources." '; } elseif ($MemStats['cas_hits'] == 0) { echo ' class="tooltip notice" title="Disable CAS with the -C parameter and save resources since it is not used." '; } ?>>Cache:</td>
                <td><?= number_format($MemStats['cas_hits']) ?> <span style="float: right;">(<?php if ($MemStats['cas_hits'] > 0) { echo number_format(($MemStats['cas_hits'] / ($MemStats['cas_hits'] + $MemStats['cas_misses'])) * 100, 3); } else { echo '0.000'; } ?>%)</span></td>
            </tr>
            <tr>
                <td>Database:</td>
                <td><?= number_format($DBStats['Com_update']['Value']) ?> <span style="float: right;">(100.000%)</span></td>
            </tr>
            <tr><td colspan="2"><strong>Deletes (Success)</strong></td></tr>
            <tr>
                <td>Cache:</td>
                <td><?= number_format($MemStats['delete_hits']) ?> <span style="float: right;">(<?= number_format(($MemStats['delete_hits'] / ($MemStats['delete_hits'] + $MemStats['delete_misses'])) * 100, 3);?>%)</span></td>
            </tr>
            <tr>
                <td>Database:</td>
                <td><?= number_format($DBStats['Com_delete']['Value']) ?> <span style="float: right;">(100.000%)</span></td>
            </tr>
            <tr><td colspan="2"></td></tr>
            <tr><td colspan="2"><strong>Special</strong></td></tr>
            <tr>
                <td>Expired unfetched:</td>
                <td><?= number_format($MemStats['expired_unfetched']) ?></span></td>
            </tr>
            <tr>
                <td>Evicted unfetched:</td>
                <td><?= number_format($MemStats['evicted_unfetched']) ?></td>
            </tr>
            <tr>
                <td>Evicted:</td>
                <td><?= number_format($MemStats['evictions']) ?></td>
            </tr>
            <tr>
                <td>Slabs moved:</td>
                <td><?= number_format($MemStats['slabs_moved']) ?></span></td>
            </tr>
            <tr>
                <td<?php if ($DBStats['Slow_queries']['Value'] > $DBStats['Questions']['Value'] / 7500) { echo ' class="tooltip invalid" title="1/7500 queries is allowed to be slow to minimize performance impact." '; } ?>>Database slow:</td>
                <td><?= number_format($DBStats['Slow_queries']['Value']) ?></td>
            </tr>
            <tr><td colspan="2"></td></tr>
            <tr><td colspan="2"><strong>Data read</strong></td></tr>
            <tr>
                <td>Cache:</td>
                <td><?= Format::get_size($MemStats['bytes_read']) ?></td>
            </tr>
            <tr>
                <td>Database:</td>
                <td><?= Format::get_size($DBStats['Bytes_received']['Value']) ?></td>
            </tr>
            <tr><td colspan="2"><strong>Data write</strong></td></tr>
            <tr>
                <td>Cache:</td>
                <td><?= Format::get_size($MemStats['bytes_written']) ?></td>
            </tr>
            <tr>
                <td>Database:</td>
                <td><?= Format::get_size($DBStats['Bytes_sent']['Value']) ?></td>
            </tr>
        </table>
    </div>
    <div class="permission_container">
        <table>
            <tr class="colhead">
                <td colspan="2">Concurrency</td>
            </tr>
            <tr><td colspan="2"><strong>Total reads</strong></td></tr>
            <tr>
                <td<?php if (($MemStats['cmd_get'] / $MemStats['uptime']) * 5 < $DBStats['Com_select']['Value'] / $DBStats['Uptime']['Value']) { echo ' class="invalid" '; } ?>>Cache:</td>
                <td><?= number_format($MemStats['cmd_get'] / $MemStats['uptime'],5) ?>/s</td>
            </tr>
            <tr>
                <td>Database:</td>
                <td><?= number_format($DBStats['Com_select']['Value'] / $DBStats['Uptime']['Value'],5) ?>/s</td>
            </tr>
            <tr><td colspan="2"><strong>Total writes</strong></td></tr>
            <tr>
                <td<?php if (($MemStats['cmd_set'] / $MemStats['uptime']) * 5 < ($DBStats['Com_insert']['Value'] + $DBStats['Com_update']['Value']) / $DBStats['Uptime']['Value']) { echo ' class="invalid" '; } ?>>Cache:</td>
                <td><?= number_format($MemStats['cmd_set'] / $MemStats['uptime'],5) ?>/s</td>
            </tr>
            <tr>
                <td>Database:</td>
                <td><?= number_format(($DBStats['Com_insert']['Value'] + $DBStats['Com_update']['Value']) / $DBStats['Uptime']['Value'],5) ?>/s</td>
            </tr>
            <tr><td colspan="2"></td></tr>
            <tr><td colspan="2"><strong>Get/Select</strong></td></tr>
            <tr>
                <td>Cache:</td>
                <td><?= number_format($MemStats['get_hits'] / $MemStats['uptime'],5) ?>/s</td>
            </tr>
            <tr>
                <td>Database:</td>
                <td><?= number_format($DBStats['Com_select']['Value'] / $DBStats['Uptime']['Value'],5) ?>/s</td>
            </tr>
            <tr><td colspan="2"><strong>Set/Insert</strong></td></tr>
            <tr>
                <td>Cache:</td>
                <td><?= number_format($MemStats['cmd_set'] / $MemStats['uptime'],5) ?>/s</td>
            </tr>
            <tr>
                <td>Database:</td>
                <td><?= number_format($DBStats['Com_insert']['Value'] / $DBStats['Uptime']['Value'],5) ?>/s</td>
            </tr>
            <tr>
                <td>Cache increment:</td>
                <td><?= number_format($MemStats['incr_hits'] / $MemStats['uptime'],5) ?>/s</td>
            </tr>
            <tr>
                <td>Cache decrement:</td>
                <td><?= number_format($MemStats['decr_hits'] / $MemStats['uptime'],5) ?>/s</td>
            </tr>
            <tr><td colspan="2"><strong>CAS/Updates</strong></td></tr>
            <tr>
                <td>Cache:</td>
                <td><?= number_format($MemStats['cas_hits'] / $MemStats['uptime'],5) ?>/s</td>
            </tr>
            <tr>
                <td>Database:</td>
                <td><?= number_format($DBStats['Com_update']['Value'] / $DBStats['Uptime']['Value'],5) ?>/s</td>
            </tr>
            <tr><td colspan="2"><strong>Deletes</strong></td></tr>
            <tr>
                <td>Cache:</td>
                <td><?= number_format($MemStats['delete_hits'] / $MemStats['uptime'],5) ?>/s</td>
            </tr>
            <tr>
                <td>Database:</td>
                <td><?= number_format($DBStats['Com_delete']['Value'] / $DBStats['Uptime']['Value'],5) ?>/s</td>
            </tr>
            <tr><td colspan="2"></td></tr>
            <tr><td colspan="2"><strong>Special</strong></td></tr>
            <tr>
                <td>Expired unfetched:</td>
                <td><?= number_format($MemStats['expired_unfetched'] / $MemStats['uptime'],5) ?>/s</span></td>
            </tr>
            <tr>
                <td>Evicted unfetched:</td>
                <td><?= number_format($MemStats['evicted_unfetched'] / $MemStats['uptime'],5) ?>/s</td>
            </tr>
            <tr>
                <td>Evicted:</td>
                <td><?= number_format($MemStats['evictions'] / $MemStats['uptime'],5) ?>/s</td>
            </tr>
            <tr>
                <td>Slabs moved:</td>
                <td><?= number_format($MemStats['slabs_moved'] / $MemStats['uptime'],5) ?>/s</span></td>
            </tr>
            <tr>
                <td>Database slow:</td>
                <td><?= number_format($DBStats['Slow_queries']['Value'] / $DBStats['Uptime']['Value'],5) ?>/s</td>
            </tr>
            <tr><td colspan="2"></td></tr>
            <tr><td colspan="2"><strong>Data read</strong></td></tr>
            <tr>
                <td>Cache:</td>
                <td><?= Format::get_size($MemStats['bytes_read'] / $MemStats['uptime']) ?>/s</td>
            </tr>
            <tr>
                <td>Database:</td>
                <td><?= Format::get_size($DBStats['Bytes_received']['Value'] / $DBStats['Uptime']['Value']) ?>/s</td>
            </tr>
            <tr><td colspan="2"><strong>Data write</strong></td></tr>
            <tr>
                <td>Cache:</td>
                <td><?= Format::get_size($MemStats['bytes_written'] / $MemStats['uptime']) ?>/s</td>
            </tr>
            <tr>
                <td>Database:</td>
                <td><?= Format::get_size($DBStats['Bytes_sent']['Value'] / $DBStats['Uptime']['Value']) ?>/s</td>
            </tr>
        </table>
    </div>
</div>
<?php
View::show_footer();
