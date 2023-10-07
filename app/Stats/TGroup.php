<?php

namespace Gazelle\Stats;

class TGroup extends \Gazelle\BaseObject {
    /**
     * This class offloads all the counting operations you might
     * want to do with a TGroup.
     */

    final const tableName         = 'tgroup_summary';
    protected const CACHE_GENERAL = 'tg_stat_%d';

    // Cache the underlying db calls
    protected array|null $info;

    public function flush(): static {
        unset($this->info);
        self::$cache->delete_value(sprintf(self::CACHE_GENERAL, $this->id));
        return $this;
    }
    public function link(): string { return sprintf('<a href="%s">%s</a>', $this->url(), 'Stats'); }
    public function location(): string { return 'torrents.php?id=' . $this->id; }

    /**
     * @see \Gazelle\Stats\TGroups::refresh()
     */
    public function info(): array {
        if (isset($this->info)) {
            return $this->info;
        }
        $key = sprintf(self::CACHE_GENERAL, $this->id);
        $info = self::$cache->get_value($key);
        if ($info === false) {
            $info = self::$db->rowAssoc("
                SELECT bookmark_total,
                    download_total,
                    leech_total,
                    seeding_total,
                    snatch_total
                FROM tgroup_summary
                WHERE tgroup_id = ?
                ", $this->id
            ) ?? [
                'bookmark_total'   => 0,
                'download_total'   => 0,
                'leech_total'      => 0,
                'seeding_total'    => 0,
                'snatch_total'     => 0,
            ];
        }
        $this->info = $info;
        return $this->info;
    }

    /**
     * Some statistics can be updated immediately, such as download_total.
     * Others, like download_unique need a possibly expensive check.
     * In any case, those stats will be updated within the hour.
     * If we can update immediately, though, we can do it here.
     */
    public function increment(string $name, int $incr = 1): int {
        self::$db->prepared_query("
            UPDATE tgroup_summary SET
                $name = $name + ?
            WHERE tgroup_id = ?
            ", $incr, $this->id
        );
        $affected = self::$db->affected_rows();
        $this->flush();
        return $affected;
    }

    public function bookmarkTotal(): int {
        return $this->info()['bookmark_total'];
    }

    public function downloadTotal(): int {
        return $this->info()['download_total'];
    }

    public function leechTotal(): int {
        return $this->info()['leech_total'];
    }

    public function seedingTotal(): int {
        return $this->info()['seeding_total'];
    }

    public function snatchTotal(): int {
        return $this->info()['snatch_total'];
    }
}
