<?php

namespace Gazelle\Schedule\Tasks;

class UpdateDailyTop10 extends \Gazelle\Schedule\Task
{
    public function run()
    {
        $this->db->prepared_query("
                INSERT INTO top10_history (Type) VALUES ('Daily')
        ");
        $historyID = $this->db->inserted_id();

        $top10 = $this->cache->get_value('top10tor_day_10');
        if ($top10 === false) {
            $this->db->prepared_query("
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
                LIMIT 10;
            ");

            $top10 = $this->db->to_array();
        }

        $i = 1;
        foreach ($top10 as $torrent) {
            list($torrentID, $groupID, $groupName, $groupCategoryID, $torrentTags,
                $format, $encoding, $media, $scene, $hasLog, $hasCue, $hasLogDB, $logScore, $logChecksum,
                $year, $groupYear, $remasterTitle, $snatched, $seeders, $leechers, $data) = $torrent;

            $displayName = '';

            $artists = \Artists::get_artist($groupID);

            if (!empty($artists)) {
                $displayName = \Artists::display_artists($artists, false, true);
            }

            // todo: doesn't this shit exist in classes somewhere already?

            $displayName .= $groupName;

            if ($groupCategoryID == 1 && $groupYear > 0) {
                $displayName .= " [$groupYear]";
            }

            // append extra info to torrent title
            $extraInfo = '';
            $addExtra = '';
            if ($format) {
                $extraInfo .= $format;
                $addExtra = ' / ';
            }
            if ($encoding) {
                $extraInfo .= $addExtra.$encoding;
                $addExtra = ' / ';
            }
            // "FLAC / Lossless / Log (100%) / Cue / CD";
            if ($hasLog) {
                $extraInfo .= "{$addExtra}Log".($hasLogDB ? " ($logScore%)" : "");
                $addExtra = ' / ';
            }
            if ($hasCue) {
                $extraInfo .= "{$addExtra}Cue";
                $addExtra = ' / ';
            }
            if ($media) {
                $extraInfo .= $addExtra.$media;
                $addExtra = ' / ';
            }
            if ($scene) {
                $extraInfo .= "{$addExtra}Scene";
                $addExtra = ' / ';
            }
            if ($year > 0) {
                $extraInfo .= $addExtra.$year;
                $addExtra = ' ';
            }
            if ($remasterTitle) {
                $extraInfo .= $addExtra.$remasterTitle;
            }
            if ($extraInfo != '') {
                $extraInfo = "- [$extraInfo]";
            }

            $titleString = "$displayName $extraInfo";

            $tagString = str_replace('|', ' ', $torrentTags);

            $this->db->prepared_query("
                INSERT INTO top10_history_torrents
                    (HistoryID, Rank, TorrentID, TitleString, TagString)
                VALUES
                    (?,         ?,    ?,         ?,           ?)
                ", $historyID, $i, $torrentID, $titleString, $tagString
            );
            $i++;
        }
    }
}
