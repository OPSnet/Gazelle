<?php

namespace Gazelle;

class ReleaseType extends Base {
    protected const CACHE_KEY = 'release_type';

    protected array $list;

    public function __construct() {
        $list = self::$cache->get_value(self::CACHE_KEY);
        if ($list === false) {
            $qid = self::$db->get_query_id();
            self::$db->prepared_query("
                SELECT ID, Name FROM release_type ORDER BY ID
            ");
            $list = self::$db->to_pair('ID', 'Name', false);
            self::$db->set_query_id($qid);
            self::$cache->cache_value(self::CACHE_KEY, $list, 86400 * 30);
        }
        $this->list = $list;
    }

    public function list(): array {
        return $this->list;
    }

    public function extendedList(): array {
        $list = $this->list;
        $list[ARTIST_SECTION_ARRANGER] = 'Arrangement';
        $list[ARTIST_SECTION_PRODUCER] = 'Produced By';
        $list[ARTIST_SECTION_COMPOSER] = 'Compositions';
        $list[ARTIST_SECTION_REMIXER] = 'Remixed By';
        $list[ARTIST_SECTION_GUEST] = 'Guest Appearances';
        // very cumbersome to do it that way, but the LHS in [ARTIST_SECTION_COMPOSER => 'foo'] is stringified by the PHP tokenizer.
        return $list;
    }

    public function findIdByName(string $name): int {
        return (int)array_search($name, $this->list()) ?: (int)array_search('Unknown', $this->list());
    }

    public function findNameById(int $id): ?string {
        return $this->list[$id] ?? null;
    }

    public function findExtendedNameById(int $id): ?string {
        return $this->extendedList()[$id] ?? null;
    }

    public function sectionTitle(int $id): string {
        $title = $this->extendedList()[$id];
        return match ($title) {
            'Anthology' => 'Anthologies',
            'DJ Mix'    => 'DJ Mixes',
            'Remix'     => 'Remixes',
            'Compositions', 'Guest Appearances', 'Produced By', 'Remixed By'
                        => $title,
            default     => "{$title}s",
        };
    }
}
