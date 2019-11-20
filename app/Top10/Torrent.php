<?php

namespace Gazelle\Top10;

class Torrent {
    /** @var \DB_MYSQL */
    private $db;

    /** @var \CACHE */
    private $cache;

    /** @var Array */
    private $formats;

    private $currentUser;

    public function __construct (\DB_MYSQL $db, \CACHE $cache, Array $formats, $currentUser) {
        $this->db = $db;
        $this->cache = $cache;
        $this->formats = $formats;
        $this->currentUser = $currentUser;
    }

    function getTopTorrents($getParameters, $details = 'all', $limit = 10) {
        $cacheKey = 'top10_' . $details . '_' . md5(implode($getParameters,'')) . '_' . $limit;
        $topTorrents = $this->cache->get_value($cacheKey);

        if ($topTorrents !== false) return $topTorrents;
        if ($this->cache->get_query_lock('top10')) return false;

        $where = [];
        if (isset($getParameters['tags'])) $where[] = $this->tagWhere($getParameters['tags']);
        if (isset($getParameters['format'])) $where[] = $this->formatWhere($getParameters['format']);
        if (isset($getParameters['freeleech'])) $where[] = $this->freeleechWhere($getParameters['freeleech']);
        if (isset($getParameters['details'])) $where[] = $this->detailsWhere($details);
        # TODO, CHANGE TO > 0
        $where[] = ["parameters" => null, "where" => "tls.Seeders >= 0"];
        
        $whereFilter = function($value){ return $value["where"]; };
        $parameterFilter = function($value){ return $value["parameters"]; };
        $filteredWhere = array_filter(array_map($whereFilter, $where));
        $parameters = array_filter(array_map($parameterFilter, $where), 'strlen');
        
        $query = $this->baseQuery . ' WHERE ' . implode(" AND ", $filteredWhere);
        $query = $query . (isset($getParameters['groups']) && $getParameters['groups'] == 'show' ? ' GROUP BY g.ID ' : '');
        $query = $query . ' ORDER BY ' . $this->orderBy($details);
        $query = $query . " LIMIT $limit";

        $this->db->prepared_query($query, ...$parameters);
        $topTorrents = $this->db->to_array();

        $this->cache->cache_value($cacheKey, $topTorrents, 3600 * 6);
        $this->cache->clear_query_lock('top10');
        return $topTorrents;
    }

    function showFreeleechTorrents($freeleechParameters) {
        if (isset($freeleechParameters)) {
            return $freeleechParameters == 'hide' ? 1 : 0;
        } else if (isset($this->currentUser['DisableFreeTorrentTop10'])) {
           return $this->currentUser['DisableFreeTorrentTop10'];
        } else {
            return 0;
        }
    }

    private function orderBy($details) {
        switch($details) {
            case 'snatched':
                return 'tls.Snatched';
                break;
            case 'seeded':
                return 'tls.Seeders';
                break;
            case 'data':
                return 'Data';
                break;
            default:
                return '(tls.Seeders + tls.Leechers)';
                break;

        }
    }

    private function detailsWhere($detailsParameters) {
        if (isset($detailsParameters)) {
            switch($detailsParameters) {
                case 'day':
                    return ["parameters" => null, "where" => "t.Time > now() - INTERVAL 1 DAY"];
                    break;
                case 'week':
                    return ["parameters" => null, "where" => "t.Time > now() - INTERVAL 1 WEEK"];
                    break;
                case 'month':
                    return ["parameters" => null, "where" => "t.Time > now() - INTERVAL 1 MONTH"];
                    break;
                case 'year':
                    return ["parameters" => null, "where" => "t.Time > now() - INTERVAL 1 YEAR"];
                    break;
                default:
                    return [];
                    break;
            }
        }
        return [];
    }

    private function formatWhere($formatParameters) {
        if (isset($formatParameters)) {
            if (in_array($formatParameters, $this->formats)) {
                return ["parameters" => $formatParameters, "where" => "t.Format = '?'"];
            }
        }

        return [];
    }

    private function freeleechWhere($freeleechParameters) {
        $disableFreeTorrentTop10 = isset($this->currentUser['DisableFreeTorrentTop10']) ? $this->currentUser['DisableFreeTorrentTop10'] : 0;

        if (isset($freeleechParameters)) {
            $disableFreeTorrentTop10 = ($freeleechParameters == 'hide' ? 1 : 0);
        }

        if ($disableFreeTorrentTop10) {
            return ["parameters" => null, "where" => "t.FreeTorrent = 0"];
        }

        return [];
    }

    private function tagWhere($tagParameter) {
        if (isset($tagParameter) && !empty($tagParameter)) {
            $tags = explode(',', str_replace('.', '_', trim($tagParameter)));
            $replace = function($tag) { return preg_replace('/[^a-z0-9_]/', '', $tag); };
            $tags = array_map($replace, $tags);
            $tags = array_filter($tags);

            $whereKeyword = $tagParameter['anyall'] == 'any' ? 'OR' : 'AND';
            $filler = array_fill(0,  count($tags), "g.TagList REGEXP '[[:<:]]?[[:>:]]'");
            $where = '(' . implode(" $whereKeyword ", $filler) . ')';

            return ["parameters" => $tags, "where" => $where];
        }

        return [];
    }

    private $baseQuery = '
        SELECT
            t.ID,
            g.ID,
            g.Name,
            g.CategoryID,
            g.wikiImage,
            g.TagList,
            t.Format,
            t.Encoding,
            t.Media,
            t.Scene,
            t.HasLog,
            t.HasCue,
            t.HasLogDB,
            t.LogScore,
            t.LogChecksum,
            t.RemasterYear,
            g.Year,
            t.RemasterTitle,
            tls.Snatched,
            tls.Seeders,
            tls.Leechers,
            ((t.Size * tls.Snatched) + (t.Size * 0.5 * tls.Leechers)) AS Data,
            g.ReleaseType,
            t.Size
        FROM torrents AS t
        INNER JOIN torrents_leech_stats tls ON (tls.TorrentID = t.ID)
        INNER JOIN torrents_group AS g ON (g.ID = t.GroupID)';
}
