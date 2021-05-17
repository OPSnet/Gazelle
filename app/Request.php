<?php

namespace Gazelle;

class Request extends Base {
    protected $id;

    public function __construct (int $id) {
        parent::__construct();
        $this->id = $id;
    }

    /**
     * Get the title of a request
     * TODO: should tie this into the caching infrastructure
     *
     * @return string Title of request
     */
    public function title() {
        return $this->db->scalar("
            SELECT Title FROM requests WHERE ID = ?
            ", $this->id
        );
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
    }

    /**
     * Remove the bounty of a user on a request
     * @param int $userId ID of user
     * @param int $staffName name of staff performing the operation
     */
    public function removeBounty(int $userId, string $staffName) {
        $bounty = $this->userBounty($userId);
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
        // TODO: make it easy to use Twig here
        (new Manager\User)->sendPM($fillerId, 0, "Bounty was reduced on a request you filled",
            sprintf("With regret, Staff informs you that a reduction of %s bounty"
                . " was applied to a request you filled on %s.\n\n"
                . "It has been determined that a person who had voted on this"
                . " request was manipulating their ratio and as a consequence"
                . " their request votes have been rescinded. One of the requests"
                . " affected was this one, and as a result your upload stats"
                . " have changed. It fills us with sadness to have to undertake"
                . " such an action, however, buffer they dumped on the request"
                . " was never real in the first place.\n\n"
                . "The request: %s\n\nReport handled by %s.",
                \Format::get_size($bounty), $fillDate, $requestUrl, $staffName
            )
        );
    }
}
