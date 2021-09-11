<?php

namespace Gazelle\Stats;

class User extends \Gazelle\BaseObject {

    /**
     * This class offloads all the counting operations you might
     * want to do with a User (so that the User class does not
     * grow to an unmanageable size).
     *
     * Counting things relating to collections of users are found
     * in the Users (plural) class.
     */

    protected const CACHE_COMMENT_TOTAL = 'user_nrcomment_%d';
    protected const CACHE_GENERAL = 'user_statx_%d';

    // Cache the underlying db calls
    protected array $commentTotal;
    protected array $general = [];

    public function tableName(): string {
        return 'user_summary';
    }

    public function flush() {
        $this->cache->deleteMulti([
            sprintf(self::CACHE_COMMENT_TOTAL, $this->id),
            sprintf(self::CACHE_GENERAL, $this->id),
        ]);
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

    /**
     * @see \Gazelle\Stats\Users::refresh()
     */
    public function general(): array {
        if (empty($this->general)) {
            $key = sprintf(self::CACHE_GENERAL, $this->id);
            $general = $this->cache->get_value($key);
            if ($general === false) {
                $general = $this->db->rowAssoc("
                    SELECT artist_added_total,
                        collage_total,
                        collage_contrib,
                        download_total,
                        download_unique,
                        fl_token_total,
                        forum_post_total,
                        forum_thread_total,
                        invited_total,
                        leech_total,
                        perfect_flac_total,
                        perfecter_flac_total,
                        request_bounty_total,
                        request_bounty_size,
                        request_created_total,
                        request_created_size,
                        request_vote_total,
                        request_vote_size,
                        seeding_total,
                        snatch_total,
                        snatch_unique,
                        unique_group_total,
                        upload_total
                    FROM user_summary
                    WHERE user_id = ?
                    ", $this->id
                ) ?? [
                    'artist_added_total'    => 0,
                    'collage_total'         => 0,
                    'collage_contrib'       => 0,
                    'download_total'        => 0,
                    'download_unique'       => 0,
                    'fl_token_total'        => 0,
                    'forum_post_total'      => 0,
                    'forum_thread_total'    => 0,
                    'invited_total'         => 0,
                    'leech_total'           => 0,
                    'perfect_flac_total'    => 0,
                    'perfecter_flac_total'  => 0,
                    'request_bounty_total'  => 0,
                    'request_bounty_size'   => 0,
                    'request_created_total' => 0,
                    'request_created_size'  => 0,
                    'request_vote_total'    => 0,
                    'request_vote_size'     => 0,
                    'seedingh_total'        => 0,
                    'snatch_total'          => 0,
                    'snatch_unique'         => 0,
                    'unique_group_total'    => 0,
                    'upload_total'          => 0,
                ];
                $this->cache->cache_value($key, $general, 300);
            }
            $this->general = $general;
        }
        return $this->general;
    }

    /**
     * Some statistics can be updated immediately, such as download_total.
     * Others, like download_unique need a possibly expensive check.
     * In any case, those stats will be updated within the hour.
     * If we can update immediately, though, we can do it here.
     */
    public function increment(string $name, int $incr = 1): int {
        $this->db->prepared_query("
            UPDATE user_summary SET
                $name = $name + ?
            WHERE user_id = ?
            ", $incr, $this->id
        );
        $this->general = [];
        $this->cache->delete_value(sprintf(self::CACHE_GENERAL, $this->id));
        return $this->db->affected_rows();
    }

    public function artistAddedTotal(): int {
        return $this->general()['artist_added_total'];
    }

    public function collageTotal(): int {
        return $this->general()['collage_total'];
    }

    public function collageContrib(): int {
        return $this->general()['collage_contrib'];
    }

    public function downloadTotal(): int {
        return $this->general()['download_total'];
    }

    public function downloadUnique(): int {
        return $this->general()['download_unique'];
    }

    public function flTokenTotal(): int {
        return $this->general()['fl_token_total'];
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

    public function leechTotal(): int {
        return $this->general()['leech_total'];
    }

    public function perfectFlacTotal(): int {
        return $this->general()['perfect_flac_total'];
    }

    public function perfecterFlacTotal(): int {
        return $this->general()['perfecter_flac_total'];
    }

    public function requestBountySize(): int {
        return $this->general()['request_bounty_size'];
    }

    public function requestBountyTotal(): int {
        return $this->general()['request_bounty_total'];
    }

    public function requestCreatedSize(): int {
        return $this->general()['request_created_size'];
    }

    public function requestCreatedTotal(): int {
        return $this->general()['request_created_total'];
    }

    public function requestVoteSize(): int {
        return $this->general()['request_vote_size'];
    }

    public function requestVoteTotal(): int {
        return $this->general()['request_vote_total'];
    }

    public function seedingTotal(): int {
        return $this->general()['seeding_total'];
    }

    public function snatchTotal(): int {
        return $this->general()['snatch_total'];
    }

    public function snatchUnique(): int {
        return $this->general()['snatch_unique'];
    }

    public function uniqueGroupTotal(): int {
        return $this->general()['unique_group_total'];
    }

    public function uploadTotal(): int {
        return $this->general()['upload_total'];
    }
}
