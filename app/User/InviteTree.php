<?php

namespace Gazelle\User;

use Gazelle\Enum\UserAuditEvent;

/* The invite tree is a bodge because Mysql cannot do recursive tree queries.
 * When looking at the Invite Tree page, position() represents how long ago
 * the user was invited (the lower, the more recent the creation), and
 * depth() represents the depth of the invite chain (the number of people
 * between the ancestor inviter and the invitee).
 */

class InviteTree extends \Gazelle\BaseUser {
    protected array $info;
    protected array $tree;

    public function flush(): static {
        unset($this->info, $this->tree);
        return $this;
    }

    protected function calculate(): void {
        $width = (int)self::$db->scalar("
            select ceil(log10(max(id))) from users_main
        ");
        /* Ordinarily, list queries are usually written to return only
         * an id, and a manager is used to hydrate the object. Amongst
         * other benefits, this simplifies cache invalidation. In the
         * case of invite trees, for some origin users there can be
         * tens of thousands of results. As a consequence, this is one
         * of the few cases where the all the required fields are
         * returned by a query. The management of paranoia is
         * particularly ghastly.
         */
        self::$db->prepared_query("
            WITH RECURSIVE r AS (
                SELECT um.inviter_user_id    AS inviter_user_id,
                    um.ID                    AS user_id,
                    0                        AS depth,
                    cast(lpad(um.ID, ?, '0') AS char(5000)) AS path
                FROM users_main um
                WHERE um.ID = ?
                UNION ALL
                SELECT c.inviter_user_id,
                    c.ID,
                    depth + 1,
                    concat(r.path, lpad(c.ID, ?, '0'))
                FROM r,
                    users_main AS c
                WHERE r.user_id = c.inviter_user_id
            )
            SELECT r.user_id,
                r.inviter_user_id,
                um.created,
                r.path,
                ula.last_access             AS last_seen,
                if(locate('s:8:\"lastseen\";', um.Paranoia) > 0, 1, 0)
                                            AS paranoid_last_seen,
                um.Username                 AS username,
                um.RequiredRatio            AS required_ratio,
                if(ui.RatioWatchEnds IS NOT NULL
                    AND ui.RatioWatchEnds < now()
                    AND uls.Uploaded <= uls.Downloaded * um.RequiredRatio,
                    1, 0)                   AS on_ratio_watch,
                if(um.Enabled = '2', 1, 0)  AS disabled,
                p.Name                      AS userclass,
                p.Level                     AS userlevel,
                uls.Uploaded                AS uploaded,
                if(locate('s:10:\"uploaded\";', um.Paranoia) > 0, 1, 0)
                                            AS paranoid_up,
                uls.Downloaded              AS downloaded,
                if(locate('s:10:\"downloaded\";', um.Paranoia) > 0, 1, 0)
                                            AS paranoid_down,
                if(ul.UserID IS NULL, 0, 1) AS donor,
                r.depth                     AS depth
            FROM r
            INNER JOIN users_main um ON (um.ID = r.user_id)
            INNER JOIN users_info ui ON (um.ID = ui.UserID)
            INNER JOIN permissions p ON (p.ID = um.PermissionID)
            INNER JOIN users_leech_stats uls ON (uls.UserID = um.ID)
            LEFT JOIN user_last_access ula ON (ula.user_id = um.ID)
            LEFT JOIN users_levels ul ON (
                ul.UserID = um.ID
                AND ul.PermissionID = (
                    SELECT ID from permissions WHERE Name = 'donor'
                )
            )
            WHERE r.user_id != ?
            ORDER BY path
            ", $width, $this->id(), $width, $this->id()
        );
        $userclassMap = []; // how many people per userclass
        $userlevelMap = []; // sort userclasses by level rather than name
        $this->info = [
            'branch'     => 0,
            'downloaded' => 0,
            'depth'      => 0,
            'direct'     => [
                'down' => 0,
                'up'   => 0,
            ],
            'disabled'   => 0,
            'donor'      => 0,
            'paranoid'   => 0,
            'uploaded'   => 0,
            'userclass'  => [],
        ];
        $this->tree = [];
        $prev_depth = 0;
        foreach (self::$db->to_array(false, MYSQLI_ASSOC, false) as $row) {
            $this->info['downloaded'] += $row['downloaded'];
            $this->info['uploaded']   += $row['uploaded'];
            if ($row['depth'] == 1) {
                $this->info['direct']['down'] += $row['downloaded'];
                $this->info['direct']['up']   += $row['uploaded'];
            }
            if ($row['depth'] > $prev_depth) {
                // we have to go deeper
                $this->info['branch']++;
            }
            if (!isset($userlevelMap[$row['userclass']])) {
                $userlevelMap[$row['userclass']] = $row['userlevel'];
            }
            if (!isset($userclassMap[$row['userclass']])) {
                $userclassMap[$row['userclass']] = 0;
            }
            $userclassMap[$row['userclass']]++;
            if (!isset($this->info['userclass'][$row['userclass']])) {
                $this->info['userclass'][$row['userclass']] = 0;
            }
            $this->info['userclass'][$row['userclass']]++;
            if ($this->info['depth'] < $row['depth']) {
                $this->info['depth'] = $row['depth'];
            }
            if ($row['disabled']) {
                $this->info['disabled']++;
            }
            if ($row['donor']) {
                $this->info['donor']++;
            }
            if ($row['paranoid_down'] || $row['paranoid_up']) {
                $this->info['paranoid']++;
            }
            $this->tree[] = $row;
            $prev_depth   = $row['depth'];
        }
        uksort($userclassMap, fn($a, $b) => $userlevelMap[$a] <=> $userlevelMap[$b]);
        $this->info['userclass'] = $userclassMap;
        $this->info['total'] = count($this->tree);
    }

    public function summary(): array {
        if (!isset($this->info)) {
            $this->calculate();
        }
        return $this->info;
    }

    public function inviteTree(): array {
        if (!isset($this->tree)) {
            $this->calculate();
        }
        return $this->tree;
    }

    public function hasInvitees(): bool {
        return $this->summary()['total'] > 0;
    }

    public function inviteeList(): array {
        return array_map(
            fn ($u) => $u['user_id'],
            $this->inviteTree()
        );
    }

    public function manipulate(
        string                $comment,
        bool                  $doDisable,
        bool                  $doInvites,
        \Gazelle\Tracker      $tracker,
        \Gazelle\User         $admin,
        \Gazelle\Manager\User $userMan,
    ): string {
        if ($doDisable) {
            $message = "Banned";
            $action = "ban";
        } elseif ($doInvites) {
            $message = "Revoked invites for";
            $action = "invites removed";
        } elseif ($comment) {
            $message = "Commented";
            $action = "comment";
        } else {
            return "No action specified";
        }

        $inviteeList = $this->inviteeList();
        $total = count($inviteeList);
        if (!$total) {
            return "No invitees for {$this->user->username()}";
        }
        $message .= " entire tree ({$total} user" . plural($total) . ')';
        $staffNote = "Invite Tree $action on {$this->user->username()} by {$admin->username()}";
        if ($comment) {
            $staffNote .= "\nReason: $comment";
        }
        $this->user->addStaffNote($staffNote)->modify();
        $this->user->auditTrail()->addEvent(UserAuditEvent::invite, $staffNote);
        $ban = [];
        foreach ($inviteeList as $inviteeId) {
            $invitee = $userMan->findById($inviteeId);
            if (is_null($invitee)) {
                continue;
            }
            if ($doDisable) {
                $ban[] = $inviteeId;
            } elseif ($doInvites) {
                $invitee->toggleAttr('disable-invites', true);
                $invitee->setField(
                    'RestrictedForums',
                    implode(',', array_unique([...$invitee->forbiddenForums(), INVITATION_FORUM_ID]))
                );
                if (in_array(INVITATION_FORUM_ID, $invitee->permittedForums())) {
                    $invitee->setField(
                        'PermittedForums',
                        implode(',', array_filter(
                            $invitee->permittedForums(),
                            fn ($id) => $id != INVITATION_FORUM_ID
                        ))
                    );
                }
            }
            if (!$doDisable) {  // $userMan->disableUserList will add the staff note otherwise
                $invitee->addStaffNote($staffNote)->modify();
                $invitee->auditTrail()->addEvent(UserAuditEvent::invite, $staffNote);
            }
        }
        if ($ban) {
            $userMan->disableUserList(
                $tracker,
                $ban,
                UserAuditEvent::invite,
                $staffNote,
                \Gazelle\Manager\User::DISABLE_TREEBAN,
            );
        }
        return $message;
    }
}
