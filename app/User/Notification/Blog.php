<?php

namespace Gazelle\User\Notification;

class Blog extends AbstractNotification {
    public function className(): string {
        return 'information';
    }

    public function clear(): int {
        return (int)(new \Gazelle\WitnessTable\UserReadBlog())->witness($this->user);
    }

    public function load(): bool {
        $blogMan = new \Gazelle\Manager\Blog();
        $latest = $blogMan->latest();
        if (is_null($latest)) {
            return false;
        }
        $lastRead = (new \Gazelle\WitnessTable\UserReadBlog())->lastRead($this->user);

        // You must be new around here.
        $newJoiner = is_null($lastRead) && $latest->createdEpoch() > strtotime($this->user->created());

        if ($newJoiner || $latest->id() > $lastRead) {
            $this->title   = "Blog: " . $latest->title();
            $this->url     = $latest->url();
            $this->context = $latest->id();
            return true;
        }
        return false;
    }
}
