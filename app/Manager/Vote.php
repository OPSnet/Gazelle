<?php

namespace Gazelle\Manager;

use Gazelle\TGroup;
use Gazelle\User\Vote as UserVote;

class Vote extends \Gazelle\Base {
    public function merge(
        TGroup $old,
        TGroup $new,
        User   $userManager = new User(),
    ): int {
        // Get a list of everybody who voted on either group to clear their cache keys
        $this->pg()->begin();
        $userIdList = $this->pg()->columnex('
            select id_user from tgroup_vote where id_tgroup in ($1, $2)
            ', $old->id, $new->id
        );

        // Add the votes to the new group that are not already there
        $this->pg()->query('
            insert into tgroup_vote
            select $1, id_user, created, upvote
            from tgroup_vote
            where id_tgroup = $2
                on conflict (id_tgroup, id_user) do nothing
            ', $new->id, $old->id
        );
        $this->pg()->query('
            delete from tgroup_vote where id_tgroup = $1
            ', $old->id
        );

        // Rebuild the summary of the new group
        $this->pg()->query("
            delete from tgroup_vote_summary where id_tgroup in ($1, $2)
            ", $new->id, $old->id
        );
        $this->pg()->query("
            insert into tgroup_vote_summary (id_tgroup, total, upvote)
            select id_tgroup,
                count(*),
                sum(case when upvote is true then 1 else 0 end)
            from tgroup_vote
            where id_tgroup = $1
            group by id_tgroup
            ", $new->id
        );
        $this->pg()->commit();

        // Decache everything
        self::$cache->delete_multi([
            "vote_pairs_{$old->id}",
            "vote_pairs_{$new->id}",
        ]);
        $affected = 0;
        foreach ($userIdList as $userId) {
            $user = $userManager->findById($userId);
            if ($user) {
                new UserVote($user)->flush();
                ++$affected;
            }
        }
        return $affected;
    }
}
