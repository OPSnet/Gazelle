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
 * The User\Download class encapsulates this logic.
 */

class Download extends Base {
    protected DownloadStatus $status;

    public function __construct(
        protected \Gazelle\Torrent $torrent,
        protected \Gazelle\User\UserclassRateLimit $limiter,
        protected bool $useToken
    ) {}

    public function status(): DownloadStatus {
        if (!isset($this->status)) {
            $this->status = $this->authorize();
        }
        return $this->status;
    }

    public function authorize(): DownloadStatus {
        /**
         * You can always download your own torrent, a torrent you have snatched or are seeding.
         */
        $user = $this->limiter->user();
        $userId = $user->id();
        if (
            $this->torrent->uploaderId() == $userId
            || $user->snatch()->isSnatched($this->torrent)
            || $user->isSeeding($this->torrent)
        ) {
            return $this->success();
        }

        /**
         * You cannot download if you are on ratio watch
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
        if (!$this->useToken && $this->torrent->uploaderId() != $userId) {
            if ($this->limiter->isOvershoot($this->torrent)) {
                return DownloadStatus::flood;
            }
        }

        /* If they are trying use a token on this, make sure they have enough.
         * If so, deduct the number required, note it in the freeleech table
         * and update their cache key.
         */
        if ($this->useToken && $this->torrent->leechType() == LeechType::Normal) {
            if (!$user->canSpendFLToken($this->torrent)) {
                return DownloadStatus::free;
            }

            // First make sure this isn't already FL, and do nothing if it is
            if (!$user->hasToken($this->torrent)) {
                $tokenCount = $this->torrent->tokenCount();
                if (!STACKABLE_FREELEECH_TOKENS && $tokenCount > 1) {
                    return DownloadStatus::too_big;
                }
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

                // Let the tracker know about this
                if (!(new \Gazelle\Tracker)->update_tracker('add_token', [
                    'info_hash' => $this->torrent->infohashEncoded(), 'userid' => $userId
                ])) {
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
                    ", $userId, $this->torrent->id(), $tokenCount, $tokenCount
                );
                self::$db->commit();
                self::$cache->delete_value("user_tokens_$userId");
                $user->flush();
            }
        }
        return $this->success();
    }

    protected function success(): DownloadStatus {
        $user = $this->limiter->user();
        self::$db->prepared_query("
            INSERT INTO users_downloads
                   (UserID, TorrentID)
            VALUES (?,      ?)
            ", $user->id(), $this->torrent->id()
        );

        $user->stats()->increment('download_total');
        if ($this->torrent->group()->image() != '' && $this->torrent->uploaderId() != $user->id()) {
            $user->snatch()->flush();
        }
        return DownloadStatus::ok;
    }
}
