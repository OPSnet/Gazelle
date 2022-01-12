<?php

namespace Gazelle\User\Notification;

class Blog extends AbstractNotification {

    public function className(): string {
        return 'information';
    }

    public function clear(): int {
        return (int)(new \Gazelle\WitnessTable\UserReadBlog)->witness($this->user->id());
    }

    public function load(): bool {
        $blogMan = new \Gazelle\Manager\Blog;
        [$blogId, $title] = $blogMan->latest();
        $lastRead = (new \Gazelle\WitnessTable\UserReadBlog)->lastRead($this->user->id());

        // You must be new around here.
        $newJoiner = is_null($lastRead)
            && $blogMan->latest() > strtotime($this->user->joinDate());

        if ($newJoiner || (!$newJoiner && $blogId > $lastRead)) {
            $this->title   = "Blog: $title";
            $this->url     = "blog.php#blog$blogId";
            $this->context = $blogId;
            return true;
        }
        return false;
    }
}
