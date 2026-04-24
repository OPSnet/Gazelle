<?php

namespace Gazelle\Torrent;

use Gazelle\Enum\TorrentFlag;
use Gazelle\Manager\Torrent as TorrentManager;
use Gazelle\Manager\Riplog  as RiplogManager;
use Gazelle\RiplogCollection;

class Report extends \Gazelle\BaseObject {
    final public const tableName = 'reportsv2';

    protected \Gazelle\Torrent|\Gazelle\TorrentDeleted|null $torrent;

    public function __construct(
        int $id,
        protected TorrentManager $torManager    = new TorrentManager(),
        protected RiplogManager  $riplogManager = new RiplogManager(),
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
        return $this->info ??= $this->pg()->rowAssocex(<<<END_SQL
            select r."ReporterID"  as reporter_id,
                r."ResolverID"     as resolver_id,
                r."TorrentID"      as torrent_id,
                r."Type"           as type,
                r."ModComment"     as comment,
                r."UserComment"    as reason,
                r."Status"         as status,
                r."ReportedTime"   as created,
                r."LastChangeTime" as modified,
                r."Track"          as track_list,
                r."Image"          as image,
                r."ExtraID"        as other_id,
                r."Link"           as external_link,
                r."LogMessage"     as message,
                trc.name
            from relay.reportsv2 r
            inner join torrent_report_configuration trc ON (trc.type = r."Type")
            where r."ID" = $1
            END_SQL,
                $this->id
        );
    }

    public function comment(): ?string {
        return $this->info()['comment'];
    }

    public function created(): \DateTimeImmutable {
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

    public function modified(): \DateTimeImmutable {
        return $this->info()['modified'];
    }

    public function name(): string {
        return $this->info()['name'];
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
                fn ($id) => $this->torManager->findById($id),
                $this->otherIdList(),
            ),
            fn ($t) => $t instanceof \Gazelle\Torrent
        );
    }

    public function otherRiplogCollection(): array {
        return array_map(
            fn ($other) => $this->riplogManager->collection($other),
            $this->otherTorrentList(),
        );
    }

    public function reason(): string {
        return $this->info()['reason'] ?? '-No reason given-';
    }

    public function reporterId(): int {
        return $this->info()['reporter_id'];
    }

    public function reportType(): \Gazelle\Torrent\ReportType {
        return new \Gazelle\Manager\Torrent\ReportType()->findByType($this->type());
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
    public function torrent(): \Gazelle\Torrent|\Gazelle\TorrentDeleted|null {
        if (!isset($this->torrent)) {
            $this->torrent = $this->torManager->findById($this->torrentId());
            if (is_null($this->torrent)) {
                $this->torrent = $this->torManager->findDeletedById($this->torrentId());
            }
        }
        return $this->torrent;
    }

    public function torrentId(): int {
        return $this->info()['torrent_id'];
    }

    public function torrentRiplogCollection(): RiplogCollection {
        return $this->riplogManager->collection($this->torrent());
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
        if ($this->torrent() === null || $this->torrent()->isDeleted()) {
            return 0;
        }
        $affected = $this->torrent()->addFlag($flag, $user);
        $this->torrent()->flush();
        return $affected;
    }

    public function claim(\Gazelle\User $user): int {
        self::$db->prepared_query("
            UPDATE reportsv2 SET
                LastChangeTime = now(),
                Status = 'InProgress',
                ResolverID = ?
            WHERE ID = ?
            ", $user->id, $this->id
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
            ", $user->id, $message, $this->id
        );
        $affected = self::$db->affected_rows();
        $this->torrent()?->flush();
        self::$cache->delete_multi([
            sprintf(\Gazelle\TorrentAbstract::CACHE_REPORTLIST, $this->torrentId()),
            'num_torrent_reportsv2',
        ]);
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
        $this->torrent()?->flush();
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
