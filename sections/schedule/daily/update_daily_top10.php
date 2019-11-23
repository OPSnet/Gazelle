<?php

// Daily top 10 history.
$DB->query("
        INSERT INTO top10_history (Date, Type)
        VALUES ('$sqltime', 'Daily')");
$HistoryID = $DB->inserted_id();

$Top10 = $Cache->get_value('top10tor_day_10');
if ($Top10 === false) {
    $DB->query("
            SELECT
                t.ID,
                g.ID,
                g.Name,
                g.CategoryID,
                g.TagList,
                t.Format,
                t.Encoding,
                t.Media,
                t.Scene,
                t.HasLog,
                t.HasCue,
                t.HasLogDB,
                t.LogScore,
                t.LogChecksum,
                t.RemasterYear,
                g.Year,
                t.RemasterTitle,
                tls.Snatched,
                tls.Seeders,
                tls.Leechers,
                ((t.Size * tls.Snatched) + (t.Size * 0.5 * tls.Leechers)) AS Data
            FROM torrents AS t
            INNER JOIN torrents_leech_stats tls ON (tls.TorrentID = t.ID)
            INNER JOIN torrents_group AS g ON (g.ID = t.GroupID)
            WHERE tls.Seeders > 0
                AND t.Time > (now() - INTERVAL 1 DAY)
            ORDER BY (tls.Seeders + tls.Leechers) DESC
            LIMIT 10;");

    $Top10 = $DB->to_array();
}

$i = 1;
foreach ($Top10 as $Torrent) {
    list($TorrentID, $GroupID, $GroupName, $GroupCategoryID, $TorrentTags,
        $Format, $Encoding, $Media, $Scene, $HasLog, $HasCue, $HasLogDB, $LogScore, $LogChecksum,
        $Year, $GroupYear, $RemasterTitle, $Snatched, $Seeders, $Leechers, $Data) = $Torrent;

    $DisplayName = '';

    $Artists = Artists::get_artist($GroupID);

    if (!empty($Artists)) {
        $DisplayName = Artists::display_artists($Artists, false, true);
    }

    $DisplayName .= $GroupName;

    if ($GroupCategoryID == 1 && $GroupYear > 0) {
        $DisplayName .= " [$GroupYear]";
    }

    // append extra info to torrent title
    $ExtraInfo = '';
    $AddExtra = '';
    if ($Format) {
        $ExtraInfo .= $Format;
        $AddExtra = ' / ';
    }
    if ($Encoding) {
        $ExtraInfo .= $AddExtra.$Encoding;
        $AddExtra = ' / ';
    }
    // "FLAC / Lossless / Log (100%) / Cue / CD";
    if ($HasLog) {
        $ExtraInfo .= "{$AddExtra}Log".($HasLogDB ? " ($LogScore%)" : "");
        $AddExtra = ' / ';
    }
    if ($HasCue) {
        $ExtraInfo .= "{$AddExtra}Cue";
        $AddExtra = ' / ';
    }
    if ($Media) {
        $ExtraInfo .= $AddExtra.$Media;
        $AddExtra = ' / ';
    }
    if ($Scene) {
        $ExtraInfo .= "{$AddExtra}Scene";
        $AddExtra = ' / ';
    }
    if ($Year > 0) {
        $ExtraInfo .= $AddExtra.$Year;
        $AddExtra = ' ';
    }
    if ($RemasterTitle) {
        $ExtraInfo .= $AddExtra.$RemasterTitle;
    }
    if ($ExtraInfo != '') {
        $ExtraInfo = "- [$ExtraInfo]";
    }

    $TitleString = "$DisplayName $ExtraInfo";

    $TagString = str_replace('|', ' ', $TorrentTags);

    $DB->query("
            INSERT INTO top10_history_torrents
                (HistoryID, Rank, TorrentID, TitleString, TagString)
            VALUES
                ($HistoryID, $i, $TorrentID, '".db_string($TitleString)."', '".db_string($TagString)."')");
    $i++;
}
