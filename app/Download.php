<?php

namespace Gazelle;

use Gazelle\Enum\DownloadStatus;
use Gazelle\Enum\LeechType;

/**
 * Downloading a torrent is tricky. The user
 *   - might be on ratio watch.
 *   - might have downloaded too many torrent files recently.
 *   - might be trying to use tokens and not have enough.
 *
 * This class encapsulates this logic.
 */

class Download extends Base {
    protected DownloadStatus $status;

    public function __construct(
        protected Torrent $torrent,
        protected User\UserclassRateLimit $limiter,
        protected bool $useToken,
    ) {}

    public function status(): DownloadStatus {
        return $this->status ??= $this->authorize();
    }

    public function authorize(): DownloadStatus {
        /**
         * You can always download your own torrent, a torrent you have snatched or are seeding.
         */
        $user = $this->limiter->user();
        $userId = $user->id;
        if (
            $this->torrent->uploaderId() == $userId
            || $user->snatch()->isSnatched($this->torrent)
            || $user->isSeeding($this->torrent)
        ) {
            // if no token is in play, we are done
            if (!$this->useToken) {
                return $this->success();
            }
        }

        /**
         * You cannot download, token or not, if you are on ratio watch
         */
        if (!$user->canLeech()) {
            return DownloadStatus::ratio;
        }

        /**
         * You can download a freeleech torrent without restriction.
         */
        if ($this->torrent->isFreeleech()) {
            return $this->success();
        }

        /* This is not their torrent, see if they have downloaded too many
         * files, compared to completely snatched items. If that is too high,
         * and they have already downloaded too many files recently, then
         * stop them. Exception: always allowed if they are using FL tokens.
         */
        if (
            !$this->useToken
            && $this->torrent->uploaderId() != $userId
            && $this->limiter->isOvershoot($this->torrent)
        ) {
            return DownloadStatus::flood;
        }

        /* They are trying use a token on this: make sure they have enough.
         * If so, deduct the number required, note it in the freeleech table
         * and update their cache key.
         */
        if ($this->useToken && $this->torrent->leechType() == LeechType::Normal) {
            // can they even consider the idea?
            $tokenCount = $this->torrent->tokenCount();
            if ($user->tokenCount() < $tokenCount || (!STACKABLE_FREELEECH_TOKENS && $tokenCount > 1)) {
                return DownloadStatus::too_big;
            }

            // it is already free for them
            if (!$user->canSpendFLToken($this->torrent)) {
                return DownloadStatus::free;
            }

            // spend some tokens to make it personal freeleech
            if (!$user->hasToken($this->torrent)) {
                self::$db->begin_transaction();
                self::$db->prepared_query('
                    UPDATE user_flt SET
                        tokens = tokens - ?
                    WHERE tokens >= ? AND user_id = ?
                    ', $tokenCount, $tokenCount, $userId
                );
                if (self::$db->affected_rows() == 0) {
                    self::$db->rollback();
                    return DownloadStatus::no_tokens;
                }
                if (!new Tracker()->addToken($this->torrent, $user)) {
                    self::$db->rollback();
                    return DownloadStatus::tracker;
                }
                self::$db->prepared_query("
                    INSERT INTO users_freeleeches (UserID, TorrentID, Uses, Time)
                    VALUES (?, ?, ?, now())
                    ON DUPLICATE KEY UPDATE
                        Time = VALUES(Time),
                        Expired = FALSE,
                        Uses = Uses + ?
                    ", $userId, $this->torrent->id, $tokenCount, $tokenCount
                );
                self::$db->commit();
                self::$cache->delete_value("user_tokens_$userId");
                $user->flush();
            }
        }
        return $this->success();
    }

    protected function success(): DownloadStatus {
        $user  = $this->limiter->user();
        $found = (bool)self::$db->scalar('
            SELECT 1
            FROM users_downloads
            WHERE UserID = ?
                AND TorrentID = ?
            ', $user->id, $this->torrent->id
        );
        self::$db->prepared_query('
            INSERT INTO users_downloads
                   (UserID, TorrentID)
            VALUES (?,      ?)
            ', $user->id, $this->torrent->id
        );
        $id = self::$db->inserted_id();
        $this->pg()->query('
            insert into user_download
                   (id_user, id_torrent, id_user_download)
            values ($1,      $2,         $3)
            ', $user->id, $this->torrent->id, $id
        );
        $this->pg()->query("
            select setval('user_download_id_user_download_seq', $1)
            ", $id
        );
        $user->stats()->incrementTGroup(Enum\UserSummaryTGroup::downloadTotal);
        if (!$found) {
            $user->stats()->incrementTGroup(Enum\UserSummaryTGroup::downloadUnique);
        }
        if ($this->torrent->group()->image() != '' && $this->torrent->uploaderId() != $user->id) {
            $user->snatch()->flush();
        }
        return DownloadStatus::ok;
    }
}
