<?php

namespace Gazelle;

class Request extends BaseObject {
    protected $info;

    protected const CACHE_REQUEST = "request_%d";
    protected const CACHE_ARTIST  = "request_artists_%d";
    protected const CACHE_VOTE    = "request_votes_%d";

    public function tableName(): string {
        return 'requests';
    }

    public function __construct(int $id) {
        parent::__construct($id);
    }

    public function flush() {
        if ($this->info()['GroupID']) {
            $this->cache->delete_value("requests_group_" . $this->info()['GroupID']);
        }
        $this->cache->deleteMulti([
            sprintf(self::CACHE_REQUEST, $this->id),
            sprintf(self::CACHE_ARTIST, $this->id),
            sprintf(self::CACHE_VOTE, $this->id),
        ]);
        $this->info = null;
    }

    public function remove(): bool {
        $this->db->begin_transaction();
        $this->db->prepared_query("DELETE FROM requests_votes WHERE RequestID = ?", $this->id);
        $this->db->prepared_query("DELETE FROM requests_tags WHERE RequestID = ?", $this->id);
        $this->db->prepared_query("DELETE FROM requests WHERE ID = ?", $this->id);
        $affected = $this->db->affected_rows();
        $this->db->prepared_query("
            SELECT ArtistID FROM requests_artists WHERE RequestID = ?
            ", $this->id
        );
        $artisIds = $this->db->collect(0);
        $this->db->prepared_query('
            DELETE FROM requests_artists WHERE RequestID = ?', $this->id
        );
        $this->db->prepared_query("
            REPLACE INTO sphinx_requests_delta (ID) VALUES (?)
            ", $this->id
        );
        (new \Gazelle\Manager\Comment)->remove('requests', $this->id);
        $this->db->commit();

        foreach ($artisIds as $artistId) {
            $this->cache->delete_value("artists_requests_$artistId");
        }
        $this->flush();

        return $affected != 0;
    }

    protected function info(): array {
        if (!$this->info) {
            $this->info = $this->db->rowAssoc("
                SELECT UserID,
                    FillerID,
                    Title,
                    CategoryID,
                    GroupID
                FROM requests
                WHERE ID = ?
                ", $this->id
            );
        }
        return $this->info;
    }

    public function userId(): int {
        return $this->info()['UserID'];
    }

    public function fillerId(): int {
        return $this->info()['FillerID'];
    }

    public function title() {
        return $this->info()['Title'];
    }

    public function fullTitle() {
        $title = '';
        if (CATEGORY[$this->info()['CategoryID'] - 1] === 'Music') {
            $title = \Artists::display_artists($this->artistList(), false, true);
        }
        return $title . $this->title();
    }

    public function artistList(): array {
        $key = sprintf(self::CACHE_ARTIST, $this->id);
        $list = $this->cache->get_value($key);
        if ($list !== false) {
            return $list;
        }
        $this->db->prepared_query("
            SELECT ra.ArtistID,
                aa.Name,
                ra.Importance
            FROM requests_artists AS ra
            INNER JOIN artists_alias AS aa USING (AliasID)
            WHERE ra.RequestID = ?
            ORDER BY ra.Importance, aa.Name
            ", $this->id
        );
        $raw = $this->db->to_array();
        $list = [];
        foreach ($raw as list($artistId, $artistName, $role)) {
            $list[$role][] = ['id' => $artistId, 'name' => $artistName];
        }
        $this->cache->cache_value($key, $list, 0);
        return $list;
    }

    /**
     * Get the bounty of request, by user
     *
     * @return array keyed by user ID
     */
    public function bounty() {
        $this->db->prepared_query("
            SELECT UserID, Bounty
            FROM requests_votes
            WHERE RequestID = ?
            ORDER BY Bounty DESC, UserID DESC
            ", $this->id
        );
        return $this->db->to_array('UserID', MYSQLI_ASSOC, false);
    }

    /**
     * Get the total bounty that a user has added to a request
     * @param int $userId ID of user
     * @return int keyed by user ID
     */
    public function userBounty(int $userId) {
        return $this->db->scalar("
            SELECT Bounty
            FROM requests_votes
            WHERE RequestID = ? AND UserID = ?
            ", $this->id, $userId
        );
    }

    /**
     * Refund the bounty of a user on a request
     * @param int $userId ID of user
     * @param int $staffName name of staff performing the operation
     */
    public function refundBounty(int $userId, string $staffName) {
        $bounty = $this->userBounty($userId);
        $this->db->begin_transaction();
        $this->db->prepared_query("
            DELETE FROM requests_votes
            WHERE RequestID = ? AND UserID = ?
            ", $this->id, $userId
        );
        if ($this->db->affected_rows() == 1) {
            $this->informRequestFillerReduction($bounty, $staffName);
            $message = sprintf("%s Refund of %s bounty (%s b) on %s by %s\n\n",
                sqltime(), \Format::get_size($bounty), $bounty,
                'requests.php?action=view&id=' . $this->id, $staffName
            );
            $this->db->prepared_query("
                UPDATE users_info ui
                INNER JOIN users_leech_stats uls USING (UserID)
                SET
                    uls.Uploaded = uls.Uploaded + ?,
                    ui.AdminComment = concat(?, ui.AdminComment)
                WHERE ui.UserId = ?
                ", $bounty, $message, $userId
            );
        }
        $this->db->commit();
        $this->cache->deleteMulti(["request_" . $this->id, "request_votes_" . $this->id]);
    }

    /**
     * Remove the bounty of a user on a request
     * @param int $userId ID of user
     * @param int $staffName name of staff performing the operation
     */
    public function removeBounty(int $userId, string $staffName) {
        $bounty = $this->userBounty($userId);
        $this->db->begin_transaction();
        $this->db->prepared_query("
            DELETE FROM requests_votes
            WHERE RequestID = ? AND UserID = ?
            ", $this->id, $userId
        );
        if ($this->db->affected_rows() == 1) {
            $this->informRequestFillerReduction($bounty, $staffName);
            $message = sprintf("%s Removal of %s bounty (%s b) on %s by %s\n\n",
                sqltime(), \Format::get_size($bounty), $bounty,
                'requests.php?action=view&id=' . $this->id, $staffName
            );
            $this->db->prepared_query("
                UPDATE users_info ui SET
                    ui.AdminComment = concat(?, ui.AdminComment)
                WHERE ui.UserId = ?
                ", $message, $userId
            );
        }
        $this->db->commit();
        $this->cache->deleteMulti(["request_" . $this->id, "request_votes_" . $this->id]);
    }

    /**
     * Inform the filler of a request that their bounty was reduced
     *
     * @param int $bounty The amount of bounty reduction
     * @param int $staffName name of staff performing the operation
     */
    public function informRequestFillerReduction(int $bounty, string $staffName) {
        list($fillerId, $fillDate) = $this->db->row("
            SELECT FillerID, date(TimeFilled)
            FROM requests
            WHERE TimeFilled IS NOT NULL AND ID = ?
            ", $this->id
        );
        if (!$fillerId) {
            return;
        }
        $requestUrl = 'requests.php?action=view&id=' . $this->id;
        $message = sprintf("%s Reduction of %s bounty (%s b) on filled request %s by %s\n\n",
            sqltime(), \Format::get_size($bounty), $bounty, $requestUrl, $staffName
        );
        $this->db->prepared_query("
            UPDATE users_info ui
            INNER JOIN users_leech_stats uls USING (UserID)
            SET
                uls.Uploaded = uls.Uploaded - ?,
                ui.AdminComment = concat(?, ui.AdminComment)
            WHERE ui.UserId = ?
            ", $bounty, $message, $fillerId
        );
        $this->cache->delete_value("user_stats_$fillerId");
        (new Manager\User)->sendPM($fillerId, 0, "Bounty was reduced on a request you filled",
            $this->twig->render('request/bounty-reduction.twig', [
                'bounty'      => $bounty,
                'fill_date'   => $fillDate,
                'request_url' => $requestUrl,
                'staff_name'  => $staffName,
            ])
        );
    }
}
