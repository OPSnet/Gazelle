<?php

namespace Gazelle\Stats;

use Gazelle\Enum\UserSummary;
use Gazelle\Enum\UserSummaryFltoken;
use Gazelle\Enum\UserSummaryMisc;
use Gazelle\Enum\UserSummaryRequest;
use Gazelle\Enum\UserSummarySeed;
use Gazelle\Enum\UserSummaryTGroup;

class User extends \Gazelle\BaseObject {
    /**
     * This class offloads all the counting operations you might
     * want to do with a User (so that the User class does not
     * grow to an unmanageable size).
     *
     * Counting things relating to collections of users are found
     * in the Users (plural) class.
     */

    final public const tableName              = 'user_summary';
    final protected const CACHE_COMMENT_TOTAL = 'user_nrcomment_%d';
    final protected const CACHE_GENERAL       = 'user_stx_%d';

    // Cache the underlying db calls
    protected array $commentTotal;

    public function flush(): static {
        self::$cache->delete_multi([
            sprintf(self::CACHE_COMMENT_TOTAL, $this->id),
            sprintf(self::CACHE_GENERAL, $this->id),
        ]);
        unset($this->info);
        return $this;
    }

    public function link(): string {
        return sprintf('<a href="%s">%s</a>', $this->url(), 'Stats');
    }

    public function location(): string {
        return 'user.php?action=stats&userid=' . $this->id;
    }

    /**
     * Get the total number of comments made by page type
     *
     * @param string $page name [artist, collages requests torrents]
     * @return int number of comments, 0 if page is invalid
     */
    public function commentTotal(string $page): int {
        if (!isset($this->commentTotal)) {
            $key = sprintf(self::CACHE_COMMENT_TOTAL, $this->id);
            $commentTotal = self::$cache->get_value($key);
            if ($commentTotal === false) {
                self::$db->prepared_query("
                    SELECT Page, count(*) as n
                    FROM comments
                    WHERE AuthorID = ?
                    GROUP BY Page
                    ", $this->id
                );
                $commentTotal = self::$db->to_pair('Page', 'n');
                self::$cache->cache_value($key, $commentTotal, 3600);
            }
            $this->commentTotal = $commentTotal;
        }
        return $this->commentTotal[$page] ?? 0;
    }

    public function incrementCommentTotal(string $page): int {
        // need to call the commentTotal() method to set first
        // comment of a page type to 0, then we can increment it.
        $this->commentTotal[$page] = 1 + $this->commentTotal($page);
        self::$cache->cache_value(
            sprintf(self::CACHE_COMMENT_TOTAL, $this->id),
            $this->commentTotal,
            3600,
        );
        return $this->commentTotal[$page];
    }

    public function historyUseragentTracker(): array {
        $result = $this->pg()->executeParams('
            select useragent, total
            from history_useragent_tracker
            where id_user = $1
            order by useragent
            ', $this->id
        );
        return $result->fetchAll(\PGSQL_ASSOC);
    }

    /**
     * @see \Gazelle\Stats\Users::refresh()
     */
    public function info(): array {
        if (isset($this->info)) {
            return $this->info;
        }
        $key = sprintf(self::CACHE_GENERAL, $this->id);
        $info = self::$cache->get_value($key);
        if ($info === false) {
            // user_summary_seed maintains data only for enabled users,
            // so it will draw a null on a disabled/unconfirmed user
            $info = $this->pg()->rowAssocex("
                select
                    usf.gifted,
                    usf.received,
                    usf.used,
                    usm.collage_created,
                    usm.collage_contrib,
                    usm.forum_post,
                    usm.forum_thread,
                    usm.invited,
                    usr.bounty_total,
                    usr.created_total,
                    usr.vote_total,
                    usr.bounty_size,
                    usr.created_size,
                    usr.vote_size,
                    ust.artist_added,
                    ust.download_total,
                    ust.download_unique,
                    ust.perfect_flac,
                    ust.perfecter_flac,
                    ust.upload,
                    ust.unique_group,
                    coalesce(uss.leeching,        0)   as leeching,
                    coalesce(uss.seeding,         0)   as seeding,
                    coalesce(uss.snatch_total,    0)   as snatch_total,
                    coalesce(uss.snatch_unique,   0)   as snatch_unique,
                    coalesce(uss.bp_accrual_hour, 0.0) as bp_accrual_hour,
                    coalesce(uss.seedtime_hour,   0.0) as seedtime_hour
                from user_summary_fltoken       usf
                inner join user_summary_misc    usm using (id_user)
                inner join user_summary_request usr using (id_user)
                inner join user_summary_tgroup  ust using (id_user)
                left  join user_summary_seed    uss using (id_user)
                where usf.id_user = $1
                ", $this->id
            );
            self::$cache->cache_value($key, $info, 300);
        }
        $this->info = $info;
        return $this->info;
    }

    /**
     * Some statistics can be updated immediately, such as download_total.
     * Others, like download_unique need a possibly expensive check.
     * In any case, those stats will be updated within the hour.
     * If we can update immediately, though, we can do it here.
     */
    public function incrementFltoken(UserSummaryFltoken $field, int $increment = 1): int {
        return $this->increment(UserSummary::fltoken, $field, $increment);
    }

    public function incrementMisc(UserSummaryMisc $field, int $increment = 1): int {
        return $this->increment(UserSummary::misc, $field, $increment);
    }

    public function incrementRequest(UserSummaryRequest $field, int $increment = 1): int {
        return $this->increment(UserSummary::request, $field, $increment);
    }

    public function incrementSeed(UserSummarySeed $field, int $increment = 1): int {
        return $this->increment(UserSummary::seed, $field, $increment);
    }

    public function incrementTGroup(UserSummaryTGroup $field, int $increment = 1): int {
        return $this->increment(UserSummary::tgroup, $field, $increment);
    }

    public function increment(
        UserSummary $table,
        UserSummaryFltoken|UserSummaryMisc|UserSummaryRequest|UserSummarySeed|UserSummaryTGroup $field,
        int $increment = 1
    ): int {
        $affected = $this->pg()->query("
            update {$table->value} set
                {$field->value} = {$field->value} + $1
            where id_user = $2
            ", $increment, $this->id
        );
        $this->flush();
        return $affected;
    }

    public function metric(UserSummaryFltoken|UserSummaryMisc|UserSummaryRequest|UserSummarySeed|UserSummaryTGroup $field): int {
        return $this->info()[$field->value];
    }

    public function metricFloat(UserSummarySeed $field): float {
        return $this->info()[$field->value];
    }

    public function artistAddedTotal(): int {
        return $this->metric(UserSummaryTGroup::artistAdded);
    }

    public function bpHourlyAccrual(): float {
        return $this->metricFloat(UserSummarySeed::bpAccrualHour);
    }

    public function collageTotal(): int {
        return $this->metric(UserSummaryMisc::collageCreated);
    }

    public function collageContrib(): int {
        return $this->metric(UserSummaryMisc::collageContrib);
    }

    public function downloadTotal(): int {
        return $this->metric(UserSummaryTGroup::downloadTotal);
    }

    public function downloadUnique(): int {
        return $this->metric(UserSummaryTGroup::downloadUnique);
    }

    public function flTokenGifted(): int {
        return $this->metric(UserSummaryFltoken::gifted);
    }

    public function flTokenReceived(): int {
        return $this->metric(UserSummaryFltoken::received);
    }

    public function flTokenUsed(): int {
        return $this->metric(UserSummaryFltoken::used);
    }

    public function forumPostTotal(): int {
        return $this->metric(UserSummaryMisc::forumPost);
    }

    public function forumThreadTotal(): int {
        return $this->metric(UserSummaryMisc::forumThread);
    }

    public function invitedTotal(): int {
        return $this->metric(UserSummaryMisc::invited);
    }

    public function leechTotal(): int {
        return $this->metric(UserSummarySeed::leeching);
    }

    public function perfectFlacTotal(): int {
        return $this->metric(UserSummaryTGroup::perfectFlac);
    }

    public function perfecterFlacTotal(): int {
        return $this->metric(UserSummaryTGroup::perfecterFlac);
    }

    public function requestBountySize(): int {
        return $this->metric(UserSummaryRequest::bountySize);
    }

    public function requestBountyTotal(): int {
        return $this->metric(UserSummaryRequest::bountyTotal);
    }

    public function requestCreatedSize(): int {
        return $this->metric(UserSummaryRequest::createdSize);
    }

    public function requestCreatedTotal(): int {
        return $this->metric(UserSummaryRequest::createdTotal);
    }

    public function requestVoteSize(): int {
        return $this->metric(UserSummaryRequest::voteSize);
    }

    public function requestVoteTotal(): int {
        return $this->metric(UserSummaryRequest::voteTotal);
    }

    public function seedingTotal(): int {
        return $this->metric(UserSummarySeed::seeding);
    }

    public function seedtimeHour(): int {
        return $this->metric(UserSummarySeed::seedtimeHour);
    }

    public function snatchTotal(): int {
        return $this->metric(UserSummarySeed::snatchTotal);
    }

    public function snatchUnique(): int {
        return $this->metric(UserSummarySeed::snatchUnique);
    }

    public function uniqueGroupTotal(): int {
        return $this->metric(UserSummaryTGroup::uniqueGroup);
    }

    public function uploadTotal(): int {
        return $this->metric(UserSummaryTGroup::upload);
    }

    public function timeline(): array {
        $key = "u_statgfx_{$this->id}";
        $charts = self::$cache->get_value($key);
        if ($charts === false) {
            $charts = [
                ['name' => 'daily',   'interval' => 1,      'count' => 24],
                ['name' => 'monthly', 'interval' => 24,     'count' => 30],
                ['name' => 'yearly',  'interval' => 24 * 7, 'count' => 52],
            ];
            foreach ($charts as &$chart) {
                self::$db->prepared_query("
                    SELECT date_format(Time, '%Y-%m-%d')
                                              AS date,
                        Uploaded              AS data_up,
                        Downloaded            AS data_down,
                        Uploaded - Downloaded AS buffer,
                        BonusPoints           AS bp,
                        Torrents              AS uploads,
                        PerfectFLACs          AS perfect
                    FROM users_stats_{$chart['name']}
                    WHERE UserID = ?
                    ORDER BY Time DESC
                    LIMIT ?
                    ", $this->id, $chart['count']
                );
                $stats = array_reverse(self::$db->to_array(false, MYSQLI_ASSOC));
                foreach (['data_up', 'data_down', 'buffer', 'bp', 'uploads', 'perfect'] as $dimension) {
                    $chart[$dimension] = array_column($stats, $dimension);
                }
                $chart['timeline'] = array_column($stats, 'date');
                unset($chart);
            }
            self::$cache->cache_value($key, $charts, 3600);
        }
        return $charts;
    }

    /**
     * How many unresolved torrent reports are there for this user?
     *
     * @return int number of unresolved reports
     */
    public function unresolvedReportsTotal(): int {
        return (int)self::$db->scalar("
            SELECT count(*)
            FROM reportsv2 AS r
            INNER JOIN torrents AS t ON (t.ID = r.TorrentID)
            WHERE r.Status != 'Resolved'
                AND t.UserID = ?
            ", $this->id
        );
    }

    public function remove(): int {
        // the object is a composition of info from other tables
        return 0;
    }
}
