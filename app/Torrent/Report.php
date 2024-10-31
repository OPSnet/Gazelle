<?php

namespace Gazelle\Torrent;

use Gazelle\Enum\TorrentFlag;

class Report extends \Gazelle\BaseObject {
    final public const tableName = 'reportsv2';

    protected \Gazelle\TorrentAbstract|null|false $torrent = false;

    public function __construct(
        int $id,
        protected \Gazelle\Manager\Torrent $torMan,
    ) {
        parent::__construct($id);
    }

    public function flush(): static {
        unset($this->info);
        return $this;
    }

    public function link(): string {
        return sprintf('<a href="%s">Report #%d</a>', $this->url(), $this->id());
    }

    public function location(): string {
        return "reportsv2.php?view=report&id=" . $this->id;
    }

    public function info(): array {
        return $this->info ??= self::$db->rowAssoc("
            SELECT ReporterID  AS reporter_id,
                ResolverID     AS resolver_id,
                TorrentID      AS torrent_id,
                Type           AS type,
                ModComment     AS comment,
                UserComment    AS reason,
                Status         AS status,
                ReportedTime   AS created,
                LastChangeTime AS modified,
                Track          AS track_list,
                Image          AS image,
                ExtraID        AS other_id,
                Link           AS external_link,
                LogMessage     AS message
            FROM reportsv2
            WHERE ID = ?
            ", $this->id
        );
    }

    public function comment(): ?string {
        return $this->info()['comment'];
    }

    public function created(): string {
        return $this->info()['created'];
    }

    public function externalLink(): array {
        $list = $this->info()['external_link'];
        if (is_null($list) || $list === '') {
            return [];
        }
        return preg_split('/\s+/', $list) ?: [];
    }

    public function image(): array {
        $list = $this->info()['image'];
        if (is_null($list) || $list === '') {
            return [];
        }
        return preg_split('/\s+/', $list) ?: [];
    }

    public function message(): ?string {
        return $this->info()['message'];
    }

    public function modified(): string {
        return $this->info()['modified'];
    }

    public function otherIdList(): array {
        $otherList = $this->info()['other_id'];
        if (is_null($otherList) || $otherList === '') {
            return [];
        }
        $list = preg_split('/\s+/', $otherList);
        return $list ? array_map('intval', $list) : [];
    }

    public function otherTorrentList(): array {
        return array_filter(
            array_map(
                fn($id) => $this->torMan->findById($id),
                $this->otherIdList()
            ),
            fn($t) => $t instanceof \Gazelle\Torrent
        );
    }

    public function reason(): string {
        return $this->info()['reason'] ?? '-No reason given-';
    }

    public function reporterId(): int {
        return $this->info()['reporter_id'];
    }

    public function reportType(): \Gazelle\Torrent\ReportType {
        return (new \Gazelle\Manager\Torrent\ReportType())->findByType($this->type());
    }

    public function resolverId(): int {
        return $this->info()['resolver_id'];
    }

    public function status(): string {
        return $this->info()['status'];
    }

    /**
     * Note: the torrent may no longer exist, e.g. resolving a report may delete
     * the torrent (even if the report knows the torrent id). This is why the
     * code must start from false and transition to either NULL or a Torrent.
     */
    public function torrent(): ?\Gazelle\TorrentAbstract {
        if ($this->torrent === false) {
            $this->torrent = $this->torMan->findById($this->torrentId());
            if (is_null($this->torrent)) {
                $this->torrent = $this->torMan->findDeletedById($this->torrentId());
            }
        }
        return $this->torrent;
    }

    public function torrentId(): int {
        return $this->info()['torrent_id'];
    }

    public function trackList(): array {
        $trackList = $this->info()['track_list'];
        if (is_null($trackList) || $trackList === '') {
            return [];
        }
        $list = preg_split('/\D+/', $trackList);
        return $list ? array_map('intval', $list) : [];
    }

    public function type(): string {
        return $this->info()['type'];
    }

    public function addTorrentFlag(TorrentFlag $flag, \Gazelle\User $user): int {
        if ($this->torrent === false) {
            return 0;
        }
        $affected = $this->torrent->addFlag($flag, $user);
        $this->torrent->flush();
        return $affected;
    }

    public function claim(\Gazelle\User $user): int {
        self::$db->prepared_query("
            UPDATE reportsv2 SET
                LastChangeTime = now(),
                Status = 'InProgress',
                ResolverID = ?
            WHERE ID = ?
            ", $user->id(), $this->id
        );
        $affected = self::$db->affected_rows();
        $this->flush();
        return $affected;
    }

    /**
     * Unclaim a report (make it new and clear the resolver)
     *
     * @return int 1 if unresolved, 0 if nothing changed and -1 if the ID does not match a report
     */
    public function unclaim(): int {
        if (self::$db->scalar("SELECT 1 FROM reportsv2 WHERE ID = ?", $this->id)) {
            self::$db->prepared_query("
                UPDATE reportsv2 SET
                    LastChangeTime = now(),
                    Status = 'New',
                    ResolverID = 0
                WHERE ResolverID != 0 AND ID = ?
                ", $this->id
            );
            return self::$db->affected_rows();
        }
        return -1;
    }

    public function resolve(string $message): int {
        self::$db->prepared_query("
            UPDATE reportsv2 SET
                Status = 'Resolved',
                LastChangeTime = now(),
                ModComment = ?
            WHERE ID = ?
            ", $message, $this->id
        );
        $affected = self::$db->affected_rows();
        self::$cache->decrement('num_torrent_reportsv2');
        $this->flush();
        return $affected;
    }

    public function moderatorResolve(\Gazelle\User $user, string $message): int {
        self::$db->prepared_query("
            UPDATE reportsv2 SET
                Status = 'Resolved',
                LastChangeTime = now(),
                ResolverID = ?,
                ModComment = ?
            WHERE Status != 'Resolved'
                AND ID = ?
            ", $user->id(), $message, $this->id
        );
        $affected = self::$db->affected_rows();
        if ($this->torrent) {
            $this->torrent->flush();
        }
        self::$cache->delete_value(sprintf(\Gazelle\TorrentAbstract::CACHE_REPORTLIST, $this->torrentId()));
        $this->flush();
        return $affected;
    }

    /**
     * Finalize a report: log the final details post-resolve
     */
    public function finalize(string $log, string $message): int {
        self::$db->prepared_query("
            UPDATE reportsv2 SET
                LastChangeTime = now(),
                LogMessage = ?,
                ModComment = ?
            WHERE ID = ?
            ", $log, $message, $this->id
        );
        $affected = self::$db->affected_rows();
        if ($this->torrent) {
            $this->torrent->flush();
        }
        self::$cache->delete_value(sprintf(\Gazelle\TorrentAbstract::CACHE_REPORTLIST, $this->torrentId()));
        self::$cache->decrement('num_torrent_reportsv2');
        $this->flush();
        return $affected;
    }

    public function changeType(\Gazelle\Torrent\ReportType $rt): int {
        self::$db->prepared_query("
            UPDATE reportsv2 SET
                LastChangeTime = now(),
                Type = ?
            WHERE ID = ?
            ", $rt->type(), $this->id
        );
        return self::$db->affected_rows();
    }

    /**
     * Update the comment of a report
     */
    public function modifyComment(string $comment): int {
        self::$db->prepared_query("
            UPDATE reportsv2 SET
                LastChangeTime = now(),
                ModComment = ?
            WHERE ID = ?
            ", trim($comment), $this->id
        );
        $affected = self::$db->affected_rows();
        $this->flush();
        return $affected;
    }
}
