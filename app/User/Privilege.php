<?php

namespace Gazelle\User;

class Privilege extends \Gazelle\BaseUser {
    final public const tableName = 'users_levels';
    final public const CACHE_KEY = 'u_prv_%d';

    public function flush(): static {
        unset($this->info);
        self::$cache->delete_value(sprintf(self::CACHE_KEY, $this->id()));
        return $this;
    }

    public function info(): array {
        if (isset($this->info)) {
            return $this->info;
        }
        $id = $this->user->id();
        $key = sprintf(self::CACHE_KEY, $id);
        $info = self::$cache->get_value($key);
        if ($info !== false) {
            return $this->info = $info;
        }

        $config = self::$db->rowAssoc("
            SELECT um.CustomPermissions AS custom_privileges,
                ui.PermittedForums      AS user_allowed_forums,
                ui.RestrictedForums     AS user_forbidden_forums,
                p.PermittedForums       AS class_forums,
                p.Values                AS privileges,
                p.Level                 AS class_level
            FROM users_main um
            INNER JOIN users_info ui ON (ui.UserID = um.ID)
            INNER JOIN permissions p ON (p.ID = um.PermissionID)
            WHERE um.ID = ?
            ", $this->id()
        );
        $info = [
            'level'                 => $config['class_level'],
            'allowed_forums'        => $config['user_allowed_forums'],
            'forbidden_forums'      => $config['user_forbidden_forums'],
            'user_allowed_forums'   => $config['user_allowed_forums'],
            'user_forbidden_forums' => $config['user_forbidden_forums'],
            'level_secondary'       => 0,
            'badge'                 => [],
            'forum'                 => [],
            'privilege'             => [],
            'custom'                => [],
            'secondary'             => [],
        ];
        foreach (unserialize($config['privileges']) ?: [] as $name => $value) {
            $info['privilege'][$name] = (bool)$value;
        }
        foreach (array_map('intval', explode(',', $config['class_forums'])) as $forumId) {
            if ($forumId) {
                $info['forum'][$forumId] = true;
            }
        }

        self::$db->prepared_query("
            SELECT p.ID,
                p.Level,
                p.Name,
                p.PermittedForums,
                p.Values,
                if(p.badge = '', NULL, p.badge) as badge
            FROM permissions p
            INNER JOIN users_levels ul ON (ul.PermissionID = p.ID)
            WHERE ul.UserID = ?
            ORDER BY p.Level DESC
            ", $id
        );
        foreach (self::$db->to_array('ID', MYSQLI_ASSOC, false) as $class) {
            $info['secondary'][$class['ID']] = $class['Name'];
            if ($class['badge']) {
                $info['badge'][$class['badge']] = $class['Name'];
            }
            if ($info['level_secondary'] < $class['Level']) {
                $info['level_secondary'] = $class['Level'];
            }
            foreach (unserialize($class['Values']) ?: [] as $name => $value) {
                $info['privilege'][$name] = (bool)$value;
            }
            foreach (array_map('intval', explode(',', $class['PermittedForums'])) as $forumId) {
                if ($forumId) {
                    if ($forumId == INVITATION_FORUM_ID && $this->user->disableInvites()) {
                        continue;
                    }
                    $info['forum'][$forumId] = true;
                }
            }
        }
        asort($info['secondary']);

        // a custom privilege may revoke a primary or secondary grant
        foreach (unserialize($config['custom_privileges'] ?? '') ?: [] as $name => $value) {
            if (!is_null($value)) {
                $info['custom'][$name] = (bool)$value;
            }
        }

        // user-level forum overrides
        foreach (array_map('intval', explode(',', $config['user_allowed_forums'])) as $forumId) {
            if ($forumId) {
                $info['forum'][$forumId] = true;
            }
        }
        // user-forbidden may override class-allowed
        foreach (array_map('intval', explode(',', $config['user_forbidden_forums'])) as $forumId) {
            if ($forumId) {
                $info['forum'][$forumId] = false;
            }
        }

        self::$cache->cache_value($key, $info, 3600);
        return $this->info = $info;
    }

    public function isSecondary(int $privilegeId): bool {
        return isset($this->info()['secondary'][$privilegeId]);
    }

    public function isFLS(): bool {
        return $this->isSecondary(FLS_TEAM);
    }

    public function isInterviewer(): bool {
        return $this->isSecondary(INTERVIEWER);
    }

    public function isRecruiter(): bool {
        return $this->isSecondary(RECRUITER);
    }

    public function allowedForumList(): array {
        return $this->info()['forum'];
    }

    /**
     * The user's badges and their names
     *
     * @return array list of badges
     *  e.g. ['IN' => 'Interviewer', 'R' => 'Recruiter']
     */
    public function badgeList(): array {
        return $this->info()['badge'];
    }

    public function permitted(string $privilege): bool {
        return $this->info()['custom'][$privilege] ?? $this->info()['privilege'][$privilege] ?? false;
    }

    public function effectiveClassLevel(): int {
        return max($this->info()['level'], $this->info()['level_secondary']);
    }

    /**
     * The forums forbidden for this user. (Usually the same as forbiddenUserForums()).
     */
    public function forbiddenForums(): string {
        return $this->info()['forbidden_forums'];
    }

    /**
     * The forums forbidden for this user (shown in staff section of profile page).
     * The userclass may allow access, but these are overridden.
     */
    public function forbiddenUserForums(): ?string {
        return $this->info()['user_forbidden_forums'];
    }

    /**
     * All forums allowed for this user, over and above those authorized by userclass
     */
    public function permittedForums(): string {
        return $this->info()['allowed_forums'];
    }

    /**
     * The extra forums allowed for this user, over and above those authorized by userclass
     */
    public function permittedUserForums(): ?string {
        return $this->info()['user_allowed_forums'];
    }

    /**
     * Forbidden forum ids for the user
     */
    public function forbiddenForumIdList(): array {
        return array_keys(array_filter($this->info()['forum'], fn ($v) => $v === false));
    }

    /**
     * Extra permitted forum ids for the user
     */
    public function permittedForumIdList(): array {
        return array_keys(array_filter($this->info()['forum'], fn ($v) => $v === true));
    }

    /**
     * The maximum secondary class level to which the user belongs
     */
    public function maxSecondaryLevel(): int {
        return $this->info()['level_secondary'];
    }

    public function customList(): array {
        return $this->info()['custom'];
    }

    public function defaultList(): array {
        return $this->info()['privilege'];
    }

    /**
     * Get the secondary classes of the user (enabled or not)
     */
    public function secondaryClassesList(): array {
        self::$db->prepared_query("
            SELECT p.ID                AS permId,
                p.Name                 AS permName,
                (l.UserID IS NOT NULL) AS isSet,
                p.Level
            FROM permissions AS p
            LEFT JOIN users_levels AS l ON (l.PermissionID = p.ID AND l.UserID = ?)
            WHERE p.Secondary = 1
            ORDER BY p.Name
            ", $this->id()
        );
        return self::$db->to_array('permName', MYSQLI_ASSOC, false);
    }

    public function secondaryClassList(): array {
        return $this->info()['secondary'];
    }

    public function hasSecondaryClassId(int $userclassId): bool {
        return isset($this->info()['secondary'][$userclassId]);
    }

    public function addSecondaryClass(int $userclassId): int {
        self::$db->prepared_query("
            INSERT INTO users_levels
                   (UserID, PermissionID)
            VALUES (?,      ?)
            ", $this->id(), $userclassId
        );
        $affected = self::$db->affected_rows();
        $this->flush();
        return $affected;
    }

    public function removeSecondaryClass(int $userclassId): int {
        self::$db->prepared_query("
            DELETE
            FROM users_levels
            WHERE UserID = ?
                AND PermissionID = ?
            ", $this->id(), $userclassId
        );
        $affected = self::$db->affected_rows();
        $this->flush();
        return $affected;
    }

    /**
     * Set the custom permissions for this user
     *
     * @return int was there a change?
     */
    public function modifyCustomList(array $current): int {
        if ($current === []) {
            // clear out all custom perms
            self::$db->prepared_query("
                UPDATE users_main SET
                    CustomPermissions = NULL
                WHERE ID = ?
                ", $this->id()
            );
            $affected = self::$db->affected_rows();
            $this->flush();
            return $affected;
        }
        $default = $this->defaultList();
        $delta = [];
        foreach (array_keys(\Gazelle\Manager\Privilege::privilegeList()) as $p) {
            if (($default[$p] ?? false) && ($current[$p] ?? false) === false) {
                $delta[$p] = 0;
            } elseif (!isset($default[$p]) && ($current[$p] ?? false)) {
                $delta[$p] = 1;
            }
        }
        self::$db->prepared_query("
            UPDATE users_main SET
                CustomPermissions = ?
            WHERE ID = ?
            ", count($delta) ? serialize($delta) : null, $this->id()
        );
        $affected = self::$db->affected_rows();
        $this->flush();
        return $affected;
    }
}
