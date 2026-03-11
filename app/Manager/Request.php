<?php

namespace Gazelle\Manager;

use Gazelle\Request\AbstractValue;
use Gazelle\Request\Encoding;
use Gazelle\Request\Format;
use Gazelle\Request\Media;
use Gazelle\Request\LogCue;
use Gazelle\User;

class Request extends \Gazelle\BaseManager {
    final public const ID_KEY = 'zz_r_%d';

    public function create(
        User     $user,
        int      $bounty,
        int      $categoryId,
        int      $year,
        string   $title,
        ?string  $image,
        string   $description,
        string   $recordLabel,
        string   $catalogueNumber,
        int      $releaseType,
        Encoding $encoding,
        Format   $format,
        Media    $media,
        LogCue   $logCue,
        array    $tagList = [],
        int|null $groupId = null,
    ): \Gazelle\Request {
        $intConverter    = $this->pg()->integerConverter();
        $stringConverter = $this->pg()->arrayConverter();
        try {
            $this->pg()->begin();
            $result = $this->pg()->executeParams(<<<END_SQL
                insert into request (
                    id_user, id_tgroup, id_category, id_release_type, year,
                    description, title, image, catalogue_number, record_label,
                    log_score, need_log, need_cue, need_checksum,
                    artist_title_ts, tag, encoding_str, format_str, media_str,
                    last_vote
                ) values (
                    $1, $2, $3, $4, $5,
                    $6, $7, $8, $9, $10,
                    $11, $12, $13, $14,
                    to_tsvector('simple', unaccent($15)), $16::int[],
                    $17::text[], $18::text[], $19::text[],
                    now()
                )
                returning id_request
                END_SQL
                , $user->id, $groupId, $categoryId, $releaseType === 0 ? null : $releaseType,
                $year === 0 ? null : $year, // 5
                $description, $title,
                $image           === '' ? null : $image,
                $catalogueNumber === '' ? null : $catalogueNumber,
                $recordLabel     === '' ? null : $recordLabel, // 10
                $logCue->minScore,
                $logCue->needLog         ? 't' : 'f',
                $logCue->needCue         ? 't' : 'f',
                $logCue->needLogChecksum ? 't' : 'f', // 14
                $title, $intConverter->output($tagList), // 16
                $stringConverter->output($encoding->dbList()),
                $stringConverter->output($format->dbList()),
                $stringConverter->output($media->dbList()), // 19
            );
            $requestId = $result->current()['id_request'];
            $request   = new \Gazelle\Request($requestId);
            if (!$request->vote($user, $bounty)) {
                throw new \RangeException(
                    "Cannot create request {$requestId} by {$user->label()}: bounty $bounty exceeds upload {$user->uploadedSize()}"
                );
            }
            $this->pg()->commit();
        } catch (\Exception | \Error $e) {
            $this->pg()->rollback();
            throw $e;
        }
        $request->artistFlush();
        return $request;
    }

    public function findById(int $id): ?\Gazelle\Request {
        $key = sprintf(self::ID_KEY, $id);
        $requestId = self::$cache->get_value($key);
        if ($requestId === false) {
            $requestId = (int)$this->pg()->scalarex("
                select id_request from request where id_request = $1
                ", $id
            );
            if ($requestId) {
                self::$cache->cache_value($key, $requestId, 7200);
            }
        }
        return $requestId ? new \Gazelle\Request($requestId) : null;
    }

    /**
     * Find a list of unfilled requests by a user, sorted
     * by most number of votes and then largest bounty
     *
     * @return array of \Gazelle\Request objects
     */
    public function findUnfilledByUser(User $user, int $limit): array {
        return array_map(
            fn ($id) => $this->findById($id),
            $this->pg()->columnex("
                select r.id_request
                from request r
                inner join request_vote_summary rvs using (id_request)
                where r.id_torrent is null
                    and r.id_user = $1
                order by rvs.user_total desc, rvs.bounty_total desc, rvs.last_vote desc
                limit $2 offset 0
                ", $user->id, $limit
            )
        );
    }

    public function findByArtist(\Gazelle\Artist $artist): array {
        $key = sprintf(\Gazelle\Artist::CACHE_REQUEST_ARTIST, $artist->id);
        $requestList = self::$cache->get_value($key);
        if ($requestList === false) {
            $requestList = $this->pg()->columnex(<<<END_SQL
                with r(id_request) as (
                    select r.id_request
                    from request                   r
                    inner join request_artist      ra using (id_request)
                    inner join relay.artists_alias aa on    (ra.id_alias = aa."AliasID")
                    where r.id_torrent is null
                        and aa."ArtistID" = $1
                    group by r.id_request
                )
                select r.id_request
                from r
                inner join request_vote_summary rvs using (id_request)
                order by rvs.user_total desc, rvs.bounty_total desc, rvs.last_vote desc
                END_SQL
                , $artist->id
            );
            self::$cache->cache_value($key, $requestList, 3600);
        }
        return array_map(fn ($id) => $this->findById($id), $requestList);
    }

    public function findByTGroup(\Gazelle\TGroup $tgroup): array {
        $key = sprintf(\Gazelle\TGroup::CACHE_REQUEST_TGROUP, $tgroup->id);
        $requestList = self::$cache->get_value($key);
        if ($requestList === false) {
            $requestList = $this->pg()->columnex(<<<END_SQL
                select r.id_request
                from request as r
                inner join relay.torrents_group tg ON (tg."ID" = r.id_tgroup)
                where r.id_torrent is null
                    and tg."ID" = $1
                order by r.created
                END_SQL
                , $tgroup->id
            );
            self::$cache->cache_value($key, $requestList, 3600);
        }
        return array_map(fn ($id) => $this->findById($id), $requestList);
    }

    public function findByTorrentReported(\Gazelle\TorrentAbstract $torrent): array {
        return array_map(
            fn ($id) => $this->findById($id),
            $this->pg()->columnex(<<<END_SQL
                select distinct req.id_request
                from request               req
                inner join relay.reportsv2 rep on (rep."TorrentID" = req.id_torrent)
                where rep."Status" != 'Resolved'
                    and req.id_torrent = $1
                END_SQL
                , $torrent->id
            )
        );
    }
}
