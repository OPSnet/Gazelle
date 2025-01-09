<?php

namespace Gazelle\Manager;

class SiteLog extends \Gazelle\Base {
    use \Gazelle\Pg;

    final protected const CACHE_TERM  = 'site_log_';

    protected array $usernames  = [];

    public function __construct(
        // the User manager is only needed for the page() method, but
        // it is a major hassle to pass it in at that point and then
        // carry it down to the method that actually needs it.
        protected \Gazelle\Manager\User $userMan,
    ) {}

    protected function configure(string $searchTerm): array {
        return $searchTerm === ''
            ? [
                'where'  => '',
                'args'   => [],
            ]
            : [
                'where'  => "where note_ts @@ websearch_to_tsquery('simple', ?)",
                'args'   => [$searchTerm],
            ];
    }

    public function total(string $searchTerm): int {
        if ($searchTerm === '') {
            return (int)$this->pg()->scalar("
                select total from table_row_count where table_name = 'site_log'
            ");
        }
        $key   = self::CACHE_TERM . \Gazelle\Util\Text::base64UrlEncode($searchTerm);
        $total = self::$cache->get_value($key);
        if ($total === false) {
            $conf  = $this->configure($searchTerm);
            $total = (int)$this->pg()->scalar("
                select count(*) from site_log {$conf['where']}
                ", ...$conf['args']
            );
            self::$cache->cache_value($key, $total, 3600);
        }
        return $total;
    }

    public function page(int $limit, int $offset, string $searchTerm): array {
        $conf = $this->configure($searchTerm);
        $st   = $this->pg()->pdo()->prepare("
            select id_site_log,
                note,
                created
            from site_log {$conf['where']}
            order by id_site_log desc
            limit ? offset ?
        ");
        array_push($conf['args'], $limit, $offset);

        if ($st === false || !$st->execute($conf['args'])) {
            return [];
        }
        return $this->decorate(
            $st->fetchAll(\PDO::FETCH_NUM)
        );
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
     * Parse the log messages and decorate where applicable with links and class
     */
    public function decorate(array $in): array {
        $out = [];
        foreach ($in as [$id, $message, $created]) {
            [$class, $message] = $this->colorize($message);
            $out[] = [
                'id'      => $id,
                'class'   => $class,
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
        $class = false;
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
                    if ($class === false) {
                        $class = 'log-uploaded';
                    }
                    $message .= " {$parts[$i]}";
                    break;
                case 'deleted':
                    if ($class === false || $class === 'log-uploaded') {
                        $class = 'log-removed';
                    }
                    $message .= " {$parts[$i]}";
                    break;
                case 'edited':
                    if ($class === false) {
                        $class = 'log-edited';
                    }
                    $message .= " {$parts[$i]}";
                    break;
                case 'un-filled':
                    if ($class === false) {
                        $class = '';
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
        return [$class, trim($message)]; // strip off the leading space
    }

    protected function usernameLookup(string $username): int|false {
        if (!isset($this->usernames[$username])) {
            $user = $this->userMan->findByUsername($username);
            $this->usernames[$username] = $user?->id() ?? false;
        }
        return $this->usernames[$username];
    }

    /**
     * Relay records from Mysql to Postgres
     */
    public function relay(): int {
        $total  = 0;
        $insert = $this->pg()->prepare('
            insert into site_log
                (id_site_log, created, note)
            select "ID",
                "Time",
                "Message"
            FROM relay.log
            WHERE "ID" > ?
            ORDER BY "ID"
            LIMIT 1000
        ');

        while (true) {
            $insert->execute([
                (int)$this->pg()->scalar("
                    select max(id_site_log) from site_log
                ")
            ]);
            $relayed = $insert->rowCount();
            if ($relayed === 0) {
                break;
            }
            $total += $relayed;
        }
        return $total;
    }
}
