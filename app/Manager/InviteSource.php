<?php

namespace Gazelle\Manager;

class InviteSource extends \Gazelle\Base {
    use \Gazelle\Pg;

    /**
     * Create an invitation source name (usually the initials or acronym of a tracker
     */
    public function create(string $name): int {
        self::$db->prepared_query("
            INSERT INTO invite_source (name) VALUES (?)
            ", $name
        );
        return self::$db->inserted_id();
    }

    /**
     * The list of all invite sources, indicating whether the
     * inviter has been assigned to this source. (Used by staff to
     * assign sources on their profile page).
     */
    public function inviterConfiguration(\Gazelle\User $user): array {
        self::$db->prepared_query("
            SELECT i.invite_source_id,
                i.name,
                ihis.invite_source_id IS NOT NULL AS active
            FROM invite_source i
            LEFT JOIN inviter_has_invite_source ihis ON (i.invite_source_id = ihis.invite_source_id AND ihis.user_id = ?)
            ORDER BY i.name
            ", $user->id()
        );
        return self::$db->to_array(false, MYSQLI_ASSOC, false);
    }

    /**
     * The of invite sources to which the inviter has been assigned.
     * (Used to show their list of sources when inviting).
     */
    public function inviterConfigurationActive(\Gazelle\User $user): array {
        self::$db->prepared_query("
            SELECT i.invite_source_id,
                i.name,
                ihis.invite_source_id IS NOT NULL AS active
            FROM invite_source i
            INNER JOIN inviter_has_invite_source ihis ON (i.invite_source_id = ihis.invite_source_id AND ihis.user_id = ?)
            ORDER BY i.name
            ", $user->id()
        );
        return self::$db->to_array(false, MYSQLI_ASSOC, false);
    }

    /**
     * Assign a list of invite sources to a user. Any existing source that
     * is not referenced in the list will be revoked for the user. Assigning
     * an empty array will revoke everything. (Once an invite source has
     * been used, it can no longer be removed.
     */
    public function modifyInviterConfiguration(\Gazelle\User $user, array $idList): int {
        $userId = $user->id();
        $args = [];
        foreach ($idList as $sourceId) {
            array_push($args, $userId, $sourceId);
        }
        self::$db->begin_transaction();
        self::$db->prepared_query("
            DELETE FROM inviter_has_invite_source WHERE user_id = ?
            ", $userId
        );
        $affected = self::$db->affected_rows();
        if ($idList) {
            self::$db->prepared_query("
                INSERT INTO inviter_has_invite_source (user_id, invite_source_id) VALUES "
                    . placeholders($idList, '(?, ?)'), ...$args
            );
            $affected += self::$db->affected_rows();
        }
        self::$db->commit();
        return $affected;
    }

    /**
     * A compact summary of invite sources per user. (Used on the
     * main configuration toolbox).
     */
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

    /**
     * When an invite is issued, create an pending record that will
     * allow the invite source and external profile to be added to
     * invitee when they create their account.
     */
    public function createPendingInviteSource(int $inviteSourceId, string $inviteKey): int {
        self::$db->prepared_query("
            INSERT INTO invite_source_pending
                   (invite_source_id, invite_key)
            VALUES (?,                ?)
            ", $inviteSourceId, $inviteKey
        );
        return self::$db->affected_rows();
    }

    /**
     * When a user creates their account from an invite, the pending
     * information is retrieved and the invite source and external
     * profile is assigned to the new user. This method is called
     * from the UserCreator class.
     */
    public function resolveInviteSource(string $inviteKey, \Gazelle\User $user): int {
        $sourceId = self::$db->scalar("
            SELECT invite_source_id
            FROM invite_source_pending
            WHERE invite_key = ?
            ", $inviteKey
        );
        if (!$sourceId) {
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
            ", $user->id(), $sourceId
        );
        return self::$db->affected_rows();
    }

    /**
     * Statistics on usage of each invite source.
     * How many inviters have been granted the use.
     * How many invitees have been invited from the source.
     */
    public function usageList(): array {
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

    /**
     * Get the list of invitees of an inviter.
     * Array is keyed by invitee id for simple lookups.
     */
    public function userSource(\Gazelle\User $user): array {
        self::$db->prepared_query("
            SELECT um.ID AS user_id,
                uhis.invite_source_id,
                i.name
            FROM users_main um
            LEFT JOIN user_has_invite_source uhis ON (uhis.user_id = um.ID)
            LEFT JOIN invite_source i USING (invite_source_id)
            WHERE um.inviter_user_id = ?
            ", $user->id()
        );
        return self::$db->to_array('user_id', MYSQLI_ASSOC, false);
    }

    /**
     * Find the invite source name of a user, if there is one.
     */
    public function findSourceNameByUser(\Gazelle\User $user): ?string {
        $name = self::$db->scalar("
            SELECT i.name
            FROM invite_source i
            INNER JOIN user_has_invite_source uhis USING (invite_source_id)
            WHERE uhis.user_id = ?
            ", $user->id()
        );
        return $name ? (string)$name : null;
    }

    /**
     * An inviter can update or remove  a source from an invitee
     * and they can also adjust the external profile
     * Each entry has the following structure:
     *   <user_id> => [
     *      "user_id" => <user_id>,
     *      "source"  => <new source id> or 0 to remove,
     *      "profile" => <new profile> or '' to remove,
     *   ]
     */
    public function modifyInviteeSource(\Gazelle\User $user, array $sourceList): int {
        self::$db->begin_transaction();
        $affected = 0;
        foreach ($sourceList as $source) {
            if (!isset($source['user_id'])) {
                continue;
            }
            $inviteeId = $source['user_id'];
            if (isset($source['source'])) {
                if ($source['source'] == 0) {
                    self::$db->prepared_query("
                        DELETE FROM user_has_invite_source WHERE user_id = ?
                        ", $inviteeId
                    );
                    $affected += self::$db->affected_rows();
                } else {
                    self::$db->prepared_query("
                        INSERT INTO user_has_invite_source
                               (user_id, invite_source_id)
                        VALUES (?,       ?)
                        ON DUPLICATE KEY UPDATE invite_source_id = ?
                        ", $inviteeId, $source['source'], $source['source']
                    );
                    $affected += self::$db->affected_rows();
                }
            }
            if (isset($source['profile'])) {
                if ($source['profile'] == '') {
                    $affected += $this->pg()->prepared_query("
                        DELETE FROM user_external_profile WHERE id_user = ?
                        ", $inviteeId
                    );
                } else {
                    $affected += $this->pg()->prepared_query("
                        INSERT INTO user_external_profile
                               (id_user, profile)
                        VALUES (?,       ?)
                        ON CONFLICT (id_user) DO UPDATE SET
                            profile = ?
                        ", $inviteeId, $source['profile'], $source['profile']
                    );
                }
            }
        }
        self::$db->commit();
        return $affected;
    }

    /**
     * Remove an unused invite source. This will fail due to
     * foreign key constraints if the source has been assigned
     * to an invite, an inviter or an invitee.
     *
     * See tests/phpunit/InviteTest.php if you need need to
     * nuke a mistake.
     */
    public function remove(int $id): int {
        self::$db->prepared_query("
            DELETE FROM invite_source WHERE invite_source_id = ?
            ", $id
        );
        return self::$db->affected_rows();
    }
}
