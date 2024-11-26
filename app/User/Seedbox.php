<?php

namespace Gazelle\User;

class Seedbox extends \Gazelle\BaseUser {
    final public const tableName = 'user_seedbox';
    protected const SUMMARY_KEY = 'seedbox_summary_';

    final public const VIEW_BY_NAME = 0;
    final public const VIEW_BY_PATH = 1;

    protected \Hashids\Hashids $hashid;
    protected array $host;
    protected array $free;
    protected bool $isUnion = false;
    protected int $source;
    protected int $target;
    protected int $viewBy = self::VIEW_BY_NAME;

    public function __construct(\Gazelle\User $user) {
        parent::__construct($user);
        $this->hashid = new \Hashids\Hashids(SEEDBOX_SALT);
        $this->build();
    }

    public function flush(): static {
        self::$cache->delete_value(self::SUMMARY_KEY . $this->user->id());
        unset($this->host);
        unset($this->free);
        return $this;
    }

    public function setUnion(bool $isUnion): static {
        $this->isUnion = $isUnion;
        return $this;
    }

    public function setSource(string $source): static {
        $this->source = $this->hashid->decode($source)[0];
        return $this;
    }

    public function setTarget(string $target): static {
        $this->target = $this->hashid->decode($target)[0];
        return $this;
    }

    public function setViewByName(): static {
        $this->viewBy = self::VIEW_BY_NAME;
        return $this;
    }

    public function setViewByPath(): static {
        $this->viewBy = self::VIEW_BY_PATH;
        return $this;
    }

    public function freeList(): array {
        if (!isset($this->free)) {
            $this->build();
        }
        return $this->free;
    }

    public function hostList(): array {
        if (!isset($this->host)) {
            $this->build();
        }
        return $this->host;
    }

    public function name(string $id): string {
        return (string)self::$db->scalar("
            SELECT name
            FROM user_seedbox
            WHERE user_id = ?
                AND user_seedbox_id = ?
            ", $this->user->id(), $this->hashid->decode($id)[0]
        );
    }

    public function viewBy(): int {
        return $this->viewBy;
    }

    protected function buildFrom(): string {
        $has = $this->isUnion ? 'IN' : 'NOT IN';
        return "FROM user_seedbox sx
            INNER JOIN xbt_files_users xfu ON (
                    xfu.ip = inet_ntoa(sx.ipaddr)
                AND xfu.useragent = sx.useragent
                AND xfu.active = 1
                AND xfu.ip != ''
                AND xfu.uid = ?
            )
            INNER JOIN torrents t ON (t.ID = xfu.fid)
            INNER JOIN torrents_group tg ON (tg.ID = t.GroupID)
            WHERE sx.user_seedbox_id = ?
                AND xfu.fid $has (
                    SELECT xfu2.fid
                    FROM user_seedbox sx2
                    INNER JOIN xbt_files_users xfu2 ON (
                            xfu2.ip = inet_ntoa(sx2.ipaddr)
                        AND xfu2.useragent = sx2.useragent
                        AND xfu2.active = 1
                        AND xfu2.uid = ?)
                    WHERE sx2.user_seedbox_id = ?
                )";
    }

    public function total(): int {
        return !(isset($this->source) && isset($this->target))
            ? 0
            : (int)self::$db->scalar("
                SELECT count(*) " . $this->buildFrom(),
                $this->user->id(), $this->source, $this->user->id(), $this->target
            );
    }

    /**
     * Get a page of torrents. Source and target must be set.
     */
    public function torrentList(\Gazelle\Manager\Torrent $torMan, int $limit, int $offset): array {
        $from = $this->buildFrom();
        $orderBy = ['tg.Name', 't.FilePath'][$this->viewBy];
        self::$db->prepared_query("
            SELECT xfu.fid, tg.Name, tg.Year, tg.RecordLabel,
                t.GroupID, tg.Name, t.FilePath, t.RemasterTitle, t.RemasterRecordLabel,
                t.Format, t.Encoding, t.Media, t.HasLog, t.HasLogDB, t.LogScore, t.HasCue, t.Scene
            $from
            ORDER BY $orderBy
            LIMIT ? OFFSET ?
            ", $this->user->id(), $this->source, $this->user->id(), $this->target,
                $limit, $offset
        );
        $info = self::$db->to_array('fid', MYSQLI_ASSOC, false);

        $list = [];
        foreach (array_map('intval', array_keys($info)) as $tid) {
            $torrent = $torMan->findById($tid);
            if (is_null($torrent)) {
                continue;
            }
            $tgroup = $torrent->group();
            $list[] = [
                'id'       => $tid,
                'folder'   => $torrent->path(),
                'sortname' => $tgroup->name(),
                'artist'   => $tgroup->hasArtistRole()
                    ? $tgroup->artistRole()->link()
                    : '&mdash;',
                'name'     => $torrent->fullLink(),
            ];
        }
        $key = $this->viewBy === self::VIEW_BY_NAME ? 'sortname' : 'folder';
        usort($list, fn ($x, $y) => $x[$key] <=> $y[$key] ?: $x['id'] <=> $y['id']);
        return $list;
    }

    /**
     * Get a list of all the torrent Ids in this comparison,
     * used to produce an archive of torrents
     */
    public function idList(): array {
        $from = $this->buildFrom();
        self::$db->prepared_query("
            SELECT xfu.fid
            $from
            ORDER BY xfu.fid
            ", $this->user->id(), $this->source, $this->user->id(), $this->target
        );
        return self::$db->collect(0, false);
    }

    /**
     * Update the names of the various seeding locations
     */
    public function updateNames(array $update): int {
        $n = 0;
        foreach ($update as $seedbox) {
            $name = $seedbox['name'];
            if ($name == '') {
                self::$db->scalar("
                    DELETE FROM user_seedbox
                    WHERE user_id = ?
                        AND user_seedbox_id = ?
                    ", $this->user->id(), $this->hashid->decode($seedbox['id'])[0]
                );
                $n += self::$db->affected_rows();
            } else {
                try {
                    self::$db->prepared_query("
                        UPDATE user_seedbox SET
                            name = ?
                        WHERE user_id = ?
                            AND user_seedbox_id = ?
                        ", mb_substr($name, 0, 100), $this->user->id(), $this->hashid->decode($seedbox['id'])[0]
                    );
                } catch (\Gazelle\DB\MysqlDuplicateKeyException) {
                    ; // do nothing
                } finally {
                    $n += self::$db->affected_rows();
                }
            }
        }
        if ($n) {
            $this->flush()->build();
        }
        return $n;
    }

    /**
     * Remove the names pointed to by a list of ids owned by the user
     *
     * @param array $remove list of encoded seedbox ids
     */
    public function removeNames(array $remove): int {
        if (empty ($remove)) {
            return 0;
        }
        $h = $this->hashid;
        self::$db->prepared_query("
            DELETE FROM user_seedbox
            WHERE user_id = ?
                AND user_seedbox_id in (" . placeholders($remove) . ")
            ", $this->user->id(), ...array_map(fn($id) => $h->decode($id)[0], $remove)
        );
        $affected = self::$db->affected_rows();
        $this->flush()->build();
        return $affected;
    }

    protected function build(): int {
        $key = self::SUMMARY_KEY . $this->user->id();
        // get the seeding locations and their totals
        $client = self::$cache->get_value($key);
        if ($client === false) {
            self::$db->prepared_query("
                SELECT concat(IP, '/', useragent) as client,
                    useragent,
                    IP as ipv4addr,
                    count(*) as total
                FROM xbt_files_users
                WHERE uid = ?
                GROUP BY IP, useragent
                ", $this->user->id()
            );
            $client = self::$db->to_array('client', MYSQLI_ASSOC, false);
            self::$cache->cache_value($key, $client, 3600);
        }
        // get the names the user has saved (no need to cache)
        self::$db->prepared_query("
            SELECT user_seedbox_id as id,
                concat(inet_ntoa(ipaddr), '/', useragent) AS client,
                inet_ntoa(ipaddr) AS ipv4addr,
                useragent,
                name
            FROM user_seedbox
            WHERE user_id = ?
            ", $this->user->id()
        );
        $nameList = self::$db->to_array('client', MYSQLI_ASSOC, false);
        $h = $this->hashid;
        foreach ($nameList as &$n) {
            $n['id'] = $h->encode($n['id']);
        }

        // go through all the peers and use a name if we have one,
        // otherwise fallback to ip/useragent as a name.
        $this->host = [];
        foreach ($client as $clientId => $seedbox) {
            $seedbox['sig'] = signature("{$seedbox['ipv4addr']}/{$seedbox['useragent']}", SEEDBOX_SALT);
            if (isset($nameList[$clientId])) {
                $seedbox['name'] = $nameList[$clientId]['name'];
                $seedbox['id'] = $nameList[$clientId]['id'];
                unset($nameList[$clientId]); // name in use
            } else {
                $seedbox['name'] = $seedbox['ipv4addr'] . '::' . $seedbox['useragent'];
                self::$db->prepared_query("
                    INSERT INTO user_seedbox
                           (user_id, name, useragent, ipaddr)
                    VALUES (?,       ?,    ?,         inet_aton(?))
                    ", $this->user->id(), mb_substr($seedbox['name'], 0, 100), $seedbox['useragent'], $seedbox['ipv4addr']
                );
                $seedbox['id'] = $this->hashid->encode(self::$db->inserted_id());
            }
            $this->host[$clientId] = $seedbox;
        }
        // TODO: sort $this->host by name

        // any names that didn't match a peer id may be ready for deletion (or merely off-line)
        $this->free = $nameList;
        return count($this->host);
    }
}
