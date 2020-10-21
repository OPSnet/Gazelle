<?php

namespace Gazelle\Notification;

class Upload extends \Gazelle\Base {
    protected $cond;
    protected $args;
    protected $debug;

    public function __construct(int $userId) {
        parent::__construct();
        $this->cond = ["um.Enabled = '1' AND um.UD != ?"];
        $this->args = [$userId];
    }

    public function setDebug(\Debug $debug) {
        $this->debug = &$debug;
    }

    public function addArtists(array $artistList) {
        if (empty($artistList)) {
            $this->cond[] = ["unf.Artists = ''"];
            return $this;
        }
        $mainArtist = [];
        $mainId = [];
        $guestArtist = [];
        $guestId = [];
        foreach ($artistList as $role => $artists) {
            foreach ($artists as $artist) {
                $clause = "unf.Artists LIKE concat('%|', ?, '|%')";
                if (in_array($role, [ARTIST_MAIN, ARTIST_COMPOSER, ARTIST_CONDUCTOR, ARTIST_DJ])) {
                    $mainArtist[] = $clause;
                    $mainId[] = $artist['name'];
                } else {
                    $guestArtist[] = $clause;
                    $guestId[] = $artist['name'];
                }
            }
        }
        // Don't add notification if >2 main artists or if tracked artist isn't a main artist
        if (count($mainArtist) > 2) {
            $this->cond[] = "unf.ExcludeVA = '0' AND (" . implode(' OR ', array_merge($mainArtist, $guestArtist, ["unf.Artists = ''"])) . ')';
            $this->args = array_merge($this->args, $mainId, $guestId);
        } else {
            if (empty($guestArtist)) {
                $cond = '';
            } else {
                $cond = "unf.ExcludeVA = '0' AND (" . implode(' OR ', $guestArtist) . ' OR ';
                $this->args = array_merge($this->args, $guestId);
            }
            $this->cond[] = "$cond" . implode(' OR ', array_merge($guestArtist, ["unf.Artists = ''"])) . ')';
            $this->args = array_merge($this->args, $mainId);
        }
        return $this;
    }

    public function addTags(array $tagList) {
        $taglist[] = '';
        $this->cond[] =  '(' . implode(' OR ', array_fill(0, count($tagList),    "unf.Tags LIKE concat('%|', ?, '|%')")) . ')';
        $this->cond[] = '!(' . implode(' OR ', array_fill(0, count($tagList), "unf.NotTags LIKE concat('%|', ?, '|%')")) . ')';
        $this->args = array_merge($this->args, $tagList, $tagList);
        return $this;
    }

    public function addFormatBitrate(array $fbList) {
        $newFormat  = array_merge(array_map(function ($f) {return $f['format'];}, $fbList), ['']);
        $newBitrate = array_merge(array_map(function ($f) {return $f['bitrate'];}, $fbList), ['']);
        $this->cond[] = "(unf.NewGroupsOnly = '0' OR (unf.NewGroupsOnly = '1' AND NOT (("
            . implode(' OR ', array_fill(0, count($newFormat), "unf.Formats LIKE concat('%|', ?, '|%')"))
            . ') AND ('
            . implode(' OR ', array_fill(0, count($newBitrate), "unf.Encodings LIKE concat('%|', ?, '|%')"))
            . "))))";
        $this->args = array_merge($this->args, $newFormat, $newBitrate);
    }

    public function addYear(int $originalYear, int $remasterYear) {
        $default = "unf.FromYear = 0 AND unf.ToYear = 0";
        if ($originalYear && $remasterYear) {
            $this->cond[] = "((? BETWEEN unf.FromYear AND unf.ToYear) OR (? BETWEEN unf.FromYear AND unf.ToYear) OR ($default))";
            $this->args = array_merge($this->args, $originalYear, $remasterYear);
        } elseif ($originalYear || $remasterYear) {
            $this->cond[] = "((? BETWEEN unf.FromYear AND unf.ToYear) OR ($default))";
            $this->args[] = max($originalYear, $remasterYear);
        } else {
            $this->cond[] = $default;
        }
        return $this;
    }

    protected function addDimension(string $column, $dimension) {
        $this->cond[] = "(unf.$column LIKE concat('%|', ?, '|%') OR unf.Categories = '')";
        $this->args[] = $dimension;
        return $this;
    }

    public function addCategory(string $category) {
        return $this->addDimension('Categories', $category);
    }

    public function addUser(int $userId) {
        return $this->addDimension('Users', $userId);
    }

    protected function addOptionalDimension(string $column, string $dimension) {
        $default = "unf.$column = ''";
        if (!$dimension) {
            $this->cond[] = $default;
            return $this;
        }
        $this->cond = array_merge($this->cond,
            '(' . implode(' OR ', [$default, "unf.$column LIKE concat('%|', ?, '|%')"]) . ')'
        );
        $this->args[] = $dimension;
        return $this;
    }

    public function addReleaseType(string $releaseType) {
        return $this->addOptionalDimension('ReleaseTypes', $releaseType);
    }

    public function addFormat(string $format) {
        return $this->addOptionalDimension('Formats', $format);
    }

    public function addEncodings(string $encoding) {
        return $this->addOptionalDimension('Encodings', $encoding);
    }

    public function addMedia(string $media) {
        return $this->addOptionalDimension('Media', $media);
    }

    public function trigger(int $groupId, int $torrentId, \Feed $feed, string $item): int {
        $this->db->prepared_query($this->sql(), ...$this->args);
        $results = $this->db->to_array('UserID');
        if (!$results) {
            return 0;
        }

        $args = [];
        foreach ($results as $user) {
            [$filterId, $userId, $passkey] = $user;
            $args = array_merge($args, [$groupId, $torrentId, $userId, $filterId]);
            $feed->populate("torrents_notify_$passkey", $item);
            $feed->populate("torrents_notify_{$filterId}_$passkey", $item);
            $this->cache->delete_value("notifications_new_$userId");
        }
        if ($this->debug) {
            $this->debug->set_flag(sprintf('upload: notification complete: (%d users)',
                count($results)
            ));
        }
        $this->db->prepared_query("
            INSERT IGNORE INTO users_notify_torrents (GroupID, TorrentID, UserID, FilterID)
            VALUES " . implode(', ', array_fill(0, count($results), '(?, ?, ?, ?)')),
            ...$args
        );
        return count($results);
    }

    public function sql(): string {
        return "SELECT unf.ID, unf.UserID, torrent_pass
            FROM users_notify_filters AS unf
            INNER JOIN users_main AS um ON (um.ID = unf.UserID)
            WHERE " . implode(' AND ', $this->cond);
    }

    public function args(): string {
        $out = [];
        foreach ($this->args as $arg) {
            $out[] = "'" . str_replace("'", "''", $arg) . "'";
        }
        return implode(', ', $out);
    }
}
