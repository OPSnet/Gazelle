<?php

namespace Gazelle\User;

class Privilege extends \Gazelle\BaseUser {
    final const CACHE_KEY = 'u_priv_%d';

    protected array $info;

    public function info(): array {
        if (isset($this->info) && !empty($this->info)) {
            return $this->info;
        }
        $id = $this->user->id();
        $key = sprintf(self::CACHE_KEY, $id);
        $info = self::$cache->get_value($key);
        if ($info !== false) {
            return $this->info = $info;
        }
        $qid = self::$db->get_query_id();
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
        $this->info = self::$db->to_array('ID', MYSQLI_ASSOC, false);
        self::$db->set_query_id($qid);
        self::$cache->cache_value($key, $this->info, 3600);
        return $this->info;
    }

    public function isDonor(): bool       { return isset($this->info()[DONOR]) || $this->user->isStaff(); }
    public function isFLS(): bool         { return isset($this->info()[FLS_TEAM]); }
    public function isInterviewer(): bool { return isset($this->info()[INTERVIEWER]); }
    public function isRecruiter(): bool   { return isset($this->info()[RECRUITER]); }

    public function allowedForumList(): array {
        $allowed = [];
        foreach ($this->info() as $p) {
            foreach (array_map('intval', explode(',', $p['PermittedForums'])) as $forumId) {
                if ($forumId) {
                    if ($forumId == INVITATION_FORUM_ID && $this->user->disableInvites()) {
                        continue;
                    }
                    $allowed[$forumId] = true;
                }
            }
        }
        return array_keys($allowed);
    }

    /**
     * The user's badges and their names
     *
     * @return array list of badges
     *  e.g. ['IN' => 'Interviewer', 'R' => 'Recruiter']
     */
    public function badgeList(): array {
        return array_merge(
            ...array_map(
                fn ($x) => [$x['badge'] => $x['Name']],
                array_filter(
                    $this->info(),
                    fn ($x) => !is_null($x['badge'])
                )
            )
        );
    }

    /**
     * The maximum secondary class level to which the user belongs
     *
     * @return int corresponding permissions.Level value
     */
    public function maxSecondaryLevel(): int {
        $level = array_map(fn ($x) => $x['Level'], $this->info());
        return $level ? max($level) : 0;
    }

    public function secondaryPrivilegeList(): array {
        $privilege = [];
        foreach ($this->info() as $p) {
            $privilege = array_merge($privilege, unserialize($p['Values']));
        }
        return $privilege;
    }

    public function secondaryClassList(): array {
        return array_map(fn($x) => $x['Name'], $this->info());
    }
}
