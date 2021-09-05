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

    protected const CACHE_COMMENT_TOTAL = 'user_nrcomment_%d';
    protected const CACHE_GENERAL = 'user_stats_%d';

    protected int $id;

    /* Some queries return two or more items of interest: these cache the
     * results so that the underlying call is only made once.
     */
    protected array $commentTotal;
    protected array $download;
    protected array $general;
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

    /**
     * Get the total number of comments made by page type
     *
     * @param string page name [artist, collages requests torrents]
     * @return int number of comments, 0 if page is invalid
     */
    public function commentTotal(string $page): int {
        if (!isset($this->commentTotal)) {
            $key = sprintf(self::CACHE_COMMENT_TOTAL, $this->id);
            $commentTotal = $this->cache->get_value($key);
            if ($commentTotal === false) {
                $this->db->prepared_query("
                    SELECT Page, count(*) as n
                    FROM comments
                    WHERE AuthorID = ?
                    GROUP BY Page
                    ", $this->id
                );
                $commentTotal = $this->db->to_pair('Page', 'n', false);
                $this->cache->cache_value($key, $commentTotal, 3600);
            }
            $this->commentTotal = $commentTotal;
        }
        return $this->commentTotal[$page] ?? 0;
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
            $this->requestCreated = $this->db->rowAssoc("
                SELECT coalesce(sum(rv.Bounty), 0) AS size,
                    count(*) AS total
                FROM requests AS r
                LEFT JOIN requests_votes AS rv ON (rv.RequestID = r.ID AND rv.UserID = r.UserID)
                WHERE r.UserID = ?
                ", $this->id
            );
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
        }
        return $this->snatch;
    }

    public function snatchTotal(): int {
        return $this->snatch()['total'];
    }

    public function snatchUnique(): int {
        return $this->snatch()['unique'];
    }

    public function general(): array {
        if (!isset($this->general)) {
            $key = sprintf(self::CACHE_GENERAL, $this->id);
            $general = $this->cache->get_value($key);
            $general = false;
            if ($general === false) {
                $general = $this->db->rowAssoc("
                    SELECT Groups    AS unique_group_total,
                        PerfectFlacs AS perfect_flac_total
                    FROM users_summary
                    WHERE UserID = ?
                    ", $this->id
                ) ?? [
                     'unique_group_total' => 0,
                     'perfect_flac_total' => 0,
                ];
                $general = array_merge($general, [
                    'collage_total' => $this->db->scalar("
                        SELECT count(*) FROM collages WHERE Deleted = '0' AND UserID = ?
                        ", $this->id
                    ),
                    'collage_contrib' => $this->db->scalar("
                        SELECT count(DISTINCT ct.CollageID)
                        FROM collages_torrents AS ct
                        INNER JOIN collages c ON (c.ID = ct.CollageID)
                        WHERE c.Deleted = '0'
                            AND ct.UserID = ?
                        ", $this->id
                    ),
                    'invited_total' => $this->db->scalar("
                        SELECT count(*) FROM users_info WHERE Inviter = ?
                        ", $this->id
                    ),
                    'forum_post_total' => $this->db->scalar("
                        SELECT count(*) FROM forums_posts WHERE AuthorID = ?
                        ", $this->id
                    ),
                    'forum_thread_total' => $this->db->scalar("
                        SELECT count(*) FROM forums_topics WHERE AuthorID = ?
                        ", $this->id
                    ),
                ]);
                $this->cache->cache_value($key, $general, 3600);
            }
            $this->general = $general;
        }
        return $this->general;
    }

    public function collageTotal(): int {
        return $this->general()['collage_total'];
    }

    public function collageContrib(): int {
        return $this->general()['collage_contrib'];
    }

    public function forumPostTotal(): int {
        return $this->general()['forum_post_total'];
    }

    public function forumThreadTotal(): int {
        return $this->general()['forum_thread_total'];
    }

    public function invitedTotal(): int {
        return $this->general()['invited_total'];
    }

    public function perfectFlacTotal(): int {
        return $this->general()['perfect_flac_total'];
    }

    public function uniqueGroupTotal(): int {
        return $this->general()['unique_group_total'];
    }
}
