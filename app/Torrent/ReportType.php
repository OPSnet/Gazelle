<?php

namespace Gazelle\Torrent;

class ReportType extends \Gazelle\BaseObject {
    final public const tableName = 'torrent_report_configuration';
    final public const pkName    = 'torrent_report_configuration_id';
    final public const CACHE_KEY = 'trepcfg_v2_%d';

    protected array $changeset;

    public function flush(): static {
        self::$cache->delete_value(sprintf(self::CACHE_KEY, $this->id));
        self::$cache->delete_value(sprintf(\Gazelle\Manager\Torrent\ReportType::ID_KEY, $this->id));
        self::$cache->delete_value(sprintf(\Gazelle\Manager\Torrent\ReportType::NAME_KEY, $this->id));
        self::$cache->delete_value(sprintf(\Gazelle\Manager\Torrent\ReportType::TYPE_KEY, $this->id));
        return $this;
    }

    public function link(): string {
        return '';
    }

    public function location(): string {
        return "tools.php?action=torrent_report_edit&id=" . $this->id;
    }

    // phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter.FoundInExtendedClass
    public function url(string|null $param = null): string {
        return htmlentities($this->location());
    }

    // phpcs:enable Generic.CodeAnalysis.UnusedFunctionParameter.FoundInExtendedClass

    public function info(): array {
        $key = sprintf(self::CACHE_KEY, $this->id);
        $info = self::$cache->get_value($key);
        if ($info === false) {
            $info = self::$db->rowAssoc("
                SELECT r.name,
                    r.type,
                    r.name,
                    r.category_id,
                    c.name AS category_name,
                    r.sequence,
                    r.tracker_reason,
                    r.is_active,
                    r.is_admin,
                    r.is_invisible,
                    r.need_image,
                    r.need_link,
                    r.need_sitelink,
                    r.need_track,
                    r.resolve_delete,
                    r.resolve_log,
                    r.resolve_upload,
                    r.resolve_warn,
                    r.explanation,
                    r.pm_body
                FROM torrent_report_configuration r
                INNER JOIN category c USING (category_id)
                WHERE r.torrent_report_configuration_id = ?
                ", $this->id
            );
            $info['is_active']      = (bool)$info['is_active'];
            $info['is_admin']       = (bool)$info['is_admin'];
            $info['resolve_delete'] = (bool)$info['resolve_delete'];
            $info['resolve_upload'] = (bool)$info['resolve_upload'];
            self::$cache->cache_value($key, $info, 0);
            $this->info = $info;
        }
        return $info;
    }

    public function field(string $field): bool|int|string|null {
        return $this->info()[$field];
    }

    public function history(): array {
        self::$db->prepared_query("
            SELECT change_set AS change_set_json,
                user_id,
                created
            FROM torrent_report_configuration_log
            WHERE torrent_report_configuration_id = ?
            ORDER BY created DESC
            ", $this->id()
        );
        $history = [];
        foreach (self::$db->to_array(false, MYSQLI_ASSOC, false) as $row) {
            $row['change_set'] = json_decode($row['change_set_json'], true);
            $history[] = $row;
        }
        return $history;
    }

    public function categoryName(): string {
        return $this->info()['category_name'];
    }

    public function categoryId(): int {
        return $this->info()['category_id'];
    }

    public function doDeleteUpload(): bool {
        return $this->info()['resolve_delete'];
    }

    public function doRevokeUploadPrivs(): bool {
        return $this->info()['resolve_upload'];
    }

    public function explanation(): string {
        return $this->info()['explanation'];
    }

    public function isActive(): bool {
        return $this->info()['is_active'];
    }

    public function isAdmin(): bool {
        return $this->info()['is_admin'];
    }

    public function isInvisible(): bool {
        return $this->info()['is_invisible'];
    }

    public function name(): string {
        return $this->info()['name'];
    }

    public function needImage(): string {
        return $this->info()['need_image'];
    }

    public function needImageDefault(): string {
        return $this->enumDefault(static::tableName, 'need_image');
    }

    public function needImageList(): array {
        return $this->enumList(static::tableName, 'need_image');
    }

    public function needLink(): string {
        return $this->info()['need_link'];
    }

    public function needLinkDefault(): string {
        return $this->enumDefault(static::tableName, 'need_link');
    }

    public function needLinkList(): array {
        return $this->enumList(static::tableName, 'need_link');
    }

    public function needSitelink(): string {
        return $this->info()['need_sitelink'];
    }

    public function needSitelinkDefault(): string {
        return $this->enumDefault(static::tableName, 'need_sitelink');
    }

    public function needSitelinkList(): array {
        return $this->enumList(static::tableName, 'need_sitelink');
    }

    public function needTrack(): string {
        return $this->info()['need_track'];
    }

    public function needTrackDefault(): string {
        return $this->enumDefault(static::tableName, 'need_track');
    }

    public function needTrackList(): array {
        return $this->enumList(static::tableName, 'need_track');
    }

    public function pmBody(): ?string {
        return $this->info()['pm_body'];
    }

    public function trackerReason(): int {
        return $this->info()['tracker_reason'];
    }

    public function resolveLog(): ?string {
        return $this->info()['resolve_log'];
    }

    public function resolveOptions(): array {
        return [
            $this->doDeleteUpload(),
            $this->doRevokeUploadPrivs(),
            $this->warnWeeks(),
        ];
    }

    public function sequence(): int {
        return $this->info()['sequence'];
    }

    public function type(): string {
        return $this->info()['type'];
    }

    public function warnWeeks(): int {
        return $this->info()['resolve_warn'];
    }

    public function setChangeset(\Gazelle\User $user, array $changeset): static {
        $this->changeset = [$user->id(), $changeset];
        return $this;
    }

    public function modify(): bool {
        [$userId, $changeset] = $this->changeset;
        foreach ($changeset as $c) {
            $this->setField($c['field'], $c['new']);
        }
        unset($this->changeset);
        $affected = parent::modify();
        if ($affected) {
            self::$db->prepared_query("
                INSERT INTO torrent_report_configuration_log
                       (user_id, change_set, torrent_report_configuration_id)
                VALUES (?,       ?,          ?)
                ", $userId, json_encode($changeset), $this->id
            );
        }
        return $affected && self::$db->affected_rows() === 1;
    }
}
