<?php

namespace Gazelle\UserRank;

/* The common code for extracting the metric counts
 * from the tables that represent the ranking dimensions
 * happens here. Call the build() method interactively to
 * see what the results look like. New dimensions should
 * follow the same pattern (count by user, group by count).
 */

abstract class AbstractUserRank extends \Gazelle\Base {

    abstract public function cacheKey(): string;
    abstract public function selector(): string;

    /**
     * Build the ranking table from a dimension's
     * selector. This is then folded down into a
     * series of buckets to map raw metrics into
     * a percentile value from 0 to 100. The table
     * is cached, not persisted to the database.
     *
     * This will possibly need to be moved to a
     * scheduler task, as some aggregations are very
     * slow.
     */
    public function build(): array {
        self::$db->prepared_query("
            DROP TEMPORARY TABLE IF EXISTS temp_stats
        ");

        self::$db->prepared_query("
            CREATE TEMPORARY TABLE temp_stats (
                id integer NOT NULL PRIMARY KEY AUTO_INCREMENT,
                n bigint NOT NULL DEFAULT 0
            )
        ");
        self::$db->prepared_query("
            INSERT INTO temp_stats (n)
            " . $this->selector()
        );

        /* Classic Mysql cannot do this (although it is fixed in MariaDB)
         * See: https://stackoverflow.com/questions/343402/getting-around-mysql-cant-reopen-table-error
         *
         *  SELECT min(n) as bucket
         *  FROM temp_stats
         *  GROUP BY ceil(id / (SELECT count(*)/100 FROM temp_stats))
         */
        self::$db->prepared_query("
            DROP TEMPORARY TABLE IF EXISTS temp_stats_dup
        ");
        self::$db->prepared_query("
            CREATE TEMPORARY TABLE temp_stats_dup LIKE temp_stats
        ");
        self::$db->prepared_query("
            INSERT INTO temp_stats_dup
            SELECT * FROM temp_stats
        ");

        self::$db->prepared_query("
            SELECT min(n) as bucket
            FROM temp_stats
            GROUP BY ceil(id / (SELECT count(*)/100 FROM temp_stats_dup))
            ORDER BY 1
        ");
        $raw = self::$db->collect('bucket');
        if (empty($raw)) {
            // This occurs only a fresh installation
            $raw = [0];
        }

        /* We now have a list of at most 100 elements. For a number
         * of metrics the series will follow a sharp exponential
         * curve with many repeated values (because most of the
         * activity comes from a relatively small number of users).
         *
         * 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1,
         * 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 2, 2, 2, 2, 2, 2,
         * 2, 2, 2, 2, 2, 2, 2, 2, 2, 2, 2, 3, 3, 3, 3, 3, 3, 3, 3,
         * 3, 4, 4, 4, 4, 4, 4, 5, 5, 5, 5, 6, 6, 6, 7, 7, 7, 8, 8,
         * 9, 10, 10, 11, 11, 12, 13, 14, 15, 17, 18, 20, 23, 25,
         * 28, 32, 36, 43, 51, 67, 83, 113, 170, 357
         *
         * The storage can be reduced by recording only the
         * percentiles breaks, making the above become:
         *
         * 357 => 100,
         * 170 => 99,
         * 113 => 98,
         *   ...
         *   3 => 50,
         *   2 => 33,
         *   1 => 1
         *
         * It is then trivial to walk down the list and read off
         * the percentile. If the user metric is greater than the
         * current value, this is their percentile. Note that some
         * dimensions like data upload and download will show little
         * to no reduction in size, as such metrics are generally
         * unique per user.
         */

        $previous = 0;
        $percentile = 0;
        $increment = max(1, 100 / count($raw));
        $table = [];
        foreach ($raw as $bucket) {
            $percentile += $increment;
            if ($previous != $bucket) {
                $table[$bucket] = (int)(round($percentile));
            }
            $previous = $bucket;
        }
        $table = array_reverse($table, true);

        // add some fuzz to the expiry time, so all the tables don't expire at once
        self::$cache->cache_value($this->cacheKey(), $table, 86400 + rand(0, 3600));
        return $table;
    }

    /**
     * Map a user's raw metric (e.g. uploads = 648) to a percentile
     * rank (e.g. 86).
     *
     * @param int $metric A result from a query of the form
     *     'select count(*) from t where user = ?' or anything
     *     else that can be counted.
     * @return int rank between 0 and 100
     */
    public function rank(int $metric): int {
        if ($metric == 0) {
            return 0;
        }
        if (($table = self::$cache->get_value($this->cacheKey())) === false) {
            $cacheLock = $this->cacheKey() . '_lock';
            if (self::$cache->get_value($cacheLock) !== false) {
                return 0;
            }
            self::$cache->cache_value($cacheLock, true, 300);
            $table = $this->build();
            self::$cache->delete_value($cacheLock);
        }
        foreach ($table as $value => $percentile) {
            if ($metric >= $value) {
                return $percentile;
            }
        }
        return 1;
    }
}
