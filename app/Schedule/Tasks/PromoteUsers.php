<?php

namespace Gazelle\Schedule\Tasks;

class PromoteUsers extends \Gazelle\Schedule\Task
{
    public function run()
    {
        $userMan = new \Gazelle\Manager\User;
        $criteria = $userMan->promotionCriteria();
        foreach ($criteria as $l) { // $l = Level
            $fromClass = $userMan->userclassName($l['From']);
            $toClass = $userMan->userclassName($l['To']);
            $this->debug("Begin promoting users from $fromClass to $toClass");
            $query = "
                SELECT ID
                FROM users_main
                INNER JOIN users_leech_stats uls ON (uls.UserID = users_main.ID)
                INNER JOIN users_info ui ON (users_main.ID = ui.UserID)
                LEFT JOIN
                (
                    SELECT rv.UserID, sum(Bounty) AS Bounty
                    FROM requests_votes rv
                    INNER JOIN requests r ON (r.ID = rv.RequestID)
                    WHERE r.UserID != r.FillerID
                    GROUP BY rv.UserID
                ) b ON (b.UserID = users_main.ID)
                WHERE users_main.PermissionID = ?
                AND ui.Warned IS NULL
                AND uls.Uploaded + coalesce(b.Bounty, 0) >= ?
                AND (uls.Downloaded = 0 OR uls.Uploaded / uls.Downloaded >= ?)
                AND ui.JoinDate < now() - INTERVAL ? WEEK
                AND (
                    SELECT count(ID)
                    FROM torrents
                    WHERE UserID = users_main.ID
                ) >= ?
                AND users_main.Enabled = '1'";

            $params = [$l['From'], $l['MinUpload'], $l['MinRatio'], $l['Weeks'], $l['MinUploads']];

            if (!empty($l['Extra'])) {
                $subQueries = array_map(function ($v) use (&$params) {
                    $params[] = $v['Count'];
                    return sprintf('(
                                %s
                            ) >= ?', $v['Query']);
                }, $l['Extra']);

                $query .= sprintf('
                        AND (
                            %s
                        )', implode(' AND ', $subQueries));
            }

            self::$db->prepared_query($query, ...$params);
            $userIds = self::$db->collect('ID');

            if (count($userIds) > 0) {
                $this->info(sprintf('Promoting %d users from %s to %s', count($userIds), $fromClass, $toClass));
                $this->processed += count($userIds);

                self::$db->prepared_query("
                    UPDATE users_main SET
                        PermissionID = ?
                    WHERE ID IN (" . placeholders($userIds) . ")
                    ", $l['To'], ...$userIds
                );

                foreach ($userIds as $userId) {
                    $this->debug(sprintf('Promoting %d from %s to %s', $userId, $fromClass, $toClass), $userId);
                    self::$cache->deleteMulti(["u_$userId", "user_stats_$userId", "user_rlim_$userId"]);
                    $comment = sprintf("%s - Class changed to %s by System\n\n", sqltime(), $toClass);
                    self::$db->prepared_query("
                        UPDATE users_info SET
                            AdminComment = CONCAT(?, AdminComment)
                        WHERE UserID = ?
                        ", $comment, $userId
                    );

                    $userMan->sendPM($userId, 0,
                        "You have been promoted to $toClass",
                        "Congratulations on your promotion to $toClass!\n\nTo read more about "
                            . SITE_NAME
                            . "'s user classes, read [url=wiki.php?action=article&amp;name=userclasses]this wiki article[/url]."
                    );
                }
            }
        }
    }
}
