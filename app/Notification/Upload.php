<?php

namespace Gazelle\Notification;

use \Gazelle\Util\Irc;
use \Gazelle\Util\IrcText;

// NB: if you receive failures running this locally, the most likely cause is the
//     presence of users_notify_filters rows that match the uploads created here.

class Upload extends \Gazelle\Base {
    protected array $cond;
    protected array $args;
    protected array $rss;

    public function __construct(
        protected \Gazelle\Torrent $torrent,
    ) {}

    /**
     * Trigger the notification: create the notifications for everyone
     *
     * @return int Number of users notified
     */
    public function trigger(): int {
        $total = $this->sendUserNotification();
        // RSS notification must be handled after user notifications
        // to ensure their personal RSS feeds are updated.
        $this->sendRssNotification();
        $this->sendIrcNotification();
        return $total;
    }

    public function configure(): int {
        $torrent = $this->torrent;
        $format  = $torrent->format();
        if ($format) {
            $this->addDimension('Formats', $format);
        }
        $encoding  = $torrent->encoding();
        if ($encoding) {
            $this->addDimension('Encodings', $encoding);
        }
        $this->addDimension('Media', $torrent->media());

        $tgroup = $torrent->group();
        $this->addDimension('Categories', $tgroup->categoryName());

        // add uploader
        $uploader = $this->torrent->uploader();
        $this->cond[] = "((coalesce(Users, '') = '' AND UserId != ?) OR (Users LIKE concat('%|', ?, '|%')))";
        $this->args[] = $uploader->id();
        $this->args[] = $uploader->id();

        // add tags
        $tagList = $tgroup->tagNameList();
        $tags    = ["unf.Tags = ''"];
        $notTags = ["unf.NotTags = ''"];
        if ($tagList) {
            $tags[]    = implode(' OR ', array_merge([], array_fill(0, count($tagList), "unf.Tags LIKE concat('%|', ?, '|%')")));
            $notTags[] = 'NOT (' . implode(' OR ', array_merge([], array_fill(0, count($tagList), "unf.NotTags LIKE concat('%|', ?, '|%')"))) . ')';
        }
        $this->cond[] = "(" . implode(' OR ', $tags) . ")";
        $this->cond[] = "(" . implode(' OR ', $notTags) . ")";
        $this->args = array_merge($this->args, $tagList, $tagList);

        // add artists if applicable
        // [main => [1, 2, 3], guest => [4, 5, 6], ...]
        if ($tgroup->categoryName() == 'Music') {
            $this->addDimension('ReleaseTypes', $tgroup->releaseTypeName());

            $mainName  = [];
            $guestName = [];
            foreach ($tgroup->artistRole()->roleList() as $role => $artists) {
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
                if (!empty($guestName)) {
                    $this->cond[] = "unf.ExcludeVA = '0'";
                }
                $all = [...$mainName, ...$guestName];
                $this->cond[] = "(" . implode(' OR ', [...["unf.Artists = ''"], ...array_fill(0, count($all), "unf.Artists LIKE concat('%|', ?, '|%')")]) . ")";
                $this->args = array_merge($this->args, $all);
            }
        }

        // exclude VA (compilations)
        if ($tgroup->releaseTypeName() === 'Compilation') {
            $this->cond[] = "unf.ExcludeVA = '0'";
        }

        // more than one entry in this group, exclude filters that want only new groups
        if (count($tgroup->torrentIdList()) > 1) {
            $this->cond[] = "unf.NewGroupsOnly = '0'";
        }

        // add year
        $originalYear = $tgroup->year();
        $remasterYear = $torrent->remasterYear();
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
        return count($this->cond);
    }

    /**
     * Add a generic dimension that triggers a notification
     */
    protected function addDimension(string $column, string $dimension): static {
        if (!$dimension) {
            $this->cond[] = "unf.$column = ''";
            return $this;
        }
        $this->cond[] = "(unf.$column = '' OR unf.$column LIKE concat('%|', ?, '|%'))";
        $this->args[] = $dimension;
        return $this;
    }

    /* Generate the SQL notification query (handy for debugging)
     * More than one notification filter for a user may be triggered,
     * so we aggregate to only return the oldest filter id.
     */
    public function sql(): string {
        if (!isset($this->cond)) {
            $this->configure();
        }
        return "SELECT min(unf.ID) AS filter_id, unf.UserID AS user_id, um.torrent_pass AS passkey
            FROM users_notify_filters AS unf
            INNER JOIN users_main AS um ON (um.ID = unf.UserID)
            WHERE " . implode("\nAND ", $this->cond) . "
            GROUP BY unf.UserID, um.torrent_pass";
    }

    /**
     * The SQL placeholder arguments for this notification query.
     */
    public function args(): array {
        if (!isset($this->args)) {
            $this->configure();
        }
        return $this->args;
    }

    /**
     * The SQL conditions for this notification query.
     */
    public function cond(): array {
        if (!isset($this->cond)) {
            $this->configure();
        }
        return $this->cond;
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
    public function userFilterList(): array {
        self::$db->prepared_query($this->sql(), ...$this->args);
        return self::$db->to_array(false, MYSQLI_ASSOC, false);
    }

    public function sendUserNotification(): int {
        $results  = $this->userFilterList();
        $nr       = count($results);
        $debug    = false;
        $tgroupId = $this->torrent->groupId();
        if (DEBUG_UPLOAD_NOTIFICATION) {
            $debug = fopen(TMPDIR . "/notification.{$this->torrent->id()}", "a");
            if ($debug !== false) {
                fprintf($debug, "g=%d t=%d results=%d\n%s\n%s\n",
                    $tgroupId, $this->torrent->id(), $nr, $this->sql(), implode("\n", $this->args())
                );
            }
        }

        if ($nr === 0) {
            if ($debug !== false) {
                fclose($debug);
            }
            return $nr;
        }

        $n = 0;
        foreach ($results as $notify) {
            if ($debug !== false) {
                fprintf($debug, "hit f={$notify['filter_id']} u={$notify['user_id']}\n");
            }
            self::$db->prepared_query("
                INSERT IGNORE INTO users_notify_torrents
                       (GroupID, TorrentID, UserID, FilterID)
                VALUES (?,       ?,         ?,      ?)
                ", $tgroupId, $this->torrent->id(), $notify['user_id'], $notify['filter_id']
            );
            $n += self::$db->affected_rows();
            $this->rss[] = "torrents_notify_{$notify['passkey']}";
            $this->rss[] = "torrents_notify_{$notify['filter_id']}_{$notify['passkey']}";
            self::$cache->delete_value("user_notify_upload_{$notify['user_id']}");
        }

        if ($debug !== false) {
            fprintf($debug, "inserted=%d\n", $n);
            fclose($debug);
        }
        return $n;
    }

    public function sendRssNotification(): int {
        $tgroup = $this->torrent->group();

        // set up RSS feed categories
        $this->rss[] = match ($tgroup->categoryName()) {
            'Music'             => 'torrents_music',
            'Applications'      => 'torrents_apps',
            'Audiobooks'        => 'torrents_abooks',
            'Comedy'            => 'torrents_comedy',
            'Comics'            => 'torrents_comics',
            'E-Books'           => 'torrents_ebooks',
            'E-Learning Videos' => 'torrents_evideos',
            default             => 'torrents_unknown',
        };

        $torrent = $this->torrent;
        if (in_array($torrent->format(), ['FLAC', 'MP3'])) {
            $this->rss[] = 'torrents_' . strtolower($torrent->format());
        }
        if ($torrent->encoding() === 'Lossless') {
            $this->rss[] = 'torrents_lossless';
        } elseif ($torrent->encoding() === '24bit Lossless') {
            $this->rss[] = 'torrents_lossless24';
        }
        if ($torrent->media() === 'Vinyl') {
            $this->rss[] = 'torrents_vinyl';
        }
        $this->rss[] = 'torrents_all';

        $feed = new \Gazelle\Feed;
        $item = $feed->item(
            title:       $torrent->fullName(),
            description: \Text::strip_bbcode($tgroup->description()),
            page:        preg_replace('/#.*$/', '', $torrent->location()) . '&action=download&torrent_pass=[[PASSKEY]]',
            date:        date('r', strtotime($torrent->created())), /** @phpstan-ignore-line */
            creator:     $torrent->uploader()->username(),
            comments:    $tgroup->location(),
            category:    implode(',', $tgroup->tagNameList()),
        );

        $n = 0;
        foreach ($this->rss as $rss) {
            $n++;
            $feed->populate($rss, $item);
        }

        // RSS for bookmarks
        self::$db->prepared_query("
            SELECT concat('torrents_bookmarks_t_', um.torrent_pass)
            FROM users_main AS um
            INNER JOIN bookmarks_torrents AS b ON (b.UserID = um.ID)
            WHERE b.GroupID = ?
            ", $tgroup->id()
        );
        foreach (self::$db->collect(0, false) as $subFeed) {
            $n++;
            $feed->populate($rss, $item);
        }
        return $n;
    }

    public function sendIrcNotification(): void {
        Irc::sendMessage(IRC_ANNOUNCE, $this->ircNotification());
    }

    public function ircNotification(): string {
        $torrent = $this->torrent;
        $tgroup  = $torrent->group();
        return match($tgroup->categoryName()) {
            'Music' => Irc::render(
                IrcText::Bold,
                'TORRENT:',
                IrcText::Bold,
                ' ',
                IrcText::DodgerBlue,
                $torrent->name(),
                IrcText::ColorOff,
                ' – ',
                IrcText::DarkOrange,
                '[',
                $torrent->isRemasteredUnknown() || !$torrent->isRemastered() ? $tgroup->year() : $torrent->remasterYear(),
                "] [{$tgroup->releaseTypeName()}] ",
                implode('/', [$torrent->media(), $torrent->format(), $torrent->encoding()]),
                IrcText::ColorOff,
                ' – ',
                IrcText::SurfieGreen,
                implode(',', $tgroup->tagNameList()),
                IrcText::ColorOff,
                ' – ',
                IrcText::FreeSpeechRed,
                SITE_URL . '/' . $tgroup->location(),
                IrcText::ColorOff,
                ' – ',
                IrcText::FreeSpeechRed,
                SITE_URL . '/' . preg_replace('/#.*$/', '', $torrent->location()) . '&action=download',
            ),
            default => Irc::render(
                IrcText::Bold,
                'TORRENT:',
                IrcText::Bold,
                ' ',
                IrcText::DodgerBlue,
                $torrent->name(),
                IrcText::ColorOff,
                ' – ',
                IrcText::SurfieGreen,
                implode(',', $tgroup->tagNameList()),
                IrcText::ColorOff,
                ' – ',
                IrcText::FreeSpeechRed,
                SITE_URL . '/' . $tgroup->location(),
                IrcText::ColorOff,
                ' – ',
                IrcText::FreeSpeechRed,
                SITE_URL . '/' . preg_replace('/#.*$/', '', $torrent->location()) . '&action=download',
            ),
        };
    }
}
