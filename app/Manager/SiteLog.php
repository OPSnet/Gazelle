<?php

namespace Gazelle\Manager;

use Gazelle\TGroup;
use Gazelle\Util\Text;

class SiteLog extends \Gazelle\Base {
    final protected const CACHE_TERM  = 'audit_trail_site_';

    protected array $usernames  = [];

    public function __construct(
        // the User manager is only needed for the page() method, but
        // it is a major hassle to pass it in at that point and then
        // carry it down to the method that actually needs it.
        protected User $userMan = new User(),
    ) {}

    protected function configure(string $searchTerm): array {
        return $searchTerm === ''
            ? [
                'where'  => '',
                'args'   => [],
            ]
            : [
                'where'  => "where note_ts @@ websearch_to_tsquery('simple', $1)",
                'args'   => [$searchTerm],
            ];
    }

    public function total(string $searchTerm): int {
        if ($searchTerm === '') {
            return (int)$this->pg()->scalarex("
                select total from table_row_count where table_name = 'audit_trail_site'
            ");
        }
        $key   = self::CACHE_TERM . Text::base64UrlEncode($searchTerm);
        $total = self::$cache->get_value($key);
        if ($total === false) {
            $conf  = $this->configure($searchTerm);
            $total = (int)$this->pg()->scalarex("
                select count(*) from audit_trail_site {$conf['where']}
                ", ...$conf['args']
            );
            self::$cache->cache_value($key, $total, 3600);
        }
        return $total;
    }

    public function page(int $limit, int $offset, string $searchTerm): array {
        $conf = $this->configure($searchTerm);
        array_push($conf['args'], $limit, $offset);
        return $this->decorate(
            $this->pg()->allex("
                select id_audit_trail_site,
                    note,
                    created
                from audit_trail_site {$conf['where']}
                order by id_audit_trail_site desc
                limit {$this->pg()->placeholder(count($conf['args']) - 2)} offset {$this->pg()->placeholder(count($conf['args']) - 1)}
                ", ...$conf['args']
            )
        );
    }

    public function tgroupLogList(TGroup $tgroup): array {
        return $this->pg()->allex(<<<END_SQL
            with t as (
                select "ID"    as id_torrent,
                    "Media"    as media,
                    "Format"   as format,
                    "Encoding" as encoding
                from relay.torrents
                where "GroupID" = $1
            )
            select att.id_torrent,
                att.id_user,
                att.note,
                att.created,
                t.media,
                t.format,
                t.encoding,
                case when t.id_torrent is null then true else false end as deleted
            from audit_trail_tgroup att
            left join t using (id_torrent)
            where att.id_tgroup = $1
            order by att.id_audit_trail_tgroup desc
            END_SQL,
                $tgroup->id
        );
    }

    /**
     * Parse the log messages and decorate where applicable with links and class
     */
    public function decorate(array $in): array {
        $out = [];
        foreach ($in as $entry) {
            [$class, $message] = $this->colorize($entry['note']);
            $out[] = [
                'id'      => $entry['id_audit_trail_site'],
                'class'   => $class,
                'message' => $message,
                'created' => $entry['created'],
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
            $this->usernames[$username] = is_null($user) ? false : $user->id;
        }
        return $this->usernames[$username];
    }
}
