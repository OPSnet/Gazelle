<?php

namespace Gazelle;

use Gazelle\Enum\UserAuditEvent;
use Gazelle\Intf\Bookmarked;
use Gazelle\Intf\CategoryHasArtist;

class Request extends BaseObject implements Bookmarked, CategoryHasArtist {
    final public const tableName  = 'requests';

    public function flush(): static {
        if ($this->tgroupId()) {
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
        self::$db->prepared_query("
            SELECT aa.ArtistID
            FROM requests_artists ra
            INNER JOIN artists_alias aa USING (AliasID)
            WHERE RequestID = ?
            ", $this->id
        );
        $affected = (int)self::$db->record_count();
        self::$cache->delete_multi([
            ...array_map(fn ($id) => "artists_requests_$id", self::$db->collect(0)),
        ]);
        return $affected;
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
        $info = self::$db->rowAssoc("
            SELECT r.UserID       AS user_id,
                r.FillerID        AS filler_id,
                r.created         AS created,
                r.TimeFilled      AS fill_date,
                r.LastVote        AS last_vote_date,
                r.CategoryID      AS category_id,
                c.name            AS category_name,
                r.Title           AS title,
                r.Description     AS description,
                r.Year            AS year,
                r.Image           AS image,
                r.CatalogueNumber AS catalogue_number,
                r.ReleaseType     AS release_type,
                coalesce(rel.Name, 'Unknown')
                                  AS release_type_name,
                r.RecordLabel     AS record_label,
                r.GroupID         AS tgroup_id,
                r.TorrentID       AS torrent_id,
                r.LogCue          AS log_cue,
                r.Checksum        AS checksum,
                coalesce(r.BitrateList, '') AS encoding_list,
                coalesce(r.FormatList, '')  AS format_list,
                coalesce(r.MediaList, '')   AS media_list,
                r.OCLC            AS oclc,
                r.updated         AS modified
            FROM requests            r
            INNER JOIN category      c ON (c.category_id = r.CategoryID)
            LEFT JOIN release_type rel ON (rel.ID = r.ReleaseType)
            WHERE r.ID = ?
            GROUP BY r.ID
            ", $this->id
        );

        self::$db->prepared_query("
            SELECT rv.UserID   AS user_id,
                sum(rv.Bounty) AS bounty
            FROM requests_votes AS rv
            WHERE rv.RequestID = ?
            GROUP BY rv.UserID
            ORDER BY rv.Bounty DESC
            ", $this->id
        );
        $info['user_vote_list'] = self::$db->to_array(false, MYSQLI_ASSOC);

        self::$db->prepared_query("
            SELECT t.Name
            FROM requests_tags AS rt
            INNER JOIN tags AS t ON (t.ID = rt.TagID)
            WHERE rt.RequestID = ?
            ORDER BY rt.TagID ASC
            ", $this->id
        );
        $info['tag'] = self::$db->collect('Name');

        $info['need_encoding'] = explode('|', $info['encoding_list']);
        $info['need_format']   = explode('|', $info['format_list']);
        $info['need_media']    = explode('|', $info['media_list']);

        $info['encoding'] = new Request\Encoding(
            $info['encoding_list'] === 'All',
            explode('|', $info['encoding_list']),
        );
        $info['format'] = new Request\Format(
            $info['format_list'] === 'All',
            explode('|', $info['format_list']),
        );
        $info['media'] = new Request\Media(
            $info['media_list'] === 'All',
            explode('|', $info['media_list']),
        );
        $info['logCue'] = new Request\LogCue(
            needLogChecksum: (bool)$info['checksum'],
            needCue:         str_contains($info['log_cue'], 'Cue'),
            needLog:         str_contains($info['log_cue'], 'Log'),
            minScore:        (int)preg_replace('/\D+/', '', $info['log_cue']),
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
            'timeAdded'       => $info['created'],
            'voteCount'       => $this->userVotedTotal(),
            'lastVote'        => $info['last_vote_date'],
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
            'oclc'            => $info['oclc'],
            'releaseType'     => $info['release_type'],
            'releaseTypeName' => $info['release_type_name'],
            'bitrateList'     => array_values($this->currentEncoding()),
            'formatList'      => array_values($this->currentFormat()),
            'mediaList'       => array_values($this->currentMedia()),
            'logCue'          => $info['log_cue'],  // deprecated, remove some time
            'needCue'         => $this->needCue(),
            'needLog'         => $this->needLog(),
            'needLogChecksum' => $this->needLogChecksum(),
            'minLogScore'     => $this->needLogScore(),
            'isFilled'        => $info['torrent_id'] > 0,
            'fillerId'        => (int)$info['filler_id'],
            'torrentId'       => $info['torrent_id'],
            'timeFilled'      => (string)$info['fill_date'],
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

    public function catalogueNumber(): string {
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
        return $this->info()['created'];
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
        return $this->info()['log_cue'];
    }

    public function descriptionMedia(): ?string {
        return $this->info()['need_media'] === 'Unknown'
            ? null
            : $this->media()->display();
    }

    public function fillerId(): int {
        return $this->info()['filler_id'];
    }

    public function fillDate(): ?string {
        return $this->info()['fill_date'];
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
        return $this->info()['last_vote_date'];
    }

    public function legacyFormatList(): string {
        return $this->info()['format_list'];
    }

    public function legacyEncodingList(): string {
        return $this->info()['encoding_list'];
    }

    public function legacyLogChecksum(): string {
        return $this->info()['checksum'];
    }

    public function legacyMediaList(): string {
        return $this->info()['media_list'];
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
        return $this->info()['need_encoding'];
    }

    public function needFormat(string $format): bool {
        if ($this->needMediaList() === ['']) {
            return true;
        }
        return in_array($format, $this->needFormatList());
    }

    public function needFormatList(): array {
        return $this->info()['need_format'];
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
        return $this->info()['need_media'];
    }

    public function oclc(): ?string {
        return $this->info()['oclc'];
    }

    public function oclcLink(): ?string {
        $oclc = $this->oclc();
        if (is_null($oclc) || $oclc === '') {
            return null;
        }
        return implode(', ',
            array_map(fn($id) => "<a href=\"https://www.worldcat.org/oclc/{$id}\">{$id}</a>",
                explode(',', $oclc)
            )
        );
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

    public function torrentId(): int {
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
            return false;
        }

        $bounty = $amount * (1 - REQUEST_TAX);
        self::$db->prepared_query("
            INSERT INTO requests_votes
                   (RequestID, UserID, Bounty)
            VALUES (?,         ?,      ?)
            ", $this->id, $user->id, $bounty
        );
        $this->pg()->executeParams('
            insert into request_vote
                   (id_request_vote, id_request, id_user, bounty)
            values ($1,              $2,         $3,      $4)
            ', self::$db->inserted_id(), $this->id, $user->id, $bounty
        );
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
            ', $this->id
        );
        self::$db->prepared_query("
            UPDATE requests SET
                LastVote = now()
            WHERE ID = ?
            ", $this->id
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

        return true;
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
        $bounty = $this->bountyTotal();
        self::$db->begin_transaction();
        self::$db->prepared_query("
            UPDATE requests SET
                TimeFilled = now(),
                FillerID = ?,
                TorrentID = ?
            WHERE ID = ?
            ", $user->id, $torrent->id, $this->id
        );
        $updated = self::$db->affected_rows();
        self::$db->commit();

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
            where id_request = $1 and id_user = $2
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
            where id_request = $1 and id_user = $2
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
        return $this->pg()->prepared_query(
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
                    and rr."ID" = ?
                group by rr."ID", rr."Title"
            )
            update request set
                artist_title_ts = r.artist_title_ts
            from r
            where request.id_request = r.id_request
END_SQL
            , $this->id
        );
    }

    public function relayTag(): int {
        $this->pg()->beginex();
        $this->pg()->executeParams('
            delete from request_tag where id_request = $1
            ', $this->id
        );
        $affected = $this->pg()->query('
            insert into request_tag (id_request, id_tag)
            select "RequestID", "TagID"
            from relay.requests_tags
            where "RequestID" = $1
            ', $this->id
        );
        $this->pg()->executeParams('
            update request set
                tag = (
                    select array_agg(id_tag)
                    from request_tag
                    where id_request = $1
                )
            where id_request = $2
            ', $this->id, $this->id
        );
        $this->pg()->commitex();
        return $affected;
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
            delete from request_tag where id_request = $1
            ', $this->id
        );
        $this->pg()->executeParams('
            delete from request where id_request = $1
            ', $this->id
        );
        return $affected;
    }
}
