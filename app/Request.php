<?php

namespace Gazelle;

use Gazelle\Intf\CategoryHasArtist;

class Request extends BaseObject implements CategoryHasArtist {
    final public const tableName         = 'requests';
    protected const CACHE_REQUEST = "request_%d";
    protected const CACHE_ARTIST  = "request_artists_%d";
    protected const CACHE_VOTE    = "request_votes_%d";

    public function flush(): static {
        if ($this->tgroupId()) {
            self::$cache->delete_value("requests_group_" . $this->tgroupId());
        }
        self::$cache->delete_multi([
            sprintf(self::CACHE_REQUEST, $this->id),
            sprintf(self::CACHE_ARTIST, $this->id),
            sprintf(self::CACHE_VOTE, $this->id),
        ]);
        unset($this->info);
        return $this;
    }
    public function link(): string { return sprintf('<a href="%s">%s</a>', $this->url(), display_str($this->title())); }
    public function location(): string { return 'requests.php?action=view&id=' . $this->id; }

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
            ...array_map(fn ($id) => "artists_requests_$id", self::$db->collect(0, false)),
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
                r.TimeAdded       AS created,
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
                r.BitrateList     AS encoding_list,
                r.FormatList      AS format_list,
                r.MediaList       AS media_list,
                r.OCLC            AS oclc
            FROM requests            r
            INNER JOIN category      c ON (c.category_id = r.CategoryID)
            LEFT JOIN release_type rel ON (rel.ID = r.ReleaseType)
            WHERE r.ID = ?
            GROUP BY r.ID
            ", $this->id
        );

        self::$db->prepared_query("
            SELECT rv.UserID AS user_id,
                rv.Bounty    AS bounty
            FROM requests_votes AS rv
            WHERE rv.RequestID = ?
            ORDER BY rv.Bounty DESC
            ", $this->id
        );
        $info['user_vote_list'] = self::$db->to_array(false, MYSQLI_ASSOC, false);

        self::$db->prepared_query("
            SELECT t.Name
            FROM requests_tags AS rt
            INNER JOIN tags AS t ON (t.ID = rt.TagID)
            WHERE rt.RequestID = ?
            ORDER BY rt.TagID ASC
            ", $this->id
        );
        $info['tag'] = self::$db->collect('Name', false);

        $info['need_encoding'] = explode('|', $info['encoding_list'] ?? 'Unknown');
        $info['need_format']   = explode('|', $info['format_list']   ?? 'Unknown');
        $info['need_media']    = explode('|', $info['media_list']    ?? 'Unknown');

        $this->info = $info;
        return $this->info;
    }

    /**
     * These fields are shared between the request and requests ajax endpoints
     */
    public function ajaxInfo(): array {
        $info = $this->info();
        return [
            'requestId'       => $this->id(),
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
            'logCue'          => $info['log_cue'],
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
        return !$this->isFilled() && $user->id() == $this->userId() && $this->userVotedTotal() < 2;
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
        return new Request\Encoding($this->needEncoding('Any'), array_keys($this->currentEncoding()));
    }

    public function format(): Request\Format {
        return new Request\Format($this->needFormat('Any'), array_keys($this->currentFormat()));
    }

    public function media(): Request\Media {
        return new Request\Media($this->needMedia('Any'), array_keys($this->currentMedia()));
    }

    public function descriptionEncoding(): ?string {
        $need = $this->info()['need_encoding'];
        return empty($need) ? null : implode(', ', $need);
    }

    public function descriptionFormat(): ?string {
        $need = $this->info()['need_format'];
        return empty($need) ? null : implode(', ', $need);
    }

    public function descriptionLogCue(): ?string {
        return $this->info()['log_cue'];
    }

    public function descriptionMedia(): ?string {
        $need = $this->info()['need_media'];
        return empty($need) ? null : implode(', ', $need);
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

    public function logCue(): Request\LogCue {
        return new Request\LogCue(
            needLogChecksum: $this->needLogChecksum(),
            needCue:         $this->needCue(),
            needLog:         $this->needLog(),
            minScore:        $this->needLogScore(),
        );
    }

    public function needCue(): bool {
        return str_contains($this->descriptionLogCue(), 'Cue');
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
        return str_contains($this->descriptionLogCue(), 'Log');
    }

    public function needLogChecksum(): bool {
        return (bool)$this->info()['checksum'];
    }

    public function needLogScore(): int {
        return preg_match('/(\d+)%/', $this->descriptionLogCue(), $match)
            ? (int)$match[1]
            : 0;
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

    public function tagSearchLink(): string {
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

    public function tagNameToSphinx(): string {
        return implode(' ', array_map(fn ($t) => str_replace('.', '_', $t), $this->tagNameList()));
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

    public function userVote(User $user): ?array {
        $vote =  array_filter($this->userIdVoteList(), fn ($r) => $r['user_id'] === $user->id());
        return $vote ? current($vote) : null;
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

    public function validate(Torrent $torrent): array {
        $error = [];
        if ($this->torrentId()) {
            $error[] = 'This request has already been filled.';
        }
        if (!in_array($this->categoryId(), [0, $torrent->group()->categoryId()])) {
            $error[] = 'This torrent is of a different category than the request. If the request is actually miscategorized, please contact staff.';
        }

        if ($torrent->media() === 'CD' && $torrent->format() === 'FLAC') {
            if ($this->needLog()) {
                if (!$torrent->hasLogDb()) {
                    $error[] = 'This request requires a log.';
                } else {
                    if ($torrent->logScore() < $this->needLogScore()) {
                        $error[] = 'This torrent\'s log score (' . $torrent->logScore() . ') is too low.';
                    }
                    if (!$torrent->logChecksum() && $this->needLogChecksum()) {
                        $error[] = 'The ripping log for this torrent does not have a valid checksum.';
                    }
                }
            }
            if ($this->needCue() && !$torrent->hasCue()) {
                $error[] = 'This request requires a cue file.';
            }
        }

        if (!$this->needMedia('Any') && !$this->needMedia($torrent->media())) {
            $error[] = $torrent->media() . " is not a permitted media for this request.";
        }
        if (!$this->needFormat('Any') && !$this->needFormat($torrent->format())) {
            $error[] = $torrent->format() . " is not an allowed format for this request.";
        }
        if ($this->needEncoding('Other')) {
            if (in_array($torrent->encoding(), ['24bit Lossless', 'Lossless', 'V0 (VBR)', 'V1 (VBR)', 'V2 (VBR)', 'APS (VBR)', 'APX (VBR)', '256', '320'])) {
                $error[] = $torrent->encoding() . " is not an allowed encoding for this request.";
            }
        } elseif (!$this->needEncoding('Any') && !$this->needEncoding($torrent->encoding())) {
            $error[] = $torrent->encoding() . " is not an allowed encoding for this request.";
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
            ", $amount, $amount, $user->id()
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
            ON DUPLICATE KEY UPDATE Bounty = Bounty + ?
            ", $this->id(), $user->id(), $bounty, $bounty
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
                    coalesce(sum(rv.Bounty), 0) AS size,
                    count(*) AS total
                FROM requests_votes rv
                INNER JOIN requests r ON (r.ID = rv.RequestID)
                WHERE r.UserID != r.FillerID
                    AND rv.UserID = ?
                GROUP BY rv.UserID
            ON DUPLICATE KEY UPDATE
                request_vote_size = VALUES(request_vote_size),
                request_vote_total = VALUES(request_vote_total)
            ", $user->id()
        );

        $this->updateSphinx();
        self::$db->commit();

        $user->flush();

        return true;
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
            ", $user->id(), $torrent->id(), $this->id
        );
        $updated = self::$db->affected_rows();
        $this->updateSphinx();
        (new \SphinxqlQuery())->raw_query(
            sprintf("
                UPDATE requests, requests_delta SET torrentid = %d, fillerid = %d WHERE id = %d
                ", $torrent->id(), $user->id(), $this->id
            ), false
        );
        self::$db->commit();

        $user->addBounty($bounty);
        $name = $this->title();
        $message = "One of your requests — [url={$this->location()}]{$name}[/url] — has been filled."
                   . " You can view it here: [pl]{$torrent->id()}[/pl]";
        self::$db->prepared_query("
            SELECT UserID FROM requests_votes WHERE RequestID = ?
            ", $this->id
        );
        foreach (self::$db->collect(0, false) as $userId) {
            (new User($userId))->inbox()->createSystem("The request \"$name\" has been filled", $message);
        }

        (new Log())->general(
            "Request {$this->id} ($name) was filled by user {$user->label()} with the torrent {$torrent->id()} for a "
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
        $this->updateSphinx();
        $filler->addBounty(-$bounty);
        $filler->flush();
        self::$db->commit();

        (new \SphinxqlQuery())->raw_query("
            UPDATE requests, requests_delta SET
                torrentid = 0,
                fillerid = 0
            WHERE id = " . $this->id, false
        );

        if ($filler->id() !== $admin->id()) {
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

        (new Log())->general("Request {$this->id} ({$this->title()}), with a " . byte_format($bounty)
            . " bounty, was unfilled by user {$admin->label()} for the reason: $reason"
        );

        $this->artistFlush();
        return $updated;
    }

    /**
     * Get the bounty of request, by user
     * TODO: redundant, given userIdVoteList()
     *
     * @return array keyed by user ID
     */
    public function bounty(): array {
        self::$db->prepared_query("
            SELECT UserID, Bounty
            FROM requests_votes
            WHERE RequestID = ?
            ORDER BY Bounty DESC, UserID DESC
            ", $this->id
        );
        return self::$db->to_array('UserID', MYSQLI_ASSOC, false);
    }

    /**
     * Get the total bounty that a user has added to a request
     */
    public function userBounty(User $user): int {
        $vote = array_filter($this->userIdVoteList(), fn($r) => $r['user_id'] == $user->id());
        return count($vote) ? current($vote)['bounty'] : 0;
    }

    /**
     * Refund the bounty of a user on a request
     */
    public function refundBounty(User $user, string $staffName): int {
        $bounty = $this->userBounty($user);
        self::$db->begin_transaction();
        self::$db->prepared_query("
            DELETE FROM requests_votes
            WHERE RequestID = ? AND UserID = ?
            ", $this->id, $user->id()
        );
        $affected = self::$db->affected_rows();
        if ($affected) {
            $this->informRequestFillerReduction($bounty, $staffName);
            $message = sprintf("Refund of %s bounty (%s b) on %s by %s\n\n",
                byte_format($bounty), $bounty, $this->url(), $staffName
            );
            self::$db->prepared_query("
                UPDATE users_info ui
                INNER JOIN users_leech_stats uls USING (UserID)
                SET
                    uls.Uploaded = uls.Uploaded + ?,
                    ui.AdminComment = concat(now(), ' - ', ?, ui.AdminComment)
                WHERE ui.UserId = ?
                ", $bounty, $message, $user->id()
            );
            $user->flush();
        }
        self::$db->commit();
        return $affected;
    }

    /**
     * Remove the bounty of a user on a request
     */
    public function removeBounty(User $user, string $staffName): int {
        $bounty = $this->userBounty($user);
        self::$db->begin_transaction();
        self::$db->prepared_query("
            DELETE FROM requests_votes
            WHERE RequestID = ? AND UserID = ?
            ", $this->id, $user->id()
        );
        $affected = self::$db->affected_rows();
        if ($affected) {
            $this->informRequestFillerReduction($bounty, $staffName);
            $message = sprintf("Removal of %s bounty (%s b) on %s by %s\n\n",
                byte_format($bounty), $bounty, $this->url(), $staffName
            );
            self::$db->prepared_query("
                UPDATE users_info ui SET
                    ui.AdminComment = concat(now(), ' - ', ?, ui.AdminComment)
                WHERE ui.UserId = ?
                ", $message, $user->id()
            );
            $user->flush();
        }
        self::$db->commit();
        return $affected;
    }

    /**
     * Inform the filler of a request that their bounty was reduced
     */
    public function informRequestFillerReduction(int $bounty, string $staffName): int {
        [$fillerId, $fillDate] = self::$db->row("
            SELECT FillerID, date(TimeFilled)
            FROM requests
            WHERE TimeFilled IS NOT NULL AND ID = ?
            ", $this->id
        );
        if (!$fillerId) {
            return 0;
        }
        $message = sprintf("Reduction of %s bounty (%s b) on filled request %s by %s\n\n",
            byte_format($bounty), $bounty, $this->url(), $staffName
        );
        self::$db->prepared_query("
            UPDATE users_info ui
            INNER JOIN users_leech_stats uls USING (UserID)
            SET
                uls.Uploaded = uls.Uploaded - ?,
                ui.AdminComment = concat(now(), ' - ', ?, ui.AdminComment)
            WHERE ui.UserId = ?
            ", $bounty, $message, $fillerId
        );
        $affected = self::$db->affected_rows();
        if ($affected) {
            (new User($fillerId))->inbox()->createSystem(
                "Bounty was reduced on a request you filled",
                self::$twig->render('request/bounty-reduction.bbcode.twig', [
                    'bounty'      => $bounty,
                    'fill_date'   => $fillDate,
                    'request_url' => $this->url(),
                    'staff_name'  => $staffName,
                ])
            );
        }
        return $affected;
    }

    public function addTag(int $tagId): int {
        self::$db->prepared_query("
            INSERT IGNORE INTO requests_tags
                   (TagID, RequestID)
            VALUES (?,     ?)
            ", $tagId, $this->id
        );
        return self::$db->affected_rows();
    }

    public function setTagList(array $tagList, User $user, Manager\Tag $manager): int {
        $affected = 0;
        self::$db->begin_transaction();
        self::$db->prepared_query("
            DELETE FROM requests_tags WHERE RequestID = ?
            ", $this->id()
        );
        foreach ($tagList as $tag) {
            $affected += $this->addTag($manager->create($tag, $user));
        }
        self::$db->commit();
        $this->flush();
        return $affected;
    }

    /**
     * Update the sphinx requests delta table.
     */
    public function updateSphinx(): int {
        self::$db->prepared_query("
            REPLACE INTO sphinx_requests_delta (
                ID, UserID, TimeAdded, LastVote, CategoryID, Title,
                Year, ReleaseType, CatalogueNumber, RecordLabel, BitrateList,
                FormatList, MediaList, LogCue, FillerID, TorrentID,
                TimeFilled, Visible, Votes, Bounty, TagList, ArtistList)
            SELECT
                ID, r.UserID, UNIX_TIMESTAMP(TimeAdded) AS TimeAdded,
                UNIX_TIMESTAMP(LastVote) AS LastVote, CategoryID, Title,
                Year, ReleaseType, CatalogueNumber, RecordLabel, BitrateList,
                FormatList, MediaList, LogCue, FillerID, TorrentID,
                UNIX_TIMESTAMP(TimeFilled) AS TimeFilled, Visible,
                COUNT(rv.UserID) AS Votes, SUM(rv.Bounty) >> 10 AS Bounty,
                ?, ?
            FROM requests AS r
            LEFT JOIN requests_votes AS rv ON (rv.RequestID = r.ID)
            WHERE r.ID = ?
            GROUP BY r.ID
            ", $this->tagNameToSphinx(), implode(' ', $this->artistRole()?->nameList() ?? []), $this->id
        );
        $affected = self::$db->affected_rows();
        $this->flush();
        return $affected;
    }

    public function updateBookmarkStats(): int {
        self::$db->prepared_query("
            SELECT UserID FROM bookmarks_requests WHERE RequestID = ?
            ", $this->id
        );
        $affected = (int)self::$db->record_count();
        if ($affected > 100) {
            // Sphinx doesn't like huge MVA updates. Update sphinx_requests_delta
            // and live with the <= 1 minute delay if we have more than 100 bookmarkers
            $this->updateSphinx();
        } else {
            (new \SphinxqlQuery())->raw_query(
                "UPDATE requests, requests_delta SET bookmarker = ("
                . implode(',', self::$db->collect('UserID'))
                . ") WHERE id = {$this->id}"
            );
        }
        return $affected;
    }

    public function remove(): bool {
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
        $artisIds = self::$db->collect(0, false);
        self::$db->prepared_query('
            DELETE FROM requests_artists WHERE RequestID = ?', $this->id
        );
        self::$db->prepared_query("
            REPLACE INTO sphinx_requests_delta (ID) VALUES (?)
            ", $this->id
        );
        (new \Gazelle\Manager\Comment())->remove('requests', $this->id);
        self::$db->commit();

        foreach ($artisIds as $artistId) {
            self::$cache->delete_value("artists_requests_$artistId");
        }
        self::$cache->delete_value(sprintf(Manager\Request::ID_KEY, $this->id));
        $this->flush();
        return $affected != 0;
    }
}
