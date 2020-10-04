<?php

namespace Gazelle;

/* The invite tree is a bodge because Mysql cannot do recursive tree queries.
 * When looking at the Invite Tree page, `TreePosition` is a measure of how
 * far down the user appears, and `TreeLevel` represents how far across they
 * are indented.
 */

class InviteTree extends Base {
    protected $id;

    public function __construct(int $id) {
        parent::__construct();
        $this->id = $id;
    }

    public function hasInvitees(): bool {
        return $this->db->scalar("
            SELECT 1
            FROM invite_tree
            WHERE InviterId = ?
            LIMIT 1
            ", $this->id
        ) ? true : false;
    }

    public function add(int $userId) {
        while (true) {
            [$treeId, $inviterPosition, $level] = $this->db->row("
                SELECT TreeID, TreePosition, TreeLevel
                FROM invite_tree
                WHERE UserID = ?
                ", $this->id
            );
            if ($treeId) {
                break;
            }
            // Not everyone is created by the genesis user. Invite trees may be disconnected.
            $this->db->prepared_query("
                INSERT INTO invite_tree
                       (UserID, TreeID)
                VALUES (?, (SELECT coalesce(max(it.TreeID), 0) + 1 FROM invite_tree AS it))
                ", $this->id
            );
        }
        $nextPosition = $this->db->scalar("
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
            $nextPosition = $this->db->scalar("
                SELECT max(TreePosition) + 1
                FROM invite_tree
                WHERE TreeID = ?
                ", $treeId
            );
        } else {
            // Someone invited Alice and then Bob. Later on, Alice invites Carol,
            // so Bob and others have to "pushed down" a row so that Carol can
            // be lodged under Alice.
            $this->db->prepared_query("
                UPDATE invite_tree SET
                    TreePosition = TreePosition + 1
                WHERE TreeID = ?
                    AND TreePosition >= ?
                ", $treeId, $nextPosition
            );
        }
        $this->db->prepared_query("
            INSERT INTO invite_tree
                   (UserID, InviterID, TreeID, TreePosition, TreeLevel)
            VALUES (?,      ?,         ?,      ?,            ?)
            ", $userId, $this->id, $treeId, $nextPosition, $level + 1
        );
    }

    function render(\Twig\Environment $twig): string {
        $qid = $this->db->get_query_id();
        [$treeId, $position, $level] = $this->db->row("
            SELECT TreeID, TreePosition, TreeLevel
            FROM invite_tree
            WHERE UserID = ?
            ", $this->id
        );
        if (!$treeId) {
            return '';
        }

        $maxLevel   = $level; // The deepest level (this changes)
        $startLevel = $level; // The level of the user we're viewing
        $prevLevel  = $level;
        $stats = [
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
        $maxPosition = $this->db->scalar("
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
        $treeQ = $this->db->prepared_query("
            SELECT
                it.UserID,
                um.Enabled,
                um.PermissionID,
                (donor.UserID IS NOT NULL) AS Donor,
                uls.Uploaded,
                uls.Downloaded,
                um.Paranoia,
                it.TreePosition,
                it.TreeLevel
            FROM invite_tree AS it
            INNER JOIN users_main AS um ON (um.ID = it.UserID)
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

        $markup = '';
        $classSummary = [];

        while ([$inviteeId, $enabled, $permissionId, $donor, $uploaded, $downloaded, $paranoia, $position, $level]
            = $this->db->next_record(MYSQLI_NUM, false)
        ) {
            $stats['total']++;
            if ($enabled == 2) {
                $stats['disabled']++;
            }
            if ($donor) {
                $stats['donor']++;
            }
            if ($level == $startLevel + 1) {
                $stats['branch']++;
                $stats['upload_top'] += $uploaded;
                $stats['download_top'] += $downloaded;
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

            global $Classes;
            if (!check_paranoia(['uploaded', 'downloaded'], $paranoia, $Classes[$permissionId]['Level'])) {
                $markup .= "&nbsp;Hidden";
                $stats['paranoid']++;
            } else {
                $markup .= sprintf(" Uploaded:&nbsp;<strong>%s</strong> Downloaded:&nbsp;<strong>%s</strong> Ratio:&nbsp;<strong>%s</strong>",
                    \Format::get_size($uploaded),
                    \Format::get_size($downloaded),
                    \Format::get_ratio_html($uploaded, $downloaded)
                );
                $stats['upload_total'] += $uploaded;
                $stats['download_total'] += $downloaded;
            }

            if ($maxLevel < $level) {
                $maxLevel = $level;
            }
            $prevLevel = $level;

            $this->db->set_query_id($treeQ);
        }

        $markup .= str_repeat("</li>\n</ul>\n", $prevLevel - $startLevel);
        if (!$stats['total']) {
            $summary = '';
        } else {
            $className = [];
            foreach ($classSummary as $id => $count) {
                $name = \Users::make_class_string($id);
                if ($count > 1) {
                    $name = ($name == 'Torrent Celebrity') ? 'Torrent Celebrities' : "{$name}s";
                }
                $className[] = "$count $name (" . number_format(($count / $stats['total']) * 100) . '%)';
            }
            $summary = $twig->render('user/invite-tree.twig', [
                'classes'     => $className,
                'depth'       => $maxLevel - $startLevel,
                'pc_disabled' => $stats['disabled'] / $stats['total'] * 100,
                'pc_donor'    => $stats['donor']    / $stats['total'] * 100,
                'pc_paranoid' => $stats['paranoid'] / $stats['total'] * 100,
                'stats'       => $stats,
            ]);
        }
        return '<div class="invitetree pad">' . $summary . $markup . '</div>';
        $this->db->set_query_id($qid);
    }
}
