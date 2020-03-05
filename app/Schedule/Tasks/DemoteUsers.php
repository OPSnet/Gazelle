<?php

namespace Gazelle\Schedule\Tasks;

class DemoteUsers extends \Gazelle\Schedule\Task
{
    public function run()
    {
        $criteria = array_reverse(\Users::get_promotion_criteria());
        foreach ($criteria as $l) { // $l = Level
            $fromClass = \Users::make_class_string($l['To']);
            $toClass = \Users::make_class_string($l['From']);
            $this->debug("Begin demoting users from $fromClass to $toClass");

            $query = "
                    SELECT ID
                    FROM users_main
                    INNER JOIN users_leech_stats uls ON (uls.UserID = users_main.ID)
                    INNER JOIN users_info ui ON (users_main.ID = ui.UserID)
                    LEFT JOIN
                    (
                        SELECT UserID, SUM(Bounty) AS Bounty
                        FROM requests_votes
                        GROUP BY UserID
                    ) b ON (b.UserID = users_main.ID)
                    WHERE users_main.PermissionID = ?
                    AND (uls.Uploaded + IFNULL(b.Bounty, 0) < ?
                            OR (
                                SELECT count(ID)
                                FROM torrents
                                WHERE UserID = users_main.ID
                                ) < ?";
            if (!empty($l['Extra'])) {
                $query .= ' OR NOT '.$l['Extra'];
            }
            $query .= "
                    )
                    AND users_main.Enabled = '1'";

            $this->db->prepared_query($query, $l['To'], $l['MinUpload'], $l['MinUploads']);
            $userIds = $this->db->collect('ID');

            if (count($userIds) > 0) {
                $this->info(sprintf('Demoting %d users from %s to %s', count($userIds), $fromClass, $toClass));
                $this->processed += count($userIds);

                $params = array_merge([$l['From']], $userIds);
                $placeholders = implode(', ', array_fill(0, count($userIds), '?'));
                $this->db->prepared_query("
                    UPDATE users_main
                    SET PermissionID = ?
                    WHERE ID IN ($placeholders)
                    ", ...$params
                );

                foreach ($userIds as $userId) {
                    $this->debug(sprintf('Demoting %d from %s to %s', $userId, $fromClass, $toClass), $userId);

                    $this->cache->delete_value("user_info_$userId");
                    $this->cache->delete_value("user_info_heavy_$userId");
                    $this->cache->delete_value("user_stats_$userId");
                    $this->cache->delete_value("enabled_$userId");
                    $comment = sprintf("%s - Class changed to %s by System\n\n", sqltime(), $toClass);
                    $this->db->prepared_query("
                        UPDATE users_info
                        SET AdminComment = CONCAT(?, AdminComment)
                        WHERE UserID = ?
                        ", $comment, $userId
                    );

                    \Misc::send_pm($userId, 0, "You have been demoted to $toClass", "You now only qualify for the \"$toClass\" user class.\n\nTo read more about ".SITE_NAME."'s user classes, read [url=".site_url()."wiki.php?action=article&amp;name=userclasses]this wiki article[/url].");
                }
            }
        }
    }
}
