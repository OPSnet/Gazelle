<?php

namespace Gazelle\Manager;

class SiteLog extends \Gazelle\Base {
    protected int $totalMatches = 0;
    protected array $result     = [];
    protected array $usernames  = [];

    public function __construct(
        protected \Gazelle\Manager\User $userMan,
    ) {}

    public function totalMatches(): int { return $this->totalMatches; }
    public function result(): array { return $this->result; }

    public function page(int $page, int $offset, string $searchTerm): array {
        if ($searchTerm === '') {
            // no full text search: hit the DB directly
            self::$db->prepared_query("
                SELECT ID   AS id,
                    Message AS message,
                    Time    AS created
                FROM log
                ORDER BY ID DESC
                LIMIT ?, ?
                ", $offset, LOG_ENTRIES_PER_PAGE
            );
            $this->totalMatches = (int)self::$db->record_count();
            if ($this->totalMatches < LOG_ENTRIES_PER_PAGE) {
                $this->totalMatches += $offset;
            } else {
                $result = (new \SphinxqlQuery())->select('id')->from('log, log_delta')->limit(0, 1, 1)->sphinxquery();
                $this->totalMatches = $result
                    ? min(SPHINX_MAX_MATCHES, (int)$result->get_meta('total_found'))
                    : 0;
            }
            return $this->decorate(self::$db->to_array(false, MYSQLI_NUM, false));
        }
        $sq = new \SphinxqlQuery();
        $sq->select('id')
            ->from('log, log_delta')
            ->order_by('id', 'DESC')
            ->limit($offset, LOG_ENTRIES_PER_PAGE, $offset + LOG_ENTRIES_PER_PAGE);
        foreach (explode(' ', $searchTerm) as $s) {
            $sq->where_match($s, 'message');
        }
        $result = $sq->sphinxquery();
        if (!$result || $result->Errno) {
            return [];
        }
        $this->totalMatches = min(SPHINX_MAX_MATCHES, $result->get_meta('total_found'));
        $logIds = $result->collect('id') ?: [0];
        self::$db->prepared_query("
            SELECT ID   AS id,
                Message AS message,
                Time    AS created
            FROM log
            WHERE ID IN (" . placeholders($logIds) . ")
            ORDER BY ID DESC
            ", ...$logIds
        );
        return $this->decorate(self::$db->to_array(false, MYSQLI_NUM, false));
    }

    public function tgroupLogList($tgroupId): array {
        self::$db->prepared_query("
            SELECT gl.TorrentID        AS torrent_id,
                gl.UserID              AS user_id,
                gl.Info                AS info,
                gl.Time                AS created,
                t.Media                AS media,
                t.Format               AS format,
                t.Encoding             AS encoding,
                if(t.ID IS NULL, 1, 0) AS deleted
            FROM group_log gl
            LEFT JOIN torrents t ON (t.ID = gl.TorrentID)
            WHERE gl.GroupID = ?
            ORDER BY gl.ID DESC
            ", $tgroupId
        );
        return self::$db->to_array(false, MYSQLI_ASSOC, false);
    }

    /**
     * Parse the log messages and decorate where applicable with links and color
     */
    public function decorate(array $in): array {
        $out = [];
        foreach ($in as [$id, $message, $created]) {
            [$color, $message] = $this->colorize($message);
            $out[] = [
                'id'      => $id,
                'color'   => $color,
                'message' => $message,
                'created' => $created,
            ];
        }
        return $out;
    }

    /**
     * Parse an individual message and enrich it.
     * Returns a flag indicated whether color was applied, and the enriched message
     */
    public function colorize(string $message): array {
        $parts    = explode(' ', $message);
        $message = '';
        $colon = false;
        $color = false;
        // need a C for loop because sometime we need to look at the next element of the message
        for ($i = 0, $n = count($parts); $i < $n; $i++) {
            if (str_starts_with($parts[$i], SITE_URL)) {
                $offset = strlen(SITE_URL) + 1; // trailing slash
                $parts[$i] = '<a href="' . substr($parts[$i], $offset) . '">' . substr($parts[$i], $offset) . '</a>';
            }
            switch (strtolower($parts[$i])) {
                case 'artist':
                    $id = $parts[$i + 1];
                    if ((int)$id) {
                        $message .= ' ' . $parts[$i++] . " <a href=\"artist.php?id=$id\">$id</a>";
                    } else {
                        $message .= ' ' . $parts[$i];
                    }
                    break;
                case 'collage':
                    $id = $parts[$i + 1];
                    if ((int)$id) {
                        $message .= ' ' . $parts[$i] . " <a href=\"collages.php?id=$id\">$id</a>";
                        $i++;
                    } else {
                        $message .= " {$parts[$i]}";
                    }
                    break;
                case 'group':
                    $id = $parts[$i + 1];
                    if ((int)$id) {
                        $message .= ' ' . $parts[$i] . " <a href=\"torrents.php?id=$id\">$id</a>";
                    } else {
                        $message .= ' ' . $parts[$i];
                    }
                    $i++;
                    break;
                case 'request':
                    $id = $parts[$i + 1];
                    if ((int)$id) {
                        $message .= ' ' . $parts[$i++] . " <a href=\"requests.php?action=view&amp;id=$id\">$id</a>";
                    } else {
                        $message .= ' ' . $parts[$i];
                    }
                    break;
                case 'torrent':
                    $id = $parts[$i + 1];
                    if ((int)$id) {
                        $message .= ' ' . $parts[$i++] . " <a href=\"torrents.php?torrentid=$id\">$id</a>";
                    } else {
                        $message .= ' ' . $parts[$i];
                    }
                    break;
                case 'by':
                    $userId = 0;
                    $URL = '';
                    if ($parts[$i + 1] == 'user') {
                        $i++;
                        if ((int)($parts[$i + 1])) {
                            $userId = $parts[++$i];
                        }
                        $URL = "user $userId (<a href=\"user.php?id=$userId\">" . substr($parts[++$i], 1, -1) . '</a>)';
                    } elseif (in_array($parts[$i - 1], ['deleted', 'uploaded', 'edited', 'created', 'recovered'])) {
                        $username = $parts[++$i];
                        if (str_ends_with($username, ':')) {
                            $username = substr($username, 0, -1);
                            $colon = true;
                        }
                        $userId = $this->usernameLookup($username);
                        $URL = $userId ? "<a href=\"user.php?id=$userId\">$username</a>" . ($colon ? ':' : '') : $username;
                    }
                    $message .= " by $URL";
                    break;
                case 'uploaded':
                    if ($color === false) {
                        $color = 'forestgreen';
                    }
                    $message .= " {$parts[$i]}";
                    break;
                case 'deleted':
                    if ($color === false || $color === 'forestgreen') {
                        $color = 'crimson';
                    }
                    $message .= " {$parts[$i]}";
                    break;
                case 'edited':
                    if ($color === false) {
                        $color = 'royalblue';
                    }
                    $message .= " {$parts[$i]}";
                    break;
                case 'un-filled':
                    if ($color === false) {
                        $color = '';
                    }
                    $message .= " {$parts[$i]}";
                    break;
                case 'marked':
                    if ($i == 1) {
                        $username = $parts[$i - 1];
                        $userId = $this->usernameLookup($username);
                        $URL = $userId ? "<a href=\"user.php?id=$userId\">$username</a>" : $username;
                        $message = "$URL {$parts[$i]}";
                    } else {
                        $message .= " {$parts[$i]}";
                    }
                    break;
                default:
                    $message .= " {$parts[$i]}";
            }
        }
        return [$color, trim($message)]; // strip off the leading space
    }

    protected function usernameLookup(string $username): int|false {
        if (!isset($this->usernames[$username])) {
            $user = $this->userMan->findByUsername($username);
            $this->usernames[$username] = $user?->id() ?? false;
        }
        return $this->usernames[$username];
    }
}
