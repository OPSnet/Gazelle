<?php

namespace Gazelle\Manager;

class Vote extends \Gazelle\Base {
    public function merge(
        \Gazelle\TGroup $old,
        \Gazelle\TGroup $new,
        \Gazelle\Manager\User $userManager,
    ): int {
        // 1. Clear the votes_pairs keys
        self::$db->prepared_query("
            SELECT concat('vote_pairs_', v2.GroupId)
            FROM users_votes AS v1
            INNER JOIN users_votes AS v2 USING (UserID)
            WHERE (v1.Type = 'Up' OR v2.Type = 'Up')
                AND (v1.GroupId     IN (?, ?))
                AND (v2.GroupId NOT IN (?, ?))
            ", $old->id(), $new->id(), $old->id(), $new->id()
        );
        self::$cache->delete_multi(self::$db->collect(0, false));

        // 2. Get a list of everybody who voted on the old group and clear their cache keys
        self::$db->prepared_query("
            SELECT UserID FROM users_votes WHERE GroupID = ?
            ", $old->id()
        );
        $affected = self::$db->affected_rows();
        foreach (self::$db->collect(0, false) as $userId) {
            $user = $userManager->findById($userId);
            if ($user) {
                (new \Gazelle\User\Vote($user))->flush();
            }
        }

        self::$db->begin_transaction();

        // 3. Update the existing votes where possible, clear out the duplicates left by key
        // conflicts, and update the torrents_votes table
        self::$db->prepared_query("
            UPDATE IGNORE users_votes SET
                GroupID = ?
            WHERE GroupID = ?
            ", $new->id(), $old->id()
        );
        self::$db->prepared_query("
            DELETE FROM users_votes WHERE GroupID = ?
            ", $old->id()
        );
        self::$db->prepared_query("
            INSERT INTO torrents_votes (GroupId, Ups, Total, Score)
            SELECT                      ?,       Ups, Total, 0
            FROM (
                SELECT
                    ifnull(sum(if(Type = 'Up', 1, 0)), 0) As Ups,
                    count(*) AS Total
                FROM users_votes
                WHERE GroupID = ?
                GROUP BY GroupID
            ) AS a
            ON DUPLICATE KEY UPDATE
                Ups = a.Ups,
                Total = a.Total
            ", $new->id(), $old->id()
        );
        self::$db->prepared_query("
            UPDATE torrents_votes SET
                Score = IFNULL(binomial_ci(Ups, Total), 0)
            WHERE GroupID = ?
            ", $new->id()
        );
        return $affected;
    }
}
