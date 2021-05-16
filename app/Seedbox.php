<?php

namespace Gazelle;

class Seedbox extends Base {

    /** @var int */
    protected $userId;

    /** @var \Hashids\Hashids */
    protected $hashid;

    /** @var array */
    protected $host;

    /** @var array */
    protected $free;

    /** @var bool */
    protected $isUnion;

    /** @var int */
    protected $source;

    /** @var int */
    protected $target;

    /** @var int */
    protected $viewBy;

    protected const SUMMARY_KEY = 'seedbox_summary_';

    public const VIEW_BY_NAME = 0;
    public const VIEW_BY_PATH = 1;

    public function __construct(int $userId) {
        parent::__construct();
        $this->userId = $userId;
        $this->hashid = new \Hashids\Hashids(SEEDBOX_SALT);
        $this->build();
        $this->viewBy = self::VIEW_BY_NAME;
    }

    public function viewBy() {
        return $this->viewBy;
    }

    public function setUnion(bool $isUnion) {
        $this->isUnion = $isUnion;
        return $this;
    }

    public function setSource(string $source) {
        $this->source = $this->hashid->decode($source)[0];
        return $this;
    }

    public function setTarget(string $target) {
        $this->target = $this->hashid->decode($target)[0];
        return $this;
    }

    public function setViewByName() {
        $this->viewBy = self::VIEW_BY_NAME;
        return $this;
    }

    public function setViewByPath() {
        $this->viewBy = self::VIEW_BY_PATH;
        return $this;
    }

    public function hostList() { return $this->host; }
    public function freeList() { return $this->free; }

    public function name(string $id): ?string {
        return $this->db->scalar("
            SELECT name
            FROM user_seedbox
            WHERE user_id = ?
                AND user_seedbox_id = ?
            ", $this->userId, $this->hashid->decode($id)[0]
        );
    }

    /**
     * Generate a signature of the useragent and IP address.
     *
     * The useragent and ip address are posted to a client-side form
     * When reading the response to match useragent and IP address to a name,
     * we need to ensure the information was not altered.
     *
     * @param string ipv4addr
     * @param string useragent
     * @return string base64 SHA2556 digest
     */
    public function signature(string $ipv4addr, string $useragent): string {
        return base64_encode(hash('sha256', implode('/', [$ipv4addr, $useragent, SEEDBOX_SALT]), true));
    }

    protected function buildFrom(): string {
        $has = $this->isUnion ? 'IN' : 'NOT IN';
        return "FROM user_seedbox sx
            INNER JOIN xbt_files_users xfu ON (
                    xfu.ip = inet_ntoa(sx.ipaddr)
                AND xfu.useragent = sx.useragent
                AND xfu.active = 1
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
        return $this->db->scalar("
            SELECT count(*) " . $this->buildFrom(),
            $this->userId, $this->source, $this->userId, $this->target
        );
    }

    /**
     * Get a page of torrents. Source and target must be set.
     *
     * @param Gazelle\Util\Paginator to help figure out where we are
     * @param Gazelle\Manager\Torrent to display to release details
     * @param Gazelle\Manager\TorrentLAbel to decorate the release details
     * @return array list of torrent IDs
     */
    public function torrentList(int $limit, int $offset, Manager\Torrent $torMan, Manager\TorrentLabel $labelMan): array {
        $from = $this->buildFrom();
        $orderBy = ['tg.Name', 't.FilePath'][$this->viewBy];
        $this->db->prepared_query("
            SELECT xfu.fid, tg.Name, tg.Year, tg.RecordLabel,
                t.GroupID, tg.Name, t.FilePath, t.RemasterTitle, t.RemasterRecordLabel,
                t.Format, t.Encoding, t.Media, t.HasLog, t.HasLogDB, t.LogScore, t.HasCue, t.Scene
            $from
            ORDER BY $orderBy
            LIMIT ? OFFSET ?
            ", $this->userId, $this->source, $this->userId, $this->target,
                $limit, $offset
        );
        $info = $this->db->to_array('fid', MYSQLI_ASSOC, false);

        $list = [];
        foreach ($info as $tid => $details) {
            $labelMan->load($details);
            $list[] = [
                'id' => $tid,
                'folder' => $details['FilePath'],
                'sortname' => $details['Name'],
                'artist' => $torMan->findById($tid)->group()->artistHtml(),
                'name' => sprintf('<a href="torrents.php?id=%d&amp;torrentid=%d">%s</a> (%s) [%s]',
                    $details['GroupID'],
                    $tid,
                    $details['Name'],
                    $labelMan->edition(),
                    $labelMan->label()
                ),
            ];
        }
        if ($this->viewBy === self::VIEW_BY_NAME) {
            usort($list, function ($x, $y) {
                return $x['sortname'] === $y['sortname']
                    ? $x['id'] <=> $y['id']
                    : $x['sortname'] > $y['sortname'];
            });
        } else {
            usort($list, function ($x, $y) {
                return $x['folder'] === $y['folder']
                    ? $x['id'] <=> $y['id']
                    : $x['folder'] > $y['folder'];
            });
        }
        return $list;
    }

    /**
     * Get a list of all the torrent Ids in this comparison,
     * used to produce an archive of torrents
     *
     * @return array of torrentIds
     */
    public function idList() {
        $from = $this->buildFrom();
        $this->db->prepared_query("
            SELECT xfu.fid
            $from
            ORDER BY xfu.fid
            ", $this->userId, $this->source, $this->userId, $this->target
        );
        return $this->db->collect(0, false);
    }

    /**
     * Update the names of the various seeding locations
     *
     * @param array $update Associate array of 'ipv4' => 'name'
     * @param int Number of rows changed.
     */
    public function updateNames(array $update) {
        $n = 0;
        $hostlist = $this->hostList();
        foreach ($update as $seedbox) {
            $name = $seedbox['name'];
            if ($name == '') {
                $this->db->scalar("
                    DELETE FROM user_seedbox
                    WHERE user_id = ?
                        AND user_seedbox_id = ?
                    ", $this->userId, $this->hashid->decode($seedbox['id'])[0]
                );
                $n += $this->db->affected_rows();
            } else {
                try {
                    $this->db->prepared_query($sql = "
                        UPDATE user_seedbox SET
                            name = ?
                        WHERE user_id = ?
                            AND user_seedbox_id = ?
                        ", $name, $this->userId, $this->hashid->decode($seedbox['id'])[0]
                    );
                } catch (\DB_MYSQL_DuplicateKeyException $e) {
                    // do nothing
                } finally {
                    $n += $this->db->affected_rows();
                }
            }
        }
        if ($n) {
            $this->flushCache()->build();
        }
        return $n;
    }

    /**
     * Remove the names pointed to by a list of ids owned by the user
     *
     * @param array $remove Associate array of 'ipv4' => 'name'
     * @return int Number of rows removed
     */
    public function removeNames(array $remove): int {
        if (empty ($remove)) {
            return 0;
        }
        $h = $this->hashid;
        $this->db->prepared_query("
            DELETE FROM user_seedbox
            WHERE user_id = ?
                AND user_seedbox_id in (" . placeholders($remove) . ")
            ", $this->userId, ...array_map(function ($id) use ($h) {return $h->decode($id)[0];}, $remove)
        );
        $n = $this->db->affected_rows();
        $this->flushCache()->build();
        return $n;
    }

    protected function flushCache() {
        $this->cache->delete_value(self::SUMMARY_KEY . $this->userId);
        return $this;
    }

    protected function build() {
        $key = self::SUMMARY_KEY . $this->userId;
        // get the seeding locations and their totals
        if (($client = $this->cache->get_value($key)) === false) {
            $this->db->prepared_query("
                SELECT concat(IP, '/', useragent) as client,
                    useragent,
                    IP as ipv4addr,
                    count(*) as total
                FROM xbt_files_users
                WHERE uid = ?
                GROUP BY IP, useragent
                ", $this->userId
            );
            $client = $this->db->to_array('client', MYSQLI_ASSOC);
            $this->cache->cache_value($key, $client, 3600);
        }
        // get the names the user has saved (no need to cache)
        $this->db->prepared_query("
            SELECT user_seedbox_id as id,
                concat(inet_ntoa(ipaddr), '/', useragent) AS client,
                inet_ntoa(ipaddr) AS ipv4addr,
                useragent,
                name
            FROM user_seedbox
            WHERE user_id = ?
            ", $this->userId
        );
        $h = $this->hashid;
        $nameList = array_map(function ($a) use ($h) {
                $b = $a; $b['id'] = $h->encode($b['id']); return $b;
            }, $this->db->to_array('client', MYSQLI_ASSOC));

        // go through all the peers and use a name if we have one,
        // otherwise fallback to ip/useragent as a name.
        $this->host = [];
        foreach ($client as $clientId => $seedbox) {
            $seedbox['sig'] = $this->signature($seedbox['ipv4addr'], $seedbox['useragent']);
            if (!isset($nameList[$clientId])) {
                $seedbox['name'] = $seedbox['ipv4addr'] . '::' . $seedbox['useragent'];
                $this->db->prepared_query("
                    INSERT INTO user_seedbox
                           (user_id, name, useragent, ipaddr)
                    VALUES (?,       ?,    ?,         inet_aton(?))
                    ", $this->userId, $seedbox['name'], $seedbox['useragent'], $seedbox['ipv4addr']
                );
                $seedbox['id'] = $this->hashid->encode($this->db->inserted_id());
            } else {
                $seedbox['name'] = $nameList[$clientId]['name'];
                $seedbox['id'] = $nameList[$clientId]['id'];
                unset($nameList[$clientId]); // name in use
            }
            $this->host[$clientId] = $seedbox;
        }
        // TODO: sort $this->host by name

        // any names that didn't match a peer id may be ready for deletion (or merely off-line)
        $this->free = $nameList;
    }
}
