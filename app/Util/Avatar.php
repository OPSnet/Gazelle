<?php

namespace Gazelle\Util;

class Avatar {
    protected $mode;
    protected $size;

    public function __construct(int $mode) {
        $this->mode = $mode;
        $this->size = 150;
    }

    public function setSize(int $size) {
        $this->size = $size;
        return $this;
    }

    public function avatar(string $username): string {
        $hash = md5(AVATAR_SALT . $username);
        switch ($this->mode) {
            case 1: return "https://secure.gravatar.com/avatar/$hash?s={$this->size}&amp;r=pg&amp;d=monsterid";
            case 2: return "https://secure.gravatar.com/avatar/$hash?s={$this->size}&amp;r=pg&amp;d=wavatar";
            case 3: return "https://secure.gravatar.com/avatar/$hash?s={$this->size}&amp;r=pg&amp;d=retro";
            case 4: return "https://robohash.org/$hash?set=set1&amp;size={$this->size}x{$this->size}";
            case 5: return "https://robohash.org/$hash?set=set2&amp;size={$this->size}x{$this->size}";
            case 6: return "https://robohash.org/$hash?set=set3&amp;size={$this->size}x{$this->size}";
            case 0:
            default:
                return "https://secure.gravatar.com/avatar/$hash?s={$this->size}&amp;d=identicon&amp;r=pg";
        }
    }
}
