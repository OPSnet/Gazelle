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
}
