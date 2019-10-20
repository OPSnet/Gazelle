<?php

$Where = ["t.HasLog='1'", "t.HasLogDB='0'"];

if ($_GET['type'] === 'active') {
    $Where[] = "tls.last_action > '".(new \DateTime())->sub(new \DateInterval('P14D'))->format('Y-m-d')."'";
}
else {
    $Where[] = 'tls.Seeders > 0';
}

$Where = implode(' AND ', $Where);
$DB->prepared_query("SELECT t.ID FROM torrents t INNER JOIN torrents_leech_stats tls ON (tls.TorrentID = t.ID) WHERE {$Where}");

json_print('success', ['IDs' => $DB->collect('ID', false)]);
