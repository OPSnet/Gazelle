<?php

namespace Gazelle;

class Request extends BaseObject {
    protected const CACHE_REQUEST = "request_%d";
    protected const CACHE_ARTIST  = "request_artists_%d";
    protected const CACHE_VOTE    = "request_votes_%d";

    protected array $info;

    public function tableName(): string {
        return 'requests';
    }

    public function location(): string {
        return 'requests.php?action=view&id=' . $this->id;
    }

    public function link(): string {
        return sprintf('<a href="%s">%s</a>', $this->url(), display_str($this->title()));
    }

    /**
     * Display a title on the request page itself. If there are artists in the name,
     * they will be linkified, and the request title itself will not
     */
    public function selfLink(): string {
        $title = display_str($this->title());
        return match($this->categoryName()) {
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
        $title = display_str($this->title());
        return match($this->categoryName()) {
            'Music' =>
                "{$this->artistRole()->link()} – "
                . ($this->isFilled()
                    ? "<a href=\"torrents.php?torrentid={$this->torrentId()}\" dir=\"ltr\">$title</a>"
                    : "<a href=\"{$this->url()}\">$title</a>"
                )
                . " [{$this->year()}]",

            'Audiobooks', 'Comedy' => $this->isFilled()
                ? "<a href=\"torrents.php?torrentid={$this->torrentId()}\" dir=\"ltr\">$title</a> [{$this->year()}]"
                : "<a href=\"{$this->url()}\">$title</a> [{$this->year()}]",

            default => $this->isFilled()
                ? "<a href=\"torrents.php?torrentid={$this->torrentId()}\" dir=\"ltr\">$title</a>"
                : "<a href=\"{$this->url()}\">$title</a>",
        };
    }

    /**
     * Display the full title of the request with no links.
     */
    public function text(): string {
        $title = display_str($this->title());
        return match($this->categoryName()) {
            'Music'       => "{$this->artistRole()->text()} – $title [{$this->year()}]",
            'Audiobooks',
            'Comedy'      => "$title [{$this->year()}]",
            default       => $title,
        };
    }

    public function flush() {
        if ($this->info()['GroupID']) {
            self::$cache->delete_value("requests_group_" . $this->info()['GroupID']);
        }
        self::$cache->deleteMulti([
            sprintf(self::CACHE_REQUEST, $this->id),
            sprintf(self::CACHE_ARTIST, $this->id),
            sprintf(self::CACHE_VOTE, $this->id),
        ]);
        $this->info = [];
    }

    public function artistFlush() {
        $this->flush();
        self::$db->prepared_query("
            SELECT ArtistID FROM requests_artists WHERE RequestID = ?
            ", $this->id
        );
        self::$cache->deleteMulti([
            ...array_map(fn ($id) => "artists_requests_$id", self::$db->collect(0, false)),
        ]);
    }

    public function artistRole(): ArtistRole\Request {
        return new ArtistRole\Request($this->id, new Manager\Artist);
    }

    public function info(): array {
        if (!isset($this->info)) {
            $info = self::$db->rowAssoc("
                SELECT r.UserID,
                    r.FillerID,
                    r.TimeAdded,
                    r.TimeFilled,
                    r.LastVote,
                    r.CategoryID,
                    c.name AS category_name,
                    r.Title,
                    r.Description,
                    r.Year,
                    r.Image,
                    r.CatalogueNumber,
                    r.ReleaseType,
                    coalesce(rel.Name, 'Unknown') AS release_type_name,
                    r.RecordLabel,
                    r.GroupID,
                    r.TorrentID,
                    r.LogCue,
                    r.Checksum,
                    r.BitrateList,
                    r.FormatList,
                    r.MediaList,
                    r.OCLC,
                    group_concat(t.Name ORDER BY t.Name) as tagList
                FROM requests r
                INNER JOIN requests_tags AS rt ON (rt.RequestID = r.ID)
                INNER JOIN tags AS t ON (rt.TagID = t.ID)
                INNER JOIN category c ON (c.category_id = r.CategoryID)
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

            $info['need_encoding'] = explode('|', $info['BitrateList'] ?? '');
            $info['need_format'] = explode('|', $info['FormatList'] ?? '');
            $info['need_media'] = explode('|', $info['MediaList'] ?? '');
            $this->info = $info;
        }
        return $this->info;
    }

    public function canEditOwn(User $user): bool {
        return !$this->isFilled() && $user->id() == $this->userId() && count($this->userIdVoteList()) < 2;
    }

    public function canEdit(User $user): bool {
        return $this->canEditOwn($user) || $user->permittedAny('site_moderate_requests', 'site_edit_requests');
    }

    public function canVote(User $user): bool {
        return !$this->isFilled() && $user->permitted('site_vote');
    }

    public function catalogueNumber(): string {
        return $this->info()['CatalogueNumber'];
    }

    public function categoryId(): int {
        return $this->info()['CategoryID'];
    }

    public function categoryName(): string {
        return $this->info()['category_name'];
    }

    public function created(): string {
        return $this->info()['TimeAdded'];
    }

    public function description(): string {
        return $this->info()['Description'];
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
        return $this->info()['LogCue'];
    }

    public function descriptionMedia(): ?string {
        $need = $this->info()['need_media'];
        return empty($need) ? null : implode(', ', $need);
    }

    public function tgroupId(): ?int {
        return $this->info()['GroupID'];
    }

    public function fillerId(): ?int {
        return $this->info()['FillerID'];
    }

    public function fillDate(): ?string {
        return $this->info()['TimeFilled'];
    }

    public function isFilled(): bool {
        return (bool)$this->info()['FillerID'];
    }

    public function image(): ?string {
        return $this->info()['Image'];
    }

    public function lastVoteDate(): string {
        return $this->info()['LastVote'];
    }

    public function needCue(): bool {
        return str_contains($this->info()['LogCue'], 'Cue');
    }

    public function needEncoding(string $encoding): bool {
        return in_array($encoding, $this->info()['need_encoding']);
    }

    public function needFormat(string $format): bool {
        return in_array($format, $this->info()['need_format']);
    }

    public function needLog(): bool {
        return str_contains($this->info()['LogCue'], 'Log');
    }

    public function needLogChecksum(): bool {
        return (bool)$this->info()['Checksum'];
    }

    public function needLogScore(): int {
        return preg_match('/(\d+)%/', $this->info()['LogCue'], $match)
            ? $match[1]
            : 0;
    }

    public function needMedia(string $media): bool {
        return in_array($media, $this->info()['need_media']);
    }

    public function oclcList(): ?string {
        $oclc = str_replace(' ', '', $this->info()['OCLC']);
        if ($oclc === '') {
            return null;
        }
        return implode(', ',
            array_map(fn ($id) => "<a href=\"https://www.worldcat.org/oclc/{$id}\">{$id}</a>",
                explode(',', $oclc)
            )
        );
    }

    public function recordLabel(): ?string {
        return $this->info()['RecordLabel'];
    }

    public function releaseTypeName(): string {
        return $this->info()['release_type_name'];
    }

    public function releaseType(): int {
        return $this->info()['ReleaseType'];
    }

    public function tagNameList(): array {
        return $this->info()['tag'];
    }

    public function title(): string {
        return $this->info()['Title'];
    }

    public function torrentId(): ?int {
        return $this->info()['TorrentID'];
    }

    public function userId(): int {
        return $this->info()['UserID'];
    }

    public function year(): int {
        return (int)$this->info()['Year'];
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

    public function bountyTotal(): int {
        return array_sum(array_column($this->userIdVoteList(), 'bounty'));
    }

    public function userVotedTotal(): int {
        return count($this->userIdVoteList());
    }

    public function artistList(): array {
        $key = sprintf(self::CACHE_ARTIST, $this->id);
        $list = self::$cache->get_value($key);
        if ($list !== false) {
            return $list;
        }
        self::$db->prepared_query("
            SELECT ra.ArtistID,
                aa.Name,
                ra.Importance
            FROM requests_artists AS ra
            INNER JOIN artists_alias AS aa USING (AliasID)
            WHERE ra.RequestID = ?
            ORDER BY ra.Importance, aa.Name
            ", $this->id
        );
        $raw = self::$db->to_array();
        $list = [];
        foreach ($raw as list($artistId, $artistName, $role)) {
            $list[$role][] = ['id' => $artistId, 'name' => $artistName];
        }
        self::$cache->cache_value($key, $list, 0);
        return $list;
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

        $this->refreshSphinxDelta();
        self::$db->commit();

        $this->flush();
        $this->artistFlush();
        $user->flush();

        return true;
   }

    public function fill(User $user, Torrent $torrent): int {
        self::$db->prepared_query("
            UPDATE requests SET
                TimeFilled = now(),
                FillerID = ?,
                TorrentID = ?
            WHERE ID = ?
            ", $user->id(), $torrent->id(), $this->id
        );
        $updated = self::$db->affected_rows();
        $this->refreshSphinxDelta();
        (new \SphinxqlQuery())->raw_query(
            sprintf("
                UPDATE requests, requests_delta SET torrentid = %d, fillerid = %d WHERE id = %d
                ", $torrent->id(), $user->id(), $this->id
            ), false
        );

        $bounty = $this->bountyTotal();
        $user->addBounty($bounty);

        $name = $torrent->group()->displayNameText();
        $message = "One of your requests&nbsp;&mdash;&nbsp;[url=requests.php?action=view&amp;id="
            . $this->id . "]$name" . "[/url]&nbsp;&mdash;&nbsp;has been filled. You can view it here: [pl]"
            . $torrent->id() . "[/pl]";
        self::$db->prepared_query("
            SELECT UserID FROM requests_votes WHERE RequestID = ?
            ", $this->id
        );
        $ids = self::$db->collect(0, false);
        $userMan = new Manager\User;
        foreach ($ids as $userId) {
            $userMan->sendPM($userId, 0, "The request \"$name\" has been filled", $message);
        }

        (new Log)->general("Request " . $this->id . " ($name) was filled by " . $user->label()
            . " with the torrent " . $torrent->id() . " for a "
            . \Format::get_size($bounty) . ' bounty.'
        );

        $this->artistFlush();
        return $updated;
    }

    public function unfill(User $admin, string $reason): int {
        $bounty = $this->bountyTotal();
        $filler = new User($this->fillerId());
        $torrent = new Torrent($this->torrentId());
        $name = $torrent->group()->displayNameText();

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
        $this->refreshSphinxDelta();
        $filler->addBounty(-$bounty);
        self::$db->commit();

        (new \SphinxqlQuery())->raw_query("
            UPDATE requests, requests_delta SET
                torrentid = 0,
                fillerid = 0
            WHERE id = " . $this->id, false
        );

        if ($filler->id() !== $admin->id()) {
            (new Manager\User)->sendPM($filler->id(), 0, 'A request you filled has been unfilled',
                self::$twig->render('request/unfill-pm.twig', [
                    'name'    => $name,
                    'reason'  => $reason,
                    'request' => $this,
                    'viewer'  => $admin,
                ])
            );
        }

        (new Log)->general("Request " . $this->id . " ($name), with a "
            . \Format::get_size($bounty) . " bounty, was unfilled by "
            . $admin->label() . " for the reason: $reason"
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
    public function userBounty(int $userId): int {
        return (int)self::$db->scalar("
            SELECT Bounty
            FROM requests_votes
            WHERE RequestID = ? AND UserID = ?
            ", $this->id, $userId
        );
    }

    /**
     * Refund the bounty of a user on a request
     */
    public function refundBounty(int $userId, string $staffName) {
        $bounty = $this->userBounty($userId);
        self::$db->begin_transaction();
        self::$db->prepared_query("
            DELETE FROM requests_votes
            WHERE RequestID = ? AND UserID = ?
            ", $this->id, $userId
        );
        if (self::$db->affected_rows() == 1) {
            $this->informRequestFillerReduction($bounty, $staffName);
            $message = sprintf("Refund of %s bounty (%s b) on %s by %s\n\n",
                \Format::get_size($bounty), $bounty, $this->url(), $staffName
            );
            self::$db->prepared_query("
                UPDATE users_info ui
                INNER JOIN users_leech_stats uls USING (UserID)
                SET
                    uls.Uploaded = uls.Uploaded + ?,
                    ui.AdminComment = concat(now(), ' - ', ?, ui.AdminComment)
                WHERE ui.UserId = ?
                ", $bounty, $message, $userId
            );
        }
        self::$db->commit();
        self::$cache->deleteMulti(["request_" . $this->id, "request_votes_" . $this->id]);
    }

    /**
     * Remove the bounty of a user on a request
     */
    public function removeBounty(int $userId, string $staffName) {
        $bounty = $this->userBounty($userId);
        self::$db->begin_transaction();
        self::$db->prepared_query("
            DELETE FROM requests_votes
            WHERE RequestID = ? AND UserID = ?
            ", $this->id, $userId
        );
        if (self::$db->affected_rows() == 1) {
            $this->informRequestFillerReduction($bounty, $staffName);
            $message = sprintf("Removal of %s bounty (%s b) on %s by %s\n\n",
                \Format::get_size($bounty), $bounty, $this->url(), $staffName
            );
            self::$db->prepared_query("
                UPDATE users_info ui SET
                    ui.AdminComment = concat(now(), ' - ', ?, ui.AdminComment)
                WHERE ui.UserId = ?
                ", $message, $userId
            );
        }
        self::$db->commit();
        self::$cache->deleteMulti(["request_" . $this->id, "request_votes_" . $this->id]);
    }

    /**
     * Inform the filler of a request that their bounty was reduced
     */
    public function informRequestFillerReduction(int $bounty, string $staffName) {
        [$fillerId, $fillDate] = self::$db->row("
            SELECT FillerID, date(TimeFilled)
            FROM requests
            WHERE TimeFilled IS NOT NULL AND ID = ?
            ", $this->id
        );
        if (!$fillerId) {
            return;
        }
        $message = sprintf("Reduction of %s bounty (%s b) on filled request %s by %s\n\n",
            \Format::get_size($bounty), $bounty, $this->url(), $staffName
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
        self::$cache->delete_value("user_stats_$fillerId");
        (new Manager\User)->sendPM($fillerId, 0, "Bounty was reduced on a request you filled",
            self::$twig->render('request/bounty-reduction.twig', [
                'bounty'      => $bounty,
                'fill_date'   => $fillDate,
                'request_url' => $this->url(),
                'staff_name'  => $staffName,
            ])
        );
    }

    public function refreshSphinxDelta(): int {
        self::$db->prepared_query("
            REPLACE INTO sphinx_requests_delta (
                ID, UserID, TimeAdded, LastVote, CategoryID, Title,
                Year, ReleaseType, CatalogueNumber, RecordLabel, BitrateList,
                FormatList, MediaList, LogCue, FillerID, TorrentID,
                TimeFilled, Visible, Votes, Bounty, TagList, ArtistList)
            SELECT
                r.ID, r.UserID, unix_timestamp(r.TimeAdded), unix_timestamp(r.LastVote), r.CategoryID, r.Title,
                r.Year, r.ReleaseType, r.CatalogueNumber, r.RecordLabel, r.BitrateList,
                r.FormatList, r.MediaList, r.LogCue, r.FillerID, r.TorrentID,
                unix_timestamp(r.TimeFilled), r.Visible,
                count(rv.UserID), sum(rv.Bounty) >> 10,
                (
                    SELECT group_concat(replace(t.Name, '.', '_') SEPARATOR ' ')
                    FROM tags t
                    INNER JOIN requests_tags AS rt ON (rt.TagID = t.ID AND rt.RequestID = ?)
                ),
                (
                    SELECT group_concat(aa.Name SEPARATOR ' ')
                    FROM requests_artists AS ra
                    INNER JOIN artists_alias AS aa USING (AliasID)
                    WHERE ra.RequestID = ?
                    GROUP BY ra.RequestID
                )
            FROM requests AS r
            LEFT JOIN requests_votes AS rv ON (rv.RequestID = r.ID)
            WHERE r.ID = ?
            GROUP BY r.ID
            ", $this->id, $this->id, $this->id
        );
        return self::$db->affected_rows();
    }

    public function remove(): bool {
        self::$db->begin_transaction();
        self::$db->prepared_query("DELETE FROM requests_votes WHERE RequestID = ?", $this->id);
        self::$db->prepared_query("DELETE FROM requests_tags WHERE RequestID = ?", $this->id);
        self::$db->prepared_query("DELETE FROM requests WHERE ID = ?", $this->id);
        $affected = self::$db->affected_rows();
        self::$db->prepared_query("
            SELECT ArtistID FROM requests_artists WHERE RequestID = ?
            ", $this->id
        );
        $artisIds = self::$db->collect(0);
        self::$db->prepared_query('
            DELETE FROM requests_artists WHERE RequestID = ?', $this->id
        );
        self::$db->prepared_query("
            REPLACE INTO sphinx_requests_delta (ID) VALUES (?)
            ", $this->id
        );
        (new \Gazelle\Manager\Comment)->remove('requests', $this->id);
        self::$db->commit();

        foreach ($artisIds as $artistId) {
            self::$cache->delete_value("artists_requests_$artistId");
        }
        $this->flush();

        return $affected != 0;
    }
}
