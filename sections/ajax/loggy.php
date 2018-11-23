<?php

$Where = ["HasLog='1'", "HasLogDB='0'"];

if ($_GET['type'] === 'active') {
    $Where[] = "last_action > '".(new \DateTime())->sub(new \DateInterval('P14D'))->format('Y-m-d')."'";
}
else {
    $Where[] = 'Seeders > 0';
}

$Where = implode(' AND ', $Where);
$DB->prepared_query("SELECT ID FROM torrents WHERE {$Where}");

json_print('success', ['IDs' => $DB->collect('ID', false)]);
