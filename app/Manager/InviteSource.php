<?php

namespace Gazelle\Manager;

class InviteSource extends \Gazelle\Base {

    public function create(string $name): int {
        self::$db->prepared_query("
            INSERT INTO invite_source (name) VALUES (?)
            ", $name
        );
        return self::$db->inserted_id();
    }

    public function createPendingInviteSource(int $inviteSourceId, string $inviteKey): int {
        self::$db->prepared_query("
            INSERT INTO invite_source_pending
                   (invite_source_id, invite_key)
            VALUES (?,                ?)
            ", $inviteSourceId, $inviteKey
        );
        return self::$db->affected_rows();
    }

    public function resolveInviteSource(string $inviteKey, int $userId): int {
        $inviteSourceId = self::$db->scalar("
            SELECT invite_source_id
            FROM invite_source_pending
            WHERE invite_key = ?
            ", $inviteKey
        );
        if (!$inviteSourceId) {
            return 0;
        }
        self::$db->prepared_query("
            DELETE FROM invite_source_pending WHERE invite_key = ?
            ", $inviteKey
        );
        self::$db->prepared_query("
            INSERT INTO user_has_invite_source
                   (user_id, invite_source_id)
            VALUES (?,       ?)
            ", $userId, $inviteSourceId
        );
        return self::$db->affected_rows();
    }

    public function findSourceNameByUserId(int $userId): ?string {
        return self::$db->scalar("
            SELECT i.name
            FROM invite_source i
            INNER JOIN user_has_invite_source uhis USING (invite_source_id)
            WHERE uhis.user_id = ?
            ", $userId
        );
    }

    public function remove(int $id): int {
        self::$db->prepared_query("
            DELETE FROM invite_source WHERE invite_source_id = ?
            ", $id
        );
        return self::$db->affected_rows();
    }

    public function listByUse(): array {
        self::$db->prepared_query("
            SELECT i.invite_source_id,
                i.name,
                count(DISTINCT ihis.user_id) AS inviter_total,
                count(DISTINCT uhis.user_id) AS user_total
            FROM invite_source i
            LEFT JOIN inviter_has_invite_source ihis USING (invite_source_id)
            LEFT JOIN user_has_invite_source uhis USING (invite_source_id)
            GROUP BY i.invite_source_id, i.name
            ORDER BY i.name
        ");
        return self::$db->to_array(false, MYSQLI_ASSOC, false);
    }

    public function summaryByInviter(): array {
        self::$db->prepared_query("
            SELECT ihis.user_id,
                group_concat(i.name ORDER BY i.name SEPARATOR ', ') as name_list
            FROM inviter_has_invite_source ihis
            INNER JOIN invite_source i USING (invite_source_id)
            INNER JOIN users_main um ON (um.ID = ihis.user_id)
            GROUP BY ihis.user_id
            ORDER BY um.username
        ");
        return self::$db->to_array(false, MYSQLI_ASSOC, false);
    }

    public function inviterConfiguration(int $userId): array {
        self::$db->prepared_query("
            SELECT i.invite_source_id,
                i.name,
                ihis.invite_source_id IS NOT NULL AS active
            FROM invite_source i
            LEFT JOIN inviter_has_invite_source ihis ON (i.invite_source_id = ihis.invite_source_id AND ihis.user_id = ?)
            ORDER BY i.name
            ", $userId
        );
        return self::$db->to_array(false, MYSQLI_ASSOC, false);
    }

    public function inviterConfigurationActive(int $userId): array {
        self::$db->prepared_query("
            SELECT i.invite_source_id,
                i.name,
                ihis.invite_source_id IS NOT NULL AS active
            FROM invite_source i
            INNER JOIN inviter_has_invite_source ihis ON (i.invite_source_id = ihis.invite_source_id AND ihis.user_id = ?)
            ORDER BY i.name
            ", $userId
        );
        return self::$db->to_array(false, MYSQLI_ASSOC, false);
    }

    public function modifyInviterConfiguration(int $userId, array $ids): int {
        self::$db->begin_transaction();
        self::$db->prepared_query("
            DELETE FROM inviter_has_invite_source WHERE user_id = ?
            ", $userId
        );
        $userAndSourceId = [];
        foreach ($ids as $sourceId) {
            $userAndSourceId[] = $userId;
            $userAndSourceId[] = $sourceId;
        }
        self::$db->prepared_query("
            INSERT INTO inviter_has_invite_source (user_id, invite_source_id)
            VALUES " . placeholders($ids, '(?, ?)'), ...$userAndSourceId
        );
        self::$db->commit();
        return self::$db->affected_rows();
    }

    public function userSource(int $userId) {
        self::$db->prepared_query("
            SELECT ui.UserID AS user_id,
                uhis.invite_source_id,
                i.name
            FROM users_info ui
            LEFT JOIN user_has_invite_source uhis ON (uhis.user_id = ui.UserID)
            LEFT JOIN invite_source i USING (invite_source_id)
            WHERE ui.inviter = ?
            ", $userId
        );
        return self::$db->to_array('user_id', MYSQLI_ASSOC, false);
    }

    public function modifyUserSource(int $userId, array $ids): int {
        $userAndSourceId = [];
        foreach ($ids as $inviteeId => $sourceId) {
            $userAndSourceId[] = $inviteeId;
            $userAndSourceId[] = $sourceId;
        }
        self::$db->begin_transaction();
        self::$db->prepared_query("
            DELETE uhis
            FROM user_has_invite_source uhis
            INNER JOIN users_info ui ON (ui.UserID = uhis.user_id)
            WHERE ui.Inviter = ?
            ", $userId
        );
        self::$db->prepared_query("
            INSERT INTO user_has_invite_source (user_id, invite_source_id)
            VALUES " . placeholders($ids, '(?, ?)'), ...$userAndSourceId
        );
        self::$db->commit();
        return self::$db->affected_rows();
    }
}
