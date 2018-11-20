<?php

//------------- Reward perflect FLAC uploads ----------------------------//
sleep(3);

$DB->query("
SELECT u.ID, u.Username, COUNT(t.UserID) AS c, u.FLT_Given, u.Invites_Given, u.Invites, u.FLTokens
FROM torrents t 
JOIN users_main u ON t.UserID = u.ID 
WHERE (t.HasLog = '1' AND t.LogScore = 100) OR (t.Media = 'Vinyl' OR t.Media = 'WEB' OR t.Media = 'DVD' OR t.Media = 'SACD' OR t.Media = 'BD') AND (t.Format = 'FLAC') 
GROUP BY t.UserID ORDER BY c DESC");

if ($DB->has_results()) {
    while (list($UserID, $Username, $Count, $FLT_Given, $Invites_Given, $CurInvites, $CurFLTokens) = $DB->next_record()) {
        $FLTokens = max(floor($Count / 5) - $FLT_Given, 0);
        $Invites = max(floor($Count / 20)-$Invites_Given, 0);
        $SQL = "UPDATE users_main SET FLTokens = FLTokens + $FLTokens, Invites = Invites + $Invites, FLT_Given = FLT_Given + $FLTokens, Invites_Given = Invites_Given + $Invites WHERE ID = $UserID";
        if ($FLTokens != 0) {
            G::$DB->query_unb($SQL);
            $Invites = $Invites + $CurInvites;
            $FLTokens = $FLTokens + $CurFLTokens;
            G::$Cache->begin_transaction('user_info_heavy_'.$UserID);
            G::$Cache->update_row(false, ['Invites' => $Invites, 'FLTokens' => $FLTokens]);
            G::$Cache->commit_transaction(0);
        }
    }
}
