<?php

namespace Gazelle;

abstract class File {
    /** @var \DB_MYSQL */
    protected $db;

    /** @var \CACHE */
    protected $cache;

    public function __construct(\DB_MYSQL $db, \CACHE $cache) {
        $this->db = $db;
        $this->cache = $cache;
    }

    public function put($source, $id) {
        return copy($source, $this->path($id));
    }

    public function exists($id) {
        return file_exists($this->path($id));
    }

    public function get($id) {
        return file_get_contents($this->path($id));
    }

    public function remove($id) {
        unlink($this->path($id));
    }

    public function path($id) {
        return "/tmp/$id";
    }
}
