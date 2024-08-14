<?php
/** @phpstan-var \Gazelle\User $Viewer */

if (!$Viewer->permitted('site_archive_ajax')) {
    json_die('failure', 'insufficient permissions to view page');
}

$where = ["t.HasLog='1'", "t.HasLogDB='0'"];

if ($_GET['type'] === 'active') {
    $where[] = 'tls.last_action > now() - INTERVAL 14 DAY';
} elseif ($_GET['type'] === 'unseeded') {
    $where = ['tls.Seeders = 0'];
} else {
    $where[] = 'tls.Seeders > 0';
}

$where = implode(' AND ', $where);
$db = Gazelle\DB::DB();
$db->prepared_query("SELECT t.ID FROM torrents t INNER JOIN torrents_leech_stats tls ON (tls.TorrentID = t.ID) WHERE {$where}");

json_print('success', ['IDs' => $db->collect('ID', false)]);
