<?php

namespace Gazelle\User\Notification;

abstract class AbstractNotification extends \Gazelle\BaseUser {
    protected int    $context; // id of a table row
    protected string $display;
    protected string $title;
    protected string $url;

    public function flush(): AbstractNotification { $this->user()->flush(); return $this; }
    public function link(): string { return $this->user()->link(); }
    public function location(): string { return $this->user()->location(); }
    public function tableName(): string { return ''; }

    abstract public function className(): string;
    abstract public function clear(): int;
    abstract public function load(): bool;

    public function context(): int {
        return $this->context ?? 0;
    }

    public function setDisplay(string $display): AbstractNotification {
        $this->display = $display;
        return $this;
    }

    public function display(): string {
        return $this->display;
    }

    public function title(): string {
        return $this->title;
    }

    public function type(): string {
        $path = explode('\\', static::class);
        return end($path); // silence "Only variables should be passed by reference"
    }

    public function notificationUrl(): string {
        return $this->url;
    }
}
