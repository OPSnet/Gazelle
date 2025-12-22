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
        self::$db->prepared_query('
            INSERT INTO requests (
                LastVote, Visible, UserID, CategoryID, Title, Year, Image, Description, RecordLabel,
                CatalogueNumber, ReleaseType, BitrateList, FormatList, MediaList, LogCue, Checksum, OCLC, GroupID)
            VALUES (
                now(), 1, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            $user->id, $categoryId, $title, $year, $image, $description, $recordLabel,
            $catalogueNumber, $releaseType,
            $encoding->dbValue(), $format->dbValue(), $media->dbValue(),
            $logCue->dbValue(), $logCue->needLogChecksum ? 1 : 0, '', $groupId
        );
        $requestId = self::$db->inserted_id();
        $created   = (string)self::$db->scalar("
            SELECT created FROM requests WHERE ID = ?
            ", $requestId
        );
        $converter = $this->pg()->arrayConverter();
        $this->pg()->executeParams('
            insert into request (
                id_request, id_user, id_tgroup, id_category, id_release_type,
                year, created, last_vote, modified,
                description, title, image, catalogue_number, record_label,
                log_score, need_log, need_cue, need_checksum,
                artist_title_ts, tag, encoding_str, format_str, media_str
            ) values (
                $1, $2, $3, $4, $5,
                $6, $7, $8, $9,
                $10, $11, $12, $13, $14,
                $15, $16, $17, $18,
                to_tsvector(\'simple\', unaccent($19)), $20::int[],
                $21::text[], $22::text[], $23::text[]
            )',
            $requestId, $user->id, $groupId, $categoryId, $releaseType === 0 ? null : $releaseType, // 5
            $year === 0 ? null : $year, $created, $created, $created, // 9
            $description, $title,
            $image           === '' ? null : $image,
            $catalogueNumber === '' ? null : $catalogueNumber,
            $recordLabel     === '' ? null : $recordLabel, // 14
            $logCue->minScore,
            $logCue->needLog         ? 't' : 'f',
            $logCue->needCue         ? 't' : 'f',
            $logCue->needLogChecksum ? 't' : 'f', // 18
            $title, $converter->output($tagList), // 20
            $converter->output($encoding->dbList()),
            $converter->output($format->dbList()),
            $converter->output($media->dbList()), // 23
        );
        $requestVoteId = \Gazelle\Request::voteById($requestId, $user, $bounty);
        if (!$requestVoteId) {
            throw new \Exception("request($requestId) vote of $bounty by {$user->username()} failed");
        }
        $this->pg()->executeParams('
            merge into request_vote_summary rvs using (
                select id_request,
                    sum(bounty)             as bounty_total,
                    count(distinct id_user) as user_total,
                    max(created)            as last_vote
                from request_vote
                where id_request = $1
                group by id_request
            ) as i on rvs.id_request = i.id_request
                when not matched then
                    insert (  id_request,   bounty_total,   user_total,   last_vote)
                    values (i.id_request, i.bounty_total, i.user_total, i.last_vote)
                when matched then
                    update set
                        id_request   = i.id_request,
                        bounty_total = i.bounty_total,
                        user_total   = i.user_total,
                        last_vote    = i.last_vote
            ', $requestId
        );
        $request = new \Gazelle\Request($requestId);
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
                    from request                       r
                    inner join request_artist          ra  using (id_request)
                    inner join relay.artists_alias     aa  on    (ra.id_alias = aa."AliasID")
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

    public function relay(): int {
        return $this->pg()->query(<<<END_SQL
            merge into request r using (
                with a_t as (
                    select rr."ID" as id_request,
                        to_tsvector(
                            'simple',
                            unaccent(
                                coalesce(string_agg(aa."Name", ' '), '')
                                    || ' ' || rr."Title"
                            )
                        ) as artist_title_ts
                    from relay.requests rr
                    left join relay.requests_artists ra on    (ra."RequestID" = rr."ID")
                    left join relay.artists_alias    aa using ("AliasID")
                    left join relay.artist_role      ar on    (ar.artist_role_id = ra.artist_role_id)
                    where (ar.slug is null or ar.slug != 'guest')
                        and rr.updated >= (select coalesce(max(modified), '2000-01-01'::timestamptz) from request)
                    group by rr."ID", rr."Title"
                ),
                tl as (
                    select rt."RequestID" as id_request,
                        array_agg(rt."TagID") as tag
                    from relay.requests_tags rt
                    inner join relay.requests rr on (rr."ID" = rt."RequestID")
                    where rr.updated >= (select coalesce(max(modified), '2000-01-01'::timestamptz) from request)
                    group by rt."RequestID"
                )
                select
                    rr."ID" as id_request, rr."UserID" as id_user, nullif(rr."FillerID", 0) as id_filler,
                    nullif(rr."TorrentID", 0) as id_torrent, nullif(rr."GroupID", 0) as id_tgroup,
                    rr."CategoryID" as id_category, nullif(rr."ReleaseType", 0) as id_release_type,
                    nullif(rr."Year", 0) as year, rr."TimeFilled" as filled,
                    rr.created, rr.updated + '1 microsecond'::interval as updated,
                    rr."Description" as description, rr."Title" as title, nullif(rr."Image", '') as image,
                    nullif(rr."CatalogueNumber", '') as catalogue_number, nullif(rr."RecordLabel", '') as record_label,
                    (case when regexp_replace(coalesce(rr."LogCue", ''), '\D+', '', 'g') = ''
                        then '0'
                        else regexp_replace(coalesce(rr."LogCue", ''), '\D+', '', 'g')
                        end)::int as log_score,
                    coalesce(position('Log' in rr."LogCue") > 0, false) as need_log,
                    coalesce(position('Cue' in rr."LogCue") > 0, false) as need_cue,
                    case when rr."Checksum" = 0 then false else true end as need_checksum,
                    a_t.artist_title_ts, coalesce(tl.tag, ARRAY[]::int[]) as tag,
                    string_to_array(nullif(rr."BitrateList", ''), '|') as encoding_str,
                    string_to_array(nullif(rr."FormatList", ''), '|') as format_str,
                    string_to_array(nullif(rr."MediaList", ''), '|') as media_str
                from relay.requests rr
                inner join a_t on (a_t.id_request = rr."ID")
                left join tl on (tl.id_request = rr."ID")
            ) as i on r.id_request = i.id_request
                when not matched then
                    insert (
                        id_request, id_user, id_tgroup, id_category, id_release_type,
                        year, created, modified,
                        description, title, image, catalogue_number, record_label,
                        log_score, need_log, need_cue, need_checksum,
                        artist_title_ts, tag, encoding_str, format_str, media_str
                    ) values (
                        i.id_request, i.id_user, i.id_tgroup, i.id_category, i.id_release_type,
                        i.year, i.created, i.updated,
                        i.description, i.title, i.image, i.catalogue_number, i.record_label,
                        i.log_score, i.need_log, i.need_cue, i.need_checksum,
                        i.artist_title_ts,
                        i.tag,
                        i.encoding_str,
                        i.format_str,
                        i.media_str
                    )
                when matched then
                    update set
                        filled = i.filled, id_filler = i.id_filler, id_torrent = i.id_torrent,
                        id_user = i.id_user, id_tgroup = i.id_tgroup, id_category = i.id_category,
                        id_release_type = i.id_release_type, year = i.year,
                        created = i.created, modified = i.updated,
                        description = i.description, title = i.title, image = i.image,
                        catalogue_number = i.catalogue_number, record_label = i.record_label,
                        log_score = i.log_score, need_log = i.need_log,
                        need_cue = i.need_cue, need_checksum = i.need_checksum,
                        artist_title_ts = i.artist_title_ts, tag = i.tag,
                        encoding_str = i.encoding_str,
                        format_str = i.format_str,
                        media_str = i.media_str
            END_SQL
        );
    }
}
