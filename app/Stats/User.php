<?php

namespace Gazelle\Stats;

class User extends \Gazelle\Base {

    /**
     * This class offloads all the counting operations you might
     * want to do with a User (so that the User class does not
     * grow to an unmanageable size).
     *
     * Counting things relating to collections of users are found
     * in the Users (plural) class.
     */

    protected int $id;

    /* Some queries return two or more items of interest: these cache the
     * results so that the underlying call is only made once.
     */
    protected array $download;
    protected array $requestBounty;
    protected array $requestCreated;
    protected array $requestVote;
    protected array $snatch;

    public function __construct(int $id) {
        parent::__construct();
        $this->id = $id;
    }

    /**
     * How many FL tokens has someone used?
     *
     * @return int Number of tokens used
     */
    public function flTokenTotal(): int {
        return $this->db->scalar("
            SELECT count(*) FROM users_freeleeches WHERE UserID = ?
            ", $this->id
        );
    }

    public function perfectFlacTotal(): int {
        return $this->db->scalar("
            SELECT count(*)
            FROM torrents
            WHERE Format = 'FLAC'
                AND (
                    (Media = 'CD' AND LogChecksum = '1' AND HasCue = '1' AND HasLogDB = '1' AND LogScore = 100)
                    OR
                    (Media in ('BD', 'Cassette', 'DAT', 'DVD', 'SACD', 'Soundboard', 'WEB', 'Vinyl'))
                )
                AND UserID = ?
            ", $this->id
        );
    }

    public function uniqueGroupsTotal(): int {
        return $this->db->scalar("
            SELECT count(DISTINCT GroupID)
            FROM torrents
            WHERE UserID = ?
            ", $this->id
        );
    }

    protected function download(): array {
        if (!isset($this->download)) {
            $this->download = $this->db->rowAssoc("
                SELECT count(*) AS total,
                    count(DISTINCT ud.TorrentID) AS 'unique'
                FROM users_downloads AS ud
                INNER JOIN torrents AS t ON (t.ID = ud.TorrentID)
                WHERE ud.UserID = ?
                ", $this->id
            );
            if (empty($this->download)) {
                $this->download = ['total' => 0, 'unique' => 0];
            }
        }
        return $this->download;
    }

    public function downloadTotal(): int {
        return $this->download()['total'];
    }

    public function downloadUnique(): int {
        return $this->download()['unique'];
    }

    protected function requestBounty(): array {
        if (!isset($this->requestBounty)) {
            $this->requestBounty = $this->db->rowAssoc("
                SELECT coalesce(sum(rv.Bounty), 0) AS size,
                    count(DISTINCT r.ID) AS total
                FROM requests AS r
                LEFT JOIN requests_votes AS rv ON (r.ID = rv.RequestID)
                WHERE r.FillerID = ?
                ", $this->id
            );
            if (empty($this->requestBounty)) {
                $this->requestBounty = ['size' => 0, 'total' => 0];
            }
        }
        return $this->requestBounty;
    }

    public function requestBountySize(): int {
        return $this->requestBounty()['size'];
    }

    public function requestBountyTotal(): int {
        return $this->requestBounty()['total'];
    }

    protected function requestCreated() {
        if (!isset($this->requestCreated)) {
            $this->db->prepared_query("
                SELECT coalesce(sum(rv.Bounty), 0) AS size,
                    count(*) AS total
                FROM requests AS r
                LEFT JOIN requests_votes AS rv ON (rv.RequestID = r.ID AND rv.UserID = r.UserID)
                WHERE r.UserID = ?
                ", $this->id
            );
            if (empty($this->requestCreated)) {
                $this->requestCreated = ['size' => 0, 'total' => 0];
            }
        }
        return $this->requestCreated;
    }

    public function requestCreatedSize(): int {
        return $this->requestCreated()['size'];
    }

    public function requestCreatedTotal(): int {
        return $this->requestCreated()['total'];
    }

    public function requestVote(): array {
        if (!isset($this->requestVote)) {
            $this->requestVote = $this->db->rowAssoc("
                SELECT coalesce(sum(rv.Bounty), 0) AS size,
                    count(*) AS total
                FROM requests_votes rv
                WHERE rv.UserID = ?
                ", $this->id
            );
            if (empty($this->requestVote)) {
                $this->requestVote = ['size' => 0, 'total' => 0];
            }
        }
        return $this->requestVote;
    }

    public function requestVoteSize(): int {
        return $this->requestVote()['size'];
    }

    public function requestVoteTotal(): int {
        return $this->requestVote()['total'];
    }

    protected function snatch(): array {
        if (!isset($this->snatch)) {
            $this->snatch = $this->db->rowAssoc("
                SELECT count(*) AS total,
                    count(DISTINCT x.fid) AS 'unique'
                FROM xbt_snatched AS x
                INNER JOIN torrents AS t ON (t.ID = x.fid)
                WHERE x.uid = ?
                ", $this->id
            );
            if (empty($this->snatch)) {
                $this->snatch = ['total' => 0, 'unique' => 0];
            }
        }
        return $this->snatch;
    }

    public function snatchTotal(): int {
        return $this->snatch()['total'];
    }

    public function snatchUnique(): int {
        return $this->snatch()['unique'];
    }

}
