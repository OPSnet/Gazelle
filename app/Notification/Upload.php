<?php

namespace Gazelle\Notification;

class Upload extends \Gazelle\Base {
    protected $cond = [];
    protected $args = [];
    protected $debug = false;
    protected $seenUserFilter = false;

    /**
     * Trace notification results on/off
     *
     * @param bool debug on/off
     */
    public function setDebug(int $debug) {
        $this->debug = $debug;
        return $this;
    }

    /**
     * Add a list of artists that will trigger a notification
     *
     * @param array array of artist roles and lists of artist ( of artists (id, id, id)
     *  [main => [1, 2, 3], guest => [4, 5, 6], ...]
     */
    public function addArtists(array $artistList) {
        if (empty($artistList)) {
            $this->cond[] = "unf.Artists = ''";
            return $this;
        }
        $mainName = [];
        $guestName = [];
        foreach ($artistList as $role => $artists) {
            foreach ($artists as $artist) {
                if (in_array($role, ['main', 'composer', 'conductor', 'arranger', 'dj'])) {
                    $mainName[] = $artist['name'];
                } else {
                    $guestName[] = $artist['name'];
                }
            }
        }
        // Don't add notification if >2 main artists or if tracked artist isn't a main artist
        if (count($mainName) > 2) {
            $like = implode(' OR ', array_merge(["unf.Artists = ''"], array_fill(0, count($mainName), "unf.Artists LIKE concat('%|', ?, '|%')")));
            $this->cond[] = "unf.ExcludeVA = '0' AND ($like)";
            $this->args = array_merge($this->args, $mainName);
        } else {
            if (!empty($guestArtist)) {
                $this->cond[] = "unf.ExcludeVA = '0'";
            }
            $all = array_merge($mainName, $guestName);
            $this->cond[] = "(" . implode(' OR ', array_merge(["unf.Artists = ''"], array_fill(0, count($all), "unf.Artists LIKE concat('%|', ?, '|%')"))) . ")";
            $this->args = array_merge($this->args, $all);
        }
        return $this;
    }

    /**
     * Add tags that trigger a notification
     *
     * @param array List of tags
     */
    public function addTags(array $tagList) {
        $tags = ["unf.Tags = ''"];
        $notTags = ["unf.NotTags = ''"];
        if ($tagList) {
            $tags[] = implode(' AND ', array_merge([], array_fill(0, count($tagList), "unf.Tags LIKE concat('%|', ?, '|%')")));
            $notTags[] = implode(' OR ', array_merge([], array_fill(0, count($tagList), "unf.NotTags LIKE concat('%|', ?, '|%')")));
        }
        $this->cond[] = "(" . implode(' OR ', $tags) . ")";
        $this->cond[] = "(" . implode(' OR NOT ', $notTags) . ")";
        $this->args = array_merge($this->args, $tagList, $tagList);
        return $this;
    }

    /**
     * Add a release category that triggers a notification
     *
     * @param string category
     */
    public function addCategory(string $category) {
        return $this->addDimension('Categories', $category);
    }

    /**
     * Add the uploader id that triggers a notification. If the uploader is
     * paranoid, nobody is notified. Otherwise everyone except the uploader
     * is notified. The uploader is notified if they explicitly set a
     * notification on their own username.
     *
     * @param int user id of the uploader
     */
    public function addUser(\Gazelle\User $uploader) {
        $this->cond[] = "((Users = '' AND UserId != ?) OR (Users LIKE concat('%|', ?, '|%')))";
        $this->args[] = $uploader->id();
        $this->args[] = $uploader->id();
        return $this;
    }

    /**
     * Add a generic dimension that triggers a notification
     *
     * @param string column The name of the DB column to look at
     * @param string dimension the value (if empty, column matches empty string)
     */
    protected function addDimension(string $column, string $dimension) {
        if (!$dimension) {
            $this->cond[] = "unf.$column = ''";
            return $this;
        }
        $this->cond[] = "(unf.$column = '' OR unf.$column LIKE concat('%|', ?, '|%'))";
        $this->args[] = $dimension;
        return $this;
    }

    /**
     * Add an optional release type that triggers a notification
     *
     * @param string release type (Album, EP, ...)
     */
    public function addReleaseType(string $releaseType) {
        return $this->addDimension('ReleaseTypes', $releaseType);
    }

    /**
     * Add an optional format that triggers a notification
     *
     * @param string format
     */
    public function addFormat(string $format) {
        return $this->addDimension('Formats', $format);
    }

    /**
     * Add an optional encoding that triggers a notification
     *
     * @param string encoding
     */
    public function addEncodings(string $encoding) {
        return $this->addDimension('Encodings', $encoding);
    }

    /**
     * Add an optional media that triggers a notification
     *
     * @param string media
     */
    public function addMedia(string $media) {
        return $this->addDimension('Media', $media);
    }

    /**
     * Add a year that triggers a notification
     *
     * @param ?int original year
     * @param ?int remaster year
    */
    public function addYear($originalYear, $remasterYear) {
        $default = "unf.FromYear = 0 AND unf.ToYear = 0";
        if ($originalYear && $remasterYear && ($originalYear !== $remasterYear)) {
            $this->cond[] = "((? BETWEEN unf.FromYear AND unf.ToYear) OR (? BETWEEN unf.FromYear AND unf.ToYear) OR ($default))";
            $this->args = array_merge($this->args, [$originalYear, $remasterYear]);
        } elseif ($originalYear || $remasterYear) {
            $this->cond[] = "((? BETWEEN unf.FromYear AND unf.ToYear) OR ($default))";
            $this->args[] = max($originalYear, $remasterYear);
        } else {
            $this->cond[] = $default;
        }
        return $this;
    }

    /**
     * Generate the list of users that the notification will trigger.
     *
     * A person who wants to be notified of artist X is also quite
     * likely to upload artist X. By default, do not notify a user
     * if that happens. If they do want to be notified of their own
     * uploads, they need to create a notification and reference
     * their own name.
     *
     * @return array of arrays [filterid, userid]
     */
    public function lookup(): array {
        self::$db->prepared_query($this->sql(), ...$this->args);
        return self::$db->to_array(false, MYSQLI_ASSOC, false);
    }

    /**
     * Trigger the notification: create the notifications for everyone
     *
     * @param int Group ID of the release
     * @param int Torrent ID of the releas
     * @param Feed an RSS feed object
     * @param string Feed item
     * @return int Number of users notified
     */
    public function trigger(int $groupId, int $torrentId, \Feed $feed, string $item): int {
        $results = $this->lookup();
        $nr = count($results);
        $file = $this->debug ? (TMPDIR . "/notification.$torrentId") : '/dev/null';
        $out = fopen($file, "a");
        fprintf($out, "g=%d t=%d results=%d\n%s\n%s\n",
            $groupId, $torrentId, $nr, $this->sql(), implode("\n", $this->args())
        );
        if ($nr === 0) {
            fclose($out);
            return $nr;
        }
        $n = 0;
        foreach ($results as $notify) {
            fprintf($out, "hit f={$notify['filter_id']} u={$notify['user_id']}\n");
            self::$db->prepared_query("
                INSERT IGNORE INTO users_notify_torrents
                       (GroupID, TorrentID, UserID, FilterID)
                VALUES (?,       ?,         ?,      ?)
                ", $groupId, $torrentId, $notify['user_id'], $notify['filter_id']
            );
            $n += self::$db->affected_rows();
            $feed->populate("torrents_notify_{$notify['passkey']}", $item);
            $feed->populate("torrents_notify_{$notify['filter_id']}_{$notify['passkey']}", $item);
            self::$cache->delete_value("user_notify_upload_{$notify['user_id']}");
        }
        fprintf($out, "inserted=%d\n", $n);
        fclose($out);
        return $n;
    }

    /* Generate the SQL notification query (handy for debugging)
     * More than one notification filter for a user may be triggered,
     * so we aggregate to only return the oldest filter id.
     *
     * @return SQL command with placeholders
     */
    public function sql(): string {
        return "SELECT min(unf.ID) AS filter_id, unf.UserID AS user_id, um.torrent_pass AS passkey
            FROM users_notify_filters AS unf
            INNER JOIN users_main AS um ON (um.ID = unf.UserID)
            WHERE " . implode("\nAND ", $this->cond) . "
            GROUP BY unf.UserID, um.torrent_pass";
    }

    /**
     * The SQL placeholder arguments for this notification query.
     *
     * @return array list of arguments
     */
    public function args(): array {
        return $this->args;
    }
}
