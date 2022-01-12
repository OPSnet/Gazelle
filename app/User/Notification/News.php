<?php

namespace Gazelle\User\Notification;

class News extends AbstractNotification {

    public function className(): string {
        return 'information';
    }

    public function clear(): int {
        if ((new \Gazelle\WitnessTable\UserReadNews)->witness($this->user->id())) {
            $this->user->flush();
        }
        return (new \Gazelle\Manager\News)->latestId();
    }

    public function load(): bool {
        $newsMan = new \Gazelle\Manager\News;
        [$newsId, $title] = $newsMan->latest();
        $lastRead = (new \Gazelle\WitnessTable\UserReadNews)->lastRead($this->user->id());

        // You must be new around here.
        $newJoiner = is_null($lastRead)
            && $newsMan->latest() > strtotime($this->user->joinDate());

        if ($newJoiner || (!$newJoiner && $newsId > $lastRead)) {
            $this->title   = "Announcement: $title";
            $this->url     = "index.php#news$newsId";
            $this->context = $newsId;
            return true;
        }
        return false;
    }
}
