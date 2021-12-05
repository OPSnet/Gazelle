<?php

namespace Gazelle;

/* The invite tree is a bodge because Mysql cannot do recursive tree queries.
 * When looking at the Invite Tree page, `TreePosition` is a measure of how
 * far down the user appears, and `TreeLevel` represents how far across they
 * are indented.
 */

class InviteTree extends Base {
    protected $userId;
    protected $treeId;
    protected $treeLevel;
    protected $treePosition;
    protected $maxPosition;

    public function __construct(int $userId) {
        $this->userId = $userId;
        [$this->treeId, $this->treeLevel, $this->treePosition, $this->maxPosition] = self::$db->row("
            SELECT
                t1.TreeID,
                t1.TreeLevel,
                t1.TreePosition,
                (
                    SELECT t2.TreePosition
                    FROM invite_tree AS t2
                    WHERE t2.TreeID = t1.TreeID
                        AND t2.TreeLevel = t1.TreeLevel
                        AND t2.TreePosition > t1.TreePosition
                    ORDER BY t2.TreePosition
                    LIMIT 1
                )
            FROM invite_tree AS t1
            WHERE t1.UserID = ?
            ", $this->userId
        );
    }

    public function treeId(): ?int {
        return $this->treeId;
    }

    public function hasInvitees(): bool {
        return self::$db->scalar("
            SELECT 1
            FROM invite_tree
            WHERE InviterId = ?
            LIMIT 1
            ", $this->userId
        ) ? true : false;
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
            ", $this->treeId, $this->treeLevel, $this->treePosition, $this->maxPosition
        );
        return self::$db->collect('UserID');
    }

    public function add(int $userId) {
        // TODO: use the new instance variables instead of doing a lookup here
        while (true) {
            [$treeId, $inviterPosition, $level] = self::$db->row("
                SELECT TreeID, TreePosition, TreeLevel
                FROM invite_tree
                WHERE UserID = ?
                ", $this->userId
            );
            if ($treeId) {
                break;
            }
            // Not everyone is created by the genesis user. Invite trees may be disconnected.
            self::$db->prepared_query("
                INSERT INTO invite_tree
                       (UserID, TreeID)
                VALUES (?, (SELECT coalesce(max(it.TreeID), 0) + 1 FROM invite_tree AS it))
                ", $this->userId
            );
        }
        $nextPosition = self::$db->scalar("
            SELECT TreePosition
            FROM invite_tree
            WHERE TreeID = ?
                AND TreePosition > ?
                AND TreeLevel <= ?
            ORDER BY TreePosition LIMIT 1
            ", $treeId, $inviterPosition, $level
        );
        if (!$nextPosition) {
            // Tack them on the end of the list.
            $nextPosition = self::$db->scalar("
                SELECT max(TreePosition) + 1
                FROM invite_tree
                WHERE TreeID = ?
                ", $treeId
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
                ", $treeId, $nextPosition
            );
        }
        self::$db->prepared_query("
            INSERT INTO invite_tree
                   (UserID, InviterID, TreeID, TreePosition, TreeLevel)
            VALUES (?,      ?,         ?,      ?,            ?)
            ", $userId, $this->userId, $treeId, $nextPosition, $level + 1
        );
    }

    function details(): array {
        $qid = self::$db->get_query_id();
        [$treeId, $position, $level] = self::$db->row("
            SELECT TreeID, TreePosition, TreeLevel
            FROM invite_tree
            WHERE UserID = ?
            ", $this->userId
        );
        if (!$treeId) {
            return '';
        }

        $maxLevel   = $level; // The deepest level (this changes)
        $startLevel = $level; // The level of the user we're viewing
        $prevLevel  = $level;
        $info = [
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

        $args = [$treeId, $position, $level];
        $maxPosition = self::$db->scalar("
            SELECT TreePosition
            FROM invite_tree
            WHERE TreeID = ?
                AND TreePosition > ?
                AND TreeLevel = ?
            ORDER BY TreePosition ASC
            LIMIT 1
            ", $treeId, $position, $level
        );
        if (is_null($maxPosition)) {
            $maxCond = '/* no max pos */';
        } else {
            $maxCond = 'AND TreePosition < ?';
            $args[] = $maxPosition;
        }
        $treeQ = self::$db->prepared_query("
            SELECT
                it.UserID,
                um.Enabled,
                um.PermissionID,
                (donor.UserID IS NOT NULL) AS Donor,
                uls.Uploaded,
                uls.Downloaded,
                ui.JoinDate,
                um.Paranoia,
                it.TreePosition,
                it.TreeLevel
            FROM invite_tree AS it
            INNER JOIN users_main AS um ON (um.ID = it.UserID)
            INNER JOIN users_info AS ui ON (ui.UserID = it.UserID)
            INNER JOIN users_leech_stats AS uls ON (uls.UserID = it.UserID)
            LEFT JOIN users_levels AS donor ON (donor.UserID = it.UserID
                AND donor.PermissionID = (SELECT ID FROM permissions WHERE Name = 'Donor' LIMIT 1)
            )
            WHERE TreeID = ?
                AND TreePosition > ?
                AND TreeLevel > ?
                $maxCond
            ORDER BY TreePosition
            ", ...$args
        );

        $userMan = new Manager\User;
        $Classes = $userMan->classList();
        $classSummary = [];
        $markup = '';
        while ([$inviteeId, $enabled, $permissionId, $donor, $uploaded, $downloaded, $joindate, $paranoia, $position, $level]
            = self::$db->next_record(MYSQLI_NUM, false)
        ) {
            $info['total']++;
            if ($enabled == 2) {
                $info['disabled']++;
            }
            if ($donor) {
                $info['donor']++;
            }
            if ($level == $startLevel + 1) {
                $info['branch']++;
                $info['upload_top'] += $uploaded;
                $info['download_top'] += $downloaded;
            }
            if (!isset($classSummary[$permissionId])) {
                $classSummary[$permissionId] = 0;
            }
            $classSummary[$permissionId]++;

            // Manage tree depth
            if ($level > $prevLevel) {
                $markup .= str_repeat("<ul class=\"invitetree\">\n<li>\n", $level - $prevLevel);
            } elseif ($level < $prevLevel) {
                $markup .= str_repeat("</li>\n</ul>\n", $prevLevel - $level) . "</li>\n<li>\n";
            } else {
                $markup .= "</li>\n<li>\n";
            }
            $markup .= '<strong>' . \Users::format_username($inviteeId, true, true, ($enabled != 2 ? false : true), true)
                . '</strong>';

            if (!check_paranoia(['uploaded', 'downloaded'], $paranoia, $Classes[$permissionId]['Level'])) {
                $markup .= "&nbsp;Hidden";
                $info['paranoid']++;
            } else {
                $markup .= sprintf(" Uploaded:&nbsp;<strong>%s</strong> Downloaded:&nbsp;<strong>%s</strong> Ratio:&nbsp;<strong>%s</strong>",
                    \Format::get_size($uploaded),
                    \Format::get_size($downloaded),
                    \Format::get_ratio_html($uploaded, $downloaded)
                );
                $info['upload_total'] += $uploaded;
                $info['download_total'] += $downloaded;
                $markup .= ", joined " . time_diff($joindate) . ".";
            }

            if ($maxLevel < $level) {
                $maxLevel = $level;
            }
            $prevLevel = $level;

            self::$db->set_query_id($treeQ);
        }

        if (!$info['total']) {
            $details = [];
        } else {
            $markup .= str_repeat("</li>\n</ul>\n", $prevLevel - $startLevel);
            $className = [];
            foreach ($classSummary as $id => $count) {
                $name = $userMan->userclassName($id);
                if ($count > 1) {
                    $name = ($name == 'Torrent Celebrity') ? 'Torrent Celebrities' : "{$name}s";
                }
                $className[] = "$count $name (" . number_format(($count / $info['total']) * 100) . '%)';
            }
            $details = [
                'classes'     => $className,
                'depth'       => $maxLevel - $startLevel,
                'info'        => $info,
                'markup'      => $markup,
            ];
        }
        self::$db->set_query_id($qid);
        return $details;
    }
}
