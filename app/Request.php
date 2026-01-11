<?php

namespace Gazelle;

use Gazelle\Enum\UserAuditEvent;
use Gazelle\Intf\Bookmarked;
use Gazelle\Intf\CategoryHasArtist;

class Request extends BaseObject implements Bookmarked, CategoryHasArtist {
    final public const tableName  = 'requests';

    public function flush(): static {
        if (isset($this->info) && $this->tgroupId()) {
            self::$cache->delete_value("requests_group_" . $this->tgroupId());
        }
        unset($this->info);
        return $this;
    }

    public function link(): string {
        return sprintf('<a href="%s">%s</a>', $this->url(), display_str($this->title()));
    }

    public function location(): string {
        return 'requests.php?action=view&id=' . $this->id;
    }

    public function bookmarkTable(): string {
        return 'bookmark_request';
    }

    public function bookmarkColumnName(): string {
        return 'id_request';
    }

    /**
     * Display a title on the request page itself. If there are artists in the name,
     * they will be linkified, and the request title itself will not
     */
    public function selfLink(): string {
        $title = display_str($this->title());
        return match ($this->categoryName()) {
            'Music' =>
                "{$this->artistRole()->link()} – "
                . ($this->isFilled()
                    ? "<a href=\"torrents.php?torrentid={$this->torrentId()}\" dir=\"ltr\">$title</a>"
                    : $title
                )
                . " [{$this->year()}]",

            'Audiobooks', 'Comedy' => $this->isFilled()
                ? "<a href=\"torrents.php?torrentid={$this->torrentId()}\" dir=\"ltr\">$title</a> [{$this->year()}]"
                : "$title [{$this->year()}]",

            default => $this->isFilled()
                ? "<a href=\"torrents.php?torrentid={$this->torrentId()}\" dir=\"ltr\">$title</a>"
                : $title,
        };
    }

    /**
     * Display the title of a request, with all fields linkified where it makes sense.
     */
    public function smartLink(): string {
        return match ($this->categoryName()) {
            'Music'                => "{$this->artistRole()->link()} – {$this->link()} [{$this->year()}]",
            'Audiobooks', 'Comedy' => "{$this->link()} [{$this->year()}]",
            default                => $this->link(),
        };
    }

    /**
     * Display the full title of the request with no links.
     */
    public function text(): string {
        return match ($this->categoryName()) {
            'Music'       => "{$this->artistRole()->text()} – {$this->title()} [{$this->year()}]",
            'Audiobooks',
            'Comedy'      => "{$this->title()} [{$this->year()}]",
            default       => $this->title(),
        };
    }

    public function artistFlush(): int {
        $this->flush();
        $artistList = $this->pg()->columnex(<<<END_SQL
            select aa."ArtistID"
            from request_artist ra
            inner join relay.artists_alias aa on (aa."AliasID" = ra.id_alias)
            where ra.id_request = $1
            END_SQL
            , $this->id
        );
        if ($artistList !== []) {
            self::$cache->delete_multi([
                ...array_map(fn ($id) => "artists_requests_$id", $artistList),
            ]);
        }
        return count($artistList);
    }

    public function artistRole(): ?ArtistRole\Request {
        if ($this->categoryName() !== 'Music') {
            return null;
        }
        return new ArtistRole\Request($this, new Manager\Artist());
    }

    public function hasArtistRole(): bool {
        return $this->artistRole() instanceof ArtistRole\Request;
    }

    public function info(): array {
        if (isset($this->info)) {
            return $this->info;
        }
        $info = $this->pg()->rowAssocex("
            select r.id_user  as user_id,
                r.id_filler   as filler_id,
                r.id_tgroup   as tgroup_id,
                r.id_torrent  as torrent_id,
                r.created,
                r.modified,
                r.filled      as fill_date,
                rvs.last_vote as last_vote_date,
                r.id_category as category_id,
                c.name        as category_name,
                r.title,
                r.description,
                r.year,
                r.image,
                r.catalogue_number,
                r.record_label,
                r.id_release_type as release_type,
                coalesce(rel.name, 'Unknown')
                                  as release_type_name,
                r.encoding_str    as need_encoding,
                r.format_str      as need_format,
                r.media_str       as need_media,
                r.need_cue,
                r.need_log,
                r.need_checksum,
                r.log_score,
                r.url
            from request                    r
            inner join request_vote_summary rvs using (id_request)
            inner join category             c   using (id_category)
            left join release_type          rel using (id_release_type)
            where r.id_request = $1
            ", $this->id
        );
        $info['user_vote_list'] = $this->pg()->allex("
            select rv.id_user as user_id,
                sum(rv.bounty) as bounty
            from request_vote rv
            where rv.id_request = $1
            group by id_user
            order by 2 desc
            ", $this->id
        );
        $info['tag'] = $this->pg()->columnex(<<<END_SQL
            with rt(id_tag) as (
                select unnest(tag)
                from request
                where id_request = $1
            )
            select t."Name" as name
            from rt
            inner join relay.tags t on (t."ID" = rt.id_tag)
            END_SQL
            , $this->id
        );

        $info['encoding'] = new Request\Encoding(
            is_null($info['need_encoding']),
            $info['need_encoding'] ?? [],
        );
        $info['format'] = new Request\Format(
            is_null($info['need_format']),
            $info['need_format'] ?? [],
        );
        $info['media'] = new Request\Media(
            is_null($info['need_media']),
            $info['need_media'] ?? [],
        );

        $info['logCue'] = new Request\LogCue(
            needLogChecksum: (bool)$info['need_checksum'],
            needCue:         (bool)$info['need_cue'],
            needLog:         (bool)$info['need_log'],
            minScore:        (int)$info['log_score'],
        );

        $this->info = $info;
        return $this->info;
    }

    /**
     * These fields are shared between the request and requests ajax endpoints
     */
    public function ajaxInfo(): array {
        $info = $this->info();
        return [
            'requestId'       => $this->id,
            'requestorId'     => $info['user_id'],
            'timeAdded'       => $this->created(),
            'voteCount'       => $this->userVotedTotal(),
            'lastVote'        => $this->lastVoteDate(),
            'totalBounty'     => $this->bountyTotal(),
            'categoryId'      => $info['category_id'],
            'categoryName'    => $info['category_name'],
            'title'           => $info['title'],
            'year'            => (int)$info['year'],
            'image'           => (string)$info['image'],
            'bbDescription'   => $info['description'],
            'description'     => \Text::full_format($info['description']),
            'catalogueNumber' => $info['catalogue_number'],
            'recordLabel'     => $info['record_label'],
            'releaseType'     => $info['release_type'],
            'releaseTypeName' => $info['release_type_name'],
            'bitrateList'     => array_values($this->currentEncoding()),
            'formatList'      => array_values($this->currentFormat()),
            'mediaList'       => array_values($this->currentMedia()),
            'needCue'         => $this->needCue(),
            'needLog'         => $this->needLog(),
            'needLogChecksum' => $this->needLogChecksum(),
            'minLogScore'     => $this->needLogScore(),
            'isFilled'        => $info['torrent_id'] > 0,
            'fillerId'        => (int)$info['filler_id'],
            'torrentId'       => (int)$info['torrent_id'],
            'timeFilled'      => $this->fillDate(),
            'tags'            => $this->tagNameList(),
        ];
    }

    public function bountyTotal(): int {
        return (int)array_sum(array_column($this->userIdVoteList(), 'bounty'));
    }

    public function canEditOwn(User $user): bool {
        return !$this->isFilled() && $user->id == $this->userId() && $this->userVotedTotal() < 2;
    }

    public function canEdit(User $user): bool {
        return $this->canEditOwn($user) || $user->permittedAny('site_moderate_requests', 'site_edit_requests');
    }

    public function canVote(User $user): bool {
        return !$this->isFilled() && $user->permitted('site_vote');
    }

    public function catalogueNumber(): string|null {
        return $this->info()['catalogue_number'];
    }

    public function categoryId(): int {
        return $this->info()['category_id'];
    }

    public function categoryName(): string {
        return $this->info()['category_name'];
    }

    public function categoryImage(): string {
        return STATIC_SERVER . "/common/noartwork/" . CATEGORY_ICON[$this->categoryId() - 1];
    }

    public function created(): string {
        // pg_wrapper returns DateTimeImmutable objects
        return $this->info()['created']->format('Y-m-d H:i:s');
    }

    public function currentEncoding(): array {
        return $this->needEncoding('Any')
            ? ENCODING
            : array_intersect(ENCODING, $this->needEncodingList());
    }

    public function currentFormat(): array {
        return $this->needFormat('Any')
            ? FORMAT
            : array_intersect(FORMAT, $this->needFormatList());
    }

    public function currentMedia(): array {
        return $this->needMedia('Any')
            ? MEDIA
            : array_intersect(MEDIA, $this->needMediaList());
    }

    public function description(): string {
        return $this->info()['description'];
    }

    public function encoding(): Request\Encoding {
        return $this->info()['encoding'];
    }

    public function format(): Request\Format {
        return $this->info()['format'];
    }

    public function media(): Request\Media {
        return $this->info()['media'];
    }

    public function descriptionEncoding(): ?string {
        return $this->info()['need_encoding'] === 'Unknown'
            ? null
            : $this->encoding()->display();
    }

    public function descriptionFormat(): ?string {
        return $this->info()['need_format'] === 'Unknown'
            ? null
            : $this->format()->display();
    }

    public function descriptionLogCue(): ?string {
        return $this->logCue()->dbValue();
    }

    public function descriptionMedia(): ?string {
        return $this->info()['need_media'] === 'Unknown'
            ? null
            : $this->media()->display();
    }

    public function fillerId(): int|null {
        return $this->info()['filler_id'];
    }

    public function fillDate(): string|null {
        return is_null($this->info()['fill_date'])
            ? null
            : $this->info()['fill_date']->format('Y-m-d H:i:s');
    }

    public function hasNewVote(): bool {
        return strtotime($this->lastVoteDate()) > strtotime($this->created());
    }

    public function isFilled(): bool {
        return (bool)$this->info()['filler_id'];
    }

    public function image(): ?string {
        return $this->info()['image'];
    }

    public function lastVoteDate(): string {
        return $this->info()['last_vote_date']->format('Y-m-d H:i:s');
    }

    public function legacyFormatList(): string {
        return $this->format()->dbValue();
    }

    public function legacyEncodingList(): string {
        return $this->encoding()->dbValue();
    }

    public function legacyLogChecksum(): string {
        return $this->logCue()->needLogChecksum ? '1' : '0';
    }

    public function legacyMediaList(): string {
        return $this->media()->dbValue();
    }

    public function modified(): string {
        return $this->info()['modified'];
    }

    public function logCue(): Request\LogCue {
        return $this->info()['logCue'];
    }

    public function needCue(): bool {
        return $this->logCue()->needCue;
    }

    public function needEncoding(string $encoding): bool {
        if ($this->needMediaList() === ['']) {
            return true;
        }
        return in_array($encoding, $this->needEncodingList());
    }

    public function needEncodingList(): array {
        return $this->info()['need_encoding'] ?? [''];
    }

    public function needFormat(string $format): bool {
        if ($this->needMediaList() === ['']) {
            return true;
        }
        return in_array($format, $this->needFormatList());
    }

    public function needFormatList(): array {
        return $this->info()['need_format'] ?? [''];
    }

    public function needLog(): bool {
        return $this->logCue()->needLog;
    }

    public function needLogChecksum(): bool {
        return $this->logCue()->needLogChecksum;
    }

    public function needLogScore(): int {
        return $this->logCue()->minScore;
    }

    public function needMedia(string $media): bool {
        if ($this->needMediaList() === ['']) {
            return true;
        }
        return in_array($media, $this->needMediaList());
    }

    public function needMediaList(): array {
        return $this->info()['need_media'] ?? [''];
    }

    public function recordLabel(): ?string {
        return $this->info()['record_label'];
    }

    public function releaseTypeName(): string {
        return $this->info()['release_type_name'];
    }

    public function releaseType(): int {
        return $this->info()['release_type'];
    }

    public function tagLinkList(): string {
        return implode(' ',
            array_map(
                fn($tag) => "<a href=\"requests.php?tags=$tag\">$tag</a>",
                $this->tagNameList()
            )
        );
    }

    public function tagNameList(): array {
        return $this->info()['tag'];
    }

    public function tgroupId(): ?int {
        return $this->info()['tgroup_id'];
    }

    public function title(): string {
        return $this->info()['title'];
    }

    public function torrentId(): int|null {
        return $this->info()['torrent_id'];
    }

    public function userId(): int {
        return $this->info()['user_id'];
    }

    public function urlencodeArtist(): string {
        return  urlencode(str_replace(
            ['arranged by ', 'performed by '],
            ['', ''],
            $this->artistRole()?->text() ?? ''
        ));
    }

    public function urlencodeTitle(): string {
        return urlencode(trim(preg_replace("/\([^\)]+\)/", '', $this->title())));
    }

    public function userIdVoteList(): array {
        return $this->info()['user_vote_list'];
    }

    public function userVoteList(Manager\User $manager): array {
        $list = $this->userIdVoteList();
        foreach ($list as &$user) {
            $user['user'] = $manager->findById($user['user_id']);
        }
        unset($user);
        return $list;
    }

    public function userVotedTotal(): int {
        return count($this->userIdVoteList());
    }

    public function year(): int {
        return (int)$this->info()['year'];
    }

    public function validate(Torrent $torrent, User $filler, bool $isAdmin): array {
        if (
            $torrent->isUploadGracePeriod()
            && $torrent->uploaderId() !== $filler->id
            && !$isAdmin
        ) {
            return [
                "There is a one hour grace period for new uploads to allow the uploader ("
                . $torrent->uploader()->username() . ") to fill the request."
            ];
        }

        $error = [];
        if ($this->torrentId()) {
            $error[] = "This request has already been filled.";
        }
        if (!in_array($this->categoryId(), [0, $torrent->group()->categoryId()])) {
            $error[] = "This torrent is of a different category than the request. If the request is actually miscategorized, please contact staff.";
        }

        if ($torrent->group()->categoryName() === 'Music') {
            if (!$this->media()->exists($torrent->media())) {
                $error[] = "{$torrent->media()} is not an allowed media for this request.";
            }
            if (!$this->format()->exists($torrent->format())) {
                $error[] = "{$torrent->format()} is not an allowed format for this request.";
            }
            if (
                $this->descriptionEncoding() !== "Other"
                && !$this->encoding()->exists($torrent->encoding())
            ) {
                $error[] = "{$torrent->encoding()} is not an allowed encoding for this request.";
            }

            if (
                $this->media()->exists("CD") && $torrent->media() === "CD"
                && $this->format()->exists("FLAC") && $torrent->format() === "FLAC"
            ) {
                if ($this->logCue()->needCue && !$torrent->hasCue()) {
                    $error[] = "This request requires a cue file.";
                }
                if ($this->logCue()->needLog) {
                    if (!$torrent->hasLogDb()) {
                        $error[] = "This request requires a valid logfile and none was uploaded with this torrent";
                    } elseif ($this->logCue()->needLogChecksum && !$torrent->logChecksum()) {
                        $error[] = "This request requires a logfile with a valid checksum";
                    } else {
                        $logScore = $this->logCue()->minScore;
                        if ($logScore > 0 && $logScore > $torrent->logScore()) {
                            $better = $logScore === 100 ? "$logScore%" : "$logScore% or better";
                            $error[] = "This request requires a logfile with a score of $better";
                        }
                    }
                }
            }
        }

        return $error;
    }

    /**
     * Vote on a request (transfer upload buffer from user to a request.
     *
     * return @bool vote was successful (user had sufficient buffer)
     */
    public function vote(User $user, int $amount): bool {
        return (bool)static::voteById($this->id, $user, $amount);
    }

    /* temporary scaffolding while the move to Pg is underway */
    public static function voteById(int $requestId, User $user, int $amount): int {
        self::$db->begin_transaction();

        self::$db->prepared_query("
            UPDATE users_leech_stats SET
                Uploaded = Uploaded - ?
            WHERE Uploaded - ? >= 0
                AND UserID = ?
            ", $amount, $amount, $user->id
        );
        if (self::$db->affected_rows() == 0) {
            // Uploaded would turn negative
            self::$db->rollback();
            return 0;
        }

        $bounty = $amount * (1 - REQUEST_TAX);
        self::$db->prepared_query("
            INSERT INTO requests_votes
                   (RequestID, UserID, Bounty)
            VALUES (?,         ?,      ?)
            ", $requestId, $user->id, $bounty
        );
        $requestVoteId = self::$db->inserted_id();
        self::pgStatic()->executeParams('
            insert into request_vote
                   (id_request_vote, id_request, id_user, bounty)
            values ($1,              $2,         $3,      $4)
            ', $requestVoteId, $requestId, $user->id, $bounty
        );
        self::pgStatic()->executeParams('
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
        self::$db->prepared_query("
            UPDATE requests SET
                LastVote = now()
            WHERE ID = ?
            ", $requestId
        );
        self::$db->prepared_query("
            INSERT INTO user_summary (user_id, request_vote_size, request_vote_total)
                SELECT rv.UserID,
                    sum(rv.Bounty) AS size,
                    count(*) AS total
                FROM requests_votes rv
                INNER JOIN requests r ON (r.ID = rv.RequestID)
                WHERE rv.UserID != r.FillerID
                    AND rv.UserID = ?
                GROUP BY rv.UserID
            ON DUPLICATE KEY UPDATE
                request_vote_size = VALUES(request_vote_size),
                request_vote_total = VALUES(request_vote_total)
            ", $user->id
        );
        self::$db->commit();

        $user->flush();

        return $requestVoteId;
    }

    /**
     * get all individual votes on this request
     */
    public function voteList(): array {
        self::$db->prepared_query("
            SELECT UserID AS user_id,
                Bounty    AS bounty,
                created
            FROM requests_votes
            WHERE RequestID = ?
            ORDER BY created DESC, requests_votes_id DESC
            ", $this->id
        );
        return self::$db->to_array(false, MYSQLI_ASSOC);
    }

    public function fill(User $user, Torrent $torrent): int {
        self::$db->prepared_query("
            UPDATE requests SET
                TimeFilled = now(),
                FillerID = ?,
                TorrentID = ?
            WHERE ID = ?
                AND FillerID = 0
            ", $user->id, $torrent->id, $this->id
        );
        $updated = self::$db->affected_rows();
        if (!$updated) {
            return 0;
        }
        $now = (string)self::$db->scalar('
            SELECT updated FROM requests WHERE ID = ?
            ', $this->id
        );
        $this->pg()->query("
            update request set
                filled     = $1,
                id_filler  = $2,
                id_torrent = $3
            where id_request = $4
            ", $now, $user->id, $torrent->id, $this->id
        );

        $bounty = $this->bountyTotal();
        $user->addBounty($bounty);
        $name = $this->title();
        $message = "One of your requests — [url={$this->location()}]{$name}[/url] — has been filled. You can view it here: [pl]{$torrent->id}[/pl]";
        self::$db->prepared_query("
            SELECT DISTINCT UserID FROM requests_votes WHERE RequestID = ?
            ", $this->id
        );
        foreach (self::$db->collect(0) as $userId) {
            new User($userId)->inbox()->createSystem("The request \"$name\" has been filled", $message);
        }

        $this->logger()->general(
            "Request {$this->id} ($name) was filled by user {$user->label()} with the torrent {$torrent->id} for a "
            . byte_format($bounty) . ' bounty.'
        );

        $this->flush();
        $this->artistFlush();
        return $updated;
    }

    public function unfill(User $admin, string $reason, Manager\Torrent $torMan): int {
        $bounty = $this->bountyTotal();
        $filler = new User($this->fillerId());
        $torrent = $torMan->findById($this->torrentId());
        if (is_null($torrent)) {
            $torrent = $torMan->findDeletedById($this->torrentId());
        }

        self::$db->begin_transaction();
        self::$db->prepared_query("
            UPDATE requests SET
                TorrentID = 0,
                FillerID = 0,
                TimeFilled = null,
                Visible = 1
            WHERE ID = ?
            ", $this->id
        );
        $updated = self::$db->affected_rows();
        if (!$updated) {
            self::$db->rollback();
            return 0;
        }
        $this->pg()->query("
            update request set
                filled     = null,
                id_filler  = null,
                id_torrent = null
            where id_request = $1
            ", $this->id
        );
        $filler->addBounty(-$bounty);
        $filler->flush();
        self::$db->commit();

        if ($filler->id !== $admin->id) {
            $filler->inbox()->createSystem(
                'A request you filled has been unfilled',
                self::$twig->render('request/unfill-pm.bbcode.twig', [
                    'name'    => $torrent->group()->text(),
                    'reason'  => $reason,
                    'request' => $this,
                    'viewer'  => $admin,
                ])
            );
        }

        $this->logger()->general(
            "Request {$this->id} ({$this->title()}), with a " . byte_format($bounty)
            . " bounty, was unfilled by user {$admin->label()} for the reason: $reason"
        );

        $this->artistFlush();
        return $updated;
    }

    /**
     * Get the bounty of request, by user
     *
     * @return array keyed by user ID
     */
    public function bounty(): array {
        $votes = [];
        foreach ($this->userIdVoteList() as $vote) {
            $votes[$vote['user_id']] = ['UserID' => $vote['user_id'], 'Bounty' => $vote['bounty']];
        }
        return $votes;
    }

    /**
     * Get the total bounty that a user has added to a request
     */
    public function userBounty(User $user): int {
        $vote = array_filter($this->userIdVoteList(), fn($r) => $r['user_id'] == $user->id);
        return count($vote) ? current($vote)['bounty'] : 0;
    }

    /**
     * Refund the bounty of a user on a request
     */
    public function refundBounty(User $user, User $admin): int {
        $bounty = $this->userBounty($user);
        self::$db->begin_transaction();
        self::$db->prepared_query("
            DELETE FROM requests_votes
            WHERE RequestID = ? AND UserID = ?
            ", $this->id, $user->id
        );
        $affected = self::$db->affected_rows();
        $this->pg()->executeParams('
            delete from request vote
            where id_request = $1
                and id_user  = $2
            ', $this->id, $user->id
        );
        if ($affected) {
            $this->informRequestFillerReduction($bounty, $admin);
            $user->auditTrail()->addEvent(
                UserAuditEvent::request,
                "Refund of " . byte_format($bounty)
                    . " bounty ({$bounty}b) on {$this->url()}",
                $admin,
            );
            self::$db->prepared_query("
                UPDATE users_leech_stats SET
                    Uploaded = Uploaded + ?
                WHERE UserId = ?
                ", $bounty, $user->id
            );
            $user->flush();
        }
        self::$db->commit();
        return $affected;
    }

    /**
     * Remove the bounty of a user on a request
     */
    public function removeBounty(User $user, User $admin): int {
        $bounty = $this->userBounty($user);
        self::$db->begin_transaction();
        self::$db->prepared_query("
            DELETE FROM requests_votes
            WHERE RequestID = ? AND UserID = ?
            ", $this->id, $user->id
        );
        $affected = self::$db->affected_rows();
        $this->pg()->executeParams('
            delete from request vote
            where id_request = $1
                and id_user  = $2
            ', $this->id, $user->id
        );
        if ($affected) {
            $this->informRequestFillerReduction($bounty, $admin);
            $user->auditTrail()->addEvent(
                UserAuditEvent::request,
                "Removal of " . byte_format($bounty)
                    . " bounty ({$bounty}b) on {$this->url()}",
                $admin,
            );
            $user->flush();
        }
        self::$db->commit();
        return $affected;
    }

    /**
     * Inform the filler of a request that their bounty was reduced
     */
    public function informRequestFillerReduction(int $bounty, User $admin): int {
        [$fillerId, $fillDate] = self::$db->row("
            SELECT FillerID, date(TimeFilled)
            FROM requests
            WHERE TimeFilled IS NOT NULL AND ID = ?
            ", $this->id
        );
        if (!$fillerId) {
            return 0;
        }
        $user = new User($fillerId);
        $user->auditTrail()->addEvent(
            UserAuditEvent::request,
            "Reduction of " . byte_format($bounty)
                . " bounty ({$bounty}b) on filled request {$this->url()}",
            $admin,
        );
        self::$db->prepared_query("
            UPDATE users_leech_stats SET
                Uploaded = Uploaded - ?
            WHERE UserId = ?
            ", $bounty, $fillerId
        );
        $affected = self::$db->affected_rows();
        if ($affected) {
            $user->inbox()->createSystem(
                "Bounty was reduced on a request you filled",
                self::$twig->render('request/bounty-reduction.bbcode.twig', [
                    'bounty'      => $bounty,
                    'fill_date'   => $fillDate,
                    'request_url' => $this->url(),
                    'staff_name'  => $admin->username(),
                ])
            );
        }
        return $affected;
    }

    /**
     * Rebuild the fulltext artist/title text vector when either changes
     */
    public function reindex(): int {
        $this->pg()->begin();
        $this->pg()->query('
            delete from request_artist where id_request = $1
            ', $this->id
        );
        $this->pg()->query('
            insert into request_artist (id_request, id_alias, id_artist_role, id_user, created)
            select "RequestID", "AliasID", artist_role_id, "UserID", created
            from relay.requests_artists ra
            where ra."RequestID" = $1
            ', $this->id
        );
        $affected = $this->pg()->query(
        <<<END_SQL
            with r(id_request, artist_title_ts) as (
                select
                    rr."ID",
                    to_tsvector(
                        'simple',
                        unaccent(
                            coalesce(string_agg(aa."Name", ' '), '')
                                || ' ' || rr."Title"
                        )
                    )
                from relay.requests rr
                left join relay.requests_artists ra on    (ra."RequestID" = rr."ID")
                left join relay.artists_alias    aa using ("AliasID")
                left join relay.artist_role      ar on    (ar.artist_role_id = ra.artist_role_id)
                where (ar.slug is null or ar.slug != 'guest')
                    and rr."ID" = $1
                group by rr."ID", rr."Title"
            )
            update request set
                artist_title_ts = r.artist_title_ts
            from r
            where request.id_request = r.id_request
END_SQL
            , $this->id
        );
        $this->pg()->commit();
        return $affected;
    }

    public function relayTag(): int {
        return $this->pg()->query('
            update request set
                tag = (
                    select array_agg(rt."TagID")
                    from relay.requests_tags rt
                    where rt."RequestID" = $1
                )
            where id_request = $1
            ', $this->id
        );
    }

    public function modify(): bool {
        $affected = parent::modify();
        if ($affected) {
            new Manager\Request()->relay();
        }
        return $affected;
    }

    public function remove(): int {
        self::$db->begin_transaction();
        self::$db->prepared_query("DELETE FROM requests_votes WHERE RequestID = ?", $this->id);
        self::$db->prepared_query("DELETE FROM requests_tags WHERE RequestID = ?", $this->id);
        self::$db->prepared_query("DELETE FROM requests WHERE ID = ?", $this->id);
        $affected = self::$db->affected_rows();
        self::$db->prepared_query("
            SELECT DISTINCT aa.ArtistID
            FROM requests_artists ra
            INNER JOIN artists_alias aa USING (AliasID)
            WHERE ra.RequestID = ?
            ", $this->id
        );
        $artisIds = self::$db->collect(0);
        self::$db->prepared_query('
            DELETE FROM requests_artists WHERE RequestID = ?', $this->id
        );
        new Manager\Comment()->remove('requests', $this->id);
        self::$db->commit();

        foreach ($artisIds as $artistId) {
            self::$cache->delete_value("artists_requests_$artistId");
        }
        self::$cache->delete_value(sprintf(Manager\Request::ID_KEY, $this->id));
        $this->flush();
        $this->pg()->executeParams('
            delete from request where id_request = $1
            ', $this->id
        );
        return $affected;
    }
}
