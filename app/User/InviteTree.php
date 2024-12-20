<?php

namespace Gazelle\User;

/* The invite tree is a bodge because Mysql cannot do recursive tree queries.
 * When looking at the Invite Tree page, position() represents how long ago
 * the user was invited (the lower, the more recent the creation), and
 * depth() represents the depth of the invite chain (the number of people
 * between the ancestor inviter and the invitee).
 */

class InviteTree extends \Gazelle\Base {
    protected array $info;

    public function __construct(
        protected \Gazelle\User $user,
        protected \Gazelle\Manager\User $userMan,
    ) {}

    public function flush(): static {
        unset($this->info);
        return $this;
    }

    public function info(): array {
        if (!isset($this->info)) {
            $this->info = self::$db->rowAssoc("
                SELECT
                    t1.TreeID        AS tree_id,
                    t1.TreeLevel     AS depth,
                    t1.TreePosition  AS position,
                    (
                        SELECT t2.TreePosition
                        FROM invite_tree AS t2
                        WHERE t2.TreeID = t1.TreeID
                            AND t2.TreeLevel = t1.TreeLevel
                            AND t2.TreePosition > t1.TreePosition
                        ORDER BY t2.TreePosition
                        LIMIT 1
                    ) AS max_position
                FROM invite_tree AS t1
                WHERE t1.UserID = ?
                ", $this->user->id()
            ) ?? [
               'tree_id'      => 0,
               'depth'        => null,
               'position'     => null,
               'max_position' => null,
            ];
        }
        return $this->info;
    }

    public function treeId(): int {
        return $this->info()['tree_id'];
    }

    public function depth(): ?int {
        return $this->info()['depth'];
    }

    public function position(): ?int {
        return $this->info()['position'];
    }

    public function maxPosition(): ?int {
        return $this->info()['max_position'];
    }

    public function hasInvitees(): bool {
        return (bool)self::$db->scalar("
            SELECT 1
            FROM invite_tree
            WHERE InviterId = ?
            LIMIT 1
            ", $this->user->id()
        );
    }

    public function inviteeList(): array {
        self::$db->prepared_query("
            SELECT UserID
            FROM invite_tree
            WHERE TreeID = ?
                AND TreeLevel > ?
                AND TreePosition > ?
                AND TreePosition < coalesce(?, 100000000)
            ORDER BY TreePosition
            ", $this->treeId(), $this->depth(), $this->position(), $this->maxPosition()
        );
        return self::$db->collect('UserID');
    }

    public function add(\Gazelle\User $user): int {
        if (!$this->treeId()) {
            // Not everyone is created by the genesis user. Invite trees may be disconnected.
            self::$db->prepared_query("
                INSERT INTO invite_tree
                       (UserID, TreeID)
                VALUES (?, (SELECT coalesce(max(it.TreeID), 0) + 1 FROM invite_tree AS it))
                ", $this->user->id()
            );
            $this->flush();
        }
        $nextPosition = self::$db->scalar("
            SELECT TreePosition
            FROM invite_tree
            WHERE TreeID = ?
                AND TreePosition > ?
                AND TreeLevel <= ?
            ORDER BY TreePosition LIMIT 1
            ", $this->treeId(), $this->position(), $this->depth()
        );
        if (!$nextPosition) {
            // Tack them on the end of the list.
            $nextPosition = self::$db->scalar("
                SELECT max(TreePosition) + 1
                FROM invite_tree
                WHERE TreeID = ?
                ", $this->treeId()
            );
        } else {
            // Someone invited Alice and then Bob. Later on, Alice invites Carol,
            // so Bob and others have to "pushed down" a row so that Carol can
            // be lodged under Alice.
            self::$db->prepared_query("
                UPDATE invite_tree SET
                    TreePosition = TreePosition + 1
                WHERE TreeID = ?
                    AND TreePosition >= ?
                ", $this->treeId(), $nextPosition
            );
        }
        self::$db->prepared_query("
            INSERT INTO invite_tree
                   (UserID, InviterID, TreeID, TreePosition, TreeLevel)
            VALUES (?,      ?,         ?,      ?,            ?)
            ", $user->id(), $this->user->id(), $this->treeId(), $nextPosition, $this->depth() + 1
        );
        $affected = self::$db->affected_rows();
        $this->flush();
        return $affected;
    }

    public function manipulate(
        string           $comment,
        bool             $doDisable,
        bool             $doInvites,
        \Gazelle\Tracker $tracker,
        \Gazelle\User    $admin,
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
        $ban = [];
        foreach ($inviteeList as $inviteeId) {
            $invitee = $this->userMan->findById($inviteeId);
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
            if (!$doDisable) {  // $this->userMan->disableUserList will add the staff note otherwise
                $invitee->addStaffNote($staffNote)->modify();
            }
        }
        if ($ban) {
            $this->userMan->disableUserList($tracker, $ban, $staffNote, \Gazelle\Manager\User::DISABLE_TREEBAN);
        }
        return $message;
    }

    public function details(\Gazelle\User $viewer): array {
        if (!$this->treeId()) {
            return [];
        }

        $maxDepth = $this->depth(); // The deepest level (this increases when an invitee invites someone else)

        $args = [$this->treeId(), $this->position(), $this->depth()];
        $maxPosition = self::$db->scalar("
            SELECT TreePosition
            FROM invite_tree
            WHERE TreeID = ?
                AND TreePosition > ?
                AND TreeLevel = ?
            ORDER BY TreePosition ASC
            LIMIT 1
            ", ...$args
        );
        if (is_null($maxPosition)) {
            $maxCond = '/* no max pos */';
        } else {
            $maxCond = 'AND it.TreePosition < ?';
            $args[] = $maxPosition;
        }
        self::$db->prepared_query("
            SELECT
                it.UserID,
                it.TreePosition,
                it.TreeLevel
            FROM invite_tree AS it
            WHERE it.TreeID = ?
                AND it.TreePosition > ?
                AND it.TreeLevel > ?
                $maxCond
            ORDER BY it.TreePosition
            ", ...$args
        );
        $inviteeList = self::$db->to_array(false, MYSQLI_NUM, false);

        $info = [
            'tree'           => [],
            'total'          => 0,
            'branch'         => 0,
            'disabled'       => 0,
            'donor'          => 0,
            'paranoid'       => 0,
            'upload_total'   => 0,
            'download_total' => 0,
            'upload_top'     => 0,
            'download_top'   => 0,
        ];
        $classSummary = [];
        foreach ($inviteeList as [$inviteeId, /* $position -- unused */, $depth]) {
            $invitee = $this->userMan->findById($inviteeId);
            if (is_null($invitee)) {
                continue;
            }

            $info['total']++;
            $info['tree'][] = [
                'user'  => $invitee,
                'depth' => $depth,
            ];
            if ($invitee->isDisabled()) {
                $info['disabled']++;
            }
            if ((new Donor($invitee))->isDonor()) {
                $info['donor']++;
            }

            $paranoid = $invitee->propertyVisibleMulti($viewer, ['uploaded', 'downloaded']) === PARANOIA_HIDE;
            if ($depth == $this->depth() + 1) {
                $info['branch']++;
                if (!$paranoid) {
                    $info['upload_top']   += $invitee->uploadedSize();
                    $info['download_top'] += $invitee->downloadedSize();
                }
            }
            if ($paranoid) {
                $info['paranoid']++;
            } else {
                $info['upload_total']   += $invitee->uploadedSize();
                $info['download_total'] += $invitee->downloadedSize();
            }

            $primaryClass = $invitee->primaryClass();
            if (!isset($classSummary[$primaryClass])) {
                $classSummary[$primaryClass] = 0;
            }
            $classSummary[$primaryClass]++;

            if ($maxDepth < $depth) {
                $maxDepth = $depth;
            }
        }
        return $info['total'] === 0
            ? []
            : [ 'classes' =>
                array_merge(
                    ...array_map(
                        fn($c) => [$this->userMan->userclassName($c) => $classSummary[$c]],
                        array_keys($classSummary)
                    )
                ),
                'depth'  => $this->depth(),
                'height' => $maxDepth - $this->depth(),
                'info'   => $info,
            ];
    }
}
