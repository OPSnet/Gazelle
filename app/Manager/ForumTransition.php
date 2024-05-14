<?php

namespace Gazelle\Manager;

class ForumTransition extends \Gazelle\BaseManager {
    final public const LIST_KEY = 'forum_transit';
    final public const ID_KEY   = 'zz_ftx_%d';

    protected array $info;

    public function flush(): void {
        self::$cache->delete_value(self::LIST_KEY);
    }

    public function create(
        \Gazelle\Forum $source,
        \Gazelle\Forum $destination,
        string         $label,
        int            $userClass,
        string         $secondaryClasses,
        string         $privileges,
        string         $userIds,
    ): \Gazelle\ForumTransition {

        $privilegeList = empty(trim($privileges))
            ? []
            : array_map(fn($p) => trim($p), explode(',', trim($privileges)));

        $secondaryClassList = empty(trim($secondaryClasses))
            ? []
            : array_map(fn($id) => (int)(trim($id)), explode(',', trim($secondaryClasses)));

        $userIdList = empty(trim($userIds))
            ? []
            : array_map(fn($id) => (int)(trim($id)), explode(',', trim($userIds)));

        self::$db->prepared_query("
            INSERT INTO forums_transitions
                   (source, destination, label, permission_class, permission_levels, permissions, user_ids)
            VALUES (?,      ?,           ?,     ?,                 ?,                ?,           ?)
            ", $source->id(),
                $destination->id(),
                $label,
                $userClass,
                implode(',', $secondaryClassList),
                implode(',', $privilegeList),
                implode(',', $userIdList),
        );
        $id = self::$db->inserted_id();
        $this->flush();
        return new \Gazelle\ForumTransition($id);
    }

    public function findById($transId): ?\Gazelle\ForumTransition {
        $key = sprintf(self::ID_KEY, $transId);
        $id = self::$cache->get_value($key);
        if ($id === false) {
            $id = self::$db->scalar("
                SELECT forums_transitions_id FROM forums_transitions WHERE forums_transitions_id = ?
                ", $transId
            );
            if (!is_null($id)) {
                self::$cache->cache_value($key, $id, 7200);
            }
        }
        return $id ? new \Gazelle\ForumTransition($id) : null;
    }

    public function transitionList(): array {
        if (isset($this->info)) {
            return $this->info;
        }
        $idList = self::$cache->get_value(self::LIST_KEY);
        if ($idList === false) {
            self::$db->prepared_query("
                SELECT forums_transitions_id FROM forums_transitions
            ");
            $idList = self::$db->collect(0, false);
            self::$cache->cache_value(self::LIST_KEY, $idList, 0);
        }
        $this->info = [];
        foreach ($idList as $id) {
            $this->info[$id] = $this->findById($id);
        }
        return $this->info;
    }

    /**
     * This is used in the control panel, where no thread context exists.
     */
    public function userTransitionList(\Gazelle\User $user): array {
        return array_filter($this->transitionList(), fn ($t) => $t->hasUser($user));
    }

    /**
     * This is used in a thread, where we can check if it is pinned or locked and
     * skip the transition if the viewer is not staff.
     */
    public function userThreadTransitionList(\Gazelle\User $user, \Gazelle\ForumThread $thread): array {
        return array_filter($this->transitionList(), fn ($t) => $t->hasUserForThread($user, $thread));
    }

    public function threadTransitionList(\Gazelle\User $user, \Gazelle\ForumThread $thread): array {
        return array_filter($this->userThreadTransitionList($user, $thread), fn ($t) => $t->sourceId() == $thread->forum()->id());
    }
}
