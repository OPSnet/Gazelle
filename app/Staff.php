<?php

namespace Gazelle;

class Staff extends BaseUser {
    final public const tableName = 'staff_blog_visits';

    public function flush(): static  {
        $this->user()->flush();
        return $this;
    }
    public function link(): string { return $this->user()->link(); }
    public function location(): string { return $this->user()->location(); }

    public function blogAlert(): bool {
        if (($readTime = self::$cache->get_value('staff_blog_read_' . $this->user->id())) === false) {
            $readTime = self::$db->scalar('
                SELECT unix_timestamp(Time)
                FROM staff_blog_visits
                WHERE UserID = ?
                ', $this->user->id()
            ) ?? 0;
            self::$cache->cache_value('staff_blog_read_' . $this->user->id(), $readTime, 1_209_600);
        }
        if (($blogTime = self::$cache->get_value('staff_blog_latest_time')) === false) {
            $blogTime = self::$db->scalar('
                SELECT unix_timestamp(max(Time))
                FROM staff_blog
                '
            ) ?? 0;
            self::$cache->cache_value('staff_blog_latest_time', $blogTime, 1_209_600);
        }
        return $readTime < $blogTime;
    }

    public function pmCount(): int {
        $cond = [
            "Status = 'Unanswered'",
            '(AssignedToUser = ? OR LEAST((SELECT max(Level) FROM permissions), Level) <= ?)',
        ];
        $effectiveClass = $this->user->privilege()->effectiveClassLevel();
        $args = [$this->user->id(), $effectiveClass];
        $classes = (new Manager\User())->classList();
        if ($effectiveClass >= $classes[MOD]['Level']) {
            $cond[] = 'Level >= ?';
            $args[] = $classes[MOD]['Level'];
        } elseif ($effectiveClass === $classes[FORUM_MOD]['Level']) {
            $cond[] = 'Level >= ?';
            $args[] = $classes[FORUM_MOD]['Level'];
        }

        return (int)self::$db->scalar("
            SELECT count(*)
            FROM staff_pm_conversations
            WHERE " . implode(' AND ', $cond), ...$args);
    }

    public function userStaffPmList(\Gazelle\User $user): array {
        self::$db->prepared_query("
            SELECT spc.ID   AS pm_id,
                spc.Subject AS subject,
                spc.Status  AS status,
                if(spc.Level = (SELECT max(Level) FROM permissions),
                    p.Name,
                    concat(coalesce(p.Name, 'First Line Support'), '+')
                )                  AS reader,
                spc.AssignedToUser AS assigned_to_user,
                spc.Date           AS date,
                spc.ResolverID     AS resolver_id,
                count(spm.ID) - 1  AS replies
            FROM staff_pm_conversations AS spc
            INNER JOIN staff_pm_messages spm ON (spm.ConvID = spc.ID)
            LEFT JOIN permissions p USING (Level)
            WHERE spc.UserID = ?
                AND (spc.Level <= ? OR spc.AssignedToUser = ?)
            GROUP BY spc.ID
            ORDER BY spc.Date DESC
            ", $user->id(), $this->user->privilege()->effectiveClassLevel(), $this->user->id()
        );
        return self::$db->to_array(false, MYSQLI_ASSOC, false);
    }
}
