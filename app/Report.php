<?php

namespace Gazelle;

class Report
{
    public static function search($db, array $filter)
    {
        $cond = [];
        $args = [];
        if (array_key_exists('reporter', $filter) && $filter['reporter']) {
            $cond[] = 'r.ReporterID = ?';
            $args[] = self::username2id($db, $filter['reporter']);
        }
        if (array_key_exists('handler', $filter) && $filter['handler']) {
            $cond[] = 'r.ResolverID = ?';
            $args[] = self::username2id($db, $filter['handler']);
        }
        if (array_key_exists('report-type', $filter)) {
            $cond[] = 'r.Type in (' . implode(', ', array_fill(0, count($filter['report-type']), '?')) . ')';
            $args = array_merge($args, $filter['report-type']);
        }
        if (array_key_exists('dt-from', $filter)) {
            $cond[] = 'r.ReportedTime >= ?';
            $args[] = $filter['dt-from'];
        }
        if (array_key_exists('dt-until', $filter)) {
            $rpt_cond[] = 'r.ReportedTime <= ? + INTERVAL 1 DAY';
            $rpt_args[] = $filter['dt-until'];
        }
        if (array_key_exists('torrent', $filter)) {
            $rpt_cond[] = 'r.TorrentID = ?';
            $rpt_args[] = $filter['torrent'];
        }
        if (array_key_exists('uploader', $filter) && $filter['uploader']) {
            $cond[] = 't.UserID = ?';
            $args[] = self::username2id($db, $filter['uploader']);
        }
        if (array_key_exists('torrent', $filter)) {
            $cond[] = 'r.TorrentID = ?';
            $args[] = $filter['torrent'];
        }
        if (array_key_exists('group', $filter)) {
            $cond[] = 't.GroupID = ?';
            $args[] = $filter['group'];
        }
        if (count($cond) == 0) {
            $cond = ['1 = 1'];
        }
        $conds = implode(' AND ', $cond);
        /* The construct below is pretty sick: we alias the group_log table to t
		 * which means that t.GroupID in a condition refers to the same thing in
		 * the `torrents` table as well. I am not certain this is entirely sane.
		 */
        $sql_base = "
			FROM reportsv2 r
			LEFT JOIN torrents t ON (t.ID = r.TorrentID)
			LEFT JOIN torrents_group g on (g.ID = t.GroupID)
			LEFT JOIN (
				SELECT max(t.ID) AS ID, t.TorrentID
				FROM group_log t
				INNER JOIN reportsv2 r using (TorrentID)
				WHERE t.Info NOT LIKE 'uploaded%'
				AND $conds
				GROUP BY t.TorrentID
			) LASTLOG USING (TorrentID)
			LEFT JOIN group_log gl ON (gl.ID = LASTLOG.ID)
			WHERE
				$conds";
        $sql = "SELECT count(*) $sql_base";
        $db->prepared_query_array($sql, array_merge($args, $args));
        list($total_results) = $db->next_record();
        if (!$total_results) {
            return [[], $total_results];
        }
        $sql = "
			SELECT r.ID, r.ReporterID, r.ResolverID, r.TorrentID, t.UserID, t.GroupID, t.Media, t.Format, t.Encoding, coalesce(g.Name, gl.Info) as Name, g.Year, r.Type, r.ReportedTime
			$sql_base
			ORDER BY r.ReportedTime DESC LIMIT ? OFFSET ?
		";
        $args = array_merge(
            $args,
            $args,
            [
                TORRENTS_PER_PAGE, // LIMIT
                TORRENTS_PER_PAGE * (max($filter['page'], 1) - 1), // OFFSET
            ]
        );
        $db->prepared_query_array($sql, $args);
        return [$db->to_array(), $total_results];
    }

    private static function username2id($db, $name)
    {
        $db->prepared_query('SELECT ID FROM users_main WHERE Username = ?', $name);
        $user = $db->next_record();
        return $user['ID'];
    }
}
