<?php

namespace Gazelle\User\Notification;

class Quote extends AbstractNotification {

    public function className(): string {
        return 'confirmation';
    }

    public function clear(): int {
        return (new \Gazelle\User\Quote($this->user))->clearAll();
    }

    public function load(): bool {
        $total = (new \Gazelle\User\Quote($this->user))->unreadTotal();
        if ($total > 0) {
            $this->title = 'New quote' . plural($total);
            $this->url   = 'userhistory.php?action=quote_notifications';
            return true;
        }
        return false;
    }

    /**
     * Parse a post/comment body for quotes and notify all quoted users that have quote notifications enabled.
     *
     * @param string $body
     * @param int $postId
     * @param string $page
     * @param int $pageId
     * @return int Number of users notified
     */
    public function create(\Gazelle\Manager\User $userMan, string $body, int $postId, string $page, int $pageId): int {
        /*
         * Explanation of the parameters PageID and Page: Page contains where
         * this quote comes from and can be forums, artist, collages, requests
         * or torrents. The PageID contains the additional value that is
         * necessary for the users_notify_quoted table. The PageIDs for the
         * different Page are: forums: TopicID artist: ArtistID collages:
         * CollageID requests: RequestID torrents: GroupID
         */
        if (!preg_match_all('/(?:\[quote=|@)' . str_replace('/', '', USERNAME_REGEXP) . '/i', $body, $match)) {
            return 0;
        };
        $quoted    = 0;
        $quoterId  = $this->user->id();
        $usernames = array_unique($match['username']);
        foreach ($usernames as $username) {
            $user = $userMan->findByUsername($username);
            if ($user) {
                $notifier = new \Gazelle\User\Notification($user);
                if ($notifier->isActive('Quote')) {
                    ++$quoted;
                    (new \Gazelle\User\Quote($user))->create($quoterId, $page, $pageId, $postId);
                }
            }
        }
        return $quoted;
    }
}
