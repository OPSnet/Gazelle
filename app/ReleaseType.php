<?php

namespace Gazelle;

class ReleaseType extends Base {
    protected const CACHE_KEY = 'release_type';

    /** @var array */
    protected $list;

    public function __construct() {
        if (($this->list = self::$cache->get_value(self::CACHE_KEY)) === false) {
            $qid = self::$db->get_query_id();
            self::$db->prepared_query("
                SELECT ID, Name FROM release_type ORDER BY ID
            ");
            $this->list = self::$db->to_pair('ID', 'Name');
            self::$db->get_query_id($qid);
            self::$cache->cache_value(self::CACHE_KEY, $this->list, 86400 * 30);
        }
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

    public function findIdByName(string $name) {
        return array_search($name, $this->list) ?: array_search('Unknown', $this->list);
    }

    public function findNameById(int $id) {
        return $this->list[$id] ?? null;
    }

    public function findExtendedNameById(int $id) {
        return $this->extendedList()[$id] ?? null;
    }

    public function sectionTitle(int $id): string {
        $title = $this->extendedList()[$id];
        switch ($title) {
            case 'Anthology':
                return 'Anthologies';
            case 'DJ Mix':
                return 'DJ Mixes';
            case 'Remix':
                return 'Remixes';
            case 'Compositions':
            case 'Guest Appearances':
            case 'Produced By':
            case 'Remixed By':
                return $title;
            default:
                return "{$title}s";
        }
    }
}
