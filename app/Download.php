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
        protected \Gazelle\User $user,
        protected \Gazelle\Torrent $torrent,
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
         * You can always download your own torrent, a torrent you have snatched or are seeding
         */
        $userId = $this->user->id();
        if (
            $this->torrent->uploaderId() == $userId
            || $this->user->snatch()->isSnatched($this->torrent)
            || $this->user->isSeeding($this->torrent)
        ) {
            return $this->success();
        }

        /**
         * You cannot download if you are on ratio watch
         */
        if (!$this->user->canLeech()) {
            return DownloadStatus::ratio;
        }

        /* If this is not their torrent, then see if they have downloaded too
         * many files, compared to completely snatched items. If that is too
         * high, and they have already downloaded too many files recently, then
         * stop them. Exception: always allowed if they are using FL tokens.
         */
        if (!$this->useToken && $this->torrent->uploaderId() != $userId) {
            $PRL = new \Gazelle\User\PermissionRateLimit($this->user);
            if (!$PRL->safeFactor() && !$PRL->safeOvershoot()) {
                $PRL->register($this->torrent);
                return DownloadStatus::flood;
            }
        }

        /* If they are trying use a token on this, we need to make sure they
         * have enough. If so, deduct the number required, note it in the freeleech
         * table and update their cache key.
         */
        if ($this->useToken && $this->torrent->leechType() == LeechType::Normal) {
            if (!$this->user->canSpendFLToken($this->torrent)) {
                return DownloadStatus::free;
            }

            // First make sure this isn't already FL, and if it is, do nothing
            if (!$this->user->hasToken($this->torrent)) {
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
                $this->user->flush();
            }
        }
        return $this->success();
    }

    protected function success(): DownloadStatus {
        self::$db->prepared_query("
            INSERT INTO users_downloads
                   (UserID, TorrentID)
            VALUES (?,      ?)
            ", $this->user->id(), $this->torrent->id()
        );

        $this->user->stats()->increment('download_total');
        if ($this->torrent->group()->image() != '' && $this->torrent->uploaderId() != $this->user->id()) {
            $this->user->snatch()->flush();
        }
        return DownloadStatus::ok;
    }
}
