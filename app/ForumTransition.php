<?php

namespace Gazelle;

/**
 * Custom rules to allow a user to move a thread from one forum to another.
 * For instance, to allow FLS to move Help threads to completed.
 *
 * Granularity is offered through
 *  - minimum userclass level (e.g. all staff)
 *  - belonging to a secondary class
 *  - userid in a specific list
 * Currently not implemented:
 *  - having a site privilege (e.g. 'site_edit_requests')
 */

class ForumTransition extends BaseObject {
    final public const tableName     = 'forums_transitions';
    final public const pkName        = 'forums_transitions_id';
    final public const CACHE_KEY     = 'ftransv2_%d';

    public function flush(): static {
        self::$cache->delete_value(sprintf(self::CACHE_KEY, $this->id));
        unset($this->info);
        return $this;
    }

    public function link(): string {
        return "";
    }

    public function location(): string {
        return "tools.php?action=forum_transitions";
    }

    public function info(): array {
        if (isset($this->info)) {
            return $this->info;
        }
        $key  = sprintf(self::CACHE_KEY, $this->id);
        $info = self::$cache->get_value($key);
        if ($info === false) {
            $info = self::$db->rowAssoc("
                SELECT source,
                    destination,
                    label,
                    permission_levels,
                    permission_class AS minimum_user_class,
                    permissions,
                    user_ids
                FROM forums_transitions
                WHERE forums_transitions_id = ?
                ", $this->id
            );
            if ($info) {
                $info['user_id_list'] = $info['user_ids'] ? array_map('intval', explode(',', $info['user_ids'])) : [];
                $info['privilege_list'] = $info['permissions'] ? array_map('trim', explode(',', $info['permissions'])) : [];
                $info['secondary_class_id_list'] = $info['permission_levels'] ? array_map('intval', explode(',', $info['permission_levels'])) : [];
                unset(
                    $info['user_ids'],
                    $info['permissions'],
                    $info['permission_levels'],
                );
                self::$cache->cache_value($key, $info, 0);
            }
        }
        $this->info = $info;
        return $this->info;
    }

    /**
     * From the control panel, a user may have a transition in a forum if
     *  - their userclass is sufficiently high
     *  - they are explicitly permitted via userid
     *  - they belong to a permitted secondary class
     */
    public function hasUser(User $user): bool {
        return $this->classLevel() <= $user->classLevel()
            || in_array($user->id(), $this->userIdList())
            || array_intersect(
                array_keys((new User\Privilege($user))->secondaryClassList()),
                $this->secondaryClassIdList()
            );
    }

    /**
     * A non-staff user cannot transition a thread if it is locked or pinned.
     */
    public function hasUserForThread(User $user, ForumThread $thread): bool {
        return $this->hasUser($user)
            && (
                $user->permitted('site_admin_forums')
                || (!$thread->isPinned() && !$thread->isLocked())
            );
    }

    public function destinationId(): int {
        return $this->info()['destination'];
    }

    public function sourceId(): int {
        return $this->info()['source'];
    }

    public function label(): string {
        return $this->info()['label'];
    }

    public function classLevel(): int {
        return $this->info()['minimum_user_class'];
    }

    public function secondaryClassIdList(): array {
        return $this->info()['secondary_class_id_list'];
    }

    public function userIdList(): array {
        return $this->info()['user_id_list'];
    }

    public function remove(): int {
        $id = $this->id;
        self::$db->prepared_query("
            DELETE FROM forums_transitions WHERE forums_transitions_id = ?
            ", $id
        );
        $affected = self::$db->affected_rows();
        self::$cache->delete_multi([
            Manager\ForumTransition::LIST_KEY,
            sprintf(Manager\ForumTransition::ID_KEY, $id),
        ]);
        $this->flush();
        return $affected;
    }
}
