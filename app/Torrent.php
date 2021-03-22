<?php

namespace Gazelle;

class Torrent extends BaseObject {

    public function tableName(): string {
        return 'torrents';
    }

    public function __construct(int $id) {
        parent::__construct($id);
    }

    public function flush() {
    }
}
